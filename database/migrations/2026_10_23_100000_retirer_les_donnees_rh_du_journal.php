<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * L'enregistrement d'une fiche chauffeur recopiait au journal tout ce qui
 * etait saisi (date de naissance, entree en service, retraite prevue,
 * motif de sortie), et le depart citait son motif dans la phrase :
 * « Depart de X (Inaptitude medicale) », une donnee de sante. De meme,
 * une indisponibilite de chauffeur citait son motif : « ... : maladie du
 * 05/10/2026 au 09/10/2026 ». Le journal n'en garde plus rien ; les
 * lignes deja ecrites sont nettoyees de la meme facon. Rejouee, la
 * migration ne change plus rien.
 */
return new class extends Migration
{
    /** Les colonnes de Driver::DONNEES_RH, figees a la date de la migration. */
    private const CHAMPS = ['license_number', 'hired_on', 'birth_date', 'retirement_planned_on', 'departure_reason'];

    /** Les libelles de Driver::MOTIFS_SORTIE, et le tiret ecrit sans motif. */
    private const MOTIFS = ['Retraite', 'Démission', 'Licenciement', 'Inaptitude médicale', 'Déchéance du permis', '—'];

    public function up(): void
    {
        DB::table('activity_logs')
            ->whereIn('action', ['driver.updated', 'driver.left'])
            ->lazyById()
            ->each(function (object $ligne) {
                $proprietes = json_decode((string) $ligne->properties, true);
                $nettoyees = is_array($proprietes)
                    ? array_diff_key($proprietes, array_flip(self::CHAMPS))
                    : $proprietes;

                $description = (string) $ligne->description;

                if ($ligne->action === 'driver.left') {
                    foreach (self::MOTIFS as $motif) {
                        if (str_ends_with($description, ' ('.$motif.')')) {
                            $description = mb_substr($description, 0, -mb_strlen(' ('.$motif.')'));

                            break;
                        }
                    }
                }

                if ($nettoyees === $proprietes && $description === (string) $ligne->description) {
                    return;
                }

                DB::table('activity_logs')->where('id', $ligne->id)->update([
                    'properties' => $nettoyees === null || $nettoyees === [] ? null : json_encode($nettoyees),
                    'description' => $description,
                ]);
            });

        // « Indisponibilite enregistree (chauffeur X) : <resume> » : le
        // resume etait ecrit dans la langue de qui saisissait la periode
        // (« ziekte van ... tot ... », « sick leave from ... to ... »).
        // La periode est donc reecrite a partir de ses deux dates, quel
        // que soit le libelle ; sans elles (phrase tronquee), seule la
        // partie avant le motif reste.
        DB::table('activity_logs')
            ->whereIn('action', ['unavailability.created', 'unavailability.removed'])
            ->where('subject_type', 'Driver')
            ->lazyById()
            ->each(function (object $ligne) {
                $description = (string) $ligne->description;

                if (! preg_match('/^(.*\)) : (.*)$/su', $description, $parties)) {
                    return;
                }

                preg_match_all('#\d{2}/\d{2}/\d{4}#', $parties[2], $dates);
                $nettoyee = count($dates[0]) === 2
                    ? $parties[1].' : du '.$dates[0][0].' au '.$dates[0][1]
                    : $parties[1];

                if ($nettoyee !== $description) {
                    DB::table('activity_logs')->where('id', $ligne->id)->update(['description' => $nettoyee]);
                }
            });
    }

    public function down(): void
    {
        // Les donnees retirees ne sont gardees nulle part : il n'y a rien
        // a restaurer.
    }
};
