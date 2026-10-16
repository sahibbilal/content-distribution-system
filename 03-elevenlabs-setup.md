# 03 — ElevenLabs Setup, API and Skills

ElevenLabs does two jobs in this pipeline. Through the API, the web app turns the approved Urdu script into a voiceover in your cloned voice, with timings for captions. In the ElevenLabs app, you make the avatar clip by hand (about 10 minutes a day) until an avatar API is available.

## 1. Account and voice

1. Choose a paid plan that includes Professional Voice Cloning and enough characters for about 30 scripts a month (a 4-minute Urdu script is roughly 3,000–4,000 characters, so plan for ~120k characters/month plus retries). Check current plans on elevenlabs.io/pricing.
2. Create a Professional Voice Clone. Record 30+ minutes of clean Urdu speech in the same style as the channel (quiet room, one microphone, no music). Include English tech words in the recordings, since the scripts mix them in.
3. After the clone is ready, test it with a real Series A script in the ElevenLabs app. Try `eleven_v3` and the multilingual model and keep whichever sounds most natural in Urdu. Confirm Urdu support with `GET /v1/models` (check the `languages` list).
4. Save the voice settings you like (stability, similarity, style). You will send the same values from the API every day.
5. Copy the `voice_id` from the voice's page.

### API key

Profile → API Keys → create a key named `webapp-series-a`. Limit it to Text to Speech, Voices (read) and User (read) if scoped keys are offered, and set a monthly character limit as a safety cap. Put it in the web app `.env` as `ELEVENLABS_API_KEY`.

### Pronunciation

Create a pronunciation dictionary for words the voice gets wrong (product names, acronyms like "LLM", "API", names of people). Add its ID to the request (`pronunciation_dictionary_locators`), or simply instruct the `urdu-script` skill to spell problem words the way they should sound.

## 2. API: voice with timestamps

Endpoint used:

```
POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}/with-timestamps
Header: xi-api-key: <key>
```

The response contains `audio_base64` and `alignment` (characters with start/end times). The alignment is what makes captions match the voice exactly, without running speech-to-text on Urdu.

Long scripts are split into paragraphs. Each request sends `previous_text` and `next_text` so the voice stays consistent across chunks, and the chunks are joined afterwards with their timings offset.

### `App\Services\ElevenLabsService`

```php
class ElevenLabsService
{
    private string $base = 'https://api.elevenlabs.io/v1';

    public function speak(string $script): array
    {
        $script = $this->cleanForSpeech($script);
        $paras  = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $script))));

        $audio = '';
        $chars = $starts = $ends = [];
        $offset = 0.0;

        foreach ($paras as $i => $text) {
            $res = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
                ->timeout(120)->retry(2, 3000)
                ->post("{$this->base}/text-to-speech/".config('services.elevenlabs.voice_id').'/with-timestamps?output_format=mp3_44100_128', [
                    'text'          => $text,
                    'model_id'      => config('services.elevenlabs.model_id'),
                    'previous_text' => $paras[$i - 1] ?? null,
                    'next_text'     => $paras[$i + 1] ?? null,
                    'voice_settings'=> config('services.elevenlabs.voice_settings'),
                ])->throw()->json();

            $audio .= base64_decode($res['audio_base64']);
            $a = $res['alignment'];

            foreach ($a['characters'] as $k => $c) {
                $chars[]  = $c;
                $starts[] = $a['character_start_times_seconds'][$k] + $offset;
                $ends[]   = $a['character_end_times_seconds'][$k] + $offset;
            }
            $chars[] = "\n"; $starts[] = end($ends); $ends[] = end($ends);   // paragraph break
            $offset = end($ends);
        }

        return ['audio' => $audio, 'alignment' => compact('chars', 'starts', 'ends')];
    }

    /** Remove [SHORT1] markers etc. before sending to TTS. */
    private function cleanForSpeech(string $s): string
    {
        return trim(preg_replace('/\[\/?SHORT\d+\]/', '', $s));
    }

    /** Build SRT: group words into caption lines of ~6 words or ~2.5 seconds. */
    public function toSrt(array $al, int $maxWords = 6, float $maxSec = 2.5): string
    {
        $words = []; $w = ''; $ws = null;
        foreach ($al['chars'] as $i => $c) {
            if (preg_match('/\s/u', $c)) {
                if ($w !== '') $words[] = [$w, $ws, $al['ends'][$i - 1]];
                $w = ''; $ws = null; continue;
            }
            $ws ??= $al['starts'][$i];
            $w .= $c;
        }
        if ($w !== '') $words[] = [$w, $ws, end($al['ends'])];

        $srt = ''; $n = 1; $line = [];
        foreach ($words as $word) {
            $line[] = $word;
            $dur = end($line)[2] - $line[0][1];
            if (count($line) >= $maxWords || $dur >= $maxSec || preg_match('/[۔؟!.?]$/u', $word[0])) {
                $srt .= $this->srtBlock($n++, $line); $line = [];
            }
        }
        if ($line) $srt .= $this->srtBlock($n, $line);
        return $srt;
    }

    private function srtBlock(int $n, array $line): string
    {
        $t = function (float $s): string {
            $ms = (int) round($s * 1000);
            return sprintf('%02d:%02d:%02d,%03d', intdiv($ms, 3600000), intdiv($ms, 60000) % 60, intdiv($ms, 1000) % 60, $ms % 1000);
        };
        return $n."\n".$t($line[0][1]).' --> '.$t(end($line)[2])."\n".implode(' ', array_column($line, 0))."\n\n";
    }

    public function remainingCharacters(): int
    {
        $s = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
            ->get("{$this->base}/user/subscription")->throw()->json();
        return $s['character_limit'] - $s['character_count'];
    }
}
```

### `config/services.php`

```php
'elevenlabs' => [
    'key'            => env('ELEVENLABS_API_KEY'),
    'voice_id'       => env('ELEVENLABS_VOICE_ID'),
    'model_id'       => env('ELEVENLABS_MODEL_ID', 'eleven_v3'),
    'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.8, 'style' => 0.2, 'use_speaker_boost' => true],
],
```

Replace the voice settings with the values you chose in step 4 of section 1.

### `GenerateVoiceJob`

```php
public function handle(ElevenLabsService $el): void
{
    $e = $this->episode->fresh();
    if ($e->status !== 'approved') return;

    $e->update(['status' => 'voicing']);

    $needed = mb_strlen($e->script_ur) + 500;
    if ($el->remainingCharacters() < $needed) {
        throw new RuntimeException('Not enough ElevenLabs characters left this month.');
    }

    $out = $el->speak($e->script_ur);
    Storage::put("episodes/{$e->id}/voice.mp3", $out['audio']);
    Storage::put("episodes/{$e->id}/captions.srt", $el->toSrt($out['alignment']));

    $e->update([
        'voice_path'    => "episodes/{$e->id}/voice.mp3",
        'captions_path' => "episodes/{$e->id}/captions.srt",
        'alignment'     => $out['alignment'],
        'status'        => 'awaiting_avatar',
    ]);

    Notify::send("Voice ready for episode {$e->id}. Upload the avatar clip.", link: route('episodes.show', $e));
}

public function failed(Throwable $ex): void
{
    EpisodeFailed::dispatch($this->episode, 'Voice: '.$ex->getMessage());
}
```

Since the Shorts are cut from the long video, the same `captions.srt` serves every output.

## 3. Daily avatar clip (manual)

1. When notified, open the episode page and download `voice.mp3`.
2. In the ElevenLabs app, create the avatar video driven by that exact audio file, so lip-sync and length match the voiceover. Do not regenerate the speech there.
3. Export at 1080p or higher with a plain or green background, avatar centered (the Shorts crop takes the middle of the frame).
4. Upload the file on the episode page. The web app saves it as `avatar.mp4` and starts the edit job.

If the avatar export comes back with its own audio track, that's fine; `render.sh` maps the audio from `voice.mp3`.

## 4. ElevenLabs skills for Claude

In this pipeline, the web app calls ElevenLabs directly, so Claude doesn't need ElevenLabs access for Series A. A skill helps when you want to ask the cloud worker for one-off audio tasks ("make a 30-second voice intro from this text", "check how many characters are left").

### Option A: official skills

ElevenLabs publishes official Agent Skills in the `elevenlabs/skills` GitHub repository. Copy the ones you need into the worker's skills folder:

```bash
cd /tmp && git clone https://github.com/elevenlabs/skills.git elevenlabs-skills
ls elevenlabs-skills            # pick the text-to-speech related skill folder(s)
cp -r elevenlabs-skills/<skill-folder> /srv/worker/workspace/.claude/skills/
```

Read each SKILL.md before installing; it should only describe API usage and must not ask for anything beyond the API key.

### Option B: your own small skill

`/srv/worker/workspace/.claude/skills/elevenlabs-voice/SKILL.md`

```markdown
---
name: elevenlabs-voice
description: Generate speech in Bilal's cloned voice with ElevenLabs, or check remaining ElevenLabs characters. Use for one-off voice requests, not for Series A episodes.
---

Run the helper only. Never print or echo the API key.

- Speech: bash .claude/skills/elevenlabs-voice/tts.sh "<text file in job folder>" out/voice.mp3
- Characters left: bash .claude/skills/elevenlabs-voice/tts.sh --usage

Keep requests under 3,000 characters; split longer text into paragraphs and run once per paragraph,
then join with: ffmpeg -f concat -safe 0 -i list.txt -c copy out/voice.mp3
```

`/srv/worker/workspace/.claude/skills/elevenlabs-voice/tts.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail
source /home/bilal/.elevenlabs.env     # ELEVENLABS_API_KEY, ELEVENLABS_VOICE_ID, ELEVENLABS_MODEL_ID

if [[ "${1:-}" == "--usage" ]]; then
  curl -s https://api.elevenlabs.io/v1/user/subscription -H "xi-api-key: $ELEVENLABS_API_KEY" \
    | jq '{used: .character_count, limit: .character_limit}'
  exit 0
fi

TEXT=$(jq -Rs . < "$1")
mkdir -p "$(dirname "$2")"
curl -sf "https://api.elevenlabs.io/v1/text-to-speech/${ELEVENLABS_VOICE_ID}?output_format=mp3_44100_128" \
  -H "xi-api-key: $ELEVENLABS_API_KEY" -H "Content-Type: application/json" \
  -d "{\"text\": $TEXT, \"model_id\": \"$ELEVENLABS_MODEL_ID\"}" -o "$2"
```

```bash
chmod 600 /home/bilal/.elevenlabs.env
chmod +x /srv/worker/workspace/.claude/skills/elevenlabs-voice/tts.sh
```

Add `"Bash(bash .claude/skills/elevenlabs-voice/tts.sh:*)"` to the `allow` list in `.claude/settings.json`. The key stays in a file Claude only uses through the script, and `curl` stays denied for everything else.

Use a separate ElevenLabs API key for the worker (with its own character cap), so a runaway request can't use up the Series A budget.

## 5. Errors to handle

| response | meaning | action |
|---|---|---|
| 401 | bad or revoked key | stop, notify, replace key |
| 422 | bad request (text too long, wrong voice/model id) | stop, notify with the message |
| 429 | rate limit or too many concurrent requests | retry after a short wait (built into `retry(2, 3000)`) |
| quota exceeded | monthly characters used up | stop the day, notify; top up or wait for reset |

Every failure ends the episode for the day with nothing posted, as described in document 01.

## 6. First-week checklist

1. Generate voice for 3 old scripts and listen for wrong pronunciations; update the dictionary.
2. Check that `captions.srt` lines up with speech in the final video, especially after paragraph joins.
3. Note real character usage per episode and adjust the plan if needed.
4. Time your avatar workflow; if it is over 10 minutes, simplify the avatar settings and save them as a preset.
