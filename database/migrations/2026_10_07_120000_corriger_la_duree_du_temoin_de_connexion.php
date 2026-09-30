<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La politique des cookies annoncait quatre cents jours pour le temoin
 * « Se souvenir de moi » ; la configuration le garde trente jours
 * (config/auth.php). Le texte deja en base est aligne.
 */
return new class extends Migration
{
    private const REMPLACEMENTS = [
        'corps_fr' => ['au terme de quatre cents jours.', 'au terme de trente jours.'],
        'corps_nl' => ['anders na vierhonderd dagen.', 'anders na dertig dagen.'],
        'corps_en' => ['otherwise after four hundred days.', 'otherwise after thirty days.'],
    ];

    public function up(): void
    {
        foreach (self::REMPLACEMENTS as $colonne => [$avant, $apres]) {
            DB::table('pages')
                ->where($colonne, 'like', '%remember_web%')
                ->update([$colonne => DB::raw('replace('.$colonne.', '.DB::getPdo()->quote($avant).', '.DB::getPdo()->quote($apres).')')]);
        }
    }

    public function down(): void
    {
        //
    }
};
