<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\ElevenLabsService;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** approved -> voicing -> awaiting_avatar: voice, alignment and captions from the approved script */
class GenerateVoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $episodeId)
    {
    }

    public function handle(ElevenLabsService $el): void
    {
        $e = Episode::find($this->episodeId);
        if (!$e || !$e->transition('approved', 'voicing', ['step_started_at' => now(), 'error' => null])) {
            return;
        }
        $e->log('voice', 'started');

        $needed = mb_strlen($e->script_ur) + 500;
        if ($el->remainingCharacters() < $needed) {
            throw new \RuntimeException('Not enough ElevenLabs characters left this month.');
        }

        $out = $el->speak($e->script_ur);
        Storage::put("episodes/{$e->id}/voice.mp3", $out['audio']);
        Storage::put("episodes/{$e->id}/captions.srt", $el->toSrt($out['alignment']));

        $e->transition('voicing', 'awaiting_avatar', [
            'voice_path' => "episodes/{$e->id}/voice.mp3",
            'captions_path' => "episodes/{$e->id}/captions.srt",
            'alignment' => $out['alignment'],
        ]);
        $e->log('voice', 'ok', mb_strlen($e->script_ur) . ' characters');

        Notify::user($e->user, 'Voice ready: upload the avatar clip', "Voice for {$e->run_date->toDateString()} is ready.", Notify::episodeLink($e->id));
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Voice: ' . $ex->getMessage());
    }
}
