<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->string('source', 24)->default('manual')->after('source_user_id')->index();
            $table->boolean('generate_ai')->default(true)->after('image_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'generate_ai']);
        });
    }
};
