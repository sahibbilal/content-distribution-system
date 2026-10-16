<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Text to speech in the cloned voice, with per-character timings that become the captions (document 03). */
class ElevenLabsService
{
    private string $base = 'https://api.elevenlabs.io/v1';

    /** @return array{audio: string, alignment: array{chars: array, starts: array, ends: array}} */
    public function speak(string $script): array
    {
        if (!config('services.elevenlabs.key') || !config('services.elevenlabs.voice_id')) {
            throw new \RuntimeException('ElevenLabs is not configured (ELEVENLABS_API_KEY / ELEVENLABS_VOICE_ID).');
        }

        $paras = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $this->cleanForSpeech($script)))));
        if (!$paras) {
            throw new \RuntimeException('The script is empty.');
        }

        $audio = '';
        $chars = $starts = $ends = [];
        $offset = 0.0;

        foreach ($paras as $i => $text) {
            $res = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
                ->timeout(120)->retry(2, 3000)
                ->post("{$this->base}/text-to-speech/" . config('services.elevenlabs.voice_id') . '/with-timestamps?output_format=mp3_44100_128', [
                    'text' => $text,
                    'model_id' => config('services.elevenlabs.model_id'),
                    'previous_text' => $paras[$i - 1] ?? null,
                    'next_text' => $paras[$i + 1] ?? null,
                    'voice_settings' => config('services.elevenlabs.voice_settings'),
                ])->throw()->json();

            $audio .= base64_decode($res['audio_base64']);
            $a = $res['alignment'];

            foreach ($a['characters'] as $k => $c) {
                $chars[] = $c;
                $starts[] = $a['character_start_times_seconds'][$k] + $offset;
                $ends[] = $a['character_end_times_seconds'][$k] + $offset;
            }
            $chars[] = "\n"; // paragraph break
            $starts[] = end($ends);
            $ends[] = end($ends);
            $offset = end($ends);
        }

        return ['audio' => $audio, 'alignment' => compact('chars', 'starts', 'ends')];
    }

    /** Remove [SHORT1] markers before sending the text to TTS */
    public function cleanForSpeech(string $s): string
    {
        return trim(preg_replace('/\[\/?SHORT\d+\]/', '', $s));
    }

    /** Build SRT: group words into caption lines of about 6 words or 2.5 seconds */
    public function toSrt(array $al, int $maxWords = 6, float $maxSec = 2.5): string
    {
        $words = [];
        $w = '';
        $ws = null;
        foreach ($al['chars'] as $i => $c) {
            if (preg_match('/\s/u', $c)) {
                if ($w !== '') {
                    $words[] = [$w, $ws, $al['ends'][$i - 1]];
                }
                $w = '';
                $ws = null;
                continue;
            }
            $ws ??= $al['starts'][$i];
            $w .= $c;
        }
        if ($w !== '') {
            $words[] = [$w, $ws, end($al['ends'])];
        }

        $srt = '';
        $n = 1;
        $line = [];
        foreach ($words as $word) {
            $line[] = $word;
            $dur = end($line)[2] - $line[0][1];
            if (count($line) >= $maxWords || $dur >= $maxSec || preg_match('/[۔؟!.?]$/u', $word[0])) {
                $srt .= $this->srtBlock($n++, $line);
                $line = [];
            }
        }
        if ($line) {
            $srt .= $this->srtBlock($n, $line);
        }

        return $srt;
    }

    private function srtBlock(int $n, array $line): string
    {
        $t = function (float $s): string {
            $ms = (int) round($s * 1000);

            return sprintf('%02d:%02d:%02d,%03d', intdiv($ms, 3600000), intdiv($ms, 60000) % 60, intdiv($ms, 1000) % 60, $ms % 1000);
        };

        return $n . "\n" . $t($line[0][1]) . ' --> ' . $t(end($line)[2]) . "\n" . implode(' ', array_column($line, 0)) . "\n\n";
    }

    public function remainingCharacters(): int
    {
        $s = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
            ->get("{$this->base}/user/subscription")->throw()->json();

        return $s['character_limit'] - $s['character_count'];
    }
}
