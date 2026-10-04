<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;

/**
 * L'encours d'une entreprise : ce qu'elle doit sur ses factures emises et
 * non reglees, TVA comprise (le reste du apres paiements partiels). Les
 * expeditions pas encore facturees n'en font pas partie : une entreprise
 * qui a regle ses factures a un encours nul. Trois factures en retard, ou
 * une commande qui ferait depasser le plafond de credit a cet encours,
 * bloquent la commande.
 */
class Encours
{
    /** A partir de ce nombre de factures en retard, l'entreprise ne commande plus. */
    public const RETARDS_BLOQUANTS = 3;

    /** @return array{factures: float, total: float, en_retard: int} */
    public static function de(Client $client): array
    {
        $dues = Invoice::where('client_id', $client->id)
            ->where('type', Invoice::FACTURE)
            ->whereIn('status', ['SENT', 'OVERDUE'])
            ->withSum('payments', 'amount')
            ->get(['id', 'amount_incl_tax', 'due_on', 'status']);

        $factures = round($dues->sum(fn (Invoice $f) => max(0, (float) $f->amount_incl_tax - (float) $f->payments_sum_amount)), 2);

        $enRetard = $dues->filter(fn (Invoice $f) => $f->status === 'OVERDUE' || $f->due_on?->lt(today()))->count();

        return [
            'factures' => $factures,
            'total' => $factures,
            'en_retard' => $enRetard,
        ];
    }

    /** Un montant hors TVA, au taux que portera la facture de l'entreprise. */
    public static function ttc(Client $client, float $horsTva): float
    {
        return round($horsTva * (1 + RegimeTva::pour($client)->taux / 100), 2);
    }

    /**
     * Le motif qui empeche l'entreprise de commander une expedition de ce
     * prix (HT), ou null si elle le peut. La commande est comptee TVA
     * comprise, comme les factures. Avec un prix nul, la question est de
     * savoir si elle peut encore commander quoi que ce soit : un plafond deja
     * atteint suffit a l'en empecher. Le personnel qui transforme un devis
     * recoit un message qui lui est adresse.
     */
    public static function refus(Client $client, float $prixHt, bool $pourLePersonnel = false): ?string
    {
        $encours = self::de($client);

        if ($encours['en_retard'] >= self::RETARDS_BLOQUANTS) {
            return $pourLePersonnel
                ? Traductions::t('msg.encours_retards_personnel', ':entreprise a :n factures en retard de paiement : aucune commande n’est possible avant leur règlement.', ['entreprise' => $client->company_name, 'n' => $encours['en_retard']])
                : Traductions::t('msg.encours_retards', ':n factures sont en retard de paiement : réglez-les avant de commander une nouvelle expédition.', ['n' => $encours['en_retard']]);
        }

        if ($client->credit_limit === null) {
            return null;
        }

        $plafond = (float) $client->credit_limit;
        $commande = self::ttc($client, $prixHt);
        $depasse = $prixHt > 0 ? $plafond < $encours['total'] + $commande : $encours['total'] >= $plafond;

        if (! $depasse) {
            return null;
        }

        $valeurs = [
            'entreprise' => $client->company_name,
            'encours' => Formats::montant($encours['total']),
            'commande' => Formats::montant($commande),
            'plafond' => Formats::montant($plafond),
        ];

        return match (true) {
            $pourLePersonnel => Traductions::t('msg.encours_plafond_personnel', 'Cette commande ferait dépasser le plafond de crédit de :entreprise : factures dues de :encours, plus :commande pour cette commande, pour un plafond de :plafond. Relevez le plafond sur l’écran Entreprises ou attendez le règlement de ses factures.', $valeurs),
            $prixHt > 0 => Traductions::t('msg.encours_plafond_commande', 'Plafond de crédit dépassé : factures dues de :encours, plus :commande pour cette commande, pour un plafond de :plafond. Réglez vos factures ou contactez-nous pour le relever.', $valeurs),
            default => Traductions::t('msg.encours_plafond', 'Plafond de crédit atteint : factures dues de :encours pour un plafond de :plafond. Réglez vos factures ou contactez-nous pour le relever.', $valeurs),
        };
    }

    /**
     * Un rappel sans blocage : une ou deux factures en retard, avant le
     * seuil qui bloque les commandes.
     */
    public static function avertissement(Client $client): ?string
    {
        $retards = self::de($client)['en_retard'];

        if ($retards === 0 || $retards >= self::RETARDS_BLOQUANTS) {
            return null;
        }

        return $retards === 1
            ? Traductions::t('msg.encours_avertissement_une', 'Une facture est en retard de paiement. À partir de :seuil, les nouvelles commandes sont bloquées.', ['seuil' => self::RETARDS_BLOQUANTS])
            : Traductions::t('msg.encours_avertissement', ':n factures sont en retard de paiement. À partir de :seuil, les nouvelles commandes sont bloquées.', ['n' => $retards, 'seuil' => self::RETARDS_BLOQUANTS]);
    }
}
