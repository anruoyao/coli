<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('target_language', 20);
            $table->char('content_hash', 64);
            $table->string('source_language', 20);
            $table->longText('translated_text');
            $table->timestamps();
            $table->unique(['post_id', 'target_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_translations');
    }
};
