<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1f2937;">
    @php
        $quote = $version->quote;
        $garage = $quote->garage;
        $client = $quote->user;
        $documentLabel = $version->version === 1 ? 'devis' : "devis (version {$version->version})";
    @endphp

    <p>Bonjour {{ $client->name }},</p>

    <p>
        Le garage <strong>{{ $garage->name }}</strong> vient de vous envoyer un {{ $documentLabel }}
        d'un montant total de {{ number_format((float) $version->total(), 0, ',', ' ') }} FCFA.
    </p>

    <p>Vous pouvez consulter ce devis et donner votre réponse directement depuis cet email :</p>

    <p>
        <a href="{{ $acceptUrl }}" style="display:inline-block; padding:10px 18px; background:#16a34a; color:#fff; text-decoration:none; border-radius:6px; margin-right:8px;">
            Accepter le devis
        </a>
        <a href="{{ $rejectUrl }}" style="display:inline-block; padding:10px 18px; background:#dc2626; color:#fff; text-decoration:none; border-radius:6px;">
            Refuser le devis
        </a>
    </p>

    <p style="color:#6b7280; font-size:12px;">
        Ce lien est personnel et valable 7 jours. Si vous n'êtes pas à l'origine de cette demande,
        vous pouvez ignorer cet email.
    </p>
</body>
</html>
