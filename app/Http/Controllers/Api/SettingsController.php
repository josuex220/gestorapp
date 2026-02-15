<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Retorna todas as configurações do usuário
     */
    public function index(): SettingsResource
    {
        $settings = UserSettings::firstOrCreate(
            ['user_id' => Auth::id()],
            $this->getDefaultSettings()
        );

        return new SettingsResource($settings);
    }

    /**
     * Atualiza todas as configurações
     */
    public function update(UpdateSettingsRequest $request): SettingsResource
    {
        $settings = UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            $request->validated()
        );

        return new SettingsResource($settings->fresh());
    }

    /**
     * Atualiza apenas categorias
     */
    public function updateCategories(Request $request): SettingsResource
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|string',
            'categories.*.label' => 'required|string|max:50',
            'categories.*.color' => 'required|string',
            'categories.*.bgColor' => 'required|string',
        ]);

        $settings = UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            ['categories' => $request->categories]
        );

        return new SettingsResource($settings->fresh());
    }

    /**
     * Atualiza lembretes
     */
    public function updateReminders(Request $request): SettingsResource
    {
        $request->validate([
            'auto_reminders' => 'sometimes|boolean',
            'reminders' => 'sometimes|array',
            'reminders.*.id' => 'required|string',
            'reminders.*.type' => 'required|in:before,on_due,after',
            'reminders.*.days' => 'required|integer|min:0|max:365',
            'reminders.*.channels' => 'required|array',
            'reminders.*.enabled' => 'required|boolean',
            'reminder_send_time' => 'sometimes|date_format:H:i',
        ]);

        $data = $request->only(['auto_reminders', 'reminders', 'reminder_send_time']);
        $data = array_filter($data, fn($v) => $v !== null);

        $settings = UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return new SettingsResource($settings->fresh());
    }

    /**
     * Atualiza notificações
     */
    public function updateNotifications(Request $request): SettingsResource
    {
        $request->validate([
            'notification_channels' => 'sometimes|array',
            'notification_channels.email' => 'sometimes|boolean',
            'notification_channels.push' => 'sometimes|boolean',
            'notification_channels.whatsapp' => 'sometimes|boolean',
            'notification_preferences' => 'sometimes|array',
        ]);

        $data = $request->only(['notification_channels', 'notification_preferences']);
        $data = array_filter($data, fn($v) => $v !== null);

        $settings = UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return new SettingsResource($settings->fresh());
    }

    /**
     * Atualiza aparência
     */
    public function updateAppearance(Request $request): SettingsResource
    {
        $request->validate([
            'theme' => 'sometimes|in:light,dark,system',
            'color_scheme' => 'sometimes|in:teal,blue,purple,green,orange,rose',
        ]);

        $data = $request->only(['theme', 'color_scheme']);
        $data = array_filter($data, fn($v) => $v !== null);

        $settings = UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return new SettingsResource($settings->fresh());
    }

    /**
     * Configurações padrão
     */
    private function getDefaultSettings(): array
    {
        return [
            'categories' => [
                ['id' => 'consultoria', 'label' => 'Consultoria', 'color' => 'text-blue-600', 'bgColor' => 'bg-blue-100'],
                ['id' => 'design', 'label' => 'Design', 'color' => 'text-purple-600', 'bgColor' => 'bg-purple-100'],
                ['id' => 'desenvolvimento', 'label' => 'Desenvolvimento', 'color' => 'text-emerald-600', 'bgColor' => 'bg-emerald-100'],
                ['id' => 'marketing', 'label' => 'Marketing', 'color' => 'text-orange-600', 'bgColor' => 'bg-orange-100'],
                ['id' => 'suporte', 'label' => 'Suporte', 'color' => 'text-cyan-600', 'bgColor' => 'bg-cyan-100'],
                ['id' => 'treinamento', 'label' => 'Treinamento', 'color' => 'text-pink-600', 'bgColor' => 'bg-pink-100'],
            ],
            'auto_reminders' => true,
            'reminders' => [
                ['id' => '1', 'type' => 'before', 'days' => 3, 'channels' => ['email' => true, 'whatsapp' => false, 'telegram' => false], 'enabled' => true],
                ['id' => '2', 'type' => 'before', 'days' => 1, 'channels' => ['email' => true, 'whatsapp' => true, 'telegram' => false], 'enabled' => true],
                ['id' => '3', 'type' => 'on_due', 'days' => 0, 'channels' => ['email' => true, 'whatsapp' => true, 'telegram' => false], 'enabled' => true],
                ['id' => '4', 'type' => 'after', 'days' => 1, 'channels' => ['email' => true, 'whatsapp' => true, 'telegram' => false], 'enabled' => true],
                ['id' => '5', 'type' => 'after', 'days' => 7, 'channels' => ['email' => true, 'whatsapp' => true, 'telegram' => true], 'enabled' => true],
            ],
            'reminder_send_time' => '09:00',
            'notification_channels' => ['email' => true, 'push' => false, 'whatsapp' => false],
            'notification_preferences' => [
                [
                    'id' => 'billing',
                    'title' => 'Cobranças',
                    'description' => 'Notificações sobre cobranças e pagamentos',
                    'settings' => [
                        ['id' => 'new_charge', 'label' => 'Nova cobrança criada', 'description' => 'Receba um alerta quando uma nova cobrança for gerada', 'enabled' => true],
                        ['id' => 'payment_received', 'label' => 'Pagamento recebido', 'description' => 'Seja notificado quando um pagamento for confirmado', 'enabled' => true],
                        ['id' => 'payment_overdue', 'label' => 'Pagamento atrasado', 'description' => 'Alerta quando uma cobrança estiver vencida', 'enabled' => true],
                    ],
                ],
                [
                    'id' => 'clients',
                    'title' => 'Clientes',
                    'description' => 'Notificações sobre atividades de clientes',
                    'settings' => [
                        ['id' => 'new_client', 'label' => 'Novo cliente cadastrado', 'description' => 'Receba um alerta quando um novo cliente se cadastrar', 'enabled' => true],
                        ['id' => 'client_inactive', 'label' => 'Cliente inativo', 'description' => 'Alerta quando um cliente ficar sem atividade por 30 dias', 'enabled' => false],
                    ],
                ],
                [
                    'id' => 'system',
                    'title' => 'Sistema',
                    'description' => 'Notificações gerais do sistema',
                    'settings' => [
                        ['id' => 'updates', 'label' => 'Atualizações do sistema', 'description' => 'Novidades e melhorias na plataforma', 'enabled' => true],
                        ['id' => 'security', 'label' => 'Alertas de segurança', 'description' => 'Notificações sobre login e atividades suspeitas', 'enabled' => true],
                    ],
                ],
            ],
            'theme' => 'system',
            'color_scheme' => 'teal',
        ];
    }
}
