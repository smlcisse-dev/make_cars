<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    @php
        $founded = $dispute->status === \App\Enums\DisputeStatus::ResolvedFounded;
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px; max-width:480px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 16px;">Bonjour {{ $dispute->user->name }},</p>

                            <p style="margin:0 0 16px;">
                                Votre réclamation concernant <strong>{{ $dispute->respondent->name }}</strong> a été
                                examinée par notre équipe.
                            </p>

                            <p style="margin:0 0 16px;">
                                Décision :
                                <strong style="color: {{ $founded ? '#16a34a' : '#dc2626' }};">
                                    {{ $founded ? 'Réclamation fondée' : 'Réclamation rejetée' }}
                                </strong>
                            </p>

                            <p style="margin:0; color:#374151;">{{ $dispute->resolution_reason }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
