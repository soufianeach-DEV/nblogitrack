@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $titre)

@section('contenu')
    <h1 style="margin:0 0 4px; color:#14324F; font-size:22px;">{{ $titre }}</h1>
    <p style="margin:0 0 20px; color:#94a3b8; font-size:12px;">{{ $t::t('courriel.version_du', 'Version du :date', ['date' => $version]) }}</p>

    <p style="margin:0 0 20px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.note_texte', 'voici la note d\'information relative au traitement de vos données. Elle vous est adressée avant tout relevé de position.') }}
    </p>

    {{-- Le texte est rendu tel qu'il a ete redige : les sauts
         de ligne sont conserves, rien n'est interprete. --}}
    <div style="color:#1A202C; font-size:14px; line-height:1.7; white-space:pre-line; border-top:1px solid #E2E8F0; padding-top:20px;">{{ $corps }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-top:24px;">
        <tr>
            <td style="padding:18px 22px; color:#4a5568; font-size:13px; line-height:1.6;">
                {{ $t::t('courriel.note_confirmation', 'À votre prochaine connexion, l\'application vous demandera de confirmer que vous avez pris connaissance de cette note.') }}
                <strong>{{ $t::t('courriel.note_pas_accord', 'Il ne s\'agit pas d\'un accord') }}</strong> : {{ $t::t('courriel.note_base_legale', 'le traitement repose sur votre contrat de travail et sur l\'intérêt légitime de l\'entreprise, non sur votre consentement.') }}
            </td>
        </tr>
    </table>
@endsection

@section('pied', $t::t('courriel.pied_donnees', 'pour toute question sur vos données : info@nblogitrack.be'))
