<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regear: deaths of roster players during an event are pulled from the Albion killboard,
 * compared with the build they should have worn, and paid out after the caller's review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('expires_at');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->json('regear_rules')->nullable()->after('ends_at');
            $table->string('regear_scan_status', 255)->nullable()->after('regear_rules');
            $table->dateTime('regear_scanned_at')->nullable()->after('regear_scan_status');
        });

        Schema::create('regear_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_attendee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('battle_id')->nullable();
            $table->string('victim_name', 50);
            $table->string('victim_albion_id', 50)->nullable();
            $table->dateTime('died_at');
            $table->string('killer_name', 50)->nullable();
            $table->string('killer_guild', 100)->nullable();
            $table->unsignedInteger('item_power')->nullable();
            $table->json('equipment');
            $table->json('checks')->nullable();
            $table->json('warnings')->nullable();
            $table->string('role_type', 10)->nullable();
            $table->unsignedBigInteger('suggested_amount')->default(0);
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['shared_link_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regear_claims');
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropColumn(['starts_at', 'ends_at', 'regear_rules', 'regear_scan_status', 'regear_scanned_at']));
    }
};
