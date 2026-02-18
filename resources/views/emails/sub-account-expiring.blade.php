<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub-contas próximas de expirar</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f4f4f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .header-urgent { background: linear-gradient(135deg, #f97316, #ea580c); }
        .header-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .header { padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px; }
        .content { padding: 24px; }
        .greeting { font-size: 16px; color: #18181b; margin-bottom: 16px; }
        .urgency-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-urgent { background: #ffedd5; color: #9a3412; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .account-card { border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .card-warning { background: #fffbeb; border: 1px solid #fde68a; }
        .card-urgent { background: #fff7ed; border: 1px solid #fed7aa; }
        .card-critical { background: #fef2f2; border: 1px solid #fecaca; }
        .account-name { font-weight: 600; color: #18181b; font-size: 15px; }
        .account-email { color: #71717a; font-size: 13px; margin-top: 2px; }
        .account-expiry { font-size: 13px; margin-top: 6px; font-weight: 500; }
        .expiry-warning { color: #b45309; }
        .expiry-urgent { color: #c2410c; }
        .expiry-critical { color: #dc2626; }
        .cta { display: inline-block; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; margin-top: 20px; color: #ffffff; }
        .cta-warning { background: #f59e0b; }
        .cta-urgent { background: #f97316; }
        .cta-critical { background: #ef4444; }
        .footer { padding: 20px 24px; background: #fafafa; text-align: center; color: #a1a1aa; font-size: 12px; border-top: 1px solid #f4f4f5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header header-{{ $urgency }}">
            @if($urgency === 'critical')
                <h1>⛔ Expiração HOJE</h1>
                <p>{{ $accounts->count() }} sub-conta(s) será(ão) bloqueada(s) hoje!</p>
            @elseif($urgency === 'urgent')
                <h1>⚠️ Expiração em 3 dias</h1>
                <p>{{ $accounts->count() }} sub-conta(s) expira(m) em breve</p>
            @else
                <h1>📋 Expiração em 7 dias</h1>
                <p>{{ $accounts->count() }} sub-conta(s) expira(m) em 7 dias</p>
            @endif
        </div>

        <div class="content">
            <p class="greeting">Olá, <strong>{{ $reseller->name }}</strong>!</p>

            <span class="urgency-badge badge-{{ $urgency }}">
                @if($urgency === 'critical')
                    Ação imediata necessária
                @elseif($urgency === 'urgent')
                    Atenção urgente
                @else
                    Aviso antecipado
                @endif
            </span>

            <p style="color: #52525b; font-size: 14px; line-height: 1.6;">
                @if($urgency === 'critical')
                    As seguintes sub-contas <strong>expiram hoje</strong> e serão <strong>bloqueadas automaticamente</strong> se não forem renovadas.
                @elseif($urgency === 'urgent')
                    As seguintes sub-contas expiram em <strong>3 dias</strong>. Renove agora para evitar interrupção no serviço.
                @else
                    As seguintes sub-contas expiram em <strong>7 dias</strong>. Renove a validade para evitar o bloqueio automático.
                @endif
            </p>

            @foreach($accounts as $account)
            <div class="account-card card-{{ $urgency }}">
                <div class="account-name">{{ $account->name }}</div>
                <div class="account-email">{{ $account->email }}</div>
                <div class="account-expiry expiry-{{ $urgency }}">
                    @if($urgency === 'critical')
                        🔴 Expira hoje: {{ \Carbon\Carbon::parse($account->reseller_expires_at)->format('d/m/Y') }}
                    @elseif($urgency === 'urgent')
                        🟠 Expira em 3 dias: {{ \Carbon\Carbon::parse($account->reseller_expires_at)->format('d/m/Y') }}
                    @else
                        🟡 Expira em 7 dias: {{ \Carbon\Carbon::parse($account->reseller_expires_at)->format('d/m/Y') }}
                    @endif
                </div>
            </div>
            @endforeach

            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/revenda" class="cta cta-{{ $urgency }}">
                    @if($urgency === 'critical')
                        Renovar Imediatamente
                    @elseif($urgency === 'urgent')
                        Renovar Agora
                    @else
                        Gerenciar Renovações
                    @endif
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Este é um e-mail automático. Você está recebendo porque possui sub-contas próximas de expirar.</p>
        </div>
    </div>
</body>
</html>
