<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $lien }}"
               style="display:inline-block; background-color:#F59E0B; color:#001D36; font-size:15px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:8px;">
                {{ $libelle }}
            </a>
            @isset($aide)
                <p style="margin:12px 0 0; font-size:11px;">
                    <a href="{{ $lien }}" style="color:#0B61A1; text-decoration:underline;">{{ $aide }}</a>
                </p>
            @endisset
        </td>
    </tr>
</table>
