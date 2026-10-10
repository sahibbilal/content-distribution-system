<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    private const COLUMNS = ['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'];

    public function index(Request $request)
    {
        $query = User::query()->orderByDesc('created_at');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate(20, self::COLUMNS);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['is_admin' => 'required|boolean']);

        if ($request->user()->is($user)) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        if ($user->is_admin && !$data['is_admin'] && User::where('is_admin', true)->count() <= 1) {
            return response()->json(['message' => 'At least one admin is required.'], 422);
        }

        $user->forceFill(['is_admin' => $data['is_admin']])->save();

        return $user->only(self::COLUMNS);
    }

    public function verify(User $user)
    {
        $user->markEmailAsVerified();

        return $user->only(self::COLUMNS);
    }

    public function resendVerification(User $user)
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 422);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not send the email: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Verification email sent.']);
    }
}
