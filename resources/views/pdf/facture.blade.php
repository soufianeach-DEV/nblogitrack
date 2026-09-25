@php($t = \App\Support\Traductions::class)
@php($f = \App\Support\Formats::class)
@php($pays = \App\Support\Pays::class)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
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
        /* 99 px a 96 dpi font 26 mm de cote : la norme EPC exige au moins
           25 mm sur papier, sous quoi le code devient illisible. */
        .qr-boite img { width: 99px; height: 99px; display: block; }
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
                    <div class="marque-sous">{{ $t::t('pdf.slogan', 'Logistique B2B') }}</div>
                </td>
                <td>
                    <div class="doc-titre">{{ $t::t('pdf.facture', 'FACTURE') }}</div>
                    <div class="doc-ref">{{ $facture->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="corps">
        <table class="blocs">
            <tr>
                <td class="bloc">
                    <div class="bloc-titre">{{ $t::t('pdf.emetteur', 'Émetteur') }}</div>
                    <p class="gras">{{ config('entreprise.nom') }}</p>
                    <p>{{ config('entreprise.adresse') }}</p>
                    <p>{{ config('entreprise.localite') }}, {{ $pays::localise(config('entreprise.pays')) }}</p>
                    <p class="mono">{{ config('entreprise.tva') }}</p>
                </td>
                <td class="bloc">
                    <div class="bloc-titre">{{ $t::t('pdf.facture_a', 'Facturé à') }}</div>
                    <p class="gras">{{ $facture->client->company_name }}</p>
                    @if ($facture->client->billing_address)
                        <p>{{ $facture->client->billing_address }}</p>
                    @endif
                    <p>{{ trim($facture->client->postal_code.' '.$facture->client->city) }}</p>
                    <p>{{ $pays::localise($facture->client->country) }}</p>
                    <p class="mono">{{ $facture->client->vat_number }}</p>
                </td>
                <td class="bloc">
                    <div class="bloc-titre">{{ $t::t('pdf.details', 'Détails') }}</div>
                    <p>{{ $t::t('pdf.emise_le', 'Émise le') }} <span class="gras">{{ $f::date($facture->issued_on) }}</span></p>
                    <p>{{ $t::t('pdf.echeance_le', 'Échéance le') }} <span class="gras">{{ $f::date($facture->due_on) }}</span></p>
                    <p>{{ $t::t('pdf.periode_du', 'Période du :date', ['date' => $f::date($facture->period_start)]) }}</p>
                    <p>{{ $t::t('pdf.periode_au', 'au :date', ['date' => $f::date($facture->period_end)]) }}</p>
                    @if ($facture->paid_on)
                        <p>{{ $t::t('pdf.payee_le', 'Payée le') }} <span class="gras">{{ $f::date($facture->paid_on) }}</span></p>
                    @endif
                </td>
            </tr>
        </table>

        <table class="lignes">
            <thead>
                <tr>
                    <th>{{ $t::t('pdf.expedition', 'Expédition') }}</th>
                    <th>{{ $t::t('pdf.prestation', 'Prestation') }}</th>
                    <th class="droite">{{ $t::t('pdf.montant_ht', 'Montant HT') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($facture->lines as $ligne)
                    <tr>
                        <td class="mono">{{ $ligne->transportOrder?->tracking_number ?? '—' }}</td>
                        <td>
                            {{-- La description est enregistree en francais,
                                 « Transport <depart> vers <arrivee> » : on la
                                 recompose dans la langue du lecteur. --}}
                            @if (str_starts_with($ligne->description, 'Transport ') && str_contains($ligne->description, ' vers '))
                                @php([$depart, $arrivee] = explode(' vers ', substr($ligne->description, 10), 2))
                                <span class="gras">{{ $t::t('pdf.transport', 'Transport') }}</span>
                                {{ $t::t('pdf.trajet', ':depart vers :arrivee', ['depart' => $depart, 'arrivee' => $arrivee]) }}
                            @else
                                {{ $ligne->description }}
                            @endif
                        </td>
                        <td class="droite">{{ $f::montant($ligne->amount_excl_tax) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totaux">
            <tr>
                <td>{{ $t::t('pdf.total_ht', 'Total HT') }}</td>
                <td class="droite">{{ $f::montant($facture->amount_excl_tax) }}</td>
            </tr>
            <tr>
                <td>{{ $t::t('pdf.tva', 'TVA :taux %', ['taux' => $f::nombre($facture->vat_rate, 2)]) }}</td>
                <td class="droite">{{ $f::montant($facture->vat_amount) }}</td>
            </tr>
            <tr class="ttc">
                <td>{{ $t::t('pdf.total_ttc', 'Total TTC') }}</td>
                <td class="droite">{{ $f::montant($facture->amount_incl_tax) }}</td>
            </tr>
        </table>

        <div class="paiement">
            <table>
                <tr>
                    <td>
                        <div class="etiquette">{{ $t::t('pdf.compte', 'Compte') }}</div>
                        <div class="valeur mono">{{ config('entreprise.iban') }}</div>
                        <div class="etiquette" style="margin-top: 9px;">{{ $t::t('pdf.communication', 'Communication structurée') }}</div>
                        <div class="valeur mono">{{ $facture->payment_reference }}</div>
                    </td>
                    <td style="text-align: right; padding-right: 18px;">
                        <div class="etiquette">
                            @if ($facture->paid_on)
                                {{ $t::t('pdf.paiement_recu', 'Paiement reçu') }}
                            @else
                                {{ $t::t('pdf.a_payer_pour', 'À payer pour le :date', ['date' => $f::date($facture->due_on)]) }}
                            @endif
                        </div>
                        <div class="valeur" style="font-size: 15px;">{{ $f::montant($facture->amount_incl_tax) }}</div>
                    </td>
                    @if ($qr ?? null)
                        <td style="width: 113px; text-align: right;">
                            <div class="qr-boite">
                                <img src="{{ $qr }}" alt="{{ $t::t('pdf.qr_alt', 'QR de virement SEPA au format EPC') }}">
                            </div>
                            <div style="font-size: 7px; color: #94a3b8; margin-top: 3px;">
                                {{ $t::t('pdf.qr_legende', 'Virement SEPA — norme EPC') }}
                            </div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        @if ($facture->reverse_charge)
            <p class="mention">
                {{ $t::t('pdf.autoliquidation', 'Autoliquidation — TVA due par le preneur (art. 21, §2 du Code de la TVA ; art. 44 de la directive 2006/112/CE).') }}
            </p>
        @endif

        <p class="mention">
            {{ $t::t('pdf.conditions', 'Paiement au comptant sauf convention contraire. À défaut de paiement à l\'échéance, intérêts de retard conformément à la loi du 2 août 2002 concernant la lutte contre le retard de paiement dans les transactions commerciales.') }}
        </p>
    </div>

    <div class="pied">
        {{ config('entreprise.nom') }} — {{ config('entreprise.adresse') }}, {{ config('entreprise.localite') }} — {{ config('entreprise.tva') }}
    </div>
</body>
</html>
