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
            $table->foreignId('visitor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->after('visitor_id');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->unsignedInteger('edits_count')->default(0)->after('error');
            $table->timestamp('last_activity_at')->nullable()->index()->after('completed_at');
            $table->index(['visitor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropIndex(['visitor_id', 'created_at']);
            $table->dropForeign(['visitor_id']);
            $table->dropColumn([
                'visitor_id',
                'ip_address',
                'user_agent',
                'edits_count',
                'last_activity_at',
            ]);
        });
    }
};
