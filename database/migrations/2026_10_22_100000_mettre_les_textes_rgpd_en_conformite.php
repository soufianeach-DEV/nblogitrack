<?php

use Database\Seeders\PageSeeder;
use Database\Seeders\ProcessingRecordSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Textes RGPD alignes sur ce que fait l'application et sur la loi :
 * hebergeur nomme dans les mentions legales ; destinataires reels (Render,
 * Supabase, Brevo, services de cartes), transferts, durees de conservation
 * appliquees (pieces sept ans a compter du 1er janvier qui suit leur annee,
 * jalons un an, conducteurs partis un an) dans la politique de
 * confidentialite ; noms exacts des cookies et stockage local du brouillon
 * de devis ; position d'un incident et ce que voit le client dans la note
 * aux conducteurs ; registre des traitements complete (dix traitements).
 *
 * Seuls les textes encore identiques a la version precedente du seeder sont
 * remplaces : une page ou un traitement retouche depuis l'interface garde
 * le texte de l'administrateur.
 */
return new class extends Migration
{
    /** Page, colonne, empreinte SHA-1 du texte precedent. */
    private const PAGES = [
        ['mentions-legales', 'corps_fr', '0ddc00b15f7a43c405ec96eccd02b465615e5eb9'],
        ['mentions-legales', 'corps_nl', 'a5f960d4057796b991d8582f7feef3335ec09cf8'],
        ['mentions-legales', 'corps_en', 'c488977c655737c5dcf150d1a81d95a416e78a9b'],
        ['confidentialite', 'corps_fr', '058310a0fa94dbc778f1080ec05eb4fd76249bbb'],
        ['confidentialite', 'corps_nl', '98ce8910da13c45de754b08d8c1fc33654f6b2cf'],
        ['confidentialite', 'corps_en', 'b7f72c7d08eafdf560238d9ee39c0706a8d5401b'],
        ['politique-cookies', 'corps_fr', '6757468d57268ccf4085985615c43f5700d21c16'],
        ['politique-cookies', 'corps_nl', 'e7054f750dad4cab4c54604cff8b072156908afc'],
        ['politique-cookies', 'corps_en', '3dbea7b5303598c9d724ced6b6890bffc81c68ac'],
        ['information-chauffeurs', 'corps_fr', '3f57db9e87b46960a2f381b229b785231148d037'],
        ['information-chauffeurs', 'corps_nl', '6dc2ca8b0b87ebf3538e65f8a25d33f3844a56b6'],
        ['information-chauffeurs', 'corps_en', 'f71edd284d4ee4622bc42d2cff5f97495a7e7302'],
    ];

    /** Traitement, empreinte SHA-1 de ses champs dans la version precedente. */
    private const TRAITEMENTS = [
        'Gestion des comptes clients' => 'f13fe0531ae24005a2b79bb8718bd8d09e7c1f49',
        'Exécution des ordres de transport' => '53da7232e8b22d5e3d704ca547dd4568f0994dfd',
        'Suivi de position des envois' => '8491ae0ef73946b4a9cfd03e812b3114b69bb747',
        'Gestion du personnel roulant' => 'a34a1c6eaa8c81fcdc0a0785ffe403a47230cd36',
        'Facturation et recouvrement' => 'a03c1288eb667176714f62e1e276fd25cd0997c7',
        'Demandes de devis' => '1587c1cdb365968adf0afd62af073b8e3a404253',
        'Journal d\'activité et sécurité' => 'daf33f28f4a9a71c29b38a6dfd09949ed079233a',
        'Accès à l\'API des partenaires' => 'af12acad15d1e2f925f02960e7b6a8f9f7d13416',
        'Mesure d\'audience du site public' => '2f10d86890203b42ca440523ad7674e334eb35ec',
    ];

    private const CHAMPS = ['finalite', 'base_legale', 'personnes', 'donnees', 'destinataires', 'conservation', 'mesures', 'transferts'];

    public function up(): void
    {
        $pages = collect((fn () => $this->pages())->call(new PageSeeder))->keyBy('slug');

        foreach (self::PAGES as [$slug, $colonne, $empreinte]) {
            $page = DB::table('pages')->where('slug', $slug)->first();

            if ($page === null || sha1((string) $page->{$colonne}) !== $empreinte) {
                continue;
            }

            DB::table('pages')->where('id', $page->id)->update([
                $colonne => $pages[$slug][$colonne],
                'contenu_modifie_le' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('processing_records')->count() === 0) {
            return;
        }

        foreach ((fn () => $this->traitements())->call(new ProcessingRecordSeeder) as $rang => $traitement) {
            $actuel = DB::table('processing_records')->where('nom', $traitement['nom'])->first();
            $valeurs = array_intersect_key($traitement, array_flip(self::CHAMPS));

            if ($actuel === null) {
                DB::table('processing_records')->insert([
                    ...$traitement,
                    'rang' => $rang,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            $empreinte = sha1(implode("\x1f", array_map(fn ($champ) => (string) $actuel->{$champ}, self::CHAMPS)));

            if ($empreinte === (self::TRAITEMENTS[$traitement['nom']] ?? null)) {
                DB::table('processing_records')->where('id', $actuel->id)->update([...$valeurs, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Textes juridiques : pas de retour en arriere automatique.
    }
};
