<?php

namespace App\Support;

use App\Mail\OrdreLivre;
use App\Models\ActivityLog;
use App\Models\TransportOrder;
use Illuminate\Support\Facades\Mail;

/**
 * Le courriel « livraison effectuee », que la livraison soit confirmee par
 * le chauffeur ou enregistree par le planificateur. Les destinataires sont
 * ceux de l'avis d'affectation.
 */
class AvisLivraison
{
    public static function envoyer(TransportOrder $ordre): void
    {
        $ordre = $ordre->fresh();

        foreach (ClientsAPrevenir::pour($ordre) as $destinataire) {
            try {
                Mail::to($destinataire->email)->send(new OrdreLivre($ordre, $destinataire));
            } catch (\Throwable $e) {
                report($e);

                ActivityLog::record(
                    'order.delivered_mail_failed',
                    'Avis de livraison non envoyé pour l\'ordre '.$ordre->tracking_number,
                    $ordre,
                    ['destinataire' => $destinataire->email],
                );
            }
        }
    }
}
