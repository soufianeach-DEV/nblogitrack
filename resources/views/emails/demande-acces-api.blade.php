@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($lignes = array_filter([
    $t::t('courriel.demande_api_entreprise', 'Entreprise') => $demande->client?->company_name,
    $t::t('courriel.demande_api_demandeur', 'Demandée par') => $demande->demandeur ? trim($demande->demandeur->first_name.' '.$demande->demandeur->last_name).' · '.$demande->demandeur->email : null,
    $t::t('courriel.demande_api_permissions', 'Permissions') => collect($demande->abilities)->map(fn ($p) => $t::t('api_permission.'.$p, \App\Models\ApiKey::PERMISSIONS[$p] ?? $p))->implode(', '),
    $t::t('courriel.demande_api_ips', 'Adresses IP') => $demande->allowed_ips ? implode(', ', $demande->allowed_ips) : null,
    $t::t('courriel.demande_api_message', 'Message') => $demande->message,
], fn ($valeur) => filled($valeur)))

@section('titre', $t::t('courriel.demande_api_sujet', 'Demande d\'accès à l\'API : :entreprise', ['entreprise' => $demande->client?->company_name]))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.demande_api_titre', 'Nouvelle demande d\'accès à l\'API') }}</h1>
    <p style="margin:0 0 20px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.demande_api_texte', 'Accordez ou refusez la demande depuis la page API REST. Une fois accordée, le client affiche lui-même sa clé dans son espace.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px; font-size:14px;">
        @foreach ($lignes as $libelle => $valeur)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#4a5568; white-space:nowrap; vertical-align:top;">{{ $libelle }}</td>
                <td style="padding:6px 0; color:#14324F; font-weight:bold;">{{ $valeur }}</td>
            </tr>
        @endforeach
    </table>

    @include('emails.bouton', [
        'lien' => route('api-keys.index', ['langue' => app()->getLocale()]),
        'libelle' => $t::t('courriel.demande_api_ouvrir', 'Traiter la demande'),
        'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
    ])
@endsection
