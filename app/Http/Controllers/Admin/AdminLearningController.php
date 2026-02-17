<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningLesson;
use App\Models\LearningTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLearningController extends Controller
{
    // ──── Tracks ────

    public function index(Request $request): JsonResponse
    {
        $query = LearningTrack::withCount('lessons')->orderBy('order');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('is_published', $status === 'published');
        }

        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(LearningTrack $track): JsonResponse
    {
        $track->load('lessons');
        $track->loadCount('lessons');

        return response()->json($track);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|string|url',
            'category' => 'nullable|string|max:100',
            'level' => 'sometimes|in:iniciante,intermediario,avancado',
            'is_published' => 'sometimes|boolean',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'uuid|exists:learning_tracks,id',
            'order' => 'sometimes|integer|min:0',
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = LearningTrack::max('order') + 1;
        }

        $track = LearningTrack::create($validated);
        $track->loadCount('lessons');

        return response()->json($track, 201);
    }

    public function update(Request $request, LearningTrack $track): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|string|url',
            'category' => 'nullable|string|max:100',
            'level' => 'sometimes|in:iniciante,intermediario,avancado',
            'is_published' => 'sometimes|boolean',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'uuid|exists:learning_tracks,id',
            'order' => 'sometimes|integer|min:0',
        ]);

        $track->update($validated);
        $track->loadCount('lessons');

        return response()->json($track);
    }

    public function destroy(LearningTrack $track): JsonResponse
    {
        $track->delete();

        return response()->json(['message' => 'Trilha excluída com sucesso.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:learning_tracks,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            LearningTrack::where('id', $id)->update(['order' => $index]);
        }

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    // ──── Lessons ────

    public function lessons(LearningTrack $track): JsonResponse
    {
        return response()->json($track->lessons);
    }

    public function storeLesson(Request $request, LearningTrack $track): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|string',
            'thumbnail_url' => 'nullable|string',
            'duration_seconds' => 'nullable|integer|min:0',
            'is_published' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.url' => 'required|string',
            'attachments.*.type' => 'required|string',
            'quiz' => 'nullable|array',
            'quiz.*.question' => 'required|string',
            'quiz.*.options' => 'required|array|min:2',
            'quiz.*.correct_index' => 'required|integer|min:0',
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = $track->lessons()->max('order') + 1;
        }

        $lesson = $track->lessons()->create($validated);

        return response()->json($lesson, 201);
    }

    public function updateLesson(Request $request, LearningTrack $track, LearningLesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'sometimes|string',
            'thumbnail_url' => 'nullable|string',
            'duration_seconds' => 'nullable|integer|min:0',
            'is_published' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'attachments' => 'nullable|array',
            'quiz' => 'nullable|array',
        ]);

        $lesson->update($validated);

        return response()->json($lesson);
    }

    public function destroyLesson(LearningTrack $track, LearningLesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(['message' => 'Lição excluída com sucesso.']);
    }

    public function reorderLessons(Request $request, LearningTrack $track): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:learning_lessons,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            LearningLesson::where('id', $id)->where('track_id', $track->id)->update(['order' => $index]);
        }

        return response()->json(['message' => 'Ordem das lições atualizada.']);
    }

    // ──── Upload de Vídeo ────

    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/webm,video/quicktime|max:512000', // 500MB
        ]);

        $path = $request->file('video')->store('learning/videos', 'public');

        return response()->json([
            'video_url' => asset('storage/' . $path),
        ]);
    }

    public function uploadThumbnail(Request $request): JsonResponse
    {
        $request->validate([
            'thumbnail' => 'required|image|max:5120', // 5MB
        ]);

        $path = $request->file('thumbnail')->store('learning/thumbnails', 'public');

        return response()->json([
            'thumbnail_url' => asset('storage/' . $path),
        ]);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB
        ]);

        $file = $request->file('file');
        $path = $file->store('learning/attachments', 'public');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'url' => asset('storage/' . $path),
            'type' => $file->getClientMimeType(),
        ]);
    }
}
