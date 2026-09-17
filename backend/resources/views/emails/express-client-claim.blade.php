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
                            <p style="margin:0 0 16px;">Bonjour {{ $user->name }},</p>

                            <p style="margin:0 0 16px;">
                                Un garage a créé un compte Make Cars pour vous lors d'une de vos visites. Vous pouvez
                                désormais récupérer ce compte en définissant votre mot de passe : vous aurez ensuite
                                accès à l'application mobile pour suivre vos rendez-vous, devis et factures.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" bgcolor="#2563eb" style="border-radius:6px;">
                                        <a href="{{ $claimUrl }}"
                                           style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:6px; background:#2563eb;">
                                            Définir mon mot de passe
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#6b7280; font-size:12px;">
                                Ce lien est personnel et valable 7 jours. Si vous n'êtes pas à l'origine de cette
                                demande, vous pouvez ignorer cet email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
