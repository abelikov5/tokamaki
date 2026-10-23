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
        Schema::table('generations', function (Blueprint $table): void {
            $table->uuid('previous_result_id')->nullable()->after('result_id');
            $table->json('previous_palette')->nullable()->after('palette');
            $table->json('previous_diagnostics')->nullable()->after('diagnostics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropColumn([
                'previous_result_id',
                'previous_palette',
                'previous_diagnostics',
            ]);
        });
    }
};
