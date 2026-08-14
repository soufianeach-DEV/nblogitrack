<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $facture->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; line-height: 1.5; }
        .entete { background: #14324F; color: #ffffff; padding: 30px 40px; }
        .entete table { width: 100%; }
        .marque { font-size: 21px; font-weight: bold; letter-spacing: 2px; }
        .marque-sous { font-size: 8px; letter-spacing: 3px; color: #cbd5e1; text-transform: uppercase; }
        .doc-titre { font-size: 17px; font-weight: bold; text-align: right; }
        .doc-ref { font-size: 12px; text-align: right; color: #cbd5e1; }
        .corps { padding: 48px 40px; }
        .blocs { width: 100%; margin-bottom: 38px; }
        .blocs td { vertical-align: top; width: 33%; padding-right: 20px; }
        .bloc-titre { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; }
        .bloc p { margin-bottom: 4px; }
        .gras { font-weight: bold; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .lignes { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .lignes th { background: #f1f5f9; text-align: left; font-size: 8px; text-transform: uppercase;
            letter-spacing: 1px; color: #475569; padding: 9px 12px; }
        .lignes th.droite, .lignes td.droite { text-align: right; }
        .lignes td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .totaux { width: 270px; margin-left: auto; margin-top: 18px; border-collapse: collapse; }
        .totaux td { padding: 6px 12px; }
        .totaux .droite { text-align: right; }
        .totaux .ttc td { border-top: 2px solid #14324F; font-weight: bold; font-size: 12px; padding-top: 10px; }
        .paiement { background: #14324F; color: #ffffff; margin-top: 30px; padding: 18px 22px; }
        .paiement table { width: 100%; }
        .paiement td { vertical-align: middle; }
        .paiement .etiquette { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px; }
        .paiement .valeur { font-size: 11px; font-weight: bold; }
        .qr-boite { background: #ffffff; padding: 6px; display: inline-block; }
        .qr-boite img { width: 74px; height: 74px; display: block; }
        .mention { margin-top: 18px; font-size: 8.5px; color: #64748b; line-height: 1.6; }
        .pied { position: fixed; bottom: 24px; left: 40px; right: 40px; font-size: 8px; color: #94a3b8;
            border-top: 1px solid #e2e8f0; padding-top: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="entete">
        <table>
            <tr>
                <td>
                    <div class="marque">NBLOGITRACK</div>
                    <div class="marque-sous">Logistique B2B</div>
                </td>
                <td>
                    <div class="doc-titre">FACTURE</div>
                    <div class="doc-ref">{{ $facture->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="corps">
        <table class="blocs">
            <tr>
                <td class="bloc">
                    <div class="bloc-titre">Émetteur</div>
                    <p class="gras">{{ config('entreprise.nom') }}</p>
                    <p>{{ config('entreprise.adresse') }}</p>
                    <p>{{ config('entreprise.localite') }}, {{ config('entreprise.pays') }}</p>
                    <p class="mono">{{ config('entreprise.tva') }}</p>
                </td>
                <td class="bloc">
                    <div class="bloc-titre">Facturé à</div>
                    <p class="gras">{{ $facture->client->company_name }}</p>
                    @if ($facture->client->billing_address)
                        <p>{{ $facture->client->billing_address }}</p>
                    @endif
                    <p>{{ trim($facture->client->postal_code.' '.$facture->client->city) }}</p>
                    <p>{{ $facture->client->country }}</p>
                    <p class="mono">{{ $facture->client->vat_number }}</p>
                </td>
                <td class="bloc">
                    <div class="bloc-titre">Détails</div>
                    <p>Émise le <span class="gras">{{ $facture->issued_on->format('d/m/Y') }}</span></p>
                    <p>Échéance le <span class="gras">{{ $facture->due_on->format('d/m/Y') }}</span></p>
                    <p>Période du {{ $facture->period_start->format('d/m/Y') }}</p>
                    <p>au {{ $facture->period_end->format('d/m/Y') }}</p>
                    @if ($facture->paid_on)
                        <p>Payée le <span class="gras">{{ $facture->paid_on->format('d/m/Y') }}</span></p>
                    @endif
                </td>
            </tr>
        </table>

        <table class="lignes">
            <thead>
                <tr>
                    <th>Expédition</th>
                    <th>Prestation</th>
                    <th class="droite">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($facture->lines as $ligne)
                    <tr>
                        <td class="mono">{{ $ligne->transportOrder?->tracking_number ?? '—' }}</td>
                        <td>
                            @if (str_starts_with($ligne->description, 'Transport '))
                                <span class="gras">Transport</span>{{ substr($ligne->description, 9) }}
                            @else
                                {{ $ligne->description }}
                            @endif
                        </td>
                        <td class="droite">{{ number_format((float) $ligne->amount_excl_tax, 2, ',', ' ') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totaux">
            <tr>
                <td>Total HT</td>
                <td class="droite">{{ number_format((float) $facture->amount_excl_tax, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td>TVA {{ number_format((float) $facture->vat_rate, 2, ',', ' ') }} %</td>
                <td class="droite">{{ number_format((float) $facture->vat_amount, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="ttc">
                <td>Total TTC</td>
                <td class="droite">{{ number_format((float) $facture->amount_incl_tax, 2, ',', ' ') }} €</td>
            </tr>
        </table>

        <div class="paiement">
            <table>
                <tr>
                    <td>
                        <div class="etiquette">Compte</div>
                        <div class="valeur mono">{{ config('entreprise.iban') }}</div>
                        <div class="etiquette" style="margin-top: 9px;">Communication structurée</div>
                        <div class="valeur mono">{{ $facture->payment_reference }}</div>
                    </td>
                    <td style="text-align: right; padding-right: 18px;">
                        <div class="etiquette">
                            @if ($facture->paid_on)
                                Paiement reçu
                            @else
                                À payer pour le {{ $facture->due_on->format('d/m/Y') }}
                            @endif
                        </div>
                        <div class="valeur" style="font-size: 15px;">{{ number_format((float) $facture->amount_incl_tax, 2, ',', ' ') }} €</div>
                    </td>
                    @if ($qr ?? null)
                        <td style="width: 88px; text-align: right;">
                            <div class="qr-boite">
                                <img src="{{ $qr }}" alt="QR de virement SEPA au format EPC">
                            </div>
                            <div style="font-size: 7px; color: #94a3b8; margin-top: 3px;">
                                Virement SEPA — norme EPC
                            </div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        @if ($facture->reverse_charge)
            <p class="mention">
                Autoliquidation — TVA due par le preneur (art. 21, §2 du Code de la TVA ; art. 44 de la directive 2006/112/CE).
            </p>
        @endif

        <p class="mention">
            Paiement au comptant sauf convention contraire. À défaut de paiement à l'échéance, intérêts de retard
            conformément à la loi du 2 août 2002 concernant la lutte contre le retard de paiement dans les transactions commerciales.
        </p>
    </div>

    <div class="pied">
        {{ config('entreprise.nom') }} — {{ config('entreprise.adresse') }}, {{ config('entreprise.localite') }} — {{ config('entreprise.tva') }}
    </div>
</body>
</html>
