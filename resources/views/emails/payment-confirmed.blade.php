@component('mail::message')
# Pagamento confirmado! ✅

Olá, {{ $userName }}

Recebemos o pagamento da sua assinatura com sucesso.

**Detalhes:**
- **Fatura:** {{ $invoiceNumber }}
- **Descrição:** {{ $description }}
- **Valor:** R$ {{ $amount }}

@if($pdfUrl)
@component('mail::button', ['url' => $pdfUrl, 'color' => 'primary'])
Baixar comprovante (PDF)
@endcomponent
@endif

Obrigado por ser nosso cliente!

Atenciosamente,<br>
{{ $appName }}
@endcomponent
