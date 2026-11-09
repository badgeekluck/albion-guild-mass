<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concepts taken from the guild's composition spreadsheets:
 * - builds: battle mount, ability picks (Q1 W4...), situational swaps / alternatives
 * - templates & parties: a briefing ("TANKS -> MIN T7 SIEGE HAMMER", "TAKE ALL SWAPS!")
 * - parties: the caller's current enemy situation (kite, monkey, clap...)
 * - registrations: a short player note ("can swap", "can fill carving")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_builds', function (Blueprint $table) {
            $table->unsignedBigInteger('mount_id')->nullable()->after('potion_id');
            $table->string('abilities', 100)->nullable()->after('role_category');
            $table->json('swaps')->nullable()->after('notes');
            // Stable identity of builds created by the spreadsheet importer (re-imports update in place).
            $table->string('import_key', 64)->nullable()->unique()->after('id');
        });

        Schema::table('party_templates', function (Blueprint $table) {
            $table->text('briefing')->nullable()->after('size');
        });

        Schema::table('shared_links', function (Blueprint $table) {
            $table->text('briefing')->nullable()->after('title');
            $table->string('situation', 30)->nullable()->after('status');
        });

        Schema::table('link_attendees', function (Blueprint $table) {
            $table->string('note', 100)->nullable()->after('in_game_name');
        });
    }

    public function down(): void
    {
        Schema::table('link_attendees', fn (Blueprint $table) => $table->dropColumn('note'));
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn(['briefing', 'situation']));
        Schema::table('party_templates', fn (Blueprint $table) => $table->dropColumn('briefing'));
        Schema::table('saved_builds', fn (Blueprint $table) => $table->dropColumn(['mount_id', 'abilities', 'swaps', 'import_key']));
    }
};
