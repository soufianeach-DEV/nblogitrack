@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $t::t('courriel.mdp_titre', 'Choisissez votre mot de passe'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.mdp_titre', 'Choisissez votre mot de passe') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.mdp_texte', 'une demande a été faite pour choisir le mot de passe de votre compte NBLogiTrack. Le lien ci-dessous est valable :minutes minutes.', ['minutes' => $minutes]) }}
    </p>

    @include('emails.bouton', [
        'lien' => $lien,
        'libelle' => $t::t('courriel.mdp_bouton', 'Choisir mon mot de passe'),
        'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
    ])

    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.mdp_ignorer', 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.') }}
    </p>
@endsection
