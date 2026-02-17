@component('mail::message')
# Olá, {{ $userName }}

Identificamos que houve uma **falha no pagamento** da sua assinatura.

**Detalhes:**
- **Fatura:** {{ $invoiceNumber }}
- **Valor:** R$ {{ $amount }}

Isso pode acontecer por saldo insuficiente, cartão expirado ou limite excedido.

@if($hostedUrl)
@component('mail::button', ['url' => $hostedUrl, 'color' => 'primary'])
Atualizar forma de pagamento
@endcomponent
@endif

Se o problema persistir, entre em contato com nosso suporte.

Atenciosamente,<br>
{{ $appName }}

@component('mail::subcopy')
Se você acredita que isso é um erro, por favor ignore este e-mail ou entre em contato com nosso suporte.
@endcomponent
@endcomponent
