<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our players seen in the event's battles (killboard), saved by the regear scan:
 * lowercase name => [name, battles, kills, deaths]. Used as attendance evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->json('battle_players')->nullable()->after('regear_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn('battle_players'));
    }
};
