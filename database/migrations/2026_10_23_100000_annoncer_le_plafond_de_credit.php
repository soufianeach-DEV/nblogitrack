<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * L'article 9 des conditions generales annonce le delai de paiement et le
 * plafond de credit propres a chaque client, et le blocage des nouvelles
 * commandes a trois factures en retard ou au-dela du plafond. Les
 * conditions deja en base recoivent le paragraphe a la fin de l'article.
 */
return new class extends Migration
{
    private const AJOUTS = [
        'corps_fr' => ['Le non-paiement d\'une facture à son échéance rend immédiatement exigibles toutes les autres factures, même non échues, et autorise le transporteur à suspendre les prestations en cours après notification écrite.', "\n\nLe transporteur peut fixer à chaque donneur d'ordre un délai de paiement et un plafond de crédit, qui borne le total des factures non réglées et des expéditions pas encore facturées. Tant que ce plafond serait dépassé, ou que trois factures restent impayées après leur échéance, aucune nouvelle expédition ne peut être commandée ; les expéditions déjà confiées ne sont pas remises en cause par ce seul fait."],
        'corps_nl' => ['De niet-betaling van één factuur op haar vervaldag maakt alle andere facturen onmiddellijk opeisbaar, ook de niet-vervallen, en machtigt de vervoerder om lopende prestaties op te schorten na schriftelijke kennisgeving.', "\n\nDe vervoerder kan voor elke opdrachtgever een betalingstermijn en een kredietlimiet vastleggen; die limiet begrenst het totaal van de onbetaalde facturen en de nog niet gefactureerde zendingen. Zolang die limiet overschreden zou worden, of zolang drie facturen na hun vervaldag onbetaald blijven, kan geen nieuwe zending besteld worden; de reeds toevertrouwde zendingen komen daardoor alleen niet in het gedrang."],
        'corps_en' => ['Non-payment of one invoice on its due date makes all other invoices immediately payable, including those not yet due, and entitles the carrier to suspend ongoing services after written notice.', "\n\nThe carrier may set a payment term and a credit limit for each customer; the limit caps the total of unpaid invoices and shipments not yet invoiced. As long as that limit would be exceeded, or three invoices remain unpaid after their due date, no new shipment can be ordered; shipments already entrusted are not called into question by this alone."],
    ];

    public function up(): void
    {
        foreach (self::AJOUTS as $colonne => [$ancre, $paragraphe]) {
            $page = DB::table('pages')->where('slug', 'conditions-generales')->first();

            if ($page === null || ! str_contains((string) $page->{$colonne}, $ancre) || str_contains((string) $page->{$colonne}, trim($paragraphe))) {
                continue;
            }

            DB::table('pages')->where('id', $page->id)->update([
                $colonne => str_replace($ancre, $ancre.$paragraphe, $page->{$colonne}),
                'contenu_modifie_le' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::AJOUTS as $colonne => [, $paragraphe]) {
            $page = DB::table('pages')->where('slug', 'conditions-generales')->first();

            if ($page !== null) {
                DB::table('pages')->where('id', $page->id)->update([$colonne => str_replace($paragraphe, '', $page->{$colonne})]);
            }
        }
    }
};
