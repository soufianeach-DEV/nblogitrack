<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();

            $table->string('titre_fr');
            $table->string('titre_nl')->nullable();
            $table->string('titre_en')->nullable();

            $table->text('corps_fr');
            $table->text('corps_nl')->nullable();
            $table->text('corps_en')->nullable();

            $table->boolean('publiee')->default(false);
            $table->timestamp('publiee_le')->nullable();

            $table->boolean('au_pied')->default(false);
            $table->unsignedSmallInteger('rang')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('page_documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');

            $table->string('nom_origine');
            $table->string('chemin');
            $table->string('mime', 100);
            $table->unsignedBigInteger('taille');

            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_documents');
        Schema::dropIfExists('pages');
    }
};
