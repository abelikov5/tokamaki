<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_poster_requests', function (Blueprint $table) {
            $table->foreignId('poster_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->string('name', 120)->nullable()->change();
            $table->string('phone', 40)->nullable()->change();
            $table->string('city', 120)->nullable()->after('email');
            $table->string('street', 180)->nullable()->after('city');
            $table->unsignedInteger('amount')->nullable()->after('size_code');
            $table->string('original_name')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('custom_poster_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poster_id');
            $table->dropColumn(['city', 'street', 'amount']);
        });
    }
};
