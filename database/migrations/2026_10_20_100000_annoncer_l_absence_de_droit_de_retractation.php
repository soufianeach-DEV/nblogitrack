<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Les conditions generales annoncent desormais l'absence de droit de
 * retractation (article 8 ter) : la clientele est exclusivement
 * professionnelle. Les conditions deja en base recoivent l'article juste
 * apres l'article 8 bis.
 */
return new class extends Migration
{
    private const AJOUTS = [
        'corps_fr' => ["Une fois la marchandise chargée, l'expédition ne peut plus être annulée en ligne. Un retour ou un déroutement se traite alors par écrit et donne lieu à facturation des prestations effectuées.", "\n\n## Article 8 ter — Droit de rétractation\nLes services du transporteur sont réservés aux entreprises. Le droit de rétractation prévu par les articles VI.47 et suivants du Code de droit économique protège les consommateurs ; il ne s'applique pas aux commandes passées par une entreprise pour les besoins de son activité.\n\nLe donneur d'ordre conserve la faculté d'annuler une expédition dans les conditions de l'article 8 bis."],
        'corps_nl' => ['Zodra de goederen geladen zijn, kan de zending niet meer online geannuleerd worden. Een terugzending of omleiding wordt dan schriftelijk geregeld en de uitgevoerde prestaties worden gefactureerd.', "\n\n## Artikel 8 ter — Herroepingsrecht\nDe diensten van de vervoerder zijn voorbehouden aan ondernemingen. Het herroepingsrecht van de artikelen VI.47 en volgende van het Wetboek van economisch recht beschermt consumenten; het geldt niet voor bestellingen die een onderneming plaatst voor haar beroepsactiviteit.\n\nDe opdrachtgever behoudt de mogelijkheid om een zending te annuleren onder de voorwaarden van artikel 8 bis."],
        'corps_en' => ['Once the goods have been loaded, the shipment can no longer be cancelled online. A return or diversion is then handled in writing and the services performed are invoiced.', "\n\n## Article 8b — Right of withdrawal\nThe carrier's services are reserved for businesses. The right of withdrawal under Articles VI.47 et seq. of the Belgian Code of Economic Law protects consumers; it does not apply to orders placed by a business for the purposes of its activity.\n\nThe customer retains the option to cancel a shipment under the conditions of Article 8a."],
    ];

    public function up(): void
    {
        foreach (self::AJOUTS as $colonne => [$ancre, $article]) {
            $page = DB::table('pages')->where('slug', 'conditions-generales')->first();

            if ($page === null || ! str_contains((string) $page->{$colonne}, $ancre) || str_contains((string) $page->{$colonne}, trim(strtok(ltrim($article), "\n")))) {
                continue;
            }

            DB::table('pages')->where('id', $page->id)->update([
                $colonne => str_replace($ancre, $ancre.$article, $page->{$colonne}),
                'contenu_modifie_le' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::AJOUTS as $colonne => [, $article]) {
            $page = DB::table('pages')->where('slug', 'conditions-generales')->first();

            if ($page !== null) {
                DB::table('pages')->where('id', $page->id)->update([$colonne => str_replace($article, '', $page->{$colonne})]);
            }
        }
    }
};
