<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;

/**
 * Le regime de TVA d'une prestation de transport de marchandises B2B.
 * Le lieu de la prestation est celui du preneur (art. 21, §2 du Code de
 * la TVA ; art. 44 de la directive 2006/112/CE) :
 *
 * - preneur belge : TVA belge a 21 % (categorie S) ;
 * - preneur etabli dans un autre Etat membre : autoliquidation (AE) ;
 * - preneur hors de l'Union : hors du champ de la TVA belge (O).
 */
final class RegimeTva
{
    public function __construct(
        public readonly string $categorie,
        public readonly float $taux,
        public readonly ?string $pays,
    ) {}

    public static function pour(Client $client): self
    {
        // Le pays est compare par son code et non par son libelle : une
        // entreprise inscrite depuis l'interface neerlandaise ou anglaise
        // porte « België » ou « Belgium ». A defaut de pays reconnu, le
        // prefixe du numero de TVA tranche.
        $pays = Pays::depuisNom($client->country)
            ?? strtoupper(substr((string) $client->vat_number, 0, 2));
        $pays = strlen((string) $pays) === 2 ? $pays : null;

        return match (true) {
            $pays === 'BE' => new self('S', Invoice::TAUX_TVA, $pays),
            Pays::horsUnion($client->country) => new self('O', 0.0, $pays),
            default => new self('AE', 0.0, $pays),
        };
    }

    public function autoliquidation(): bool
    {
        return $this->categorie !== 'S';
    }
}
