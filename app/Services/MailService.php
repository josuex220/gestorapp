<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MailService
 *
 * Serviço centralizado para disparo de e-mails transacionais da plataforma via Mailgun.
 * Agora utiliza templates HTML próprios armazenados na tabela `email_templates`.
 *
 * Fluxo:
 *   1. Busca o template pelo slug na tabela email_templates
 *   2. Renderiza o HTML substituindo as variáveis
 *   3. Envia via Mailgun como HTML inline (sem depender de templates do Mailgun)
 *
 * Uso:
 *   MailService::paymentConfirmed($to, ['client_name' => 'João', 'charge_amount' => 'R$ 100,00', ...]);
 */
class MailService
{
    // ─── Eventos de alto nível ────────────────────────────────────────────

    public static function paymentConfirmed(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'payment_confirmed', $vars);
    }

    public static function paymentFailed(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'payment_failed', $vars);
    }

    public static function subscriptionCancelled(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'subscription_cancelled', $vars);
    }

    public static function subscriptionActivated(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'subscription_activated', $vars);
    }

    public static function subscriptionRenewed(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'subscription_renewed', $vars);
    }

    public static function chargeReminder(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'charge_reminder', $vars);
    }

    public static function chargeOverdue(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'charge_overdue', $vars);
    }

    public static function welcome(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'welcome', $vars);
    }

    public static function passwordReset(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'password_reset', $vars);
    }

    public static function chargeCreated(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'charge_created', $vars);
    }

    public static function subscriptionPaymentConfirmed(string $to, array $vars): bool
    {
        return self::sendTemplate($to, 'subscription_payment_confirmed', $vars);
    }

    // ─── Motor principal (com template do banco) ─────────────────────────

    /**
     * Envia e-mail usando template armazenado no banco de dados.
     * Fallback: se o template não existir, envia texto simples.
     */
    public static function sendTemplate(string $to, string $slug, array $variables = []): bool
    {
        $template = EmailTemplate::findBySlug($slug);

        if (!$template) {
            Log::warning("MailService: Template '{$slug}' não encontrado ou inativo. E-mail não enviado.", [
                'to' => $to,
                'slug' => $slug,
            ]);
            return false;
        }

        $variables['current_year'] = $variables['current_year'] ?? date('Y');

        // Injetar logo_url automaticamente se disponível
        if (!isset($variables['logo_url'])) {
            $logoSetting = DB::table('system_settings')->where('key', 'email_logo_url')->first();
            $variables['logo_url'] = $logoSetting->value ?? '';
        }

        // Gerar header_logo: imagem se logo configurado, senão texto company_name
        $companyName = $variables['company_name'] ?? 'Sistema';
        if (!empty($variables['logo_url'])) {
            $logoUrl = htmlspecialchars($variables['logo_url'], ENT_QUOTES, 'UTF-8');
            $altText = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
            $variables['header_logo'] = '<img src="' . $logoUrl . '" alt="' . $altText . '" style="max-width:180px;max-height:60px;height:auto;display:block;margin:0 auto" />';
        } else {
            $variables['header_logo'] = '<span style="color:#fff;font-size:24px;font-weight:700">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $html    = $template->render($variables);
        $subject = $template->renderSubject($variables);

        return self::sendHtml($to, $subject, $html, $slug);
    }

    /**
     * Envia e-mail com HTML inline (sem template do banco).
     * Usado também internamente pelo sendTemplate após renderizar.
     */
    public static function sendHtml(string $to, string $subject, string $html, ?string $event = null): bool
    {
        $config = self::getConfig();

        if (!$config) {
            Log::warning('MailService: Mailgun não configurado.');
            return false;
        }

        $baseUrl = $config['region'] === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        try {
            $response = Http::withBasicAuth('api', $config['api_key'])
                ->asForm()
                ->post("{$baseUrl}/v3/{$config['domain']}/messages", [
                    'from'    => "{$config['from_name']} <{$config['from_email']}>",
                    'to'      => $to,
                    'subject' => $subject,
                    'html'    => $html,
                ]);

            if ($response->successful()) {
                $mailgunId = $response->json('id');
                Log::info('MailService: E-mail enviado.', [
                    'to' => $to,
                    'event' => $event,
                    'mailgun_id' => $mailgunId,
                ]);
                self::logMail($to, $subject, $event ?? 'html_direct', 'accepted', $mailgunId);
                return true;
            }

            Log::error('MailService: Falha ao enviar e-mail.', [
                'to' => $to,
                'event' => $event,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            self::logMail($to, $subject, $event ?? 'html_direct', 'failed', null, json_encode($response->json()));
            return false;

        } catch (\Exception $e) {
            Log::error('MailService: Exceção ao enviar e-mail.', [
                'to' => $to,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            self::logMail($to, $subject, $event ?? 'html_direct', 'failed', null, $e->getMessage());
            return false;
        }
    }

    /**
     * Método legado mantido para compatibilidade.
     * Agora redireciona para sendTemplate quando possível.
     */
    public static function send(string $to, string $subject, string $template, array $variables = []): bool
    {
        // Tenta usar o template do banco primeiro
        $dbTemplate = EmailTemplate::findBySlug($template);
        if ($dbTemplate) {
            return self::sendTemplate($to, $template, $variables);
        }

        // Fallback: envia via Mailgun template (comportamento antigo)
        $config = self::getConfig();
        if (!$config) {
            Log::warning('MailService: Mailgun não configurado.');
            return false;
        }

        $baseUrl = $config['region'] === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        try {
            $response = Http::withBasicAuth('api', $config['api_key'])
                ->asForm()
                ->post("{$baseUrl}/v3/{$config['domain']}/messages", [
                    'from'                  => "{$config['from_name']} <{$config['from_email']}>",
                    'to'                    => $to,
                    'subject'               => $subject,
                    'template'              => $template,
                    'h:X-Mailgun-Variables' => json_encode($variables),
                ]);

            if ($response->successful()) {
                self::logMail($to, $subject, $template, 'accepted', $response->json('id'));
                return true;
            }

            self::logMail($to, $subject, $template, 'failed', null, json_encode($response->json()));
            return false;

        } catch (\Exception $e) {
            self::logMail($to, $subject, $template, 'failed', null, $e->getMessage());
            return false;
        }
    }

    // ─── Config helper ────────────────────────────────────────────────────

    private static ?array $cachedConfig = null;

    private static function getConfig(): ?array
    {
        if (self::$cachedConfig !== null) {
            return self::$cachedConfig ?: null;
        }

        $integration = DB::table('admin_integrations')
            ->where('slug', 'mailgun')
            ->first();

        if (!$integration || !$integration->fields) {
            self::$cachedConfig = [];
            return null;
        }

        $fields = json_decode($integration->fields, true);
        if (!is_array($fields)) {
            self::$cachedConfig = [];
            return null;
        }

        $config = [];
        foreach ($fields as $key => $value) {
            if (is_array($value) && isset($value['key'], $value['value'])) {
                $config[$value['key']] = $value['value'];
            } else {
                $config[$key] = $value;
            }
        }

        if (empty($config['api_key']) || empty($config['domain']) || empty($config['from_email'])) {
            self::$cachedConfig = [];
            return null;
        }

        self::$cachedConfig = [
            'api_key'    => $config['api_key'],
            'domain'     => $config['domain'],
            'from_email' => $config['from_email'],
            'from_name'  => $config['from_name'] ?? 'Sistema',
            'region'     => $config['region'] ?? 'us',
        ];

        return self::$cachedConfig;
    }

    // ─── Log helper ───────────────────────────────────────────────────────

    private static function logMail(
        string $to,
        string $subject,
        string $event,
        string $status,
        ?string $mailgunId = null,
        ?string $error = null
    ): void {
        try {
            DB::table('mail_logs')->insert([
                'id'         => (string) Str::uuid(),
                'to'         => $to,
                'subject'    => $subject,
                'event'      => $event,
                'status'     => $status,
                'mailgun_id' => $mailgunId,
                'error'      => $error,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('MailService: Falha ao registrar log.', ['error' => $e->getMessage()]);
        }
    }
}
