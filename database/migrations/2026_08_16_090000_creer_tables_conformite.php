<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_acknowledgements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('version');
            $table->timestamp('acknowledged_at');

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'version']);
        });

        Schema::create('processing_records', function (Blueprint $table) {
            $table->id();

            $table->string('nom');
            $table->unsignedSmallInteger('rang')->default(0);

            $table->text('finalite');
            $table->string('base_legale');
            $table->text('personnes');
            $table->text('donnees');
            $table->text('destinataires');
            $table->text('conservation');
            $table->text('mesures');

            $table->text('transferts')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_records');
        Schema::dropIfExists('driver_acknowledgements');
    }
};
