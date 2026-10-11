<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = Schedule::where('user_id', $request->user()->id)
            ->with('post.media')
            ->orderBy('scheduled_at', 'asc')
            ->paginate(15);

        return response()->json($schedules);
    }

    /**
     * Everything scheduled between from and to (ISO dates), for the calendar page.
     * Admins can pass scope=all to see every user's schedules.
     */
    public function calendar(Request $request)
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after:from',
            'scope' => 'nullable|in:all',
        ]);

        $all = ($data['scope'] ?? null) === 'all';
        if ($all && !$request->user()->is_admin) {
            return response()->json(['message' => 'Only admins can view every account.'], 403);
        }

        $schedules = Schedule::with(['post:id,content,status,platforms', 'user:id,name'])
            ->when(!$all, fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereBetween('scheduled_at', [$data['from'], $data['to']])
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn ($s) => $s->post);

        $types = Platform::whereIn('id', $schedules->flatMap(fn ($s) => $s->post->platforms ?? [])->unique())
            ->pluck('platform_type', 'id');

        return response()->json($schedules->map(fn ($s) => [
            'id' => $s->id,
            'post_id' => $s->post_id,
            'scheduled_at' => $s->scheduled_at,
            'status' => $s->status,
            'content' => $s->post->content,
            'platforms' => collect($s->post->platforms ?? [])->map(fn ($id) => $types[$id] ?? null)->filter()->unique()->values(),
            'user' => $all ? ['id' => $s->user->id, 'name' => $s->user->name] : null,
        ])->values());
    }

    public function destroy(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}

