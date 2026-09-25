@php($t = \App\Support\Traductions::class)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre')</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FA; font-family:'Inter', Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#14324F; padding:24px 32px;">
                            <img src="{{ asset('images/logo-blanc.png') }}" alt="NB LOGITRACK" width="170" style="display:block; max-width:170px; height:auto;">
                            <span style="color:#9fb3c8; font-size:11px; letter-spacing:1px; display:block; margin-top:8px;">{{ $t::t('courriel.slogan', 'LOGISTIQUE B2B') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('contenu')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F7FA; padding:16px 32px; color:#94a3b8; font-size:11px;">
                            © {{ date('Y') }} NBLogiTrack Belgium — @hasSection('pied')@yield('pied')@else{{ $t::t('courriel.pied_automatique', 'cet e-mail a été envoyé automatiquement, merci de ne pas y répondre.') }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
