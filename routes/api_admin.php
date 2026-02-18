<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminClientController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmailSettingsController;
use App\Http\Controllers\Admin\AdminEmailTemplateController;
use App\Http\Controllers\Admin\AdminFaqController;
use App\Http\Controllers\Admin\AdminIntegrationController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminLearningController;
use App\Http\Controllers\Admin\AdminMailLogController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminTicketController;
use App\Http\Controllers\Api\MailgunController;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
| Prefix: /api/admin (registered in RouteServiceProvider or bootstrap/app.php)
|
| Route::middleware('api')
|     ->prefix('api/admin')
|     ->group(base_path('routes/api_admin.php'));
|
*/

// Auth (public)
Route::post('auth/login', [AdminAuthController::class, 'login']);

// Protected admin routes
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {

    // Auth
    Route::post('auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('user', [AdminAuthController::class, 'user']);

    // Dashboard

    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('dashboard/scheduled-payments', [AdminDashboardController::class, 'scheduledPayments']);
    Route::get('dashboard/activity', [AdminDashboardController::class, 'activity']);
    Route::get('dashboard/tickets', [AdminDashboardController::class, 'tickets']);
    Route::get('dashboard/event-distribution', [AdminDashboardController::class, 'eventDistribution']);

    // Plans (Platform Plans - tabela platform_plans)
    Route::apiResource('plans', AdminPlanController::class)->names('admin.plan');

    // Clients (Users da plataforma - tabela users)
    Route::apiResource('clients', AdminClientController::class)->except(['store'])
        ->parameters(['clients' => 'client:id'])->names('admin.clients');
    Route::patch('clients/{client}/toggle-status', [AdminClientController::class, 'toggleStatus']);
    Route::patch('clients/{client}/assign-plan', [AdminClientController::class, 'assignPlan']);
    Route::post('clients/{client}/impersonate', [AdminClientController::class, 'impersonate']);

    // Invoices (Charges de todos os usuários - tabela charges)
    Route::get('invoices', [AdminInvoiceController::class, 'index']);
    Route::get('invoices/summary', [AdminInvoiceController::class, 'summary']);
    Route::get('invoices/event-counts', [AdminInvoiceController::class, 'eventCounts']);
    Route::get('invoices/overdue', function () {
        return \App\Models\PlatformInvoice::where('status', 'overdue')
            ->with('user.platformPlan')
            ->latest('due_date')
            ->get()
            ->map(fn ($inv) => [
                'id'           => $inv->id,
                'client_name'  => $inv->user->name ?? 'N/A',
                'client_email' => $inv->user->email ?? '',
                'plan'         => $inv->user->platformPlan->name ?? 'N/A',
                'amount'       => (float) $inv->amount,
                'status'       => $inv->status,
                'due_date'     => $inv->due_date,
            ]);
    });
    Route::get('invoices/{invoice}', [AdminInvoiceController::class, 'show']);
    Route::patch('invoices/{invoice}/mark-paid', [AdminInvoiceController::class, 'markPaid']);
    Route::patch('invoices/{invoice}/cancel', [AdminInvoiceController::class, 'cancel']);


    // Support Tickets
    Route::get('tickets', [AdminTicketController::class, 'index']);
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply']);
    Route::patch('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus']);
    Route::post('tickets/{ticket}/close', [AdminTicketController::class, 'close']);

    // Integrations (admin_integrations table)
    Route::get('integrations', [AdminIntegrationController::class, 'index']);
    Route::put('integrations/{integration}', [AdminIntegrationController::class, 'update']);
    Route::post('integrations/{integration}/disconnect', [AdminIntegrationController::class, 'disconnect']);
    Route::post('integrations/{integration}/test', [AdminIntegrationController::class, 'test']);

    // Profile
    Route::put('profile', [AdminProfileController::class, 'update']);
    Route::post('profile/avatar', [AdminProfileController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [AdminProfileController::class, 'deleteAvatar']);
    Route::post('security/change-password', [AdminProfileController::class, 'changePassword']);


    Route::apiResource('faqs', AdminFaqController::class)->names('admin.faqs');

    // Learning — Tracks
    Route::apiResource('learning/tracks', AdminLearningController::class)->names('admin.learning.tracks');
    Route::post('learning/tracks/reorder', [AdminLearningController::class, 'reorder']);
    // Learning — Lessons (nested under track)
    Route::get('learning/tracks/{track}/lessons', [AdminLearningController::class, 'lessons']);
    Route::post('learning/tracks/{track}/lessons', [AdminLearningController::class, 'storeLesson']);
    Route::put('learning/tracks/{track}/lessons/{lesson}', [AdminLearningController::class, 'updateLesson']);
    Route::delete('learning/tracks/{track}/lessons/{lesson}', [AdminLearningController::class, 'destroyLesson']);
    Route::post('learning/tracks/{track}/lessons/reorder', [AdminLearningController::class, 'reorderLessons']);

    // Learning — Uploads
    Route::post('learning/upload/video', [AdminLearningController::class, 'uploadVideo']);
    Route::post('learning/upload/thumbnail', [AdminLearningController::class, 'uploadThumbnail']);
    Route::post('learning/upload/attachment', [AdminLearningController::class, 'uploadAttachment']);

    // Mailgun — E-mail transacional da plataforma
    Route::post('mailgun/send', [MailgunController::class, 'send']);
    Route::post('mailgun/send-html', [MailgunController::class, 'sendHtml']);
    Route::post('mailgun/test', [MailgunController::class, 'test']);

    // Mail Logs — Histórico de e-mails enviados
    Route::get('mail-logs', [AdminMailLogController::class, 'index']);

    // Email Templates — Templates editáveis de e-mail
    Route::get('email-templates', [AdminEmailTemplateController::class, 'index']);
    Route::post('email-templates', [AdminEmailTemplateController::class, 'store']);
    Route::get('email-templates/{template}', [AdminEmailTemplateController::class, 'show']);
    Route::put('email-templates/{template}', [AdminEmailTemplateController::class, 'update']);
    Route::delete('email-templates/{template}', [AdminEmailTemplateController::class, 'destroy']);
    Route::post('email-templates/{template}/preview', [AdminEmailTemplateController::class, 'preview']);
    Route::post('email-templates/{template}/send-test', [AdminEmailTemplateController::class, 'sendTest']);

    // Email Settings — Logo e configurações visuais dos e-mails
    Route::get('email-settings/logo', [AdminEmailSettingsController::class, 'getLogo']);
    Route::post('email-settings/logo', [AdminEmailSettingsController::class, 'uploadLogo']);
    Route::delete('email-settings/logo', [AdminEmailSettingsController::class, 'deleteLogo']);
});
