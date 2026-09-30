@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $t::t('courriel.verif_titre', 'Confirmez votre adresse e-mail'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.verif_titre', 'Confirmez votre adresse e-mail') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.verif_texte', 'confirmez que cette adresse est bien la vôtre : les liens de mot de passe et les factures de NBLogiTrack y seront envoyés. Le lien ci-dessous est valable trois jours.') }}
    </p>

    @include('emails.bouton', [
        'lien' => $lien,
        'libelle' => $t::t('courriel.verif_bouton', 'Confirmer mon adresse'),
        'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
    ])

    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.verif_ignorer', 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : l\'adresse ne sera pas utilisée.') }}
    </p>
@endsection
