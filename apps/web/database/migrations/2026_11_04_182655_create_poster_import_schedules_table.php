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
        Schema::create('poster_import_schedules', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('source_user_id')->default(1);
            $table->string('scheduled_time', 8)->default('03:30');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->unsignedInteger('random_count')->default(20);
            $table->unsignedTinyInteger('image_limit')->default(3);
            $table->boolean('only_new')->default(true);
            $table->boolean('generate_ai')->default(true);
            $table->timestampTz('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poster_import_schedules');
    }
};
