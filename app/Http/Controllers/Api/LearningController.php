<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    /**
     * GET /api/learning/tracks
     * Lista trilhas publicadas para o usuário.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LearningTrack::where('is_published', true)
            ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('order');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $tracks = $query->get();

        return response()->json(['data' => $tracks]);
    }

    /**
     * GET /api/learning/tracks/{track}
     * Detalhe de uma trilha com lições publicadas.
     */
    public function show(LearningTrack $track): JsonResponse
    {
        if (!$track->is_published) {
            abort(404);
        }

        $track->load(['lessons' => fn ($q) => $q->where('is_published', true)->orderBy('order')]);
        $track->loadCount(['lessons' => fn ($q) => $q->where('is_published', true)]);

        return response()->json($track);
    }
}
