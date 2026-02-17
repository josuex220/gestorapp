@component('mail::message')
# Assinatura cancelada

Olá, {{ $userName }}

Sua assinatura do plano **{{ $planName }}** foi cancelada com sucesso.

A partir de agora, seu acesso estará limitado ao plano gratuito.

Se mudar de ideia, você pode reativar sua assinatura a qualquer momento:

@component('mail::button', ['url' => $resubscribeUrl, 'color' => 'primary'])
Reativar assinatura
@endcomponent

Sentiremos sua falta! Se precisar de ajuda, entre em contato com nosso suporte.

Atenciosamente,<br>
{{ $appName }}
@endcomponent
