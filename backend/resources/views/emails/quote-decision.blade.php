<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    @php
        $quote = $version->quote;
        $garage = $quote->garage;
        $client = $quote->user;
        $documentLabel = $version->version === 1 ? 'devis' : "devis (version {$version->version})";
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px; max-width:480px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 16px;">Bonjour {{ $client->name }},</p>

                            <p style="margin:0 0 16px;">
                                Le garage <strong>{{ $garage->name }}</strong> vient de vous envoyer un {{ $documentLabel }}
                                d'un montant total de {{ number_format((float) $version->total(), 0, ',', ' ') }} FCFA.
                                Vous le trouverez en pièce jointe (PDF) à cet email.
                            </p>

                            <p style="margin:0 0 24px;">Vous pouvez donner votre réponse directement depuis cet email :</p>

                            <!-- Boutons (table-based pour compatibilité maximale avec les clients mail) -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" bgcolor="#16a34a" style="border-radius:6px;">
                                        <a href="{{ $acceptUrl }}"
                                           style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:6px; background:#16a34a;">
                                            ✓ Accepter le devis
                                        </a>
                                    </td>
                                    <td style="width:16px;">&nbsp;</td>
                                    <td align="center" bgcolor="#dc2626" style="border-radius:6px;">
                                        <a href="{{ $rejectUrl }}"
                                           style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:6px; background:#dc2626;">
                                            ✗ Refuser le devis
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#6b7280; font-size:12px;">
                                Ce lien est personnel et valable 7 jours. Si vous n'êtes pas à l'origine de cette demande,
                                vous pouvez ignorer cet email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
