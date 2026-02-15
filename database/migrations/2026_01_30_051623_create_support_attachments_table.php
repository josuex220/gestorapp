<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->integer('size'); // em bytes
            $table->string('path');
            $table->timestamps();

            // Foreign key
            $table->foreign('message_id')->references('id')->on('support_messages')->onDelete('cascade');

            // Índice
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
    }
};
