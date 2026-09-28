@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($f = \App\Support\Formats::class)
@php($suivi = route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]))

@section('titre', $t::t('courriel.affecte_titre', 'Votre expédition est prise en charge'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.affecte_titre', 'Votre expédition est prise en charge') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.affecte_texte', 'un camion et un chauffeur sont réservés pour votre expédition. Voici la date d\'enlèvement retenue.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.numero_suivi', 'Numéro de suivi') }}</span>
                <div style="color:#14324F; font-size:24px; font-weight:bold; margin:4px 0 12px;">{{ $ordre->tracking_number }}</div>
                @if ($ordre->pickup_date)
                    <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.enlevement_prevu', 'Enlèvement prévu') }}</span>
                    <div style="color:#D97706; font-size:20px; font-weight:bold; margin-top:4px;">{{ $f::dateHeure($ordre->pickup_date) }}</div>
                @endif
            </td>
        </tr>
    </table>

    @include('emails.bouton', [
        'lien' => $suivi,
        'libelle' => $t::t('courriel.suivre', 'Suivre mon expédition'),
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
        @if ($ordre->vehicle_registration)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.vehicule', 'Véhicule') }}</td>
                <td style="padding:4px 0;">{{ $ordre->vehicle_registration }}</td>
            </tr>
        @endif
        @if ($ordre->requested_delivery_date)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.livraison_souhaitee', 'Livraison souhaitée') }}</td>
                <td style="padding:4px 0;">{{ $f::date($ordre->requested_delivery_date) }}</td>
            </tr>
        @endif
    </table>

    <p style="margin:16px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.affecte_suivi', 'Vous pourrez suivre l\'enlèvement puis la livraison sur la page de suivi, avec le code d\'accès reçu à la commande.') }}
    </p>
@endsection
