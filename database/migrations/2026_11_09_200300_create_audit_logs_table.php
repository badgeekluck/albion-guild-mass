<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who did what: caller actions on parties, regear decisions and settings changes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Kept after a party is deleted (no cascade): the log is the record of what happened.
            $table->unsignedBigInteger('shared_link_id')->nullable()->index();
            $table->string('action', 40);
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
