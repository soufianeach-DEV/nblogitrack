<?php

namespace App\Support;

/**
 * Le libelle d'une ligne de facture dans la langue du lecteur. Il est
 * enregistre en francais au moment de l'emission (« Transport <depart>
 * vers <arrivee> », « Indemnité d'annulation <numero> ») : l'ecran et le
 * PDF le recomposent de la meme maniere.
 */
class LigneFacture
{
    private const TRANSPORT = 'Transport ';

    private const INDEMNITE = 'Indemnité d\'annulation ';

    public static function libelle(string $description): string
    {
        if (str_starts_with($description, self::TRANSPORT) && str_contains($description, ' vers ')) {
            [$depart, $arrivee] = explode(' vers ', substr($description, strlen(self::TRANSPORT)), 2);

            return Traductions::t('pdf.transport', 'Transport').' '
                .Traductions::t('pdf.trajet', ':depart vers :arrivee', ['depart' => $depart, 'arrivee' => $arrivee]);
        }

        if (str_starts_with($description, self::INDEMNITE)) {
            return Traductions::t('pdf.indemnite_annulation', 'Indemnité d\'annulation :numero', [
                'numero' => substr($description, strlen(self::INDEMNITE)),
            ]);
        }

        return $description;
    }
}
