<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poster_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poster_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('role', 24)->default('slide')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remote_url')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->unique(['poster_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poster_images');
    }
};
