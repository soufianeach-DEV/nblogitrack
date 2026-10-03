<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une entreprise cliente demande un acces a l'API ; l'administration
        // l'accorde ou le refuse.
        Schema::create('api_key_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('abilities');
            $table->json('allowed_ips')->nullable();
            $table->string('message', 500)->nullable();

            $table->string('status', 10)->default('PENDING');
            $table->string('refusal_reason', 300)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            // La cle accordee, chiffree, jusqu'a ce que le client l'affiche
            // une fois dans son espace : elle ne passe jamais par un e-mail.
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->text('key_ciphertext')->nullable();
            $table->timestamp('revealed_at')->nullable();

            $table->timestamps();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_requests');
    }
};
