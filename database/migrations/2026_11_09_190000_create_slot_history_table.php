<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sat in which slot, and when. Regear matches a death to the slot the player held
 * at that moment, even if they were moved, benched, kicked or left the party afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_attendee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('in_game_name', 50)->nullable();
            $table->unsignedSmallInteger('slot_index');
            $table->string('claim_status', 12)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->index(['shared_link_id', 'in_game_name']);
        });

        Schema::table('regear_claims', function (Blueprint $table) {
            // Slot held at the moment of death (from slot_history).
            $table->unsignedSmallInteger('slot_index')->nullable()->after('link_attendee_id');
        });
    }

    public function down(): void
    {
        Schema::table('regear_claims', fn (Blueprint $table) => $table->dropColumn('slot_index'));
        Schema::dropIfExists('slot_history');
    }
};
