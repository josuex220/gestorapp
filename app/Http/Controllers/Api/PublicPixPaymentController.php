<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPixConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Public controller (no auth) for PIX payment pages.
 * Serves charge data + PIX config to the public payment link,
 * and handles proof upload / payment confirmation.
 */
class PublicPixPaymentController extends Controller
{
    /**
     * GET /api/public/pix/{chargeId}
     * Returns charge details merged with the user's PIX config.
     */
    public function show(string $chargeId)
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        // Accept charges that are PIX by method OR by provider
        $isPixCharge = $charge->payment_method === 'pix'
            || ($charge->payment_provider ?? null) === 'pix_manual';

        if (!$isPixCharge) {
            return response()->json(['message' => 'Esta cobrança não é PIX'], 400);
        }

        $pixConfig = UserPixConfig::where('user_id', $charge->user_id)
            ->where('is_active', true)
            ->first();

        if (!$pixConfig) {
            return response()->json(['message' => 'Configuração PIX não encontrada'], 404);
        }

        $client = DB::table('clients')->where('id', $charge->client_id)->first();

        return response()->json([
            'charge_id'      => $charge->id,
            'amount'         => (float) $charge->amount,
            'description'    => $charge->description ?? 'Pagamento via PIX',
            'holder_name'    => $pixConfig->holder_name,
            'pix_key'        => $pixConfig->key_value,
            'pix_key_type'   => $pixConfig->key_type,
            'require_proof'  => (bool) $pixConfig->require_proof,
            'proof_required' => (bool) $pixConfig->proof_required,
            'status'         => $charge->status,
            'due_date'       => $charge->due_date,
            'client_name'    => $client->name ?? null,
        ]);
    }

    /**
     * POST /api/public/pix/{chargeId}/proof
     * Uploads a payment proof file attached to the charge.
     */
    public function uploadProof(Request $request, string $chargeId)
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        $request->validate([
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('proof')->store(
            "pix-proofs/{$chargeId}",
            'public'
        );

        DB::table('charges')->where('id', $chargeId)->update([
            'proof_path'        => $path,
            'proof_uploaded_at' => now(),
            'updated_at'        => now(),
        ]);

        // Notify charge owner
        $this->notifyChargeOwner($charge, 'proof_uploaded', $path);

        return response()->json(['message' => 'Comprovante enviado com sucesso']);
    }

    /**
     * POST /api/public/pix/{chargeId}/confirm
     * Client confirms they made the payment (without proof).
     */
    public function confirm(string $chargeId)
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        DB::table('charges')->where('id', $chargeId)->update([
            'client_confirmed_at' => now(),
            'updated_at'          => now(),
        ]);

        // Notify charge owner
        $this->notifyChargeOwner($charge, 'payment_confirmed');

        return response()->json(['message' => 'Pagamento informado com sucesso']);
    }

    // ─── Notification Logic ──────────────────────────────────────────────

    /**
     * Notify the charge owner (cobrador) via email and/or WhatsApp.
     *
     * @param object $charge       The charge record
     * @param string $eventType    'proof_uploaded' or 'payment_confirmed'
     * @param string|null $proofPath  Storage path of the uploaded proof (if any)
     */
    private function notifyChargeOwner(object $charge, string $eventType, ?string $proofPath = null): void
    {
        try {
            $owner = User::find($charge->user_id);
            if (!$owner) return;

            $client = DB::table('clients')->where('id', $charge->client_id)->first();
            $clientName = $client->name ?? 'Cliente';
            $amount = number_format((float) $charge->amount, 2, ',', '.');

            // Load user notification settings
            $settings = DB::table('user_settings')->where('user_id', $charge->user_id)->first();
            $channels = $settings
                ? json_decode($settings->notification_channels ?? '{}', true)
                : ['email' => true];

            // ── Email notification ──
            if ($channels['email'] ?? true) {
                $this->sendEmailNotification($owner, $clientName, $amount, $eventType, $charge->id, $proofPath);
            }

            // ── WhatsApp notification ──
            if ($channels['whatsapp'] ?? false) {
                $this->sendWhatsAppNotification($owner, $clientName, $amount, $eventType, $charge->id);
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao notificar cobrador sobre pagamento PIX', [
                'charge_id' => $charge->id,
                'event'     => $eventType,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification to charge owner.
     */
    private function sendEmailNotification(
        User $owner,
        string $clientName,
        string $amount,
        string $eventType,
        string $chargeId,
        ?string $proofPath
    ): void {
        $isProof = $eventType === 'proof_uploaded';

        $subject = $isProof
            ? "📎 Comprovante recebido — R$ {$amount} de {$clientName}"
            : "💰 Pagamento informado — R$ {$amount} de {$clientName}";

        $body = $isProof
            ? "O cliente <strong>{$clientName}</strong> enviou um comprovante de pagamento no valor de <strong>R$ {$amount}</strong>.<br><br>"
              . "Acesse o painel para visualizar o comprovante e confirmar o recebimento."
            : "O cliente <strong>{$clientName}</strong> informou que realizou o pagamento PIX no valor de <strong>R$ {$amount}</strong>.<br><br>"
              . "Acesse o painel para verificar e confirmar o recebimento.";

        Mail::html($body, function ($message) use ($owner, $subject) {
            $message->to($owner->email)
                    ->subject($subject);
        });
    }

    /**
     * Send WhatsApp notification to charge owner via configured integration.
     * Uses the WhatsApp integration API (e.g., Evolution API, Z-API, etc.)
     */
    private function sendWhatsAppNotification(
        User $owner,
        string $clientName,
        string $amount,
        string $eventType,
        string $chargeId
    ): void {
        $phone = $owner->phone;
        if (!$phone) return;

        $isProof = $eventType === 'proof_uploaded';

        $text = $isProof
            ? "📎 *Comprovante recebido*\n\nCliente: {$clientName}\nValor: R$ {$amount}\n\nAcesse o painel para visualizar e confirmar."
            : "💰 *Pagamento informado*\n\nCliente: {$clientName}\nValor: R$ {$amount}\n\nAcesse o painel para verificar e confirmar.";

        // Load WhatsApp integration config
        $whatsappConfig = DB::table('admin_integrations')
            ->where('slug', 'whatsapp')
            ->where('is_active', true)
            ->first();

        if (!$whatsappConfig) {
            Log::info('WhatsApp integration not configured, skipping notification');
            return;
        }

        $fields = json_decode($whatsappConfig->fields ?? '{}', true);
        $apiUrl = $fields['api_url'] ?? null;
        $apiKey = $fields['api_key'] ?? null;
        $instanceName = $fields['instance_name'] ?? null;

        if (!$apiUrl || !$apiKey) {
            Log::info('WhatsApp API credentials incomplete, skipping');
            return;
        }

        // Send via Evolution API (or similar)
        try {
            $client = new \GuzzleHttp\Client();
            $client->post("{$apiUrl}/message/sendText/{$instanceName}", [
                'headers' => [
                    'apikey'       => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'number' => preg_replace('/\D/', '', $phone),
                    'text'   => $text,
                ],
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar WhatsApp ao cobrador', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
