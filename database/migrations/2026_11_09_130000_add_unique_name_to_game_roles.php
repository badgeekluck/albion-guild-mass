<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Albion's unique item ID (e.g. T8_MAIN_MACE). The render API resolves these reliably,
 * while lookups by localized name ("Elder's Mace") are slow and intermittently fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_roles', function (Blueprint $table) {
            $table->string('unique_name', 80)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('game_roles', fn (Blueprint $table) => $table->dropColumn('unique_name'));
    }
};
