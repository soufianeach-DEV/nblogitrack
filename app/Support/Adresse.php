<?php

namespace App\Support;

class Adresse
{
    /**
     * La localite d'une adresse, celle qui identifie un trajet dans une liste
     * ou sur une carte.
     *
     * Les adresses n'ont pas toutes la meme forme : le jeu de donnees
     * s'arrete a la localite (« Rue Neuve 43, 3500 Hasselt ») alors que le
     * formulaire de commande ajoute le pays (« Rue Haute 100, 1000
     * Bruxelles, Belgique »). Prendre le dernier segment renvoyait donc
     * « Belgique » pour toutes les commandes passees en ligne.
     *
     * On cherche a la place le segment qui porte le code postal : c'est lui
     * qui contient la localite, quelle que soit la suite.
     */
    public static function localite(string $adresse): string
    {
        $segments = array_map('trim', explode(',', $adresse));

        foreach (array_reverse($segments) as $segment) {
            if (preg_match('/^\d{4,6}\s+(.+)$/u', $segment, $trouve)) {
                return trim($trouve[1]);
            }
        }

        $dernier = (string) end($segments);

        return $dernier !== '' ? $dernier : $adresse;
    }

    /**
     * Le pays d'une adresse, quand elle le porte.
     *
     * Le formulaire de commande ecrit « ..., 1000 Bruxelles, Belgique » :
     * le pays est alors le dernier segment, et il vient apres celui qui
     * porte le code postal. Le jeu de donnees s'arrete a la localite et
     * n'en a pas ; on rend null plutot que de prendre la ville pour un
     * pays.
     */
    public static function pays(string $adresse): ?string
    {
        $segments = array_values(array_filter(array_map('trim', explode(',', $adresse))));
        $dernier = (string) end($segments);

        if (count($segments) < 2 || $dernier === '' || preg_match('/\d/', $dernier)) {
            return null;
        }

        return $dernier;
    }
}
