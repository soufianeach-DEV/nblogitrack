@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($type = \App\Support\Incidents::libelle($incident['type'] ?? null))
@php($endommagee = ($incident['type'] ?? null) === 'DOMMAGE' || ($incident['marchandise_endommagee'] ?? false))
@php($lien = route('planning.index', ['q' => $ordre->tracking_number, 'suivre' => 1]))
@php($lignes = array_filter([
    $t::t('courriel.alerte_mission', 'Mission') => $ordre->tracking_number,
    $t::t('courriel.alerte_donneur', 'Donneur d\'ordre') => $ordre->client?->company_name,
    $t::t('courriel.alerte_chauffeur', 'Chauffeur') => trim($chauffeur->first_name.' '.$chauffeur->last_name.($chauffeur->phone ? ' · '.$chauffeur->phone : '')),
    $t::t('courriel.alerte_camion', 'Camion') => $ordre->vehicle_registration,
    $t::t('courriel.alerte_enlevement', 'Enlèvement') => $ordre->pickup_address,
    $t::t('courriel.alerte_livraison', 'Livraison') => $ordre->delivery_address,
    $t::t('courriel.alerte_marchandise', 'Marchandise') => $endommagee
        ? $t::t('courriel.alerte_endommagee', 'endommagée')
        : $t::t('courriel.alerte_intacte', 'non endommagée selon le chauffeur'),
], fn ($valeur) => filled($valeur)))

@section('titre', $type.' · '.$ordre->tracking_number)

@section('contenu')
    <h1 style="margin:0 0 8px; color:#B91C1C; font-size:22px;">{{ $t::t('courriel.alerte_incident_titre', 'Incident signalé par le chauffeur : :type', ['type' => $type]) }}</h1>
    <p style="margin:0 0 20px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.alerte_incident_texte', 'Le camion est immobilisé : la mission est bloquée jusqu\'à la reprise de la route ou l\'affectation d\'un autre camion. Appelez le chauffeur.') }}
        @if ($endommagee)
            <br><strong>{{ $t::t('courriel.alerte_decision', 'La marchandise est endommagée : c\'est à vous de décider de la suite avec le client (annuler, envoyer un autre camion ou autoriser la reprise).') }}</strong>
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FEF2F2; border-radius:10px; margin-bottom:16px;">
        <tr>
            <td style="padding:16px 24px;">
                <span style="color:#B91C1C; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.alerte_description', 'Description du chauffeur') }}</span>
                <div style="color:#1A202C; font-size:15px; margin-top:4px;">{{ $incident['commentaire'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px; font-size:14px;">
        @foreach ($lignes as $libelle => $valeur)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#4a5568; white-space:nowrap; vertical-align:top;">{{ $libelle }}</td>
                <td style="padding:6px 0; color:#14324F; font-weight:bold;">{{ $valeur }}</td>
            </tr>
        @endforeach
        @if (isset($incident['lat'], $incident['lng']))
            <tr>
                <td style="padding:6px 12px 6px 0; color:#4a5568; white-space:nowrap;">{{ $t::t('courriel.alerte_position', 'Position') }}</td>
                <td style="padding:6px 0;"><a href="https://www.openstreetmap.org/?mlat={{ $incident['lat'] }}&amp;mlon={{ $incident['lng'] }}#map=15/{{ $incident['lat'] }}/{{ $incident['lng'] }}" style="color:#14324F;">{{ $t::t('courriel.alerte_voir_carte', 'Voir sur la carte') }}</a></td>
            </tr>
        @endif
    </table>

    @include('emails.bouton', [
        'lien' => $lien,
        'libelle' => $t::t('courriel.alerte_ouvrir', 'Ouvrir la mission dans la planification'),
        'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
    ])
@endsection
