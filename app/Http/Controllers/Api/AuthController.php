<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Cadastro
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|min:8|confirmed',
            'terms'    => 'accepted',
        ], [
            'terms.accepted' => 'Você precisa aceitar os termos de uso.'
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Conta criada com sucesso',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Revoga tokens antigos (opcional)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token');

        // Criar sessão
        $session = UserSession::createFromRequest($request, $user->id, $token->accessToken->id);

        // Log de sucesso
        AccessLog::logLogin($user->id, $request);


        return response()->json([
            'message' => 'Login realizado com sucesso',
            'user'    => $user,
            'token'   => $token->plainTextToken,
            'session_id' => $session->id,

        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

         // Log de logout
        AccessLog::logLogout($user->id, $request);

        // Encerrar sessão atual
        UserSession::where('user_id', $user->id)
            ->where('is_current', true)
            ->delete();
        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ]);
    }
}
