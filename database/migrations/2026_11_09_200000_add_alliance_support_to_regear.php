<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alliance CTAs (optional, per party): members of the other guilds of our alliance
 * can join, pass the IGN check and get their deaths into regear.
 * The victim's guild / alliance is stored so our own side is never counted as an enemy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->boolean('regear_include_alliance')->default(false)->after('regear_min_battle_size');
        });

        Schema::table('regear_claims', function (Blueprint $table) {
            $table->string('victim_guild_id', 50)->nullable()->after('victim_albion_id');
            $table->string('victim_guild', 100)->nullable()->after('victim_guild_id');
            $table->string('victim_alliance_id', 50)->nullable()->after('victim_guild');
        });
    }

    public function down(): void
    {
        Schema::table('regear_claims', fn (Blueprint $table) => $table->dropColumn(['victim_guild_id', 'victim_guild', 'victim_alliance_id']));
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn('regear_include_alliance'));
    }
};
