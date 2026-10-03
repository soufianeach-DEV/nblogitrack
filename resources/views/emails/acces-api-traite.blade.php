@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($accorde = $demande->status === \App\Models\ApiKeyRequest::ACCORDEE)

@section('titre', $accorde
    ? $t::t('courriel.acces_api_accorde_sujet', 'Votre accès à l\'API NBLogiTrack est prêt')
    : $t::t('courriel.acces_api_refuse_sujet', 'Votre demande d\'accès à l\'API NBLogiTrack'))

@section('contenu')
    <p style="margin:0 0 16px; color:#1A202C; font-size:14px;">{{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}</p>
    @if ($accorde)
        <p style="margin:0 0 20px; color:#1A202C; font-size:14px; line-height:1.6;">
            {{ $t::t('courriel.acces_api_accorde_texte', 'votre demande d\'accès à l\'API est accordée. Pour votre sécurité, la clé n\'est pas envoyée par e-mail : connectez-vous et affichez-la dans votre espace. Elle ne s\'affiche qu\'une seule fois, copiez-la aussitôt.') }}
        </p>
        @include('emails.bouton', [
            'lien' => route('company.api.index', ['langue' => app()->getLocale()]),
            'libelle' => $t::t('courriel.acces_api_afficher', 'Afficher ma clé'),
            'aide' => $t::t('courriel.ou_lien', 'ou ouvrez ce lien dans votre navigateur'),
        ])
    @else
        <p style="margin:0 0 16px; color:#1A202C; font-size:14px; line-height:1.6;">
            {{ $t::t('courriel.acces_api_refuse_texte', 'votre demande d\'accès à l\'API n\'a pas été accordée.') }}
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F1F5F9; border-radius:10px; margin-bottom:24px;">
            <tr>
                <td style="padding:16px 24px;">
                    <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.acces_api_motif', 'Motif') }}</span>
                    <div style="color:#1A202C; font-size:15px; margin-top:4px;">{{ $demande->refusal_reason }}</div>
                </td>
            </tr>
        </table>
    @endif
@endsection
