<?php

namespace App\Models;

use App\Support\Notify;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Episode extends Model
{
    protected $fillable = [
        'user_id', 'series', 'kind', 'prompt', 'platform_ids', 'day_key', 'run_date', 'topic', 'status', 'failed_step', 'script_ur', 'sources',
        'voice_path', 'alignment', 'captions_path', 'avatar_path', 'outputs', 'post_ids', 'scheduled_for',
        'rejection_note', 'error', 'worker_job_id', 'step_started_at',
    ];

    protected $casts = [
        'run_date' => 'date',
        'sources' => 'array',
        'platform_ids' => 'array',
        'alignment' => 'array',
        'outputs' => 'array',
        'post_ids' => 'array',
        'scheduled_for' => 'datetime',
        'step_started_at' => 'datetime',
    ];

    /** Where "Retry" sends a failed episode back to, keyed by the step it failed in */
    public const RETRY_FROM = [
        'researching' => 'pending',
        'voicing' => 'approved',
        'editing' => 'awaiting_avatar',
        'importing' => 'awaiting_avatar', // the download links have expired, so the edit runs again
        'scheduling' => 'ready',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Today's date in the series timezone (the day an episode belongs to) */
    public static function today(): string
    {
        return now(config('series.a.timezone'))->toDateString();
    }

    /** The default publish time (series timezone) of the episode for a date, as an app-timezone Carbon */
    public static function defaultPublishTime(string $date): Carbon
    {
        return Carbon::parse($date . ' ' . config('series.a.publish_at'), config('series.a.timezone'))
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Open the daily video episode of a date. Returns [episode, created]; there is one a day per user,
     * so asking twice returns the existing one instead of starting a second.
     */
    public static function open(User $user, string $date, array $attributes = []): array
    {
        $episode = static::firstOrCreate(
            ['user_id' => $user->id, 'day_key' => "series-a:{$date}"],
            $attributes + ['series' => 'series-a', 'kind' => 'video', 'run_date' => $date, 'status' => 'pending', 'scheduled_for' => static::defaultPublishTime($date)],
        );

        return [$episode, $episode->wasRecentlyCreated];
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    /** The status "Retry" goes back to for the step this episode failed in (null when it can't be retried) */
    public function retryTarget(): ?string
    {
        // a text or image job has no avatar step, so a failed import runs the whole write again
        if ($this->failed_step === 'importing' && !$this->isVideo()) {
            return 'pending';
        }

        return self::RETRY_FROM[$this->failed_step] ?? null;
    }

    public function logs()
    {
        return $this->hasMany(EpisodeLog::class)->orderBy('id');
    }

    /**
     * Move to a new status only if the episode is still in one of the expected ones. The check and the
     * write are one SQL statement, so a retry or duplicate webhook can never run a step twice.
     */
    public function transition(array|string $from, string $to, array $attributes = []): bool
    {
        $moved = static::whereKey($this->id)->whereIn('status', (array) $from)
            ->update(array_merge($attributes, ['status' => $to, 'updated_at' => now()])) === 1;

        if ($moved) {
            $this->refresh();
        }

        return $moved;
    }

    public function log(string $step, string $status, ?string $message = null, ?array $payload = null): void
    {
        EpisodeLog::create([
            'episode_id' => $this->id,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /** Stop the episode: nothing is posted, and the step it failed in is remembered for Retry */
    public function fail(string $message): void
    {
        $step = $this->fresh()->status;
        if (in_array($step, ['failed', 'published', 'skipped', 'cancelled'], true)) {
            return;
        }

        if ($this->transition($step, 'failed', ['error' => $message, 'failed_step' => $step])) {
            $this->log($step, 'failed', $message);
            Notify::user($this->user, "Episode {$this->run_date->toDateString()} failed at {$step}", $message);
        }
    }
}
