<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $admin = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $admin->id,
        ]);

        $admin->update($request->only(['name', 'email']));

        return response()->json($admin->fresh());
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $admin = $request->user();

        if ($admin->avatar_url) {
            $oldPath = str_replace('/storage/', '', parse_url($admin->avatar_url, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('avatar')->store('avatars/admins', 'public');
        $url = Storage::disk('public')->url($path);

        $admin->update(['avatar_url' => $url]);

        return response()->json(['avatar_url' => $url]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin->avatar_url) {
            $oldPath = str_replace('/storage/', '', parse_url($admin->avatar_url, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
            $admin->update(['avatar_url' => null]);
        }

        return response()->json(['message' => 'Avatar removido']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = $request->user();

        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json(['message' => 'Senha atual incorreta'], 422);
        }

        $admin->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Senha alterada com sucesso']);
    }
}
