<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->boolean('is_admin_recommended')->default(false)->after('status');
            $table->unsignedBigInteger('view_count')->default(0)->after('is_admin_recommended');
            $table->index(
                ['status', 'is_admin_recommended', 'published_at'],
                'posters_catalog_recommendation_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->dropIndex('posters_catalog_recommendation_index');
            $table->dropColumn(['is_admin_recommended', 'view_count']);
        });
    }
};
