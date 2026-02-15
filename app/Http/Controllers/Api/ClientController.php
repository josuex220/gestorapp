<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    use AuthorizesRequests;

    /**
     * Lista paginada de clientes com filtros
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::byUser(Auth::id());

        // Filtro de busca
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filtro de status
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->active(),
                'inactive' => $query->inactive(),
                default => null,
            };
        }

        // Filtro por tag
        if ($request->filled('tag')) {
            $query->byTag($request->tag);
        }

        // Ordenação
        $query->orderBy('created_at', 'desc');

        // Paginação
        $perPage = $request->input('per_page', 20);
        $clients = $query->paginate($perPage);

        return ClientResource::collection($clients);
    }

    /**
     * Exibe detalhes de um cliente
     */
    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return new ClientResource($client);
    }

    /**
     * Cria um novo cliente
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza um cliente existente
     */
    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return new ClientResource($client->fresh());
    }

    /**
     * Remove um cliente
     */
    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json([
            'message' => 'Cliente excluído com sucesso',
        ]);
    }

    /**
     * Alterna o status ativo/inativo do cliente
     */
    public function toggleStatus(Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client->update([
            'is_active' => !$client->is_active,
        ]);

        return new ClientResource($client->fresh());
    }

    /**
     * Adiciona uma tag ao cliente
     */
    public function addTag(Request $request, Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $client->addTag($request->tag);

        return new ClientResource($client->fresh());
    }

    /**
     * Remove uma tag do cliente
     */
    public function removeTag(Client $client, string $tag): ClientResource
    {
        $this->authorize('update', $client);

        $client->removeTag(urldecode($tag));

        return new ClientResource($client->fresh());
    }
}
