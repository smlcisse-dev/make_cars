{{-- Gabarit commun des emails du parcours d'inscription professionnelle
     (CLAUDE.md §5, ajout v0.26) : même mise en forme sobre, inline, que les
     autres emails (express-client-claim, quote-decision). --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px; max-width:480px;">
                    <tr>
                        <td>
                            @yield('content')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
