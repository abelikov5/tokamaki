<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_now_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poster_import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source', 24)->index();
            $table->string('status', 24)->index();
            $table->unsignedInteger('urls_count')->default(0);
            $table->text('error')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_now_submissions');
    }
};
