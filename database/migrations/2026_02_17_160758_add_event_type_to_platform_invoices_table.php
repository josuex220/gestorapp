<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->string('event_type')->default('activation')->after('description');
            // event_type: activation, reactivation, renewal, cancellation
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn('event_type');
        });
    }
};
