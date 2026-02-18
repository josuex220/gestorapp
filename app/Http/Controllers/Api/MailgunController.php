<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * MailgunController
 *
 * Responsável por disparar e-mails transacionais da plataforma via Mailgun.
 * As credenciais são lidas da tabela admin_integrations (slug = 'mailgun').
 *
 * Eventos suportados:
 * - Confirmação de pagamento
 * - Falha de cobrança
 * - Cancelamento de assinatura
 * - Lembrete de vencimento
 * - Boas-vindas (novo cadastro)
 * - Recuperação de senha
 */
class MailgunController extends Controller
{
    /**
     * Envia um e-mail via Mailgun.
     *
     * POST /api/admin/mailgun/send
     *
     * Body:
     * {
     *   "to": "cliente@email.com",
     *   "subject": "Assunto do e-mail",
     *   "template": "payment_confirmed",  // nome do template
     *   "variables": { "name": "João", "amount": "R$ 100,00" }
     * }
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'template' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $config = $this->getMailgunConfig();
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Mailgun não configurado. Acesse Integrações para configurar.',
            ], 422);
        }

        $baseUrl = $config['region'] === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        try {
            $response = Http::withBasicAuth('api', $config['api_key'])
                ->asForm()
                ->post("{$baseUrl}/v3/{$config['domain']}/messages", [
                    'from' => "{$config['from_name']} <{$config['from_email']}>",
                    'to' => $validated['to'],
                    'subject' => $validated['subject'],
                    'template' => $validated['template'],
                    'h:X-Mailgun-Variables' => json_encode($validated['variables'] ?? []),
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'E-mail enviado com sucesso.',
                    'mailgun_id' => $response->json('id'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha ao enviar e-mail.',
                'error' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar com Mailgun.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envia um e-mail com HTML inline (sem template Mailgun).
     *
     * POST /api/admin/mailgun/send-html
     */
    public function sendHtml(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'html' => 'required|string',
        ]);

        $config = $this->getMailgunConfig();
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Mailgun não configurado.',
            ], 422);
        }

        $baseUrl = $config['region'] === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        try {
            $response = Http::withBasicAuth('api', $config['api_key'])
                ->asForm()
                ->post("{$baseUrl}/v3/{$config['domain']}/messages", [
                    'from' => "{$config['from_name']} <{$config['from_email']}>",
                    'to' => $validated['to'],
                    'subject' => $validated['subject'],
                    'html' => $validated['html'],
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'E-mail enviado com sucesso.',
                    'mailgun_id' => $response->json('id'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha ao enviar e-mail.',
                'error' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar com Mailgun.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Testa a conexão com Mailgun enviando um e-mail de teste para o remetente.
     *
     * POST /api/admin/mailgun/test
     */
    public function test()
    {
        $config = $this->getMailgunConfig();
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Mailgun não configurado.',
            ], 422);
        }

        $baseUrl = $config['region'] === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        try {
            // Valida domínio listando-o
            $response = Http::withBasicAuth('api', $config['api_key'])
                ->get("{$baseUrl}/v3/domains/{$config['domain']}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Conexão verificada! Domínio {$config['domain']} ativo.",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha na validação: verifique a API Key e o domínio.',
                'error' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar com Mailgun.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna a configuração do Mailgun da tabela admin_integrations.
     */
    private function getMailgunConfig(): ?array
    {
        $integration = DB::table('admin_integrations')
            ->where('slug', 'mailgun')
            ->first();

        if (!$integration || !$integration->fields) {
            return null;
        }

        $fields = json_decode($integration->fields, true);
        if (!is_array($fields)) {
            return null;
        }

        // Normalizar: fields pode ser array de objetos ou objeto plano
        $config = [];
        foreach ($fields as $key => $value) {
            if (is_array($value) && isset($value['key'], $value['value'])) {
                $config[$value['key']] = $value['value'];
            } else {
                $config[$key] = $value;
            }
        }

        // Verificar campos obrigatórios
        if (empty($config['api_key']) || empty($config['domain']) || empty($config['from_email'])) {
            return null;
        }

        return [
            'api_key' => $config['api_key'],
            'domain' => $config['domain'],
            'from_email' => $config['from_email'],
            'from_name' => $config['from_name'] ?? 'Sistema',
            'region' => $config['region'] ?? 'us',
        ];
    }
}
