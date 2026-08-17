<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();

            $table->string('cle')->unique();
            $table->string('groupe')->index();

            $table->text('fr');
            $table->text('nl')->nullable();
            $table->text('en')->nullable();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('fr')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::dropIfExists('translations');
    }
};
