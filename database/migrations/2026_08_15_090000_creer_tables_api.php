<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->string('prefix', 12)->unique();
            $table->string('token_hash', 64);

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->json('abilities');

            $table->json('allowed_ips')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('requests_count')->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->cascadeOnDelete();

            $table->string('method', 8);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('refus')->nullable();

            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
        Schema::dropIfExists('api_keys');
    }
};
