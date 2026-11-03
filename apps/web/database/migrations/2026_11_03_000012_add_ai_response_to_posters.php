<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->json('ai_response')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->dropColumn('ai_response');
        });
    }
};
