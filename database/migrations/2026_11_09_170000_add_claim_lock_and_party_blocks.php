<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - claim_locked: the caller benched / rejected this player; they can't self-claim
 *   (or self-move) until the caller places them again.
 * - blocked_user_ids: players kicked from the party can't sign up to it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_attendees', function (Blueprint $table) {
            $table->boolean('claim_locked')->default(false)->after('claim_status');
        });

        Schema::table('shared_links', function (Blueprint $table) {
            $table->json('blocked_user_ids')->nullable()->after('situation');
        });
    }

    public function down(): void
    {
        Schema::table('link_attendees', fn (Blueprint $table) => $table->dropColumn('claim_locked'));
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn('blocked_user_ids'));
    }
};
