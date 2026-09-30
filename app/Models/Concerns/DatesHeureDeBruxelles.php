<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Les dates partent vers les pages a l'heure de Bruxelles, sans fuseau :
 * un navigateur regle sur un autre fuseau n'affiche plus une livraison du
 * 2 octobre au 1er, ni un chargement de 9 h a 8 h. L'API publique formate
 * ses dates elle-meme.
 */
trait DatesHeureDeBruxelles
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s');
    }
}
