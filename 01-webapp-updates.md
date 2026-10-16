# 01 — Web App Updates (Claude + ElevenLabs)

**Status: built and tested in the web app** (48 PHPUnit tests pass). Not yet run against the real worker or ElevenLabs: that needs documents 02 and 03 set up first. See section 9 for what is left.

The web app already schedules and publishes posts to FB, Instagram, YouTube, TikTok, X and LinkedIn. This update adds a daily "Series A" pipeline on top of it. The web app stays the controller: it starts each step, stores every file and status, handles approval, and hands the final videos to the existing scheduler.

## 1. Flow

```
[Web App]  5:00 pm scheduler ──► POST /jobs/research ──► [Cloud Worker + Claude Code]
                                                            researches, writes Urdu script,
                                                            fact-checks against sources
[Web App]  ◄── webhook: script + sources ───────────────────┘
           Approval screen: approve / edit / revise with an instruction / reject
           ──► ElevenLabs API (cloned voice, with timestamps)
           ◄── voice.mp3 + alignment → captions.srt
           Upload avatar clip (manual, ~10 min)
           ──► POST /jobs/edit (signed links to voice, avatar, captions) ──► [Cloud Worker + Claude Code]
                                                            long video, Shorts, captions, thumbnail
[Web App]  ◄── webhook: signed output links + titles/descriptions ──┘
           Downloads the files into the media library
           Final approval screen: watch the videos, check the publish time, approve
           Creates posts in the existing scheduler (default 9:00 pm PKT)
           Email summary with links after publishing
```

## 1a. One page for writing posts: Create Post (with "Ask AI")

Text and image posts no longer have their own screen. **Create Post** has two ways in: **Write it myself** (the editor as before) or **Ask AI**. Ask AI only fills the form; nothing is posted until you press Publish or Schedule.

- **Ask AI:** a request ("search the latest news about Laravel 12 and write a post"), with options: search the web, add hashtags, make an image. Choose the AI per request: the **Anthropic API** (a few seconds; key in Admin > AI integrations) or the **Claude worker** (minutes; uses the subscription, polled by the page).
- **Two texts in one go:** the AI writes a long text for Facebook/LinkedIn/Instagram/website and a separate short text for X (the short text plus its hashtags must fit the X limit; the server asks the AI once more if it does not). The short text goes into X's own version in the form.
- **Different text per channel, by hand:** under the editor, every selected channel has a counter and a "Different text" option. Longer than the channel allows turns red and blocks publishing. Limits: X 280 characters (spaces and hashtags count; Premium accounts 25,000), Instagram 2,200, LinkedIn 3,000.
- **Hashtags:** a field below Media. They apply to every channel; "Different hashtags" per channel gives one channel its own (or none). They are added after the text as `#a #b`. The older **Website tags** field only shows when the website is selected.
- **Image only:** text is optional when a file is attached, so a lone image works on Instagram.
- **Images from the AI:** the AI looks at the images you uploaded, writes about them and warns when one does not fit the request (`image_check`). "Make an image" creates a picture with the OpenAI image API (key and image model in Admin > AI integrations) and attaches it to the post.
- **X account type:** set when connecting X (Free or Premium) and changeable on the X page; it decides the length limit.
- A scheduled post can be edited on its post page with the same hashtag and per-channel controls. Saved on the post as `hashtags` and `platform_overrides` ({platformId: {content?, hashtags?}}); `PublishPostJob` sends each channel its own version through `App\Support\PostText` (mirrored in `resources/js/postText.js`).
- Worker drafts: the worker's `research` result may carry `versions: {long, short}`, `hashtags` and `hashtags_short`; the request carries `options: {hashtags, x_limit}` (see document 02).
- The **Video episodes** page (`/episodes`) is now only the daily video, which needs the voice and avatar steps.

## 1a-2. AI assistant: rulebook, projects and memory (`/ai-assistant`)

An editable instruction file for the AI, per user, with a workspace per kind of content:

- **Instructions** (tab): one editable section each for *How to create a post*, *Format for each channel*, *Always remember* (who you are, audience, voice, never-do list), *Images*, *Scripts*, *Videos* and *Reading PDFs*. Starter text is in `config/ai_instructions.php`; a saved section replaces it and "Reset to starter text" brings it back. The first three apply to every Ask AI request, *Images* when a picture is wanted. *Scripts*, *Videos* and *Reading PDFs* are stored and editable now and will be used by the video-script and PDF projects (next steps).
- **Projects** (tab): a workspace with its own instructions ("what this project creates"), starting choices for Ask AI (search the web / hashtags / make an image) and default channels. Starter projects: *Latest trends* and *Jokes* (created once per user). Pick a project in Ask AI on Create Post and its choices and channels are applied.
- **Memory:** every draft the assistant writes is saved (`ai_generations`) with its opening line. The latest 40 openings of the project are given back to the AI as "Already written, do not repeat". A draft is marked **Posted** once a post is made from it. The memory can be viewed and cleared per project. A real model cannot be trained on your files, so this memory plus the PDF text (next step) is what the assistant "learns" from.
- **How it is applied:** `App\Services\AiInstructions::compose()` joins the sections, the project and its memory into the rules in the system prompt of `AiDraftService` (Anthropic API) and into `options.instructions` for the Claude worker.
- **Still to build:** (b) PDF library, batch plan ("7 posts from this PDF"), automatic checks and one-click "Schedule all"; (c) the video-script project and trend research checks.

## 1b. Other kinds of content (text and image posts) through the workerThe same pipeline is not only for video. An episode has a `kind`:

| kind | how it starts | steps | result |
|---|---|---|---|
| `video` | daily at 17:00, or "Start today's video" | research → script approval → voice → avatar → edit → final approval | long video + Shorts posts |
| `text` | "New AI post" on the AI Content page: a request (e.g. "search the latest news about X and write a LinkedIn post"), the platforms, a publish time | worker searches and writes → you approve, edit or revise | one scheduled post |
| `image` | same, with "Text + image" | worker searches, writes and renders an image card → you see text and image together → approve | one scheduled post with the image |

For text and image jobs, **Approve** is the only approval: it approves the draft and schedules the post in one tap (the publish time must be in the future). The post is an ordinary scheduled post, so it shows in Posts and Calendar and can be edited there. The editor, the media upload and Create Post are unchanged. Several text/image jobs can exist per day; only the daily video is limited to one a day (`day_key`).

Platforms are checked when the job is created: text only is refused for Instagram, YouTube and TikTok (they need media), and a text + image job is refused for platforms that cannot take an image.

Images are branded cards rendered by the worker (document 02). AI-generated pictures can be added later with the same `image` field.

ElevenLabs returns audio only. The avatar video stays a manual upload until ElevenLabs offers an avatar API.

The publish time is chosen in the web app on the episode page (date/time picker, "Publish tomorrow" button). The default is 21:00 PKT of the episode's day.

## 2. Database

### `episodes`

| column | type | notes |
|---|---|---|
| id, user_id | | episodes belong to a user (single user now, ready for more) |
| series | string | `series-a` for the daily video, `ai-post` for on-demand jobs |
| kind | string | `video`, `text` or `image` |
| prompt | text, nullable | what was asked for (text and image jobs) |
| platform_ids | json, nullable | where a text or image job is posted |
| day_key | string, nullable | `series-a:{date}`, unique per user: one daily video a day |
| run_date | date | the day the episode belongs to |
| topic | string, nullable | filled by research |
| status | string | see state machine |
| failed_step | string, nullable | status at the time of failure; Retry uses it |
| script_ur | longText, nullable | current Urdu script |
| sources | json, nullable | `[{title, url, claim}]` |
| voice_path, captions_path, avatar_path | string, nullable | private files under `storage/app/episodes/{id}/` |
| alignment | json, nullable | ElevenLabs character timings |
| outputs | json, nullable | `{long: media id, shorts: [media ids], thumbnail: media id, titles, descriptions}` |
| post_ids | json, nullable | posts created for the episode (used by "Cancel posting") |
| scheduled_for | datetime | publish time, editable |
| rejection_note | text, nullable | your note when rejecting or revising |
| error | text, nullable | last failure message |
| worker_job_id | string, nullable | job id returned by the worker |
| step_started_at | timestamp, nullable | when the current worker step began (watchdog) |

### `episode_logs`

`id, episode_id, step, status (started|ok|failed), message, payload json, created_at`. One row per step. Script rows keep the full text in `payload` (worker draft, hand edits, rejected scripts, revise instructions), so the history of every draft and instruction is kept. This is the base for the "history as training data" idea in document 04.

## 3. State machine

```
pending → researching → awaiting_approval → approved → voicing → awaiting_avatar
        → editing → importing → ready → scheduling → scheduled → published

awaiting_approval → pending        (reject with retry, or revise with an instruction)
awaiting_approval → rejected       (reject, stop the day)
scheduled → ready                  (Cancel posting)
any open step → failed             (stop, notify, nothing posted)
any step before scheduled → skipped    (not ready 15 minutes before the publish time)
any open step → cancelled          (you stopped it)
```

Rule: every status change is one atomic SQL update (`UPDATE ... WHERE status IN (expected)`). A job or webhook that finds a different status does nothing. This makes retries, duplicate webhooks and double clicks safe: a step can never run twice. `importing` and `scheduling` exist only to claim the step before the slow work starts.

## 4. Config

**Everything below can be set in the web app** on **Admin > Content pipeline** (admin only), with a test button for each connection. A value saved there overrides the `.env` value; a blank field falls back to `.env`. Secrets are stored encrypted and are never shown again (only the last 4 characters). The page also has an "Is everything in place?" checklist (worker, ElevenLabs, cron heartbeat, queue worker heartbeat, connected video platforms, mail, daily video owner), which is repeated at the top of the AI Content page for admins. The `.env` keys remain as defaults:

`.env`

```
CLAUDE_WORKER_URL=https://worker.yourdomain.com
CLAUDE_WORKER_SECRET=long-random-string        # HMAC for both directions; the webhook refuses everything while empty
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=...
ELEVENLABS_MODEL_ID=eleven_v3
```

**Series A settings are no longer in `.env`.** All of them are fields on Admin > Content pipeline: research time, publish time, timezone, Shorts per episode, who owns the daily video, minutes before publishing after which an unready day is skipped, the time limit of the research, voice and edit steps, and which platforms the long video and each Short go to (YouTube, Facebook, Instagram, TikTok, X, LinkedIn). `config/series.php` only holds the starting values; a blank field goes back to them. The worker and ElevenLabs keys can still be given in `.env` (`config/services.php`) but the page overrides them.

Notifications are emails to the episode's user through the app's existing mail setup. Telegram is not used (it needs a VPN locally); push notifications come with the mobile app (document 04).

## 5. Code map

| file | does |
|---|---|
| `app/Models/Episode.php`, `EpisodeLog.php` | model, atomic `transition()`, `fail()`, `open()` (one episode per day) |
| `app/Services/ClaudeWorkerClient.php` | signed requests to the worker; signed download links for the worker |
| `app/Services/ElevenLabsService.php` | TTS with timestamps, chunking, SRT builder |
| `app/Jobs/StartResearchJob` | pending → researching, calls the worker |
| `app/Jobs/GenerateVoiceJob` | approved → voicing → awaiting_avatar (checks the character quota first) |
| `app/Jobs/StartEditJob` | awaiting_avatar → editing, calls the worker |
| `app/Jobs/ImportOutputsJob` | editing → importing → ready; downloads only from the worker's host |
| `app/Jobs/ImportImageJob` | text + image jobs: researching → importing → awaiting_approval (stores the image; replaces the old one on revise) |
| `app/Services/WorkerFileDownloader.php` | shared safe download of worker files into the media library |
| `app/Jobs/ScheduleEpisodePostsJob` | ready → scheduling → scheduled; creates normal scheduled posts |
| `app/Jobs/EpisodeWatchdogJob` | every 15 min: fails stuck steps, skips late days, closes published episodes |
| `app/Jobs/EpisodeSummaryJob` | email with every post's result and link |
| `app/Console/Commands/StartDailyEpisode.php` | `episodes:start-daily`, scheduled at 17:00 PKT |
| `app/Http/Controllers/WorkerWebhookController.php` | `POST /api/webhooks/worker` |
| `app/Http/Controllers/EpisodeController.php` | the episode API |
| `app/Http/Controllers/AdminPipelineController.php`, `app/Support/PipelineSettings.php`, `app/Providers/PipelineServiceProvider.php` | Admin > Content pipeline: saved settings (table `pipeline_settings`, applied to `config()` at boot and before every queued job), connection tests, checklist |
| `app/Jobs/QueueHeartbeatJob.php` | proves a queue worker is running (the scheduler writes its own heartbeat every minute) |
| `resources/js/pages/AdminPipeline.vue`, `components/PipelineChecks.vue` | the settings page and the checklist |
| `resources/js/pages/Episodes.vue`, `EpisodeDetail.vue` | list and episode page |

### Episode API (Sanctum token, so the mobile app can use it later)

`GET /api/episodes`, `POST /api/episodes` (`kind` video by default: today's video, or paste a `script_ur` to skip research; `kind` text/image with `prompt`, `platform_ids`, optional `scheduled_for`), `GET|PATCH /api/episodes/{id}` (publish time; script while awaiting approval), and `POST` on `approve`, `reject` (`note`, `retry`), `revise` (`instruction`), `avatar` (file), `approve-outputs`, `cancel-posting`, `retry`, `cancel`. Voice, avatar and captions are served from `GET /api/episode-files/{episode}/{kind}` with a signed, 3-hour link.

### Worker contract

- Requests to the worker carry `X-Signature: HMAC-SHA256(body, secret)`. The webhook verifies the same header.
- `POST /jobs/research`: `episode_id, series, kind, prompt, platforms, date, feedback, current_script, callback`. `feedback` and `current_script` are set when revising (the worker should change that script by the instruction). On a reject-and-retry, `feedback` is set and `current_script` is null (pick a different angle).
- `POST /jobs/edit`: `episode_id, files{voice,avatar,captions}` (signed links), `script, shorts, callback`.
- Webhook results: `{episode_id, type: research, status: ok, topic, script, sources}` `{... type: research, image: <signed url>}` for image jobs, and `{episode_id, type: edit, status: ok, files{long, shorts[], thumbnail}, meta{titles, descriptions}}` where `files` are signed download links on the worker's own host. Any failure: `{episode_id, type, status: failed, error}`. An unknown `type` gets a 422.

Document 02's worker needs two small changes to match: pass `current_script` to the research prompt when present, and send `files` as signed links (it already does).

### Platforms and media rules

The long video goes to YouTube and Facebook; each Short goes to YouTube, Facebook, Instagram and TikTok (`config/series.php`). X and LinkedIn are left out because the current publishers only accept images for them. A platform that is not connected is skipped; if none is connected the episode fails with a clear message. The Shorts/Reels versions use the same platform accounts as normal posts, so the existing services decide how a short video is posted.

## 6. Episode page (`/episodes/{id}`)

- Shows the topic, the Urdu script (RTL, editable), and the sources with each claim next to its link.
- **Approve** (saving any edits), **Save edits only**, **Revise** (an instruction, keeps the script), **Reject** with a note (research another story, or stop the day).
- The voice player and a download button once the voice exists, then the avatar upload with a progress bar.
- Final screen: watch the long video, Shorts and thumbnail, check the publish time, then **Approve and schedule posts**. Nothing is posted before this.
- **Cancel posting** works until publishing starts. It removes the posts and keeps the videos, so the episode can be approved again.
- Failed episodes show the error and a **Retry** button that goes back one step. A history list shows every step.
- The page refreshes itself every 5 seconds while a job is running, without wiping text being typed.

## 7. Failure rules

Any failed step sets `status = failed`, writes `error` and a log row, and emails you. No posts are created unless you approve the finished videos. Retry resets to the step before the failed one and runs only that job again (a failed edit goes back to the avatar step because the worker's download links expire).

Watchdog limits: research 30 min, voice 10 min, edit 60 min, import 30 min, scheduling 10 min. Approval and avatar have no limit, but if the episode is still before `scheduled` 15 minutes before its publish time, the day is skipped and you are emailed. Move the publish time (or use "Publish tomorrow") before that if you need more time.

## 8. Platform checks before going live

YouTube uploads from an unverified API project stay private until the project passes Google's audit, so apply early. TikTok direct posting needs an approved app. Instagram needs a Business account linked to a Facebook Page. Confirm that a video post works on each platform you rely on.

## 9. What is done and what is left

Done and tested (PHPUnit, 27 tests): webhook signature and duplicate handling, every state change, approve/reject/revise, voice job, SRT, import (including refusing foreign hosts), scheduling without duplicates, cancel posting, retry, daily command, watchdog.

Not done or not verified:

1. The episode pages build but have not been clicked through in a browser.
2. The real worker (document 02) and a real ElevenLabs call have not been run. Do the first-week checklist in document 03.
3. The server needs a queue worker and the scheduler cron (production uses the `database` queue). Without a queue worker nothing past the approval button moves. Run `php artisan migrate` on every environment.
4. Avatar files can be large. The upload limit is 2 GB in the code, but PHP's `upload_max_filesize` / `post_max_size` and the host's limits must allow it; chunked upload is not built.
5. Run a week with "Cancel posting" checked before 9 pm, then let it run.
6. Real AI-generated pictures (only branded cards are planned), per-platform text variants, and the mobile app with the other features in document 04 (topics, auto-drafts, voice commands, push) are the next phase.
