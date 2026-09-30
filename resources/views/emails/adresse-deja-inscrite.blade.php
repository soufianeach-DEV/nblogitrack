@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $t::t('courriel.deja_inscrit_titre', 'Vous avez déjà un compte'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.deja_inscrit_titre', 'Vous avez déjà un compte') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.deja_inscrit_texte', 'une demande d\'inscription vient d\'être faite avec votre adresse, qui a déjà un compte NBLogiTrack. Aucun nouveau compte n\'a été créé. Si vous avez oublié votre mot de passe, choisissez-en un nouveau :') }}
    </p>

    @include('emails.bouton', [
        'lien' => route('password.request', ['langue' => $destinataire->locale ?: 'fr']),
        'libelle' => $t::t('courriel.deja_inscrit_bouton', 'Choisir un nouveau mot de passe'),
        'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
    ])

    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.deja_inscrit_ignorer', 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre compte n\'a pas été modifié.') }}
    </p>
@endsection
