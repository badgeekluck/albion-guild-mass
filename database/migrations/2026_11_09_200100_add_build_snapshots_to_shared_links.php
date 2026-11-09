<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builds as they were at a finished event: when a build used by an event that is
 * already over gets edited or deleted, its previous version is kept on that party,
 * so the old roster and regear decisions don't change afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->json('build_snapshots')->nullable()->after('template_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn('build_snapshots'));
    }
};
