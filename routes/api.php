<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\LessonProgressController;
use App\Http\Controllers\Api\MercadoPagoController;
use App\Http\Controllers\Api\MercadoPagoWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PixConfigController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicPixPaymentController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PlatformPlanController;
use App\Http\Controllers\Api\PlatformSubscriptionController;
use App\Http\Controllers\Api\ResellerController;
use App\Http\Controllers\Api\StripeWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::get('/platform-plans', [PlatformPlanController::class, 'index']);
Route::get('/platform-plans/{plan}', [PlatformPlanController::class, 'show']);

/** API SUPPORT */
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('support')->group(function () {
        Route::get('stats', [SupportTicketController::class, 'stats']);
        Route::get('tickets', [SupportTicketController::class, 'index']);
        Route::post('tickets', [SupportTicketController::class, 'store']);
        Route::get('tickets/{ticket}', [SupportTicketController::class, 'show']);
        Route::post('tickets/{ticket}/reply', [SupportTicketController::class, 'reply']);
        Route::post('tickets/{ticket}/close', [SupportTicketController::class, 'close']);
    });
});

/** API DASHBOARD */
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('activity', [DashboardController::class, 'recentActivity']);
        Route::get('weekly-chart', [DashboardController::class, 'weeklyChart']);
        Route::get('monthly-chart', [DashboardController::class, 'monthlyChart']);
        Route::get('upcoming-payments', [DashboardController::class, 'upcomingPayments']);
        Route::get('summary', [DashboardController::class, 'summary']);
    });
});


/** API CLIENT */
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::patch('clients/{client}/toggle-status', [ClientController::class, 'toggleStatus']);
    Route::post('clients/{client}/tags', [ClientController::class, 'addTag']);
    Route::delete('clients/{client}/tags/{tag}', [ClientController::class, 'removeTag']);
});


/** API PLANOS */
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('plans', PlanController::class);
    Route::patch('plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus']);
});

/** API Cobranças */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('charges/summary', [ChargeController::class, 'summary']);
    Route::apiResource('charges', ChargeController::class);
    Route::patch('charges/{charge}/status', [ChargeController::class, 'updateStatus']);
    Route::post('charges/{charge}/resend-notification', [ChargeController::class, 'resendNotification']);
    Route::patch('charges/{charge}/mark-paid', [ChargeController::class, 'markAsPaid']);
    Route::patch('charges/{charge}/cancel', [ChargeController::class, 'cancel']);
});

/** API Assinatura */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('subscriptions/summary', [SubscriptionController::class, 'summary']);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::patch('subscriptions/{subscription}/status', [SubscriptionController::class, 'updateStatus']);
    Route::patch('subscriptions/{subscription}/suspend', [SubscriptionController::class, 'suspend']);
    Route::patch('subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate']);
    Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
});

/** API Pagamentos */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('payments/summary', [PaymentController::class, 'summary']);
    Route::get('payments/monthly-revenue', [PaymentController::class, 'monthlyRevenue']);
    Route::apiResource('payments', PaymentController::class)->except(['update', 'destroy']);
    Route::patch('payments/{payment}/refund', [PaymentController::class, 'refund']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Configurações Gerais
    Route::get('settings', [SettingsController::class, 'index']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::patch('settings/categories', [SettingsController::class, 'updateCategories']);
    Route::patch('settings/reminders', [SettingsController::class, 'updateReminders']);
    Route::patch('settings/notifications', [SettingsController::class, 'updateNotifications']);
    Route::patch('settings/appearance', [SettingsController::class, 'updateAppearance']);

    // Perfil
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);

    // Segurança
    Route::post('security/change-password', [SecurityController::class, 'changePassword']);
    Route::get('security/sessions', [SecurityController::class, 'getSessions']);
    Route::delete('security/sessions/{id}', [SecurityController::class, 'endSession']);
    Route::delete('security/sessions', [SecurityController::class, 'endAllSessions']);
    Route::post('security/2fa/enable', [SecurityController::class, 'enable2FA']);
    Route::post('security/2fa/disable', [SecurityController::class, 'disable2FA']);
    Route::get('security/logs', [SecurityController::class, 'getAccessLogs']);
});

//** FAQ */
Route::prefix('faq')->group(function () {
    Route::get('/', [FaqController::class, 'index']);
    Route::get('/categories', [FaqController::class, 'categories']);
    Route::get('/{faq}', [FaqController::class, 'show']);
});

// Mercado Pago - Rotas autenticadas
Route::middleware('auth:sanctum')->prefix('integrations/mercadopago')->group(function () {
    Route::get('config', [MercadoPagoController::class, 'getConfig']);
    Route::put('config', [MercadoPagoController::class, 'updateConfig']);
    Route::post('connect', [MercadoPagoController::class, 'connect']);
    Route::delete('disconnect', [MercadoPagoController::class, 'disconnect']);
    Route::post('test', [MercadoPagoController::class, 'testConnection']);
    Route::post('preferences', [MercadoPagoController::class, 'createPreference']);
    Route::get('logs', [MercadoPagoController::class, 'getLogs']);
});

// Webhook publico (SEM auth)
Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle']);

// API Learning
Route::middleware('auth:sanctum')->group(function () {
    Route::get('learning/tracks', [LearningController::class, 'index']);
    Route::get('learning/tracks/{track}', [LearningController::class, 'show']);

    // Progress tracking
    Route::get('learning/progress', [LessonProgressController::class, 'index']);
    Route::get('learning/progress/summary', [LessonProgressController::class, 'summary']);
    Route::post('learning/progress', [LessonProgressController::class, 'store']);
    Route::delete('learning/progress/{lessonId}', [LessonProgressController::class, 'destroy']);
});


//PIX PROGRESS
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('integrations/pix')->group(function () {
        Route::get('config', [PixConfigController::class, 'show']);
        Route::post('config', [PixConfigController::class, 'store']);
        Route::delete('disconnect', [PixConfigController::class, 'disconnect']);
        Route::delete('config', [PixConfigController::class, 'destroy']);
    });
});

Route::get('public/pix/{chargeId}', [PublicPixPaymentController::class, 'show']);
Route::post('public/pix/{chargeId}/proof', [PublicPixPaymentController::class, 'uploadProof']);
Route::post('public/pix/{chargeId}/confirm', [PublicPixPaymentController::class, 'confirm']);

Route::middleware('auth:sanctum')->prefix('platform-subscription')->group(function () {
    Route::get('/', [PlatformSubscriptionController::class, 'show']);
    Route::post('/checkout', [PlatformSubscriptionController::class, 'checkout']);
    Route::post('/confirm', [PlatformSubscriptionController::class, 'confirmCheckout']);
    Route::post('/reactivate', [PlatformSubscriptionController::class, 'reactivate']);
    Route::post('/cancel', [PlatformSubscriptionController::class, 'cancel']);
    Route::get('/invoices', [PlatformSubscriptionController::class, 'invoices']);
    Route::post('/sync', [PlatformSubscriptionController::class, 'sync']);
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->prefix('reseller')->group(function () {
    Route::get('summary',  [ResellerController::class, 'summary']);
    Route::get('accounts', [ResellerController::class, 'index']);
    Route::post('accounts', [ResellerController::class, 'store']);
    Route::put('accounts/{id}', [ResellerController::class, 'update']);
    Route::patch('accounts/{id}/toggle-status', [ResellerController::class, 'toggleStatus']);
    Route::get('report',   [ResellerController::class, 'report']);
    Route::get('notification-settings',  [ResellerController::class, 'getNotificationSettings']);
    Route::put('notification-settings',  [ResellerController::class, 'updateNotificationSettings']);
    Route::delete('accounts/{id}', [ResellerController::class, 'destroy']);
    Route::patch('accounts/{id}/renew', [ResellerController::class, 'renew']);
    Route::get('accounts/{id}/renewal-history', [ResellerController::class, 'renewalHistory']);
    Route::post('accounts/{id}/charge', [ResellerController::class, 'charge']);
});

Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('charges', [ChargeController::class, 'index']);
    Route::get('charges/summary', [ChargeController::class, 'summary']);
    Route::post('charges', [ChargeController::class, 'store']);
    Route::get('charges/{id}', [ChargeController::class, 'show']);
    Route::put('charges/{id}', [ChargeController::class, 'update']);
    Route::delete('charges/{id}', [ChargeController::class, 'destroy']);
    Route::patch('charges/{id}/status', [ChargeController::class, 'updateStatus']);
    Route::patch('charges/{id}/mark-paid', [ChargeController::class, 'markAsPaid']);
    Route::patch('charges/{id}/cancel', [ChargeController::class, 'cancel']);
    Route::post('charges/{id}/resend', [ChargeController::class, 'resend']);
});
