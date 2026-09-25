@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $t::t('courriel.active_titre', 'Votre compte est activé'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.active_titre', 'Votre compte est activé') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.active_texte', 'nous avons vérifié les informations de votre entreprise auprès des registres officiels. Votre accès est ouvert : vous pouvez dès maintenant créer vos ordres de transport.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.entreprise', 'Entreprise') }}</span>
                <div style="color:#14324F; font-size:18px; font-weight:bold; margin:4px 0 12px;">{{ $client->company_name }}</div>
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.numero_tva', 'Numéro de TVA') }}</span>
                <div style="color:#14324F; font-size:15px; margin:4px 0 12px;">{{ $client->vat_number }}</div>
                @if ($client->peppol_id)
                    <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.identifiant_peppol', 'Identifiant Peppol') }}</span>
                    <div style="color:#0B61A1; font-size:15px; margin-top:4px;">{{ $client->peppol_id }}</div>
                @endif
            </td>
        </tr>
    </table>

    @include('emails.bouton', [
        'lien' => route('login'),
        'libelle' => $t::t('courriel.me_connecter', 'Me connecter'),
        'aide' => $t::t('courriel.ou_connexion', 'ou ouvrez la page de connexion dans votre navigateur'),
    ])

    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.active_rappel', 'Connectez-vous avec l\'adresse :email et le mot de passe choisi lors de votre inscription.', ['email' => $destinataire->email]) }}
    </p>
@endsection
