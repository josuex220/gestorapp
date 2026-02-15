<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Listar FAQs publicadas (público)
     */
    public function index(Request $request)
    {
        $query = Faq::published()->ordered();

        // Filtro por categoria
        if ($request->category && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        // Busca por texto
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $faqs = $query->get();

        return response()->json([
            'data' => $faqs,
            'total' => $faqs->count(),
        ]);
    }

    /**
     * Exibir FAQ específica e incrementar views
     */
    public function show(Faq $faq)
    {
        if (!$faq->is_published) {
            abort(404);
        }

        $faq->incrementViews();

        return response()->json($faq);
    }

    /**
     * Listar categorias disponíveis com contagem
     */
    public function categories()
    {
        $categories = Faq::published()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $allCategories = [
            'cobrancas' => ['label' => 'Cobranças', 'count' => $categories['cobrancas'] ?? 0],
            'pagamentos' => ['label' => 'Pagamentos', 'count' => $categories['pagamentos'] ?? 0],
            'clientes' => ['label' => 'Clientes', 'count' => $categories['clientes'] ?? 0],
            'integracoes' => ['label' => 'Integrações', 'count' => $categories['integracoes'] ?? 0],
            'conta' => ['label' => 'Conta', 'count' => $categories['conta'] ?? 0],
            'seguranca' => ['label' => 'Segurança', 'count' => $categories['seguranca'] ?? 0],
        ];

        return response()->json($allCategories);
    }
}
