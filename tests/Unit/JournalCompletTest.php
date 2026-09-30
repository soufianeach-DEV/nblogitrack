<?php

namespace Tests\Unit;

use App\Http\Controllers\ActivityLogController;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Chaque action ecrite dans le journal a son libelle et figure dans le
 * filtre de l'ecran : sinon l'administrateur ne peut ni la lire ni la
 * retrouver.
 */
class JournalCompletTest extends TestCase
{
    public function test_chaque_action_journalisee_a_son_libelle(): void
    {
        $actions = [];
        $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

        foreach ($fichiers as $fichier) {
            if ($fichier->getExtension() !== 'php') {
                continue;
            }

            $code = file_get_contents($fichier->getPathname());

            // Action litterale, ou les deux branches d'un « ? : ».
            preg_match_all("/ActivityLog::record\\(\\s*(?:[^,]*?\\?\\s*)?'([a-z_]+\\.[a-z_]+)'(?:\\s*:\\s*'([a-z_]+\\.[a-z_]+)')?/", $code, $trouves);
            $actions = array_merge($actions, $trouves[1], array_filter($trouves[2]));
        }

        $this->assertNotEmpty($actions);
        $this->assertSame([], array_values(array_diff(array_unique($actions), array_keys(ActivityLogController::ACTIONS))));
    }
}
