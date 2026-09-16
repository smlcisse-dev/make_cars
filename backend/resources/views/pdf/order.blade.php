<!DOCTYPE html>
{{--
    Vue interne, jamais servie en HTTP (API REST pure — CLAUDE.md §4) :
    consommée uniquement par dompdf. Même structure que pdf.quote, mais
    $seller peut être un Garage (mini-boutique) ou un MarketSpaceAccount —
    les deux exposent name/address/phone (CLAUDE.md §5, ajout v0.9).
--}}
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .header table { width: 100%; }
        .header td { vertical-align: top; width: 50%; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.lines th, table.lines td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f4f6; }
        table.lines td.amount, table.lines th.amount { text-align: right; }
        .total-row td { font-weight: bold; }
        .meta { margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Facture</h1>
    <p class="muted">
        Facture n° {{ $order->id }} — {{ $order->paid_at?->translatedFormat('d/m/Y') }}
    </p>

    <div class="header">
        <table>
            <tr>
                <td>
                    <strong>Vendeur</strong><br>
                    {{ $seller->name }}<br>
                    {{ $seller->address }}<br>
                    @if($seller->phone)
                        Tél. {{ $seller->phone }}
                    @endif
                </td>
                <td>
                    <strong>Client</strong><br>
                    {{ $client->name }}<br>
                    @if($client->phone)
                        Tél. {{ $client->phone }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="lines">
        <thead>
        <tr>
            <th>Désignation</th>
            <th>Qté</th>
            <th class="amount">Prix unitaire (FCFA)</th>
            <th class="amount">Total (FCFA)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line->label }}</td>
                <td>{{ $line->quantity }}</td>
                <td class="amount">{{ number_format((float) $line->unit_price, 0, ',', ' ') }}</td>
                <td class="amount">{{ number_format((float) $line->line_total, 0, ',', ' ') }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">Total</td>
            <td class="amount">{{ number_format($total, 0, ',', ' ') }}</td>
        </tr>
        </tbody>
    </table>

    <p class="meta muted">Facture soldée — paiement enregistré le {{ $order->paid_at?->translatedFormat('d/m/Y') }}.</p>
</body>
</html>
