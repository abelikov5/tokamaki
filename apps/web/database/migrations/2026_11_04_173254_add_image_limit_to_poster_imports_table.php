<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->unsignedTinyInteger('image_limit')->default(3)->after('only_new');
        });
    }

    public function down(): void
    {
        Schema::table('poster_imports', function (Blueprint $table): void {
            $table->dropColumn('image_limit');
        });
    }
};
