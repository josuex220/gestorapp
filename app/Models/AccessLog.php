<?php
// app/Models/AccessLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Jenssegers\Agent\Agent;

class AccessLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'device',
        'device_type',
        'browser',
        'platform',
        'ip_address',
        'location',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Tipos de ações
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_PASSWORD_RESET = 'password_reset';
    const ACTION_2FA_ENABLED = '2fa_enabled';
    const ACTION_2FA_DISABLED = '2fa_disabled';
    const ACTION_SESSION_ENDED = 'session_ended';
    const ACTION_ALL_SESSIONS_ENDED = 'all_sessions_ended';
    const ACTION_PROFILE_UPDATED = 'profile_updated';
    const ACTION_SETTINGS_UPDATED = 'settings_updated';
    const ACTION_EMAIL_CHANGED = 'email_changed';

    const ACTIONS = [
        self::ACTION_LOGIN => ['label' => 'Login realizado', 'icon' => 'log-in'],
        self::ACTION_LOGOUT => ['label' => 'Logout realizado', 'icon' => 'log-out'],
        self::ACTION_LOGIN_FAILED => ['label' => 'Tentativa de login falhou', 'icon' => 'x-circle'],
        self::ACTION_PASSWORD_CHANGE => ['label' => 'Senha alterada', 'icon' => 'key'],
        self::ACTION_PASSWORD_RESET => ['label' => 'Senha redefinida', 'icon' => 'refresh-cw'],
        self::ACTION_2FA_ENABLED => ['label' => '2FA ativado', 'icon' => 'shield'],
        self::ACTION_2FA_DISABLED => ['label' => '2FA desativado', 'icon' => 'shield-off'],
        self::ACTION_SESSION_ENDED => ['label' => 'Sessão encerrada', 'icon' => 'monitor-off'],
        self::ACTION_ALL_SESSIONS_ENDED => ['label' => 'Todas as sessões encerradas', 'icon' => 'log-out'],
        self::ACTION_PROFILE_UPDATED => ['label' => 'Perfil atualizado', 'icon' => 'user'],
        self::ACTION_SETTINGS_UPDATED => ['label' => 'Configurações alteradas', 'icon' => 'settings'],
        self::ACTION_EMAIL_CHANGED => ['label' => 'E-mail alterado', 'icon' => 'mail'],
    ];

    const STATUS_SUCCESS = 'success';
    const STATUS_WARNING = 'warning';
    const STATUS_ERROR = 'error';

    const STATUSES = [
        self::STATUS_SUCCESS => 'Sucesso',
        self::STATUS_WARNING => 'Atenção',
        self::STATUS_ERROR => 'Erro',
    ];

    // ==================== RELACIONAMENTOS ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== SCOPES ====================

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeSecurityRelated(Builder $query): Builder
    {
        return $query->whereIn('action', [
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_PASSWORD_CHANGE,
            self::ACTION_PASSWORD_RESET,
            self::ACTION_2FA_ENABLED,
            self::ACTION_2FA_DISABLED,
            self::ACTION_SESSION_ENDED,
            self::ACTION_ALL_SESSIONS_ENDED,
        ]);
    }

    // ==================== ACCESSORS ====================

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action]['label'] ?? $this->action;
    }

    public function getActionIconAttribute(): string
    {
        return self::ACTIONS[$this->action]['icon'] ?? 'activity';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getDeviceLabelAttribute(): string
    {
        $parts = [];

        if ($this->browser) {
            $parts[] = $this->browser;
        }

        if ($this->platform) {
            $parts[] = "em {$this->platform}";
        }

        return !empty($parts) ? implode(' ', $parts) : ($this->device ?? 'Dispositivo desconhecido');
    }

    public function getCreatedAtFormattedAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    public function getCreatedAtAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    /**
     * Registra um log de acesso a partir do request
     */
    public static function log(
        int $userId,
        string $action,
        $request = null,
        string $status = self::STATUS_SUCCESS,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description ?? (self::ACTIONS[$action]['label'] ?? $action),
            'status' => $status,
            'metadata' => $metadata,
        ];

        if ($request) {
            $agent = new Agent();
            $agent->setUserAgent($request->userAgent());

            $deviceType = 'desktop';
            if ($agent->isMobile()) {
                $deviceType = 'mobile';
            } elseif ($agent->isTablet()) {
                $deviceType = 'tablet';
            }

            $data['device'] = $agent->browser() . ' em ' . $agent->platform();
            $data['device_type'] = $deviceType;
            $data['browser'] = $agent->browser();
            $data['platform'] = $agent->platform();
            $data['ip_address'] = $request->ip();
            $data['location'] = UserSession::getLocationFromIp($request->ip());
        }

        return self::create($data);
    }

    /**
     * Atalhos para ações comuns
     */
    public static function logLogin(int $userId, $request): self
    {
        return self::log($userId, self::ACTION_LOGIN, $request);
    }

    public static function logLogout(int $userId, $request): self
    {
        return self::log($userId, self::ACTION_LOGOUT, $request);
    }

    public static function logLoginFailed(int $userId, $request, ?string $reason = null): self
    {
        return self::log(
            $userId,
            self::ACTION_LOGIN_FAILED,
            $request,
            self::STATUS_ERROR,
            $reason ?? 'Tentativa de login com credenciais inválidas'
        );
    }

    public static function logPasswordChange(int $userId, $request): self
    {
        return self::log($userId, self::ACTION_PASSWORD_CHANGE, $request, self::STATUS_WARNING);
    }

    public static function log2FAEnabled(int $userId, $request): self
    {
        return self::log($userId, self::ACTION_2FA_ENABLED, $request);
    }

    public static function log2FADisabled(int $userId, $request): self
    {
        return self::log($userId, self::ACTION_2FA_DISABLED, $request, self::STATUS_WARNING);
    }

    /**
     * Limpa logs antigos
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }
}
