<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Retorna o perfil do usuário
     */
    public function show(): ProfileResource
    {
        $profile = Profile::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'full_name' => Auth::user()->name,
                'phone' => Auth::user()->phone,
            ]
        );

        return new ProfileResource($profile);
    }

    /**
     * Atualiza o perfil
     */
    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $profile = Profile::updateOrCreate(
            ['user_id' => Auth::id()],
            $request->validated()
        );

        return new ProfileResource($profile->fresh());
    }

    /**
     * Upload de avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = Auth::user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id]);

        // Remove avatar antigo se existir
        if ($profile->avatar_url) {
            $oldPath = str_replace('/storage/', '', $profile->avatar_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Salva novo avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $profile->update(['avatar_url' => '/storage/' . $path]);

        return response()->json([
            'avatar_url' => $profile->avatar_url,
            'message' => 'Avatar atualizado com sucesso',
        ]);
    }
}
