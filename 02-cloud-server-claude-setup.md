# 02 — Cloud Server, Claude Code and Skills

This server is the "Cloud Worker". It receives jobs from the web app, runs Claude Code headless with your own Claude subscription, and sends results back by webhook. It is for your personal use only. The same Claude account is used on your laptop, and both share one usage limit.

## 1. Server

| item | choice |
|---|---|
| Size | 4 vCPU, 8 GB RAM, 80+ GB disk (video rendering needs this) |
| OS | Ubuntu 24.04 LTS |
| Providers | Hetzner, Hostinger KVM 2, Contabo, DigitalOcean (~$10–15/month) |
| Domain | `worker.yourdomain.com` → server IP |

### Base setup

```bash
# as root, first login
adduser bilal && usermod -aG sudo bilal
rsync --archive --chown=bilal:bilal ~/.ssh /home/bilal
# disable password + root SSH login
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/; s/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl restart ssh

ufw allow OpenSSH && ufw allow 80,443/tcp && ufw enable

# 4 GB swap for long renders
fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

timedatectl set-timezone Asia/Karachi
apt update && apt upgrade -y
```

### Packages

```bash
sudo apt install -y git curl unzip jq ffmpeg nginx supervisor redis-server \
  php8.3-fpm php8.3-cli php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-redis php8.3-sqlite3 \
  fonts-noto-core fonts-noto-ui-core fonts-noto-color-emoji

# Urdu Nastaliq font for captions and thumbnails
sudo mkdir -p /usr/share/fonts/truetype/noto-urdu
sudo curl -L -o /usr/share/fonts/truetype/noto-urdu/NotoNastaliqUrdu.ttf \
  https://github.com/notofonts/nastaliq/raw/main/fonts/NotoNastaliqUrdu/unhinted/ttf/NotoNastaliqUrdu-Regular.ttf
sudo fc-cache -f

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node (for Playwright thumbnails)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
sudo npx -y playwright@latest install --with-deps chromium

# HTTPS
sudo apt install -y certbot python3-certbot-nginx
```

Check the font URL against the notofonts/nastaliq repo if it has moved. Test Urdu caption rendering once with ffmpeg before relying on it (section 6).

## 2. Install Claude Code

Run as `bilal`, never with sudo:

```bash
curl -fsSL https://claude.ai/install.sh | bash
claude --version
```

### Log in with your subscription (not an API key)

On your laptop (or on the server), run:

```bash
claude setup-token
```

It opens a browser login with your Claude account and prints a long-lived token. On the server, store it only for the worker user:

```bash
echo 'export CLAUDE_CODE_OAUTH_TOKEN="paste-token-here"' >> ~/.bashrc
source ~/.bashrc
unset ANTHROPIC_API_KEY          # must NOT be set, or usage is billed to API
claude -p "Say OK" --output-format json
```

The JSON should show a result with no API cost. Put the same variable in the Supervisor config (section 5) because workers don't read `.bashrc`. When the token expires, run `claude setup-token` again and replace it.

## 3. Workspace layout

```
/srv/worker/
├── app/                      # small Laravel app: API + queue (section 4)
└── workspace/                # Claude Code runs here
    ├── CLAUDE.md             # standing rules for every run
    ├── .claude/
    │   ├── settings.json     # tool permissions
    │   └── skills/
    │       ├── news-research/SKILL.md
    │       ├── urdu-script/SKILL.md
    │       ├── video-edit/
    │       │   ├── SKILL.md
    │       │   └── render.sh
    │       └── thumbnail/
    │           ├── SKILL.md
    │           ├── template.html
    │           └── render.mjs
    └── jobs/{episode_id}/    # one folder per run, deleted after 7 days
```

Skills placed in `workspace/.claude/skills/` are project skills, so Claude Code picks them up whenever it runs inside `workspace/` or a job folder below it (pass `--add-dir` if needed). Claude loads a skill automatically when the prompt matches its description.

### `CLAUDE.md`

```markdown
# Cloud Worker rules

- You run unattended. Never ask questions. If something is missing or wrong, write
  {"status":"failed","error":"..."} to result.json and stop.
- Work only inside the current job folder.
- Always finish by writing result.json in the job folder. It is the only output the web app reads.
- Never invent facts, quotes, numbers or links. Every claim must come from a source you fetched today.
- Language for scripts: Urdu (Nastaliq script). Keep English technical terms (API, model names,
  company names) in English letters.
```

### `.claude/settings.json`

```json
{
  "permissions": {
    "allow": [
      "WebSearch",
      "WebFetch",
      "Read",
      "Write",
      "Edit",
      "Bash(ffmpeg:*)",
      "Bash(ffprobe:*)",
      "Bash(bash .claude/skills/video-edit/render.sh:*)",
      "Bash(node .claude/skills/thumbnail/render.mjs:*)",
      "Bash(jq:*)",
      "Bash(ls:*)"
    ],
    "deny": [
      "Bash(rm -rf:*)",
      "Bash(curl:*)",
      "Bash(sudo:*)"
    ]
  }
}
```

## 4. Skills

### `news-research/SKILL.md`

```markdown
---
name: news-research
description: Find the single top AI or web development story of the day and collect verified sources. Use when asked to research today's story for Series A.
---

1. Search news from the last 24 hours (PKT) on AI and web development: model releases,
   major product launches, big funding, policy, major open-source releases.
2. Pick ONE story with the most impact for a Pakistani developer audience.
   Skip rumours, single-source claims and anything older than 48 hours.
3. Fetch at least 3 independent sources, preferring the original announcement.
4. Write sources.json:
   [{"title": "", "url": "", "published": "", "key_facts": ["..."]}]
5. Write facts.json: every fact you will use, each with the source url that supports it.
   Mark any fact found in only one source as "single_source": true.
6. If feedback is provided without current_script, choose a different angle or story accordingly.
7. If current_script is provided with feedback, keep that script and change only what the feedback asks (save it as current_script.md in the job folder first); re-check every changed sentence against facts.json.
```

### `urdu-script/SKILL.md`

```markdown
---
name: urdu-script
description: Write a 4-minute Urdu video script from facts.json and fact-check it. Use after news-research for Series A.
---

Script rules
- Length: 550–650 words (about 4 minutes of speech).
- Structure: hook (first 10 seconds), what happened, why it matters, what it means for
  developers in Pakistan, one-line closing with call to follow.
- Conversational Urdu, short sentences, no heavy Persian/Arabic vocabulary.
- English terms stay in English letters. Spell numbers in words where they are spoken.
- No facts outside facts.json. Do not use single_source facts as headline claims.
- Mark 2–3 strong 30–55 second segments that can stand alone as Shorts:
  wrap them as [SHORT1]...[/SHORT1].

Fact-check
- Re-read every sentence against facts.json. Remove or fix anything not supported.

Output
- script.md: the script only (with SHORT markers).
- result.json:
  {"status":"ok","type":"research","topic":"","script":"<script.md content>",
   "sources":[{"title":"","url":"","claim":""}]}
```

### `video-edit/SKILL.md`

```markdown
---
name: video-edit
description: Build the long video and Shorts from avatar.mp4, voice.mp3 and captions.srt using render.sh. Use for Series A edit jobs.
---

Inputs in the job folder: avatar.mp4, voice.mp3, captions.srt, script.md.

1. Run ffprobe on avatar.mp4 and voice.mp3; fail if either is missing or under 60 seconds.
2. Long video: bash .claude/skills/video-edit/render.sh long
   → out/long.mp4 (16:9, 1080p, burned captions).
3. Shorts: find each [SHORTn] segment in script.md, locate its start/end time in captions.srt,
   then for each: bash .claude/skills/video-edit/render.sh short <n> <start> <end>
   → out/short<n>.mp4 (9:16, 1080x1920, under 60 s, burned captions).
4. Thumbnail: use the thumbnail skill → out/thumbnail.png.
5. Write titles and descriptions (Urdu + English keywords, 3–5 hashtags) for long and each short.
6. Write result.json:
   {"status":"ok","type":"edit","files":{"long":"out/long.mp4","shorts":["out/short1.mp4"],
    "thumbnail":"out/thumbnail.png"},
    "meta":{"titles":{"long":"","shorts":[""]},"descriptions":{"long":"","shorts":[""]}}}

Do not write your own ffmpeg pipelines unless render.sh fails; if it fails, report the error.
```

### `video-edit/render.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail
mkdir -p out
STYLE="FontName=Noto Nastaliq Urdu,FontSize=22,PrimaryColour=&H00FFFFFF,OutlineColour=&H00000000,BorderStyle=3,Outline=2,MarginV=60"

case "$1" in
  long)
    ffmpeg -y -i avatar.mp4 -i voice.mp3 \
      -map 0:v -map 1:a -c:v libx264 -preset medium -crf 20 -c:a aac -b:a 192k -shortest \
      -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,subtitles=captions.srt:force_style='${STYLE}'" \
      out/long.mp4 ;;
  short)
    N=$2; START=$3; END=$4
    ffmpeg -y -ss "$START" -to "$END" -i out/long.mp4 \
      -vf "crop=ih*9/16:ih,scale=1080:1920" -c:v libx264 -crf 20 -c:a aac \
      "out/short${N}.mp4" ;;
  *) echo "usage: render.sh long | short N START END" >&2; exit 1 ;;
esac
```

The Short is cut from the finished long video, so captions are already burned in. If the avatar is not centered, adjust the crop offset once and keep it fixed.

### `thumbnail/SKILL.md`

```markdown
---
name: thumbnail
description: Create the Series A thumbnail PNG from a frame and a short Urdu headline.
---

1. Extract a frame: ffmpeg -ss 00:00:05 -i avatar.mp4 -frames:v 1 out/frame.jpg
2. Write a headline of 3–5 Urdu words (plus one English keyword if it helps).
3. Run: node .claude/skills/thumbnail/render.mjs "<headline>" out/frame.jpg out/thumbnail.png
```

### `thumbnail/render.mjs`

```js
import { chromium } from 'playwright';
import { readFileSync } from 'fs';
import path from 'path';

const [headline, frame, out] = process.argv.slice(2);
const dir = path.dirname(new URL(import.meta.url).pathname);
const img = 'data:image/jpeg;base64,' + readFileSync(frame).toString('base64');
const html = readFileSync(path.join(dir, 'template.html'), 'utf8')
  .replace('{{HEADLINE}}', headline).replace('{{FRAME}}', img);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 720 } });
await page.setContent(html, { waitUntil: 'networkidle' });
await page.screenshot({ path: out });
await browser.close();
```

`template.html` is a 1280×720 page with the frame as background, a dark gradient on one side and `{{HEADLINE}}` in `Noto Nastaliq Urdu`, `dir="rtl"`, large yellow or white text. Design it once and keep it as your channel style.

### Text and image posts (`kind` = `text` or `image`)

The web app also sends on-demand jobs to `POST /jobs/research` with `kind: "text" | "image"`, a free-text `prompt` ("search the latest news about X and write a LinkedIn post") and `platforms` (the platform types it will be posted to, e.g. `["linkedin","facebook"]`). The same `feedback` and `current_script` fields work for revising. There is no voice or video step.

`post-writer/SKILL.md`

```markdown
---
name: post-writer
description: Research a topic from a request and write a social media post in the right length and tone for the target platforms. Use for text and image jobs (kind text or image).
---

1. Use the news-research skill for the request in the prompt (search, open sources, facts.json).
2. Write ONE post text that works on every platform in `platforms`: use the strictest limit
   (X: 280 characters; Facebook/LinkedIn: up to ~1,300; Instagram caption: up to ~2,000). If the limits are far
   apart, write for the shortest platform. Use the language the prompt is written in.
3. No facts outside facts.json. Add the source link at the end only if the post is for LinkedIn/Facebook/Website.
4. If current_script.md exists, keep it and change only what the feedback asks.
5. For kind image, also run the image-card skill with a short headline from the post.
6. Write result.json:
   {"status":"ok","type":"research","topic":"","script":"<post text>",
    "sources":[{"title":"","url":"","claim":""}],
    "image":"out/card.png"}          // "image" only for kind image
```

`image-card/SKILL.md`: renders `out/card.png` (1080×1080) from a headline and, optionally, a stat or quote, with the same Playwright approach as `thumbnail/render.mjs` and its own `template.html` (channel colours, `dir="auto"` so English and Urdu both work). Allow `Bash(node .claude/skills/image-card/render.mjs:*)` in `settings.json`. Real AI-generated pictures are a later option: add an image-model skill and keep the same `image` field in `result.json`.

The Create Post page ("Ask AI" with the Claude worker) sends the same request plus `options: {"hashtags": true|false, "x_limit": 280|null, "instructions": "<the user's rulebook, the project's instructions and its memory>", "project_id": 1|null}`. Follow `instructions` in everything you write, including what is listed under "Already written".

When `x_limit` is set (an X channel is selected), write a **separate** short text for X and return both:

```json
{"status":"ok","type":"research","topic":"","script":"<long text>",
 "versions":{"long":"<long text>","short":"<X text>"},
 "hashtags":["a","b"],"hashtags_short":["a"],
 "sources":[],"image":"out/card.png"}
```

`short` plus a blank line plus its `#hashtags` must be at most `x_limit` characters (spaces count). Leave `hashtags` empty when `options.hashtags` is false. The web app reads this result and fills the form; it does not post it.

For `kind` = `image` the worker signs `image` like the edit outputs (`GET /files/...`, valid 3 hours) before calling back. The web app downloads it, shows it on the approval screen and attaches it to the post.

## 5. Worker API app

A small Laravel app in `/srv/worker/app` with two endpoints and one queue.

```
POST /jobs/research   → creates jobs/{episode_id}/, queues RunClaudeJob(research)
POST /jobs/edit       → downloads voice/avatar/captions into jobs/{episode_id}/, queues RunClaudeJob(edit)
GET  /files/{episode}/{path}?signature=...   → signed download of outputs
GET  /health                                 → 200 {"ok":true} when X-Signature is valid
```

Both POST routes verify the `X-Signature` HMAC with the shared secret and return `{"job_id": "..."}` right away.

`GET /health` verifies the same header, where the signed body is the empty string (`hash_hmac('sha256', '', $secret)`), and answers 401 when it does not match. The web app's **Admin > Content pipeline > Save and test** button calls it, so a wrong URL or secret shows up there instead of during the first real job.

### `RunClaudeJob`

```php
public $timeout = 3600;
public $tries = 1;

public function handle(): void
{
    $dir = "/srv/worker/workspace/jobs/{$this->episodeId}";

    $prompt = $this->type === 'research'
        ? ($this->kind === 'video'
            ? "Use the news-research skill, then the urdu-script skill for Series A, date {$this->date}."
            : "Use the post-writer skill. Kind: {$this->kind}. Platforms: " . implode(', ', $this->platforms) . ". Request: {$this->prompt}")
          . ($this->feedback ? " Feedback: {$this->feedback}." . ($this->currentScript ? " Revise the existing script in current_script.md." : " Choose a different story.") : '')
        : "Use the video-edit skill for this job folder.";

    $run = Process::path($dir)->timeout(3500)->env([
        'CLAUDE_CODE_OAUTH_TOKEN' => config('services.claude.oauth_token'),
    ])->run([
        'claude', '-p', $prompt,
        '--output-format', 'json',
        '--max-turns', '40',
        '--add-dir', '/srv/worker/workspace',
    ]);

    $result = is_file("$dir/result.json") ? json_decode(file_get_contents("$dir/result.json"), true) : null;

    if (! $run->successful() || ! $result || ($result['status'] ?? '') !== 'ok') {
        $this->callback(['status' => 'failed', 'type' => $this->type,
            'error' => $result['error'] ?? trim($run->errorOutput()) ?: 'No result.json']);
        return;
    }

    if ($this->type === 'edit') {
        $result['files'] = $this->signUrls($result['files']);   // GET /files/... signed for 3 hours
    }

    if ($this->type === 'research' && ! empty($result['image'])) {
        $result['image'] = $this->signUrl($result['image']);   // text + image jobs
    }

    $this->callback($result);
}
```

`callback()` POSTs to the web app's `/webhooks/worker` with `episode_id` and the same HMAC header.

### Supervisor (`/etc/supervisor/conf.d/worker.conf`)

```ini
[program:worker-queue]
command=php /srv/worker/app/artisan queue:work redis --timeout=3600 --tries=1
directory=/srv/worker/app
user=bilal
numprocs=1
autostart=true
autorestart=true
environment=HOME="/home/bilal",PATH="/home/bilal/.local/bin:/usr/local/bin:/usr/bin:/bin",CLAUDE_CODE_OAUTH_TOKEN="paste-token-here"
stdout_logfile=/var/log/worker-queue.log
```

Keep `numprocs=1`: one Claude run at a time protects your usage limit. Check the `PATH` with `which claude` after install.

### Nginx + HTTPS

Standard Laravel site for `worker.yourdomain.com`, then `sudo certbot --nginx -d worker.yourdomain.com`. Optionally allow only your web app's IP in Nginx for `/jobs/*`.

## 6. Tests before connecting the web app

```bash
cd /srv/worker/workspace && mkdir -p jobs/test && cd jobs/test

# research + script
claude -p "Use the news-research skill, then the urdu-script skill for Series A, date $(date +%F)." \
  --output-format json --max-turns 40 --add-dir /srv/worker/workspace
cat result.json | jq .

# edit: copy a sample avatar.mp4, voice.mp3, captions.srt here first
claude -p "Use the video-edit skill for this job folder." --output-format json --max-turns 40 --add-dir /srv/worker/workspace
ls -la out/
```

Watch `out/long.mp4` and one Short on your phone to check Urdu caption shaping, position and crop.

## 7. Maintenance

```bash
# crontab -e  (as bilal)
0 3 * * * find /srv/worker/workspace/jobs -mindepth 1 -maxdepth 1 -mtime +7 -exec rm -rf {} +
```

The native installer updates Claude Code by itself. Watch `/var/log/worker-queue.log` for failures. If runs start failing with auth errors, renew the token with `claude setup-token`. If you hit usage limits often, upgrade from Pro to Max; nothing else changes.
