<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPixConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixConfigController extends Controller
{
    /**
     * Get the authenticated user's PIX config.
     */
    public function show(Request $request): JsonResponse
    {
        $config = UserPixConfig::where('user_id', $request->user()->id)->first();

        if (!$config) {
            return response()->json([
                'configured' => false,
                'config' => null,
            ]);
        }

        return response()->json([
            'configured' => true,
            'config' => [
                'id' => $config->id,
                'key_type' => $config->key_type,
                'key_value' => $config->masked_key_value,
                'holder_name' => $config->holder_name,
                'require_proof' => $config->require_proof,
                'proof_required' => $config->proof_required,
                'is_active' => $config->is_active,
                'created_at' => $config->created_at?->toISOString(),
                'updated_at' => $config->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Create or update the user's PIX config.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key_type' => 'required|string|in:cpf,cnpj,email,phone,random',
            'key_value' => 'required|string|max:255',
            'holder_name' => 'required|string|max:255',
            'require_proof' => 'boolean',
            'proof_required' => 'boolean',
        ]);

        $config = UserPixConfig::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'key_type' => $validated['key_type'],
                'key_value' => $validated['key_value'],
                'holder_name' => $validated['holder_name'],
                'require_proof' => $validated['require_proof'] ?? false,
                'proof_required' => $validated['proof_required'] ?? false,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Configuração PIX salva com sucesso.',
            'configured' => true,
            'config' => [
                'id' => $config->id,
                'key_type' => $config->key_type,
                'key_value' => $config->masked_key_value,
                'holder_name' => $config->holder_name,
                'require_proof' => $config->require_proof,
                'proof_required' => $config->proof_required,
                'is_active' => $config->is_active,
            ],
        ]);
    }

    /**
     * Disconnect (deactivate) the PIX config.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $config = UserPixConfig::where('user_id', $request->user()->id)->first();

        if ($config) {
            $config->update(['is_active' => false]);
        }

        return response()->json([
            'message' => 'PIX desconectado com sucesso.',
            'configured' => false,
        ]);
    }

    /**
     * Delete the PIX config entirely.
     */
    public function destroy(Request $request): JsonResponse
    {
        UserPixConfig::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Configuração PIX removida.',
            'configured' => false,
        ]);
    }
}
