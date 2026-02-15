<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Http\Resources\AccessLogResource;
use App\Models\UserSession;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SecurityController extends Controller
{
    /**
     * Altera a senha do usuário
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Senha atual incorreta',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Registrar log
        AccessLog::create([
            'user_id' => $user->id,
            'action' => 'password_change',
            'device' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'location' => $this->getLocationFromIp($request->ip()),
            'status' => 'success',
        ]);

        return response()->json([
            'message' => 'Senha alterada com sucesso',
        ]);
    }

    /**
     * Lista sessões ativas
     */
    public function getSessions(): JsonResponse
    {
        $sessions = UserSession::where('user_id', Auth::id())
            ->orderBy('is_current', 'desc')
            ->orderBy('last_active_at', 'desc')
            ->get();

        return response()->json(SessionResource::collection($sessions));
    }

    /**
     * Encerra uma sessão específica
     */
    public function endSession(string $sessionId): JsonResponse
    {
        $session = UserSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($session->is_current) {
            return response()->json([
                'message' => 'Não é possível encerrar a sessão atual',
            ], 422);
        }

        $session->delete();

        // Registrar log
        AccessLog::create([
            'user_id' => Auth::id(),
            'action' => 'session_ended',
            'device' => $session->device,
            'status' => 'success',
            'metadata' => ['session_id' => $sessionId],
        ]);

        return response()->json([
            'message' => 'Sessão encerrada com sucesso',
        ]);
    }

    /**
     * Encerra todas as outras sessões
     */
    public function endAllSessions(): JsonResponse
    {
        $count = UserSession::where('user_id', Auth::id())
            ->where('is_current', false)
            ->delete();

        AccessLog::create([
            'user_id' => Auth::id(),
            'action' => 'all_sessions_ended',
            'ip_address' => request()->ip(),
            'status' => 'success',
            'metadata' => ['count' => $count],
        ]);

        return response()->json([
            'message' => "Todas as {$count} sessões foram encerradas",
        ]);
    }

    /**
     * Ativa 2FA
     */
    public function enable2FA(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Aqui você implementaria a lógica real do 2FA
        // Por exemplo, usando Google Authenticator
        $user->update(['two_factor_enabled' => true]);

        AccessLog::create([
            'user_id' => $user->id,
            'action' => '2fa_enabled',
            'ip_address' => $request->ip(),
            'status' => 'success',
        ]);

        return response()->json([
            'message' => '2FA ativado com sucesso',
            'recovery_codes' => $this->generateRecoveryCodes(),
        ]);
    }

    /**
     * Desativa 2FA
     */
    public function disable2FA(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Senha incorreta',
            ], 422);
        }

        $user->update(['two_factor_enabled' => false]);

        AccessLog::create([
            'user_id' => $user->id,
            'action' => '2fa_disabled',
            'ip_address' => $request->ip(),
            'status' => 'warning',
        ]);

        return response()->json([
            'message' => '2FA desativado',
        ]);
    }

    /**
     * Histórico de acessos
     */
    public function getAccessLogs(Request $request): JsonResponse
    {
        $logs = AccessLog::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 20))
            ->get();

        return response()->json(AccessLogResource::collection($logs));
    }

    /**
     * Gera códigos de recuperação
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    /**
     * Obtém localização a partir do IP
     */
    private function getLocationFromIp(?string $ip): ?string
    {
        // Implementar integração com serviço de geolocalização
        // Ex: ipapi.co, ip-api.com, etc.
        return null;
    }
}
