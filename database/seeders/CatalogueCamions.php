<?php

namespace Database\Seeders;

class CatalogueCamions
{
    /**
     * Chaque modele porte le gabarit reel de sa categorie. La charge est la
     * charge utile en tonnes, pas la masse totale : c'est elle que la
     * planification compare au poids de la marchandise.
     *
     * @var list<array{marque: string, modele: string, gabarit: string, carrosseries: list<string>, charge: array{float, float}, volume: array{int, int}, hayon: int}>
     */
    public const MODELES = [
        // Utilitaires legers, 3,5 t de masse totale.
        ['marque' => 'Mercedes-Benz', 'modele' => 'Sprinter', 'gabarit' => 'Utilitaire 3,5 t', 'carrosseries' => ['Camionnette', 'Fourgon', 'Frigo'], 'charge' => [1.0, 1.5], 'volume' => [11, 17], 'hayon' => 45],
        ['marque' => 'Iveco', 'modele' => 'Daily', 'gabarit' => 'Utilitaire 3,5 t', 'carrosseries' => ['Camionnette', 'Fourgon', 'Plateau'], 'charge' => [1.0, 1.5], 'volume' => [12, 20], 'hayon' => 45],
        ['marque' => 'Renault Trucks', 'modele' => 'Master', 'gabarit' => 'Utilitaire 3,5 t', 'carrosseries' => ['Camionnette', 'Fourgon', 'Frigo'], 'charge' => [1.0, 1.4], 'volume' => [11, 17], 'hayon' => 45],

        // Porteurs de distribution, 12 t.
        ['marque' => 'Mercedes-Benz', 'modele' => 'Atego', 'gabarit' => 'Porteur 12 t', 'carrosseries' => ['Porteur', 'Fourgon', 'Frigo', 'Plateau'], 'charge' => [5.5, 6.5], 'volume' => [30, 45], 'hayon' => 55],
        ['marque' => 'DAF', 'modele' => 'LF', 'gabarit' => 'Porteur 12 t', 'carrosseries' => ['Porteur', 'Fourgon', 'Benne', 'Plateau'], 'charge' => [5.5, 6.5], 'volume' => [30, 45], 'hayon' => 55],
        ['marque' => 'Iveco', 'modele' => 'Eurocargo', 'gabarit' => 'Porteur 12 t', 'carrosseries' => ['Porteur', 'Fourgon', 'Frigo', 'Benne'], 'charge' => [5.5, 6.5], 'volume' => [30, 45], 'hayon' => 55],

        // Porteurs lourds, 26 t.
        ['marque' => 'MAN', 'modele' => 'TGM', 'gabarit' => 'Porteur 26 t', 'carrosseries' => ['Porteur', 'Benne', 'Plateau', 'Citerne'], 'charge' => [9.0, 14.0], 'volume' => [45, 60], 'hayon' => 20],
        ['marque' => 'Volvo', 'modele' => 'FE', 'gabarit' => 'Porteur 26 t', 'carrosseries' => ['Porteur', 'Frigo', 'Benne', 'Citerne'], 'charge' => [9.0, 14.0], 'volume' => [45, 60], 'hayon' => 20],
        ['marque' => 'DAF', 'modele' => 'CF', 'gabarit' => 'Porteur 26 t', 'carrosseries' => ['Porteur', 'Citerne', 'Plateau', 'Benne'], 'charge' => [9.0, 14.0], 'volume' => [45, 60], 'hayon' => 20],
        ['marque' => 'Renault Trucks', 'modele' => 'C', 'gabarit' => 'Porteur 26 t', 'carrosseries' => ['Porteur', 'Benne', 'Plateau'], 'charge' => [9.0, 13.0], 'volume' => [45, 60], 'hayon' => 20],
        ['marque' => 'Scania', 'modele' => 'G410', 'gabarit' => 'Porteur 26 t', 'carrosseries' => ['Porteur', 'Citerne', 'Frigo'], 'charge' => [9.0, 14.0], 'volume' => [45, 60], 'hayon' => 20],

        // Tracteurs de ligne, ensemble articule a 44 t.
        ['marque' => 'Mercedes-Benz', 'modele' => 'Actros', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Citerne', 'Frigo', 'Plateau'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'MAN', 'modele' => 'TGX', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Citerne', 'Plateau'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'DAF', 'modele' => 'XF', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Frigo', 'Plateau'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Scania', 'modele' => 'R500', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Citerne', 'Benne'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Scania', 'modele' => 'S450', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Frigo', 'Plateau'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Volvo', 'modele' => 'FH', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Citerne', 'Frigo'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Volvo', 'modele' => 'FM', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Plateau', 'Benne'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Iveco', 'modele' => 'S-Way', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Citerne', 'Frigo'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
        ['marque' => 'Renault Trucks', 'modele' => 'T High', 'gabarit' => 'Semi-remorque 44 t', 'carrosseries' => ['Semi-remorque', 'Plateau', 'Frigo'], 'charge' => [24.0, 27.0], 'volume' => [80, 100], 'hayon' => 10],
    ];

    /**
     * Un hayon elevateur se monte sur une caisse ouvrante par l'arriere.
     * Une citerne et une benne n'en recoivent jamais.
     */
    public const SANS_HAYON = ['Citerne', 'Benne'];
}
