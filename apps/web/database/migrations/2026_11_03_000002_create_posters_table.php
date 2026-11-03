<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->string('source_key');
            $table->unsignedBigInteger('source_user_id')->default(1)->index();
            $table->string('nm_id')->unique();
            $table->string('vendor_code')->nullable()->index();
            $table->string('slug')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('category')->nullable()->index();
            $table->string('style')->nullable()->index();
            $table->string('room')->nullable()->index();
            $table->string('primary_color')->nullable()->index();
            $table->string('orientation', 16)->nullable()->index();
            $table->json('search_keywords')->nullable();
            $table->json('source_payload')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['source_user_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posters');
    }
};
