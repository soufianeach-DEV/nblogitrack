<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demande d'inscription</title>
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
                            <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">Votre demande n'a pas été retenue</h1>
                            <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Bonjour {{ $destinataire->first_name }},<br>
                                nous avons examiné la demande d'inscription de {{ $client->company_name }}. Nous ne pouvons pas y donner suite en l'état.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FEF2F2; border-left:4px solid #DC2626; border-radius:6px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <span style="color:#991B1B; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Motif</span>
                                        <div style="color:#1A202C; font-size:14px; line-height:1.6; margin-top:6px;">{{ $motif }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Si vous pensez qu'il s'agit d'une erreur, ou si votre situation a changé depuis, écrivez-nous en joignant vos documents d'entreprise à jour. Nous réexaminerons votre dossier.
                            </p>
                            <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Aucune donnée de votre demande n'est conservée au-delà du délai légal.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F7FA; padding:16px 32px; color:#94a3b8; font-size:11px;">
                            © {{ date('Y') }} NBLogiTrack Belgium — cet e-mail a été envoyé automatiquement.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>