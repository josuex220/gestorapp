<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_used_trial')->default(false)->after('trial_ends_at');
        });

        // Mark existing users who already had a trial
        DB::table('users')
            ->whereNotNull('trial_ends_at')
            ->update(['has_used_trial' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_used_trial');
        });
    }
};
