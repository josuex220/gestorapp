<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFaqController extends Controller
{
    /**
     * GET /api/admin/faqs
     * Query params: search, category, status (all|published|draft), page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Faq::query()->orderBy('category')->orderBy('order');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($request->has('page') || $perPage < 100) {
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

        return response()->json($query->get());
    }

    /**
     * POST /api/admin/faqs
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|in:cobrancas,pagamentos,clientes,integracoes,conta,seguranca',
            'order' => 'sometimes|integer|min:0',
            'is_published' => 'sometimes|boolean',
        ]);

        $faq = Faq::create($validated);

        return response()->json($faq, 201);
    }

    /**
     * PUT /api/admin/faqs/{faq}
     */
    public function update(Request $request, Faq $faq): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|string|max:500',
            'answer' => 'sometimes|string',
            'category' => 'sometimes|in:cobrancas,pagamentos,clientes,integracoes,conta,seguranca',
            'order' => 'sometimes|integer|min:0',
            'is_published' => 'sometimes|boolean',
        ]);

        $faq->update($validated);

        return response()->json($faq);
    }

    /**
     * DELETE /api/admin/faqs/{faq}
     */
    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'FAQ excluída com sucesso.']);
    }
}
