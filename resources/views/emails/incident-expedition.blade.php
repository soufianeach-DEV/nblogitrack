@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($suivi = route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]))

@section('titre', $t::t('courriel.incident_titre', 'Un incident est survenu pendant le transport'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.incident_titre', 'Un incident est survenu pendant le transport') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.incident_texte', 'notre chauffeur a signalé un incident pendant le transport de votre expédition. Notre équipe de planification s\'en occupe : la livraison peut prendre du retard. Nous revenons vers vous dès que la situation est réglée.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.numero_suivi', 'Numéro de suivi') }}</span>
                <div style="color:#14324F; font-size:24px; font-weight:bold; margin-top:4px;">{{ $ordre->tracking_number }}</div>
            </td>
        </tr>
    </table>

    @include('emails.bouton', [
        'lien' => $suivi,
        'libelle' => $t::t('courriel.suivre', 'Suivre mon expédition'),
        'aide' => $t::t('courriel.ou_suivi', 'ou ouvrez la page de suivi dans votre navigateur'),
    ])

    <p style="margin:16px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.incident_reserves', 'À la livraison, vérifiez la marchandise en présence du chauffeur : tout dommage constaté est noté dans les réserves.') }}
    </p>
@endsection
