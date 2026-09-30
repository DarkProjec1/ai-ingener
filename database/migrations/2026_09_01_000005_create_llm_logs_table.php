<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->text('user_message');
            $table->json('llm_response')->nullable();
            $table->boolean('escalated')->default(false);
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
