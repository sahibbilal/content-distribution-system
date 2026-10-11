<?php
// temporary: verifies App\Support\PlatformMediaRules (deleted right after the run)
require __DIR__ . '/vendor/autoload.php';
use App\Support\PlatformMediaRules as R;

$f = fn ($type, $ext, $name = null, $size = 1000) => ['type' => $type, 'ext' => $ext, 'name' => $name ?? "file.$ext", 'size' => $size];
$pass = 0; $fail = 0;
$check = function (string $name, array $got, ?string $expectContains) use (&$pass, &$fail) {
    $ok = $expectContains === null ? $got === [] : (count($got) > 0 && str_contains(implode(' | ', $got), $expectContains));
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name" . ($ok ? '' : '  got: ' . json_encode($got)) . "\n";
    if ($ok && $expectContains !== null) echo "      -> " . implode(' | ', $got) . "\n";
};

// YouTube
$check('youtube + image rejected', R::issues('youtube', [$f('image', 'png', 'pic.png')]), "can't post image");
$check('youtube + audio rejected', R::issues('youtube', [$f('audio', 'mp3', 'song.mp3')]), "can't post audio file");
$check('youtube + no media required', R::issues('youtube', []), 'needs a video');
$check('youtube + video ok', R::issues('youtube', [$f('video', 'mp4')]), null);
$check('youtube + 2 videos rejected', R::issues('youtube', [$f('video', 'mp4'), $f('video', 'mov')]), 'at most 1 file');
$check('youtube + video + image rejected', R::issues('youtube', [$f('video', 'mp4'), $f('image', 'png', 'a.png')]), "can't post image");
// TikTok
$check('tiktok + audio rejected', R::issues('tiktok', [$f('audio', 'wav')]), "can't post audio file");
$check('tiktok + no media required', R::issues('tiktok', []), 'needs a video or an image');
$check('tiktok + mp4 ok', R::issues('tiktok', [$f('video', 'mp4')]), null);
$check('tiktok + avi rejected', R::issues('tiktok', [$f('video', 'avi')]), 'only accepts MP4, MOV, WEBM');
$check('tiktok + png rejected', R::issues('tiktok', [$f('image', 'png')]), 'only accepts JPG, WEBP');
$check('tiktok + jpg x3 ok', R::issues('tiktok', [$f('image', 'jpg'), $f('image', 'jpg'), $f('image', 'webp')]), null);
$check('tiktok + mixed rejected', R::issues('tiktok', [$f('image', 'jpg'), $f('video', 'mp4')]), "can't mix");
// Instagram
$check('instagram + no media required', R::issues('instagram', []), 'needs an image or video');
$check('instagram + audio rejected', R::issues('instagram', [$f('audio', 'mp3')]), "can't post audio file");
$check('instagram + image ok', R::issues('instagram', [$f('image', 'png')]), null);
$check('instagram + video ok', R::issues('instagram', [$f('video', 'mp4')]), null);
$check('instagram + 2 files rejected', R::issues('instagram', [$f('image', 'png'), $f('image', 'jpg')]), 'at most 1 file');
// Facebook
$check('facebook + text only ok', R::issues('facebook', []), null);
$check('facebook + audio rejected', R::issues('facebook', [$f('audio', 'mp3')]), "can't post audio file");
$check('facebook + image ok', R::issues('facebook', [$f('image', 'png')]), null);
$check('facebook + video ok', R::issues('facebook', [$f('video', 'mp4')]), null);
// LinkedIn
$check('linkedin + text only ok', R::issues('linkedin', []), null);
$check('linkedin + video rejected', R::issues('linkedin', [$f('video', 'mp4', 'clip.mp4')]), "can't post video");
$check('linkedin + image ok', R::issues('linkedin', [$f('image', 'jpg')]), null);
// X
$check('x + text only ok', R::issues('twitter', []), null);
$check('x + video rejected', R::issues('twitter', [$f('video', 'mp4')]), "can't post video");
$check('x + 4 images ok', R::issues('twitter', array_fill(0, 4, $f('image', 'png'))), null);
$check('x + 5 images rejected', R::issues('twitter', array_fill(0, 5, $f('image', 'png'))), 'at most 4');
$check('x + bmp rejected', R::issues('twitter', [$f('image', 'bmp')]), 'only accepts JPG, PNG, WEBP, GIF');
$check('x + 7MB image rejected', R::issues('twitter', [$f('image', 'png', 'big.png', 7 * 1048576)]), '5MB or smaller');
$check('unknown platform ignored', R::issues('mastodon', [$f('audio', 'mp3')]), null);

echo "\n$pass passed, $fail failed\n";
