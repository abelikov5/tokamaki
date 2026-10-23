<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->uuid('resume_token')->nullable()->unique()->after('id');
        });

        DB::table('generations')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $generation): void {
                DB::table('generations')
                    ->where('id', $generation->id)
                    ->update(['resume_token' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropUnique(['resume_token']);
            $table->dropColumn('resume_token');
        });
    }
};
