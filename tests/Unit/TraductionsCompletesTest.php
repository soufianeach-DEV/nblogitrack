<?php

namespace Tests\Unit;

use Database\Seeders\TranslationSeeder;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClassConstant;

/**
 * Aucune phrase de l'interface ne doit rester sans traduction. Ce test lit
 * le code, releve chaque cle demandee, et exige qu'elle existe dans le
 * dictionnaire en francais, en neerlandais et en anglais. Une cle ajoutee
 * sans ses traductions fait echouer l'integration continue.
 */
class TraductionsCompletesTest extends TestCase
{
    private const LANGUES = ['fr', 'nl', 'en'];

    /** @return array<string, array{string, string, string}> */
    private function dictionnaire(): array
    {
        $textes = (new ReflectionClassConstant(TranslationSeeder::class, 'TEXTES'))->getValue();
        $cles = [];

        foreach ($textes as $groupe => $entrees) {
            foreach ($entrees as $cle => $traductions) {
                $cles[$groupe.'.'.$cle] = $traductions;
            }
        }

        return $cles;
    }

    /**
     * @param  list<string>  $dossiers
     * @return array<string, list<string>> cle => fichiers qui la demandent
     */
    private function clesDemandees(array $dossiers, string $extensions, string $motif): array
    {
        $racine = dirname(__DIR__, 2);
        $trouvees = [];

        foreach ($dossiers as $dossier) {
            $fichiers = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($racine.'/'.$dossier, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($fichiers as $fichier) {
                if (! preg_match($extensions, $fichier->getFilename())) {
                    continue;
                }

                preg_match_all($motif, (string) file_get_contents($fichier->getPathname()), $correspondances);

                foreach ($correspondances[1] as $cle) {
                    $trouvees[$cle][] = str_replace($racine.'/', '', $fichier->getPathname());
                }
            }
        }

        return $trouvees;
    }

    public function test_chaque_cle_de_l_interface_est_traduite_dans_les_trois_langues(): void
    {
        $demandees = $this->clesDemandees(
            ['resources/js'],
            '/\.jsx?$/',
            "/\\bt\\(\\s*'([a-z0-9_]+\\.[a-z0-9_.]*[a-z0-9])'/",
        ) + $this->clesDemandees(
            ['app', 'resources/views'],
            '/\.php$/',
            "/(?:Traductions|\\\$t)::t\\(\\s*'([a-z0-9_]+\\.[a-z0-9_.]*[a-z0-9])'/",
        );

        $this->assertNotEmpty($demandees);

        $dictionnaire = $this->dictionnaire();
        $manques = [];

        foreach ($demandees as $cle => $fichiers) {
            $traductions = $dictionnaire[$cle] ?? null;

            if ($traductions === null) {
                $manques[] = $cle.' (absente, demandee par '.implode(', ', array_unique($fichiers)).')';

                continue;
            }

            foreach (self::LANGUES as $rang => $langue) {
                if (trim((string) ($traductions[$rang] ?? '')) === '') {
                    $manques[] = $cle.' (vide en '.$langue.')';
                }
            }
        }

        $this->assertSame([], $manques, "Cles sans traduction complete :\n".implode("\n", $manques));
    }

    public function test_les_fichiers_de_langue_du_cadriciel_ont_les_memes_cles(): void
    {
        $racine = dirname(__DIR__, 2).'/lang';

        foreach (['auth', 'passwords', 'pagination'] as $fichier) {
            $reference = array_keys(require $racine.'/fr/'.$fichier.'.php');
            sort($reference);

            foreach (['nl', 'en'] as $langue) {
                $cles = array_keys(require $racine.'/'.$langue.'/'.$fichier.'.php');
                sort($cles);

                $this->assertSame($reference, $cles, "lang/$langue/$fichier.php n'a pas les memes cles que le francais.");
            }
        }

        $reference = array_keys((require $racine.'/fr/validation.php')['attributes']);
        sort($reference);

        foreach (['nl', 'en'] as $langue) {
            $cles = array_keys((require $racine.'/'.$langue.'/validation.php')['attributes']);
            sort($cles);

            $this->assertSame($reference, $cles, "Les noms de champs de lang/$langue/validation.php ne suivent pas le francais.");
        }
    }
}
