<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regear results sent to players by Discord DM:
 * - reject_reason: optional note from the caller, shown to the player;
 * - notified_state: the decision last sent (so only new / changed decisions are sent again);
 * - paid_notified_at: the "your regear was paid" DM went out;
 * - notify_error: dm_closed | no_discord | failed (shown on the review screen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regear_claims', function (Blueprint $table) {
            $table->string('reject_reason', 200)->nullable()->after('amount');
            $table->string('notified_state', 64)->nullable()->after('paid_at');
            $table->timestamp('notified_at')->nullable()->after('notified_state');
            $table->timestamp('paid_notified_at')->nullable()->after('notified_at');
            $table->string('notify_error', 20)->nullable()->after('paid_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('regear_claims', fn (Blueprint $table) => $table->dropColumn(['reject_reason', 'notified_state', 'notified_at', 'paid_notified_at', 'notify_error']));
    }
};
