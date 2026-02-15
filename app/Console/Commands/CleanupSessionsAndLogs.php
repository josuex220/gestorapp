<?php
namespace App\Console\Commands;

use App\Models\UserSession;
use App\Models\AccessLog;
use Illuminate\Console\Command;

class CleanupSessionsAndLogs extends Command
{
    protected $signature = 'app:cleanup-sessions-logs';
    protected $description = 'Limpa sessões expiradas e logs antigos';

    public function handle()
    {
        $sessionsDeleted = UserSession::cleanupExpired();
        $logsDeleted = AccessLog::cleanup(90);

        $this->info("Sessões removidas: {$sessionsDeleted}");
        $this->info("Logs removidos: {$logsDeleted}");
    }
}
