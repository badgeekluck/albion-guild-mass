<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Page views and actions of logged-in users (admin activity screen). Kept 30 days, no IP addresses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 8);
            $table->string('route', 80)->nullable();
            $table->string('path', 255);
            $table->unsignedBigInteger('shared_link_id')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->boolean('is_mobile')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
