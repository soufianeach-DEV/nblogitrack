@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($f = \App\Support\Formats::class)
@php($suivi = route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]))

@section('titre', $t::t('courriel.ordre_titre', 'Votre expédition est enregistrée'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.ordre_titre', 'Votre expédition est enregistrée') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.ordre_texte', 'votre commande de transport a bien été créée. Conservez précieusement les informations ci-dessous : elles permettent de suivre votre envoi à tout moment.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.numero_suivi', 'Numéro de suivi') }}</span>
                <div style="color:#14324F; font-size:24px; font-weight:bold; margin:4px 0 12px;">{{ $ordre->tracking_number }}</div>
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.code_acces', 'Code d\'accès') }}</span>
                <div style="color:#D97706; font-size:20px; font-weight:bold; letter-spacing:3px; margin-top:4px;">{{ $ordre->tracking_code }}</div>
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
            <td style="padding:12px 0 4px;">{{ $ordre->pickup_address }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.destination', 'Destination') }}</td>
            <td style="padding:4px 0;">{{ $ordre->delivery_address }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.marchandise', 'Marchandise') }}</td>
            <td style="padding:4px 0;">{{ $t::vocabulaire('marchandise', $ordre->goods_type) }} · {{ $f::nombre($ordre->weight) }} kg{{ $ordre->is_hazardous ? ' · ADR' : '' }}</td>
        </tr>
        @if ($grille)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.formule', 'Formule') }}</td>
                <td style="padding:4px 0;">{{ $ordre->formule() }} — {{ $t::t('courriel.livraison_en', 'livraison en :jours j', ['jours' => $ordre->delaiPromis() ?? $grille->delivery_days]) }}</td>
            </tr>
        @endif
        @if ($ordre->pickup_date)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.chargement', 'Chargement') }}</td>
                <td style="padding:4px 0;">{{ $f::dateHeure($ordre->pickup_date) }}</td>
            </tr>
        @endif
        @if ($ordre->requested_delivery_date)
            <tr>
                <td style="padding:4px 0; color:#94a3b8;">{{ $t::t('courriel.livraison_souhaitee', 'Livraison souhaitée') }}</td>
                <td style="padding:4px 0;">{{ $f::date($ordre->requested_delivery_date) }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:12px 0; color:#94a3b8; border-top:1px solid #e2e8f0;">{{ $t::t('courriel.prix_estime', 'Prix estimé') }}</td>
            <td style="padding:12px 0; border-top:1px solid #e2e8f0; color:#14324F; font-size:16px; font-weight:bold;">{{ $f::montant($ordre->estimated_cost) }}</td>
        </tr>
    </table>

    <p style="margin:16px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.code_personnel', 'Ce code est strictement personnel : ne le partagez qu\'avec les personnes autorisées à consulter cet envoi.') }}
    </p>
@endsection
