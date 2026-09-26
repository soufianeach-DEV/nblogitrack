<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une demande de devis complete : de quoi chiffrer sans rappeler le
 * client pour chaque detail (societe et facturation, contacts sur place,
 * horaires et acces, colis, temperature, ADR, pieces jointes), et de quoi
 * la transformer en commande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            // Societe et facturation
            $table->string('legal_form', 100)->nullable();
            $table->string('sector', 100)->nullable();
            $table->string('eori_number', 17)->nullable();
            $table->string('billing_street', 255)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_city', 120)->nullable();
            $table->char('billing_country', 2)->nullable();
            $table->char('correspondence_language', 2)->default('fr');

            // Contact
            $table->string('contact_function', 100)->nullable();
            $table->string('mobile_phone', 30)->nullable();
            $table->string('billing_email', 150)->nullable();
            $table->string('preferred_channel', 10)->default('email');
            $table->string('callback_slot', 20)->nullable();

            // Enlevement et livraison sur place
            foreach (['pickup', 'delivery'] as $lieu) {
                $table->string($lieu.'_contact_name', 150)->nullable();
                $table->string($lieu.'_contact_phone', 30)->nullable();
                $table->string($lieu.'_opening_hours', 100)->nullable();
                $table->string($lieu.'_time_slot', 20)->nullable();
                $table->boolean($lieu.'_has_dock')->nullable();
                $table->boolean($lieu.'_appointment')->default(false);
                $table->json($lieu.'_access')->nullable();
                $table->string($lieu.'_access_notes', 255)->nullable();
            }

            $table->date('delivery_date')->nullable();

            // Marchandise
            $table->json('packages')->nullable();
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->boolean('needs_temperature')->default(false);
            $table->decimal('temperature_min', 4, 1)->nullable();
            $table->decimal('temperature_max', 4, 1)->nullable();
            $table->string('un_number', 4)->nullable();
            $table->string('adr_class', 5)->nullable();
            $table->string('packing_group', 3)->nullable();

            // Commercial
            $table->string('monthly_volume', 40)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->date('response_deadline')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->foreignId('converted_order_id')->nullable()->constrained('transport_orders')->nullOnDelete();
        });

        DB::statement('ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_temperature CHECK (temperature_min IS NULL OR temperature_max IS NULL OR temperature_min <= temperature_max)');
        DB::statement("ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_numero_onu CHECK (un_number IS NULL OR un_number ~ '^[0-9]{4}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quote_requests DROP CONSTRAINT IF EXISTS quote_requests_temperature');
        DB::statement('ALTER TABLE quote_requests DROP CONSTRAINT IF EXISTS quote_requests_numero_onu');

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_order_id');

            $colonnes = ['legal_form', 'sector', 'eori_number', 'billing_street', 'billing_postal_code', 'billing_city',
                'billing_country', 'correspondence_language', 'contact_function', 'mobile_phone', 'billing_email',
                'preferred_channel', 'callback_slot', 'delivery_date', 'packages', 'declared_value', 'needs_temperature',
                'temperature_min', 'temperature_max', 'un_number', 'adr_class', 'packing_group', 'monthly_volume',
                'budget', 'response_deadline', 'attachments', 'privacy_accepted_at'];

            foreach (['pickup', 'delivery'] as $lieu) {
                foreach (['contact_name', 'contact_phone', 'opening_hours', 'time_slot', 'has_dock', 'appointment', 'access', 'access_notes'] as $champ) {
                    $colonnes[] = $lieu.'_'.$champ;
                }
            }

            $table->dropColumn($colonnes);
        });
    }
};
