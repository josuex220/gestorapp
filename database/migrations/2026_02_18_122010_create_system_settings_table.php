<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Inserir configuração inicial do logo
        DB::table('system_settings')->insert([
            ['key' => 'email_logo_url', 'value' => null, 'group' => 'email', 'description' => 'URL do logo exibido nos e-mails', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_name', 'value' => 'GestorApp', 'group' => 'general', 'description' => 'Nome da aplicação', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
