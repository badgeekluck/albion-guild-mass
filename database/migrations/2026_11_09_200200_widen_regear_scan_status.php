<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scan status can carry warnings (e.g. the killboard page limit was hit). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', fn (Blueprint $table) => $table->text('regear_scan_status')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('shared_links', fn (Blueprint $table) => $table->string('regear_scan_status', 255)->nullable()->change());
    }
};
