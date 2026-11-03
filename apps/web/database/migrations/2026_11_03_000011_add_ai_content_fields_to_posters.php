<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->string('ai_generation_status')->default('not_requested')->index();
            $table->string('ai_model')->nullable();
            $table->string('ai_prompt_version')->nullable();
            $table->string('ai_input_hash', 64)->nullable()->index();
            $table->text('ai_generation_error')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_generation_status',
                'ai_model',
                'ai_prompt_version',
                'ai_input_hash',
                'ai_generation_error',
                'ai_generated_at',
            ]);
        });
    }
};
