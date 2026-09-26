<?php

use App\Support\Pays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Le socle de la facturation :
     *
     * - la facture fige l'identite de l'acheteur a l'emission : si
     *   l'entreprise change d'adresse ou de nom, ses anciennes factures ne
     *   changent pas (obligation de conservation, art. 60 du Code TVA) ;
     * - chaque ligne porte sa nature, sa quantite, son prix unitaire et sa
     *   categorie de TVA (S, AE, O), et n'est plus forcement liee a une
     *   expedition ;
     * - un avoir est une facture de type CREDIT_NOTE qui renvoie a la
     *   facture qu'il annule ;
     * - les paiements ont leur propre table : un reglement partiel, un
     *   virement et un paiement en ligne se cumulent ;
     * - les supplements (attente, manutention, peage exceptionnel) se
     *   posent sur une expedition et partent sur la facture suivante.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type', 12)->default('INVOICE')->after('reference');
            $table->foreignId('credited_invoice_id')->nullable()->after('type')
                ->constrained('invoices')->restrictOnDelete();
            $table->text('credit_reason')->nullable()->after('credited_invoice_id');
            $table->string('vat_category', 2)->default('S')->after('vat_rate');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_vat_number', 30)->nullable();
            $table->string('buyer_peppol_id', 60)->nullable();
            $table->string('buyer_address')->nullable();
            $table->string('buyer_postal_code', 20)->nullable();
            $table->string('buyer_city', 120)->nullable();
            $table->string('buyer_country', 2)->nullable();
        });

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
            CHECK (status IN ('DRAFT', 'SENT', 'PAID', 'OVERDUE', 'CREDITED'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_type_check
            CHECK (type IN ('INVOICE', 'CREDIT_NOTE'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_vat_category_check
            CHECK (vat_category IN ('S', 'AE', 'O', 'Z', 'E'))");

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('kind', 20)->default('TRANSPORT')->after('invoice_id');
            $table->decimal('quantity', 10, 2)->default(1)->after('description');
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
            $table->string('vat_category', 2)->default('S')->after('amount_excl_tax');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('vat_category');
            // Une ligne annulee par un avoir « avec refacturation » libere
            // son expedition, qui repart sur une nouvelle facture.
            $table->boolean('active')->default(true);
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropUnique(['transport_order_id']);
            $table->foreignId('transport_order_id')->nullable()->change();
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_order_id')->constrained('transport_orders')->restrictOnDelete();
            $table->string('label', 200);
            $table->decimal('amount', 10, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('order_charge_id')->nullable()->after('transport_order_id')
                ->constrained('order_charges')->restrictOnDelete();
        });

        // Une expedition, ou un supplement, n'est facture qu'une fois a la
        // fois : l'unicite ne porte que sur les lignes actives.
        DB::statement("CREATE UNIQUE INDEX invoice_lines_ordre_actif ON invoice_lines (transport_order_id) WHERE active AND kind IN ('TRANSPORT', 'CANCELLATION')");
        DB::statement('CREATE UNIQUE INDEX invoice_lines_supplement_actif ON invoice_lines (order_charge_id) WHERE active AND order_charge_id IS NOT NULL');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 120)->nullable()->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('invoice_id');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE order_charges ADD CONSTRAINT order_charges_amount_check CHECK (amount > 0)');

        $this->reprendreLesFacturesExistantes();
    }

    private function reprendreLesFacturesExistantes(): void
    {
        DB::table('invoice_lines')
            ->where('description', 'like', 'Indemnité d%annulation %')
            ->update(['kind' => 'CANCELLATION']);
        DB::table('invoice_lines')->update(['unit_price' => DB::raw('amount_excl_tax')]);

        $clients = DB::table('clients')->get()->keyBy('id');

        foreach (DB::table('invoices')->get() as $facture) {
            $client = $clients->get($facture->client_id);
            $pays = $client === null ? null
                : (Pays::depuisNom($client->country) ?? strtoupper(substr((string) $client->vat_number, 0, 2)));
            $categorie = ! $facture->reverse_charge ? 'S'
                : ($client !== null && Pays::horsUnion($client->country) ? 'O' : 'AE');

            DB::table('invoices')->where('id', $facture->id)->update([
                'vat_category' => $categorie,
                'buyer_name' => $client?->company_name,
                'buyer_vat_number' => $client?->vat_number,
                'buyer_peppol_id' => $client?->peppol_id,
                'buyer_address' => $client?->billing_address,
                'buyer_postal_code' => $client?->postal_code,
                'buyer_city' => $client?->city,
                'buyer_country' => $pays !== null && strlen($pays) === 2 ? $pays : null,
            ]);

            DB::table('invoice_lines')->where('invoice_id', $facture->id)->update([
                'vat_category' => $categorie,
                'vat_rate' => $facture->vat_rate,
            ]);

            if ($facture->status === 'PAID') {
                DB::table('payments')->insert([
                    'invoice_id' => $facture->id,
                    'amount' => $facture->amount_incl_tax,
                    'paid_on' => $facture->paid_on ?? $facture->updated_at,
                    'method' => 'OTHER',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_type_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_vat_category_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
            CHECK (status IN ('DRAFT', 'SENT', 'PAID', 'OVERDUE'))");
        DB::statement('DROP INDEX IF EXISTS invoice_lines_ordre_actif');
        DB::statement('DROP INDEX IF EXISTS invoice_lines_supplement_actif');
        Schema::dropIfExists('payments');

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_charge_id');
            $table->dropColumn(['kind', 'quantity', 'unit_price', 'vat_category', 'vat_rate', 'active']);
        });

        Schema::dropIfExists('order_charges');

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->unique('transport_order_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credited_invoice_id');
            $table->dropColumn([
                'type', 'credit_reason', 'vat_category', 'buyer_name', 'buyer_vat_number', 'buyer_peppol_id',
                'buyer_address', 'buyer_postal_code', 'buyer_city', 'buyer_country',
            ]);
        });
    }
};
