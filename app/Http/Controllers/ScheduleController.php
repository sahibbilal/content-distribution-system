<?php

namespace App\Http\Controllers;

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

    public function destroy(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}

