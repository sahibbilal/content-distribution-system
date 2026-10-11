<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** Avatars accepted by real content type (never the client's name or Content-Type) */
    private const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
    private const AVATAR_MAX_PIXELS = 25_000_000; // guards against decompression bombs
    private const AVATAR_SIZE = 400;

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);

        $request->user()->update(['name' => trim(strip_tags($data['name']))]);

        return response()->json($request->user()->fresh());
    }

    public function avatar(Request $request)
    {
        $request->validate(['avatar' => 'required|file|max:2048']);

        $file = $request->file('avatar');
        $path = $file->isValid() ? $file->getRealPath() : null;
        $mime = $path ? $file->getMimeType() : null;
        $info = $path && in_array($mime, self::AVATAR_TYPES, true) ? @getimagesize($path) : false;

        if ($info === false || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['avatar' => ['Choose a valid JPG, PNG or WebP image. The file content must match, whatever its name says.']]);
        }
        if ($info[0] * $info[1] > self::AVATAR_MAX_PIXELS || $file->getSize() > self::AVATAR_MAX_BYTES) {
            throw ValidationException::withMessages(['avatar' => ['This image is too large (max 2MB).']]);
        }

        $source = @imagecreatefromstring(file_get_contents($path));
        if (!$source) {
            throw ValidationException::withMessages(['avatar' => ['This image file is corrupt.']]);
        }

        // Re-encode as a square JPEG: drops metadata and anything hidden in the original file
        $source = $this->applyExifOrientation($source, $mime === 'image/jpeg' ? $path : null);
        [$w, $h] = [imagesx($source), imagesy($source)];
        $side = min($w, $h);
        $avatar = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagefill($avatar, 0, 0, imagecolorallocate($avatar, 255, 255, 255)); // transparent areas become white
        imagecopyresampled($avatar, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);

        ob_start();
        imagejpeg($avatar, null, 85);
        $jpeg = ob_get_clean();

        $user = $request->user();
        $newPath = 'avatars/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($newPath, $jpeg);

        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $newPath])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json($user->fresh());
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return response()->json($user->fresh());
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Your current password is incorrect.']]);
        }

        $user->update(['password' => $data['password']]); // hashed by the model cast

        // sign out every other device; this session stays signed in
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed. Other devices have been signed out.']);
    }

    /** Phone photos are often stored rotated with an EXIF flag; apply it so the avatar isn't sideways */
    private function applyExifOrientation(\GdImage $image, ?string $path): \GdImage
    {
        if (!$path || !function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
