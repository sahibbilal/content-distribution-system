<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\AiProject;
use App\Models\Platform;
use App\Services\AiInstructions;
use Illuminate\Http\Request;

/** The AI assistant page: the editable rulebook, projects, and each project's memory. Everything is per user. */
class AiAssistantController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        AiInstructions::ensureStarterProjects($user);

        return response()->json([
            'sections' => collect(AiInstructions::sections($user))->map(fn ($s, $key) => ['key' => $key] + $s)->values(),
            'projects' => AiProject::where('user_id', $user->id)->orderBy('id')->get()->map(fn ($p) => $this->present($p)),
        ]);
    }

    public function updateSection(Request $request, string $section)
    {
        $data = $request->validate(['body' => 'required|string|max:20000']);

        AiInstructions::save($request->user(), $section, $data['body']);

        return response()->json(['saved' => true]);
    }

    public function resetSection(Request $request, string $section)
    {
        abort_unless(array_key_exists($section, config('ai_instructions.sections')), 404);
        AiInstructions::reset($request->user(), $section);

        return response()->json(['body' => config("ai_instructions.sections.{$section}.body")]);
    }

    public function storeProject(Request $request)
    {
        $project = AiProject::create($this->projectData($request) + ['user_id' => $request->user()->id]);

        return response()->json($this->present($project), 201);
    }

    public function updateProject(Request $request, $id)
    {
        $project = $this->mine($request, $id);
        $project->update($this->projectData($request));

        return response()->json($this->present($project->fresh()));
    }

    public function destroyProject(Request $request, $id)
    {
        $this->mine($request, $id)->delete(); // its memory stays, as drafts without a project

        return response()->json(['deleted' => true]);
    }

    /** What the project already wrote (newest first) */
    public function memory(Request $request, $id)
    {
        $project = $this->mine($request, $id);

        return response()->json(
            AiGeneration::where('project_id', $project->id)->orderByDesc('id')->limit(100)->get(['id', 'hook', 'post_id', 'created_at'])
                ->map(fn ($g) => ['id' => $g->id, 'hook' => $g->hook, 'posted' => $g->post_id !== null, 'created_at' => $g->created_at])
        );
    }

    public function clearMemory(Request $request, $id)
    {
        $project = $this->mine($request, $id);
        AiGeneration::where('project_id', $project->id)->delete();

        return response()->json(['cleared' => true]);
    }

    private function projectData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:trends,jokes,custom',
            'instructions' => 'nullable|string|max:20000',
            'platform_ids' => 'nullable|array|max:10',
            'platform_ids.*' => 'integer',
            'options' => 'nullable|array',
            'options.web_search' => 'boolean',
            'options.hashtags' => 'boolean',
            'options.make_image' => 'boolean',
        ]);

        // only the user's own platforms
        $data['platform_ids'] = Platform::where('user_id', $request->user()->id)->whereIn('id', $data['platform_ids'] ?? [])->pluck('id')->all();
        $data['options'] = [
            'web_search' => (bool) ($data['options']['web_search'] ?? false),
            'hashtags' => (bool) ($data['options']['hashtags'] ?? false),
            'make_image' => (bool) ($data['options']['make_image'] ?? false),
        ];

        return $data;
    }

    private function mine(Request $request, $id): AiProject
    {
        return AiProject::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function present(AiProject $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'instructions' => $p->instructions,
            'platform_ids' => $p->platform_ids ?? [],
            'options' => ($p->options ?? []) + ['web_search' => false, 'hashtags' => false, 'make_image' => false],
            'memory_count' => AiGeneration::where('project_id', $p->id)->count(),
        ];
    }
}
