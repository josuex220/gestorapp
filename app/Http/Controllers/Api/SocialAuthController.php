<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;

class SocialAuthController extends Controller
{
    /**
     * Redireciona o usuário para o provedor OAuth.
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Processa o callback do provedor OAuth.
     * Se o usuário não existir, cria automaticamente com perfil.
     */
    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            $frontendUrl = config('app.frontend_url', 'https://automa-care.lovable.app');
            return redirect("{$frontendUrl}/auth/callback?error=" . urlencode('Falha na autenticação com ' . $provider));
        }

        $isNewUser = false;

        // Busca pelo provider_id ou pelo email
        $user = User::where('social_provider', $provider)
                     ->where('social_provider_id', $socialUser->getId())
                     ->first();

        if (!$user && $socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();

            // Vincula a conta social ao usuário existente
            if ($user) {
                $user->update([
                    'social_provider' => $provider,
                    'social_provider_id' => $socialUser->getId(),
                    'social_avatar' => $socialUser->getAvatar(),
                ]);
            }
        }

        // Cria novo usuário se não encontrou
        if (!$user) {
            $isNewUser = true;

            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Usuário',
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(32)), // Senha aleatória (login social)
                'social_provider' => $provider,
                'social_provider_id' => $socialUser->getId(),
                'social_avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(), // Email verificado pelo provedor
            ]);
        }

        // Gera token Sanctum
        $token = $user->createToken('social-auth')->plainTextToken;

        $frontendUrl = config('app.frontend_url', 'https://automa-care.lovable.app');
        $params = http_build_query([
            'token' => $token,
            'is_new_user' => $isNewUser ? '1' : '0',
        ]);

        return redirect("{$frontendUrl}/auth/callback?{$params}");
    }

    private function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'apple'])) {
            abort(422, 'Provedor não suportado.');
        }
    }
}
