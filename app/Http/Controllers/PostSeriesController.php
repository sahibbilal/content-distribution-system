<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostSeries;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Series of posts: how many of each were posted, and the last two */
class PostSeriesController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            PostSeries::where('user_id', $request->user()->id)->orderBy('name')->get()->map(fn ($s) => $this->present($s))
        );
    }

    public function store(Request $request)
    {
        $series = PostSeries::create(['user_id' => $request->user()->id, 'name' => $this->name($request)]);

        return response()->json($this->present($series), 201);
    }

    public function update(Request $request, $id)
    {
        $series = $this->mine($request, $id);
        $series->update(['name' => $this->name($request, $series->id)]);

        return response()->json($this->present($series));
    }

    public function destroy(Request $request, $id)
    {
        $this->mine($request, $id)->delete(); // its posts stay, without a series

        return response()->json(['deleted' => true]);
    }

    private function mine(Request $request, $id): PostSeries
    {
        return PostSeries::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function name(Request $request, ?int $ignore = null): string
    {
        $request->merge(['name' => trim((string) $request->name)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('post_series', 'name')->where('user_id', $request->user()->id)->ignore($ignore)],
        ], ['name.unique' => 'You already have a series with that name.'])['name'];
    }

    private function present(PostSeries $series): array
    {
        $posts = Post::where('series_id', $series->id);
        $published = (clone $posts)->where('status', 'published');

        return [
            'id' => $series->id,
            'name' => $series->name,
            'published_count' => (clone $published)->count(),
            'scheduled_count' => (clone $posts)->where('status', 'scheduled')->count(),
            // newest first: the last post, then the one before it
            'latest' => (clone $published)->orderByDesc('published_at')->orderByDesc('id')->limit(2)->get(['id', 'content', 'published_at'])
                ->map(fn ($p) => ['id' => $p->id, 'content' => mb_strimwidth($p->content, 0, 160, '...'), 'published_at' => $p->published_at])->all(),
        ];
    }
}
