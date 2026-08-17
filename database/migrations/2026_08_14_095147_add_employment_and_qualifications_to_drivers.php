<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('employment_status', 20)->default('OUVRIER')->after('id');
            $table->date('hired_on')->nullable()->after('employment_status');

            $table->date('birth_date')->nullable()->after('hired_on');
            $table->date('retirement_planned_on')->nullable()->after('birth_date');

            $table->date('cpc_expiry')->nullable()->after('license_expiry');
            $table->date('tacho_card_expiry')->nullable()->after('cpc_expiry');

            $table->date('left_on')->nullable();
            $table->string('departure_reason', 20)->nullable();

            $table->index('left_on');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['left_on']);
            $table->dropColumn([
                'employment_status', 'hired_on', 'birth_date', 'retirement_planned_on',
                'cpc_expiry', 'tacho_card_expiry', 'left_on', 'departure_reason',
            ]);
        });
    }
};
