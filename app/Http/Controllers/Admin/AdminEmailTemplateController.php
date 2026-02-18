<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * AdminEmailTemplateController
 *
 * CRUD de templates de e-mail editáveis pelo admin.
 * Os templates são armazenados na tabela email_templates e usados pelo MailService
 * para enviar e-mails com layout próprio (HTML inline) em vez de templates do Mailgun.
 */
class AdminEmailTemplateController extends Controller
{
    /**
     * Listar todos os templates.
     */
    public function index(Request $request)
    {
        $query = EmailTemplate::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return $query->orderBy('category')->orderBy('name')->get();
    }

    /**
     * Exibir um template.
     */
    public function show(string $id)
    {
        return EmailTemplate::findOrFail($id);
    }

    /**
     * Criar novo template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug'      => 'required|string|unique:email_templates,slug',
            'name'      => 'required|string|max:255',
            'subject'   => 'required|string|max:255',
            'html_body' => 'required|string',
            'variables' => 'nullable|array',
            'category'  => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['id'] = (string) Str::uuid();

        return EmailTemplate::create($validated);
    }

    /**
     * Atualizar template existente.
     */
    public function update(Request $request, string $id)
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'subject'   => 'sometimes|string|max:255',
            'html_body' => 'sometimes|string',
            'variables' => 'nullable|array',
            'category'  => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return $template;
    }

    /**
     * Excluir template.
     */
    public function destroy(string $id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template removido com sucesso.']);
    }

    /**
     * Preview: renderiza o template substituindo variáveis com dados de exemplo.
     */
    public function preview(Request $request, string $id)
    {
        $template = EmailTemplate::findOrFail($id);

        // Buscar logo_url das configurações
        $logoSetting = \DB::table('system_settings')->where('key', 'email_logo_url')->first();
        $logoUrl = $logoSetting->value ?? '';

        $variables = collect($template->variables ?? [])->mapWithKeys(function ($var) {
            $key = $var['key'];
            $sampleValues = [
                'client_name'        => 'João Silva',
                'company_name'       => 'Minha Empresa',
                'charge_description' => 'Mensalidade Março/2025',
                'charge_amount'      => 'R$ 150,00',
                'due_date'           => '15/03/2025',
                'payment_link'       => 'https://pagamento.exemplo.com/abc123',
                'payment_date'       => '10/03/2025',
                'payment_method'     => 'Pix',
                'days_until_due'     => '3',
                'days_overdue'       => '5',
                'error_message'      => 'Cartão recusado pela operadora',
                'plan_name'          => 'Plano Profissional',
                'plan_amount'        => 'R$ 99,90/mês',
                'next_billing_date'  => '15/04/2025',
                'end_date'           => '15/04/2025',
                'login_url'          => 'https://app.exemplo.com/login',
                'reset_link'         => 'https://app.exemplo.com/reset?token=abc123',
                'current_year'       => date('Y'),
                // Reseller variables
                'account_name'       => 'Maria Santos',
                'account_email'      => 'maria@exemplo.com',
                'reseller_name'      => 'João Revendedor',
                'validity_days'      => '30',
                'current_expiry'     => '15/03/2025',
                'new_expiry'         => '14/04/2025',
                'days_remaining'     => '5',
                'expiry_date'        => '20/03/2025',
                'renewal_price'      => 'R$ 99,90',
            ];
            return [$key => $sampleValues[$key] ?? "{{$key}}"];
        })->toArray();

        // Adicionar logo_url como variável global
        $variables['logo_url'] = $logoUrl;
        $variables['current_year'] = date('Y');

        $html = $template->html_body;
        foreach ($variables as $key => $value) {
            $html = str_replace("{{{$key}}}", $value, $html);
        }

        return response()->json([
            'html'    => $html,
            'subject' => str_replace(
                array_map(fn($k) => "{{{$k}}}", array_keys($variables)),
                array_values($variables),
                $template->subject
            ),
        ]);
    }

    /**
     * Enviar e-mail de teste usando o template.
     * Retorna detalhes do erro em caso de falha para facilitar diagnóstico.
     */
    public function sendTest(Request $request, string $id)
    {
        $request->validate(['email' => 'required|email']);

        $template = EmailTemplate::findOrFail($id);

        // Gera preview com dados de exemplo
        $previewData = $this->preview($request, $id)->getData(true);

        $sent = \App\Services\MailService::sendHtml(
            $request->input('email'),
            '[TESTE] ' . $previewData['subject'],
            $previewData['html']
        );

        // Se falhou, buscar o log mais recente para obter detalhes do erro
        $errorDetails = null;
        if (!$sent) {
            $lastLog = \DB::table('mail_logs')
                ->where('to', $request->input('email'))
                ->where('status', 'failed')
                ->orderByDesc('created_at')
                ->first();

            $errorDetails = $lastLog ? $lastLog->error : 'Erro desconhecido - verifique as configurações do Mailgun';

            // Verificar se Mailgun está configurado
            $integration = \DB::table('admin_integrations')
                ->where('slug', 'mailgun')
                ->first();

            $mailgunConfig = null;
            if ($integration && $integration->fields) {
                $fields = json_decode($integration->fields, true);
                if (is_array($fields)) {
                    $config = [];
                    foreach ($fields as $key => $value) {
                        if (is_array($value) && isset($value['key'], $value['value'])) {
                            $config[$value['key']] = $value['value'];
                        } else {
                            $config[$key] = $value;
                        }
                    }
                    $mailgunConfig = $config;
                }
            }

            // Diagnóstico detalhado
            $diagnostics = [];
            if (!$integration) {
                $diagnostics[] = 'Integração Mailgun não encontrada na tabela admin_integrations';
            } elseif (!$mailgunConfig) {
                $diagnostics[] = 'Campos da integração Mailgun estão vazios ou inválidos';
            } else {
                if (empty($mailgunConfig['api_key'])) {
                    $diagnostics[] = 'API Key do Mailgun não configurada';
                }
                if (empty($mailgunConfig['domain'])) {
                    $diagnostics[] = 'Domínio do Mailgun não configurado';
                } elseif (str_starts_with($mailgunConfig['domain'], 'http')) {
                    $diagnostics[] = 'O campo "Domínio" deve conter apenas o domínio (ex: mg.seudominio.com), não a URL completa';
                }
                if (empty($mailgunConfig['from_email'])) {
                    $diagnostics[] = 'E-mail remetente não configurado';
                }
            }

            if (!empty($diagnostics)) {
                $errorDetails = implode('; ', $diagnostics);
            }
        }

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'E-mail de teste enviado!' : 'Falha ao enviar e-mail de teste.',
            'error'   => $errorDetails,
        ]);
    }
}
