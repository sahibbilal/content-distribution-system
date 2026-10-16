# 04 — Personal AI Content Assistant (Mobile Companion)

**Status:** Parked. Build order is: finish the web app update (document 01, with 02 and 03) first, then start this.

## 1. What it is

A mobile companion to the content distribution web app. It is for one user (the owner) for now. It may be sold later.

- Generates posts from AI trends, topics, or PDFs in the library.
- Schedules to FB, Instagram, YouTube, TikTok, X, and LinkedIn through the web app.
- Sends every draft to the owner for approval before it posts.

## 2. Core features

- **Topics:** each has its own tone, platforms, and source material.
- **Auto-generation:** drafts are created ahead of the scheduled time.
- **Approval flow:** approve, revise with an instruction, or edit manually. Nothing posts without approval.
- **Voice control:** speak revisions or commands in Urdu, English, or mixed, then confirm before it runs.
- **Platform variants:** one idea adapted per platform.
- **Queue:** scheduled, posted, and failed posts, with edit and cancel.
- **Push alerts:** draft ready, posted, failed, token expired.

## 3. What is new compared to the current setup

- Approvals move from Telegram/WhatsApp into the owner's own app.
- Voice-driven workflow.
- One place for history: drafts, instructions, edits, final versions, performance.
- That history becomes training data for better generation over time.

## 4. Decisions so far

| topic | decision |
|---|---|
| Telegram | Not used. It needs a VPN locally and has limited options. |
| Notifications | Push from the app itself (FCM or Expo Push), email as fallback. |
| Scheduling time | Selected from a form in the web app (date/time picker, default 21:00 PKT). |
| Voice safety | A voice command can only create or change a draft. Publishing needs one explicit Approve tap. |
| Transcription | Needs Urdu/English/mixed. Candidates: ElevenLabs Scribe, Whisper. Not decided. |
| Users | Single user now. Put `user_id` on episodes, posts, and commands, and use token auth (Sanctum) from day one so selling later is not a rewrite. No billing or multi-tenant work until there is a buyer. |
| Client | Recommended: React Native with Expo (Android and iPhone, voice and push built in, direct APK on Android). A PWA is the faster fallback. Not final. |

## 5. Architecture sketch

```
Phone app ──(text or voice note)──► POST /api/commands
                                     ├─ voice? → transcribe
                                     ├─ Claude worker interprets intent + context
                                     └─ returns a DRAFT action
Phone app ◄── push + draft card ─────┘
Owner: Approve / change by voice or text / reject
          ──► existing scheduler publishes to the platforms
```

### Screens

- **Inbox:** scripts, finished videos, and post drafts waiting for approval (Approve / Edit / Reject).
- **New command:** mic button and text box.
- **Chat per item:** a thread attached to a draft so revisions keep context (uses a `/jobs/revise` worker endpoint).
- **Schedule / Queue:** scheduled, posted, failed, with edit and cancel.

### API (Laravel, Sanctum tokens)

`GET /api/episodes/{id}`, `POST .../approve`, `POST .../reject`, `POST .../revise`, `POST /api/commands`.

## 6. Prerequisites in the web app (must come first)

- Complete the Series A pipeline from document 01 (episodes, state machine, approval screen, ElevenLabs, worker client, scheduler hookup).
- Fixes noted in the 01 review: atomic status updates in the webhook, a default case for unknown webhook types, S3/R2 for large files, cost and runtime caps per episode, a "publish tomorrow" option, and a final-video approval step.
- Date/time picker for scheduled posts.
- Store history (drafts, instructions, edits, final versions, performance) from the start so it can be used later.

## 7. Build order (after the web app is done)

1. API layer: Sanctum auth, `episodes`, `commands`, approve/reject/revise endpoints.
2. Transcription plus a `/jobs/command` worker endpoint that turns a request into a draft post.
3. Mobile app: login, Inbox, approve/reject, push.
4. Voice and text commands, and per-item chat.
5. Post creation from commands for FB and LinkedIn (reuse the existing publishers), then the other platforms.

## 8. Open questions

- Android or iPhone?
- Expo app or PWA first?
- Transcription provider (ElevenLabs Scribe vs Whisper)?
- How is "performance" measured and stored (platform analytics APIs)?
- How is history used to improve generation (prompt examples vs fine-tuning)?
