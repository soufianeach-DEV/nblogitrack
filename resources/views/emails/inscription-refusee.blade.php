@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)

@section('titre', $t::t('courriel.refus_titre', 'Votre demande n\'a pas été retenue'))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.refus_titre', 'Votre demande n\'a pas été retenue') }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $destinataire->first_name]) }}<br>
        {{ $t::t('courriel.refus_texte', 'nous avons examiné la demande d\'inscription de :entreprise. Nous ne pouvons pas y donner suite en l\'état.', ['entreprise' => $client->company_name]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FEF2F2; border-left:4px solid #DC2626; border-radius:6px; margin-bottom:24px;">
        <tr>
            <td style="padding:16px 20px;">
                <span style="color:#991B1B; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.motif', 'Motif') }}</span>
                <div style="color:#1A202C; font-size:14px; line-height:1.6; margin-top:6px;">{{ $motif }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.refus_recours', 'Si vous pensez qu\'il s\'agit d\'une erreur, ou si votre situation a changé depuis, écrivez-nous en joignant vos documents d\'entreprise à jour. Nous réexaminerons votre dossier.') }}
    </p>
    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.refus_donnees', 'Aucune donnée de votre demande n\'est conservée au-delà du délai légal.') }}
    </p>
@endsection

@section('pied', $t::t('courriel.pied_envoye', 'cet e-mail a été envoyé automatiquement.'))
