<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminIntegration extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'key_label',
        'key_value',
        'fields',
        'connected',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'fields' => 'array',
    ];

    public function getConnectedAttribute(): bool
    {
        $fields = $this->fields ?? [];
        foreach ($fields as $field) {
            if (($field['required'] ?? false) && empty($field['value'])) {
                return false;
            }
        }
        return count($fields) > 0;
    }
    /**
     * Mask legacy single key_value.
     */
    public function getMaskedKeyValueAttribute(): string
    {
        if (empty($this->attributes['key_value'])) return '';
        $prefix = substr($this->attributes['key_value'], 0, 8);
        return $prefix . str_repeat('*', 20);
    }

    /**
     * Return fields with sensitive values masked.
     */
    public function getMaskedFieldsAttribute(): ?array
    {
        $fields = $this->fields;
        if (empty($fields) || !is_array($fields)) return null;

        $sensitiveKeys = ['secret_key', 'client_secret', 'webhook_secret', 'api_token'];
        $masked = [];

        foreach ($fields as $key => $value) {
            if (in_array($key, $sensitiveKeys) && !empty($value)) {
                $prefix = substr($value, 0, 8);
                $masked[$key] = $prefix . str_repeat('*', 20);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
