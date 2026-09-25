@extends('emails.cadre')
@php($t = \App\Support\Traductions::class)
@php($f = \App\Support\Formats::class)

@section('titre', $t::t('courriel.facture_titre', 'Votre facture :reference', ['reference' => $facture->reference]))

@section('contenu')
    <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">{{ $t::t('courriel.facture_titre', 'Votre facture :reference', ['reference' => $facture->reference]) }}</h1>
    <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
        {{ $t::t('courriel.bonjour', 'Bonjour :prenom,', ['prenom' => $prenom]) }}<br>
        {{ $t::t('courriel.facture_texte', 'voici la facture des transports réalisés du :debut au :fin. Vous la trouverez en pièce jointe au format PDF, ainsi qu\'au format XML Peppol pour votre logiciel comptable.', [
            'debut' => $f::date($facture->period_start),
            'fin' => $f::date($facture->period_end),
        ]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 24px;">
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.montant_a_payer', 'Montant à payer') }}</span>
                <div style="color:#14324F; font-size:22px; font-weight:bold; margin:4px 0 12px;">{{ $f::montant($facture->amount_incl_tax) }}</div>
                @if ($facture->reverse_charge)
                    <div style="color:#4a5568; font-size:12px; margin:-8px 0 12px;">{{ $t::t('courriel.autoliquidation', 'Autoliquidation : TVA due par le preneur.') }}</div>
                @endif
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.echeance', 'Échéance') }}</span>
                <div style="color:#14324F; font-size:15px; margin:4px 0 12px;">{{ $f::date($facture->due_on) }}</div>
                <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">{{ $t::t('courriel.virement', 'Virement') }}</span>
                <div style="color:#14324F; font-size:15px; margin-top:4px;">
                    {{ config('entreprise.iban') }}<br>
                    {{ $t::t('courriel.communication', 'Communication') }} : <strong>{{ $facture->payment_reference }}</strong>
                </div>
            </td>
        </tr>
    </table>

    @include('emails.bouton', [
        'lien' => route('invoices.show', ['langue' => app()->getLocale(), 'invoice' => $facture->id]),
        'libelle' => $t::t('courriel.voir_facture', 'Voir la facture'),
    ])

    <p style="margin:-12px 0 24px; font-size:11px; color:#4a5568; text-align:center;">
        {{ $t::t('courriel.payer_en_ligne', 'Depuis votre espace, vous pouvez aussi la régler en ligne par carte ou Bancontact.') }}
    </p>

    <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
        {{ $t::t('courriel.facture_question', 'Une question sur cette facture ? Répondez à votre interlocuteur habituel en indiquant la référence :reference.', ['reference' => $facture->reference]) }}
    </p>
@endsection
