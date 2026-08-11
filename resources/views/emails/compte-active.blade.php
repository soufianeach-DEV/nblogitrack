<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compte activé</title>
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
                            <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">Votre compte est activé</h1>
                            <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Bonjour {{ $destinataire->first_name }},<br>
                                nous avons vérifié les informations de votre entreprise auprès des registres officiels. Votre accès est ouvert : vous pouvez dès maintenant créer vos ordres de transport.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Entreprise</span>
                                        <div style="color:#14324F; font-size:18px; font-weight:bold; margin:4px 0 12px;">{{ $client->company_name }}</div>
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Numéro de TVA</span>
                                        <div style="color:#14324F; font-size:15px; margin:4px 0 12px;">{{ $client->vat_number }}</div>
                                        @if ($client->peppol_id)
                                            <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Identifiant Peppol</span>
                                            <div style="color:#0B61A1; font-size:15px; margin-top:4px;">{{ $client->peppol_id }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ route('login') }}"
                                           style="display:inline-block; background-color:#F59E0B; color:#001D36; font-size:15px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:8px;">
                                            Me connecter
                                        </a>
                                        <p style="margin:12px 0 0; font-size:11px;">
                                            <a href="{{ route('login') }}" style="color:#0B61A1; text-decoration:underline;">
                                                ou ouvrez la page de connexion dans votre navigateur
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Connectez-vous avec l'adresse {{ $destinataire->email }} et le mot de passe choisi lors de votre inscription.
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