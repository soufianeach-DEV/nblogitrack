<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facture {{ $facture->reference }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FA; font-family:'Inter', Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#14324F; padding:24px 32px;">
                            <img src="{{ asset('images/logo-blanc.png') }}" alt="NB LOGITRACK" width="170" style="display:block; max-width:170px; height:auto;">
                            <span style="color:#9fb3c8; font-size:11px; letter-spacing:1px; display:block; margin-top:8px;">LOGISTIQUE B2B</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">Votre facture {{ $facture->reference }}</h1>
                            <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Bonjour {{ $prenom }},<br>
                                voici la facture des transports réalisés du {{ $facture->period_start->format('d/m/Y') }} au {{ $facture->period_end->format('d/m/Y') }}. Vous la trouverez en pièce jointe au format PDF, ainsi qu'au format XML Peppol pour votre logiciel comptable.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Montant à payer</span>
                                        <div style="color:#14324F; font-size:22px; font-weight:bold; margin:4px 0 12px;">{{ number_format((float) $facture->amount_incl_tax, 2, ',', ' ') }} €</div>
                                        @if ($facture->reverse_charge)
                                            <div style="color:#4a5568; font-size:12px; margin:-8px 0 12px;">Autoliquidation : TVA due par le preneur.</div>
                                        @endif
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Échéance</span>
                                        <div style="color:#14324F; font-size:15px; margin:4px 0 12px;">{{ $facture->due_on->format('d/m/Y') }}</div>
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Virement</span>
                                        <div style="color:#14324F; font-size:15px; margin-top:4px;">
                                            {{ config('entreprise.iban') }}<br>
                                            Communication : <strong>{{ $facture->payment_reference }}</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ route('invoices.show', ['langue' => 'fr', 'invoice' => $facture->id]) }}"
                                           style="display:inline-block; background-color:#F59E0B; color:#001D36; font-size:15px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:8px;">
                                            Voir la facture
                                        </a>
                                        <p style="margin:12px 0 0; font-size:11px; color:#4a5568;">
                                            Depuis votre espace, vous pouvez aussi la régler en ligne par carte ou Bancontact.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Une question sur cette facture ? Répondez à votre interlocuteur habituel en indiquant la référence {{ $facture->reference }}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F7FA; padding:16px 32px; color:#94a3b8; font-size:11px;">
                            © {{ date('Y') }} NBLogiTrack Belgium — cet e-mail a été envoyé automatiquement, merci de ne pas y répondre.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
