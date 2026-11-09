<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service slots: a player "claims" an open slot; in CTA parties the caller confirms it.
 * null = waitlist, 'claimed' = waiting for the caller, 'confirmed' = placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_attendees', function (Blueprint $table) {
            $table->string('claim_status', 12)->nullable()->after('slot_index');
        });

        // Everyone already in a slot was placed by a caller.
        DB::table('link_attendees')->whereNotNull('slot_index')->update(['claim_status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('link_attendees', fn (Blueprint $table) => $table->dropColumn('claim_status'));
    }
};
