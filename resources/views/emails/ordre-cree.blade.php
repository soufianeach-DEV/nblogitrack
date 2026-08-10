<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expédition {{ $ordre->tracking_number }}</title>
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
                            <h1 style="margin:0 0 8px; color:#14324F; font-size:22px;">Votre expédition est enregistrée</h1>
                            <p style="margin:0 0 24px; color:#1A202C; font-size:14px; line-height:1.6;">
                                Bonjour {{ $destinataire->first_name }},<br>
                                votre commande de transport a bien été créée. Conservez précieusement les informations ci-dessous : elles permettent de suivre votre envoi à tout moment.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; border-radius:10px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Numéro de suivi</span>
                                        <div style="color:#14324F; font-size:24px; font-weight:bold; margin:4px 0 12px;">{{ $ordre->tracking_number }}</div>
                                        <span style="color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Code d'accès</span>
                                        <div style="color:#D97706; font-size:20px; font-weight:bold; letter-spacing:3px; margin-top:4px;">{{ $ordre->tracking_code }}</div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]) }}"
                                           style="display:inline-block; background-color:#F59E0B; color:#001D36; font-size:15px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:8px;">
                                            Suivre mon expédition
                                        </a>
                                        <p style="margin:12px 0 0; font-size:11px;">
                                            <a href="{{ route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]) }}" style="color:#0B61A1; text-decoration:underline;">
                                                ou ouvrez la page de suivi dans votre navigateur
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0; font-size:13px; color:#4a5568;">
                                <tr>
                                    <td style="padding:12px 0 4px; width:40%; color:#94a3b8;">Départ</td>
                                    <td style="padding:12px 0 4px;">{{ $ordre->pickup_address }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#94a3b8;">Destination</td>
                                    <td style="padding:4px 0;">{{ $ordre->delivery_address }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#94a3b8;">Marchandise</td>
                                    <td style="padding:4px 0;">{{ $ordre->goods_type }} · {{ number_format($ordre->weight, 0, ',', ' ') }} kg{{ $ordre->is_hazardous ? ' · ADR' : '' }}</td>
                                </tr>
                                @if ($grille)
                                    <tr>
                                        <td style="padding:4px 0; color:#94a3b8;">Formule</td>
                                        <td style="padding:4px 0;">{{ $grille->label }} — livraison en {{ $grille->delivery_days }} j</td>
                                    </tr>
                                @endif
                                @if ($ordre->pickup_date)
                                    <tr>
                                        <td style="padding:4px 0; color:#94a3b8;">Chargement</td>
                                        <td style="padding:4px 0;">{{ $ordre->pickup_date->format('d/m/Y à H\hi') }}</td>
                                    </tr>
                                @endif
                                @if ($ordre->requested_delivery_date)
                                    <tr>
                                        <td style="padding:4px 0; color:#94a3b8;">Livraison souhaitée</td>
                                        <td style="padding:4px 0;">{{ $ordre->requested_delivery_date->format('d/m/Y') }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 0; color:#94a3b8; border-top:1px solid #e2e8f0;">Prix estimé</td>
                                    <td style="padding:12px 0; border-top:1px solid #e2e8f0; color:#14324F; font-size:16px; font-weight:bold;">{{ number_format($ordre->estimated_cost, 2, ',', ' ') }} €</td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Ce code est strictement personnel : ne le partagez qu'avec les personnes autorisées à consulter cet envoi.
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
