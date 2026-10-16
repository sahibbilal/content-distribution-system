<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Email notifications for the episode pipeline. A mail problem must never break a pipeline step. */
class Notify
{
    public static function user(?User $user, string $subject, string $body, ?string $link = null): void
    {
        if (!$user?->email) {
            return;
        }

        try {
            Mail::raw($body . ($link ? "\n\n{$link}" : ''), fn ($m) => $m->to($user->email)->subject($subject));
        } catch (\Throwable $e) {
            Log::warning('Could not send notification: ' . $e->getMessage(), ['subject' => $subject]);
        }
    }

    /** Link to an episode page in the web app */
    public static function episodeLink(int $episodeId): string
    {
        return rtrim(config('app.url'), '/') . '/episodes/' . $episodeId;
    }
}
