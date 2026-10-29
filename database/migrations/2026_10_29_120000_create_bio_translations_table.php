<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bio_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_language', 20);
            $table->char('content_hash', 64);
            $table->string('source_language', 20);
            $table->text('translated_text');
            $table->timestamps();
            $table->unique(['user_id', 'target_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bio_translations');
    }
};
