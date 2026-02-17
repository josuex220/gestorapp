<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    /**
     * GET /api/learning/progress
     * Retorna todo o progresso do usuário autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $progress = UserLessonProgress::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('lesson_id');

        return response()->json(['data' => $progress]);
    }

    /**
     * GET /api/learning/progress/summary
     * Retorna resumo de progresso (total de vídeos, completados, horas).
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $total = UserLessonProgress::where('user_id', $userId)->count();
        $completed = UserLessonProgress::where('user_id', $userId)->where('is_completed', true)->count();
        $watchedSeconds = UserLessonProgress::where('user_id', $userId)->sum('watched_seconds');

        return response()->json([
            'total_started' => $total,
            'total_completed' => $completed,
            'total_watched_seconds' => (int) $watchedSeconds,
        ]);
    }

    /**
     * POST /api/learning/progress
     * Cria ou atualiza progresso de uma lição (upsert por user_id + lesson_id).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => 'required|uuid|exists:learning_lessons,id',
            'track_id' => 'required|uuid|exists:learning_tracks,id',
            'watched_seconds' => 'required|integer|min:0',
            'total_seconds' => 'required|integer|min:0',
            'is_completed' => 'sometimes|boolean',
        ]);

        $userId = $request->user()->id;

        // Auto-complete at 90%
        $watchedPct = $validated['total_seconds'] > 0
            ? $validated['watched_seconds'] / $validated['total_seconds']
            : 0;
        $shouldComplete = $watchedPct >= 0.9;

        $progress = UserLessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $validated['lesson_id']],
            [
                'track_id' => $validated['track_id'],
                'watched_seconds' => max(
                    $validated['watched_seconds'],
                    UserLessonProgress::where('user_id', $userId)
                        ->where('lesson_id', $validated['lesson_id'])
                        ->value('watched_seconds') ?? 0
                ),
                'total_seconds' => $validated['total_seconds'],
                'is_completed' => $validated['is_completed'] ?? $shouldComplete,
                'completed_at' => ($validated['is_completed'] ?? $shouldComplete) ? now() : null,
                'last_watched_at' => now(),
            ]
        );

        return response()->json($progress);
    }

    /**
     * DELETE /api/learning/progress/{lessonId}
     * Remove progresso de uma lição específica.
     */
    public function destroy(Request $request, string $lessonId): JsonResponse
    {
        UserLessonProgress::where('user_id', $request->user()->id)
            ->where('lesson_id', $lessonId)
            ->delete();

        return response()->json(['message' => 'Progresso removido.']);
    }
}
