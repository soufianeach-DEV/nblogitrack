<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }}</title>
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
                            <h1 style="margin:0 0 4px; color:#14324F; font-size:22px;">{{ $titre }}</h1>
                            <p style="margin:0 0 20px; color:#94a3b8; font-size:12px;">Version du {{ $version }}</p>

                            <p style="margin:0 0 20px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Bonjour {{ $destinataire->first_name }},<br>
                                voici la note d'information relative au traitement de vos données. Elle vous est adressée avant tout relevé de position.
                            </p>

                            {{-- Le texte est rendu tel qu'il a ete redige : les sauts
                                 de ligne sont conserves, rien n'est interprete. --}}
                            <div style="color:#1A202C; font-size:14px; line-height:1.7; white-space:pre-line; border-top:1px solid #E2E8F0; padding-top:20px;">{{ $corps }}</div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-top:24px;">
                                <tr>
                                    <td style="padding:18px 22px; color:#4a5568; font-size:13px; line-height:1.6;">
                                        À votre prochaine connexion, l'application vous demandera de confirmer que vous avez pris connaissance de cette note.
                                        <strong>Il ne s'agit pas d'un accord</strong> : le traitement repose sur votre contrat de travail et sur l'intérêt légitime de l'entreprise, non sur votre consentement.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F7FA; padding:16px 32px; color:#94a3b8; font-size:11px;">
                            © {{ date('Y') }} NBLogiTrack Belgium — pour toute question sur vos données : info@nblogitrack.be
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
