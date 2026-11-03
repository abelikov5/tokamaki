<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_poster_requests', function (Blueprint $table) {
            $table->id();
            $table->string('status', 24)->default('new')->index();
            $table->string('name', 120);
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->string('size_code', 16);
            $table->string('original_name');
            $table->string('photo_path');
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_poster_requests');
    }
};
