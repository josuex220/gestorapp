<?php
// app/Models/UserSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Jenssegers\Agent\Agent;

class UserSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'token_id',
        'device',
        'device_type',
        'browser',
        'platform',
        'ip_address',
        'location',
        'user_agent',
        'is_current',
        'last_active_at',
        'expires_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'last_active_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const DEVICE_TYPES = [
        'desktop' => 'Desktop',
        'mobile' => 'Mobile',
        'tablet' => 'Tablet',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeOthers(Builder $query): Builder
    {
        return $query->where('is_current', false);
    }

    public function scopeRecentlyActive(Builder $query, int $minutes = 30): Builder
    {
        return $query->where('last_active_at', '>=', now()->subMinutes($minutes));
    }

    // ==================== ACCESSORS ====================

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

    public function getDeviceTypeLabelAttribute(): string
    {
        return self::DEVICE_TYPES[$this->device_type] ?? 'Desktop';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getLastActiveAgoAttribute(): string
    {
        if (!$this->last_active_at) {
            return 'Nunca';
        }

        return $this->last_active_at->diffForHumans();
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    /**
     * Cria uma nova sessão a partir do request
     */
    public static function createFromRequest($request, int $userId, ?string $tokenId = null): self
    {
        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $deviceType = 'desktop';
        if ($agent->isMobile()) {
            $deviceType = 'mobile';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        }

        // Marcar todas as outras sessões como não-atuais
        self::where('user_id', $userId)->update(['is_current' => false]);

        return self::create([
            'user_id' => $userId,
            'token_id' => $tokenId,
            'device' => $agent->browser() . ' em ' . $agent->platform(),
            'device_type' => $deviceType,
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'ip_address' => $request->ip(),
            'location' => self::getLocationFromIp($request->ip()),
            'user_agent' => $request->userAgent(),
            'is_current' => true,
            'last_active_at' => now(),
            'expires_at' => now()->addDays(30), // Sessão expira em 30 dias
        ]);
    }

    /**
     * Atualiza o timestamp de última atividade
     */

    public function updateActivity(): bool
    {
        $this->last_active_at = now();
        return $this->save();
    }
    /**
     * Encerra a sessão
     */
    public function terminate(): bool
    {
        // Revogar token Sanctum se existir
        if ($this->token_id && $this->user) {
            $this->user->tokens()->where('id', $this->token_id)->delete();
        }

        return $this->delete();
    }

    /**
     * Obtém localização a partir do IP
     */
    public static function getLocationFromIp(?string $ip): ?string
    {
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
            return 'Local';
        }

        try {
            // Usando ip-api.com (gratuito para uso não comercial)
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country,countryCode");

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['city']) && isset($data['countryCode'])) {
                    return "{$data['city']}, {$data['countryCode']}";
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Limpa sessões expiradas
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }
}
