<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // ===== COBRANÇAS =====
            [
                'question' => 'Como criar uma cobrança avulsa?',
                'answer' => "Para criar uma cobrança avulsa, acesse o menu 'Cobranças' e clique no botão 'Nova Cobrança'. Preencha os dados do cliente, valor, data de vencimento e método de pagamento desejado. Você pode enviar a cobrança por e-mail, WhatsApp ou gerar um link de pagamento.",
                'category' => 'cobrancas',
                'order' => 1,
            ],
            [
                'question' => 'Como configurar cobranças recorrentes?',
                'answer' => "Acesse 'Assinaturas' no menu lateral e clique em 'Nova Assinatura'. Selecione o cliente, escolha um plano existente ou crie um personalizado, defina a periodicidade (mensal, trimestral, anual) e a data de início. As cobranças serão geradas automaticamente.",
                'category' => 'cobrancas',
                'order' => 2,
            ],
            [
                'question' => 'É possível parcelar uma cobrança?',
                'answer' => "Sim! Ao criar uma nova cobrança, selecione a opção 'Parcelamento' e defina o número de parcelas. O sistema calculará automaticamente o valor de cada parcela e gerará as datas de vencimento correspondentes.",
                'category' => 'cobrancas',
                'order' => 3,
            ],
            [
                'question' => 'Como enviar lembretes de cobrança?',
                'answer' => "Vá em 'Configurações > Lembretes' para configurar lembretes automáticos antes e depois do vencimento. Você pode definir quantos dias antes/depois enviar, os canais (e-mail, WhatsApp, Telegram) e personalizar as mensagens.",
                'category' => 'cobrancas',
                'order' => 4,
            ],

            // ===== PAGAMENTOS =====
            [
                'question' => 'Quais métodos de pagamento são aceitos?',
                'answer' => "Aceitamos Pix, Boleto Bancário e Cartão de Crédito. Você pode configurar quais métodos disponibilizar para seus clientes em 'Integrações > Meios de Pagamento'. Cada método pode ser habilitado/desabilitado individualmente.",
                'category' => 'pagamentos',
                'order' => 1,
            ],
            [
                'question' => 'Quanto tempo leva para o pagamento ser confirmado?',
                'answer' => 'Pix: confirmação instantânea. Boleto: até 3 dias úteis após o pagamento. Cartão de Crédito: confirmação instantânea. Você receberá uma notificação assim que o pagamento for identificado.',
                'category' => 'pagamentos',
                'order' => 2,
            ],
            [
                'question' => 'Como fazer estorno de um pagamento?',
                'answer' => "Acesse 'Pagamentos', localize a transação desejada e clique em 'Ver detalhes'. Utilize o botão 'Solicitar estorno' e siga as instruções. O prazo de estorno varia conforme o método de pagamento e instituição financeira.",
                'category' => 'pagamentos',
                'order' => 3,
            ],

            // ===== CLIENTES =====
            [
                'question' => 'Como importar clientes de uma planilha?',
                'answer' => "Vá em 'Clientes' e clique em 'Importar'. Baixe o modelo de planilha Excel/CSV, preencha com os dados dos seus clientes e faça o upload. O sistema validará os dados e mostrará um resumo antes de confirmar a importação.",
                'category' => 'clientes',
                'order' => 1,
            ],
            [
                'question' => 'Como segmentar minha base de clientes?',
                'answer' => 'Utilize tags e categorias para organizar seus clientes. Acesse o perfil de cada cliente e adicione tags personalizadas. Você também pode filtrar clientes por status de pagamento, plano, data de cadastro e outros critérios.',
                'category' => 'clientes',
                'order' => 2,
            ],
            [
                'question' => 'Posso ter múltiplos contatos para um cliente?',
                'answer' => "Sim! Cada cliente pode ter vários contatos (e-mails, telefones). Ao editar um cliente, clique em 'Adicionar contato' e defina qual será o contato principal para receber as cobranças.",
                'category' => 'clientes',
                'order' => 3,
            ],

            // ===== INTEGRAÇÕES =====
            [
                'question' => 'Como integrar com o Zapier?',
                'answer' => "Acesse 'Integrações > Outras > Zapier' e clique em 'Conectar'. Você receberá uma API Key para usar no Zapier. Temos templates prontos para as automações mais comuns como notificações no Slack, Google Sheets e CRMs.",
                'category' => 'integracoes',
                'order' => 1,
            ],
            [
                'question' => 'É possível usar webhooks personalizados?',
                'answer' => "Sim! Em 'Integrações > Outras > Webhooks', adicione a URL do seu endpoint. Você pode configurar quais eventos deseja receber (novo pagamento, cobrança vencida, cliente cadastrado, etc.) e testar a conexão.",
                'category' => 'integracoes',
                'order' => 2,
            ],
            [
                'question' => 'Como configurar notificações por WhatsApp?',
                'answer' => "Vá em 'Integrações > Notificações > WhatsApp' e conecte sua conta comercial. Você pode usar a API oficial do WhatsApp Business ou integrações como Twilio e Z-API. Configure os templates de mensagem em 'Configurações > Templates'.",
                'category' => 'integracoes',
                'order' => 3,
            ],

            // ===== CONTA =====
            [
                'question' => 'Como alterar meu plano?',
                'answer' => "Acesse 'Configurações > Plano' para ver seu plano atual e opções de upgrade. As mudanças são aplicadas imediatamente e o valor é ajustado proporcionalmente. Downgrades são aplicados no próximo ciclo de faturamento.",
                'category' => 'conta',
                'order' => 1,
            ],
            [
                'question' => 'Como adicionar mais usuários à minha conta?',
                'answer' => "Em 'Configurações > Usuários', clique em 'Convidar usuário'. Defina o e-mail, função (Admin, Operador, Visualizador) e permissões específicas. O usuário receberá um e-mail para criar sua senha e acessar a conta.",
                'category' => 'conta',
                'order' => 2,
            ],
            [
                'question' => 'É possível ter múltiplas empresas na mesma conta?',
                'answer' => 'Sim! Com o plano Business, você pode cadastrar múltiplas empresas/CNPJs. Cada empresa tem sua própria base de clientes, cobranças e configurações, mas você gerencia tudo de um único painel.',
                'category' => 'conta',
                'order' => 3,
            ],

            // ===== SEGURANÇA =====
            [
                'question' => 'Como ativar a autenticação de dois fatores (2FA)?',
                'answer' => "Acesse 'Configurações > Segurança' e ative a opção '2FA'. Escaneie o QR Code com seu aplicativo autenticador (Google Authenticator, Authy, etc.) e confirme com o código gerado. Guarde os códigos de recuperação em local seguro.",
                'category' => 'seguranca',
                'order' => 1,
            ],
            [
                'question' => 'Como ver os acessos à minha conta?',
                'answer' => "Em 'Configurações > Segurança', você encontra o histórico de acessos com data, hora, localização e dispositivo. Também pode encerrar sessões ativas em outros dispositivos e receber alertas de acessos suspeitos.",
                'category' => 'seguranca',
                'order' => 2,
            ],
            [
                'question' => 'Meus dados estão seguros?',
                'answer' => 'Sim! Utilizamos criptografia de ponta a ponta, servidores com certificação SOC 2 Type II, backups diários e conformidade com a LGPD. Seus dados financeiros são processados por gateways certificados PCI-DSS.',
                'category' => 'seguranca',
                'order' => 3,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
