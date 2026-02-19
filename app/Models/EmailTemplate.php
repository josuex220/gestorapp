<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasUuids;

    protected $table = 'email_templates';

    protected $fillable = [
        'slug',
        'name',
        'subject',
        'html_body',
        'variables',
        'category',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Busca um template pelo slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Renderiza o template substituindo as variáveis.
     */
    public function render(array $variables = []): string
    {
        $html = $this->html_body;
        $variables['current_year'] = $variables['current_year'] ?? date('Y');

        foreach ($variables as $key => $value) {
            $html = str_replace("{{{$key}}}", (string) $value, $html);
        }

        foreach ($variables as $key => $value) {
            $html = str_replace("{{{{{$key}}}}}", (string) $value, $html);
        }

        return $html;
    }

    /**
     * Renderiza o assunto substituindo as variáveis.
     */
    public function renderSubject(array $variables = []): string
    {
        $subject = $this->subject;
        foreach ($variables as $key => $value) {
            $subject = str_replace("{{{$key}}}", (string) $value, $subject);
        }

        foreach ($variables as $key => $value) {
            $subject = str_replace("{{{{{$key}}}}}", (string) $value, $subject);
        }
        return $subject;
    }
}
