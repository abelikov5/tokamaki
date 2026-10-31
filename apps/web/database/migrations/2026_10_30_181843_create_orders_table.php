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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('new')->index();
            $table->string('email');
            $table->string('city', 120);
            $table->string('street', 180);
            $table->boolean('designer_processing')->default(false);
            $table->unsignedInteger('amount');
            $table->string('original_name');
            $table->string('original_image_path');
            $table->string('generated_image_path');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
