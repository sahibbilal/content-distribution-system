<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\AiProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The editable AI rulebook. A section a user saved replaces the starter text from config/ai_instructions.php;
 * compose() joins the sections, the project's own instructions and the project's memory into the rules
 * that are given to the AI with every request.
 */
class AiInstructions
{
    /** Sections applied to every post (the others are stored and edited now, and used by the PDF and video projects later) */
    private const ALWAYS = ['general', 'format', 'remember'];
    private const MEMORY_SIZE = 40;

    /** @return array<string, array{title: string, help: string, used_by: string, body: string, default: string, edited: bool}> */
    public static function sections(User $user): array
    {
        $saved = DB::table('ai_instructions')->where('user_id', $user->id)->pluck('body', 'section');

        $sections = [];
        foreach (config('ai_instructions.sections') as $key => $section) {
            $sections[$key] = [
                'title' => $section['title'],
                'help' => $section['help'],
                'used_by' => $section['used_by'],
                'body' => $saved[$key] ?? $section['body'],
                'default' => $section['body'],
                'edited' => isset($saved[$key]) && $saved[$key] !== $section['body'],
            ];
        }

        return $sections;
    }

    public static function save(User $user, string $section, string $body): void
    {
        abort_unless(array_key_exists($section, config('ai_instructions.sections')), 404);

        DB::table('ai_instructions')->updateOrInsert(
            ['user_id' => $user->id, 'section' => $section],
            ['body' => trim($body), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public static function reset(User $user, string $section): void
    {
        DB::table('ai_instructions')->where('user_id', $user->id)->where('section', $section)->delete();
    }

    /** Create the starter projects the first time a user opens the assistant */
    public static function ensureStarterProjects(User $user): void
    {
        if (AiProject::where('user_id', $user->id)->exists() || DB::table('ai_instructions')->where('user_id', $user->id)->where('section', '__projects_seeded')->exists()) {
            return;
        }

        foreach (config('ai_instructions.projects') as $project) {
            AiProject::create($project + ['user_id' => $user->id]);
        }
        // remembered, so deleting every project later does not bring the starter ones back
        DB::table('ai_instructions')->insert(['user_id' => $user->id, 'section' => '__projects_seeded', 'body' => '1', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** The rules text for one request */
    public static function compose(User $user, ?AiProject $project, bool $wantImage, ?array $chosen = null): string
    {
        $sections = self::sections($user);
        $parts = [];

        // $chosen: the sections picked for this chat (null = the standard ones); images are added when a picture is wanted
        $keys = $chosen === null ? self::ALWAYS : array_values(array_intersect(array_keys($sections), $chosen));
        if ($wantImage && !in_array('images', $keys, true)) {
            $keys[] = 'images';
        }
        foreach ($keys as $key) {
            $parts[] = '## ' . $sections[$key]['title'] . "\n" . trim($sections[$key]['body']);
        }
        if ($project && trim((string) $project->instructions) !== '') {
            $parts[] = "## What the project \"{$project->name}\" creates\n" . trim($project->instructions);
        }

        $memory = self::memory($user, $project);
        if ($memory) {
            $parts[] = "## Already written in this project (do not repeat these ideas, jokes or openings)\n- " . implode("\n- ", $memory);
        }

        return implode("\n\n", $parts);
    }

    /** Openings of the latest drafts of a project (or of the posts made without a project), newest first */
    public static function memory(User $user, ?AiProject $project): array
    {
        return AiGeneration::where('user_id', $user->id)
            ->where('project_id', $project?->id)
            ->orderByDesc('id')
            ->limit(self::MEMORY_SIZE)
            ->pluck('hook')
            ->all();
    }
}
