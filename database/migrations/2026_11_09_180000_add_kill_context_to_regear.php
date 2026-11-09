<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telling event deaths from unrelated ones (open world ganks):
 * - only battles of at least N players are scanned (default: the party's slot count);
 * - who killed the player (guild / alliance / weapon) is stored to spot kills by
 *   someone other than the main enemy, guildless killers or gank weapons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->unsignedSmallInteger('regear_min_battle_size')->nullable()->after('regear_rules');
        });

        Schema::table('regear_claims', function (Blueprint $table) {
            $table->string('killer_guild_id', 50)->nullable()->after('killer_guild');
            $table->string('killer_alliance', 100)->nullable()->after('killer_guild_id');
            $table->string('killer_alliance_id', 50)->nullable()->after('killer_alliance');
            $table->string('killer_weapon', 80)->nullable()->after('killer_alliance_id');
            $table->unsignedSmallInteger('battle_size')->nullable()->after('battle_id');
        });
    }

    public function down(): void
    {
        Schema::table('regear_claims', fn (Blueprint $table) => $table->dropColumn(['killer_guild_id', 'killer_alliance', 'killer_alliance_id', 'killer_weapon', 'battle_size']));
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn('regear_min_battle_size'));
    }
};
