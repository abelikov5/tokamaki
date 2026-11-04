<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->string('selection_mode', 24)->default('all')->after('source_user_id')->index();
            $table->unsignedInteger('random_count')->nullable()->after('requested_nm_ids');
            $table->boolean('only_new')->default(false)->after('random_count');
            $table->unsignedInteger('total_count')->default(0)->after('only_new');
            $table->unsignedInteger('skipped_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->dropColumn(['selection_mode', 'random_count', 'only_new', 'total_count', 'skipped_count']);
        });
    }
};
