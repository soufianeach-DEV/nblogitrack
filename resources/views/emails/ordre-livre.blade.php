@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($f = \App\Support\Formats::class)
@php($suivi = route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]))
@php($livreLe = $ordre->delivered_at ? $f::dateHeure($ordre->delivered_at) : ($ordre->actual_delivery_date ? $f::date($ordre->actual_delivery_date) : null))

@section('titre', $t::t('courriel.livre_titre', 'Votre expédition a été livrée'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.livre_titre', 'Votre expédition a été livrée') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.livre_texte', 'notre chauffeur a livré votre expédition. Merci de votre confiance.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0FDF4; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.numero_suivi', 'Numéro de suivi') }}</span>
                <div style="color:#14324F; font-size:24px; font-weight:bold; margin:4px 0 12px;">{{ $ordre->tracking_number }}</div>
                @if ($livreLe)
                    <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.livre_le', 'Livrée le') }}</span>
                    <div style="color:#15803D; font-size:20px; font-weight:bold; margin-top:4px;">{{ $livreLe }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($ordre->delivery_reserves)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FEF2F2; border-radius:10px; margin-bottom:24px;">
            <tr>
                <td style="padding:16px 24px;">
                    <span style="color:#B91C1C; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.livre_reserves', 'Réserves notées à la livraison') }}</span>
                    <div style="color:#1A202C; font-size:14px; margin-top:4px; white-space:pre-line;">{{ $ordre->delivery_reserves }}</div>
                </td>
            </tr>
        </table>
    @endif

    @include('emails.bouton', [
        'lien' => $suivi,
        'libelle' => $t::t('courriel.livre_voir', 'Voir le détail de la livraison'),
        'aide' => $t::t('courriel.ou_suivi', 'ou ouvrez la page de suivi dans votre navigateur'),
    ])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0; font-size:13px; color:#4a5568;">
        <tr>
            <td style="padding:12px 0 4px; width:40%; color:#94a3b8;">{{ $t::t('courriel.depart', 'Départ') }}</td>
            <td style="padding:12px 0 4px;">{{ \App\Support\Adresse::localiser($ordre->pickup_address) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.destination', 'Destination') }}</td>
            <td style="padding:4px 0;">{{ \App\Support\Adresse::localiser($ordre->delivery_address) }}</td>
        </tr>
        @if ($ordre->received_by)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.livre_receptionnaire', 'Réceptionné par') }}</td>
                <td style="padding:4px 0;">{{ $ordre->received_by }}</td>
            </tr>
        @endif
    </table>

    <p style="margin:16px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.livre_facture', 'Ce transport figurera sur votre facture du mois de livraison, émise le mois suivant.') }}
    </p>
@endsection
