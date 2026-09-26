<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use XMLWriter;

class FactureUbl
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CUSTOMISATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private const PROFIL = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    public static function pour(Invoice $facture): string
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->setIndentString('  ');
        $x->startDocument('1.0', 'UTF-8');

        // Un avoir est un document CreditNote, qui renvoie a la facture
        // qu'il annule (BillingReference).
        $avoir = $facture->estAvoir();
        $racine = $avoir ? 'CreditNote' : 'Invoice';

        $x->startElement($racine);
        $x->writeAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:'.$racine.'-2');
        $x->writeAttribute('xmlns:cbc', self::CBC);
        $x->writeAttribute('xmlns:cac', self::CAC);

        self::texte($x, 'cbc:CustomizationID', self::CUSTOMISATION);
        self::texte($x, 'cbc:ProfileID', self::PROFIL);
        self::texte($x, 'cbc:ID', $facture->reference);
        self::texte($x, 'cbc:IssueDate', $facture->issued_on->format('Y-m-d'));

        if (! $avoir) {
            self::texte($x, 'cbc:DueDate', $facture->due_on->format('Y-m-d'));
        }

        self::texte($x, $avoir ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode', $avoir ? '381' : '380');
        self::texte($x, 'cbc:Note', $avoir
            ? 'Avoir annulant la facture '.$facture->creditedInvoice?->reference.' : '.$facture->credit_reason
            : 'Prestations de transport du '
                .$facture->period_start->format('d/m/Y').' au '.$facture->period_end->format('d/m/Y'));
        self::texte($x, 'cbc:DocumentCurrencyCode', 'EUR');
        self::texte($x, 'cbc:BuyerReference', $facture->reference);

        self::periode($x, $facture);

        if ($avoir && $facture->creditedInvoice) {
            $x->startElement('cac:BillingReference');
            $x->startElement('cac:InvoiceDocumentReference');
            self::texte($x, 'cbc:ID', $facture->creditedInvoice->reference);
            self::texte($x, 'cbc:IssueDate', $facture->creditedInvoice->issued_on->format('Y-m-d'));
            $x->endElement();
            $x->endElement();
        }

        self::partie($x, 'cac:AccountingSupplierParty', [
            'peppol' => config('entreprise.peppol'),
            'nom' => config('entreprise.nom'),
            'rue' => config('entreprise.adresse'),
            'code_postal' => strtok((string) config('entreprise.localite'), ' '),
            'localite' => trim((string) strstr((string) config('entreprise.localite'), ' ')),
            'pays' => 'BE',
            'tva' => str_replace([' ', '.'], '', config('entreprise.tva')),
        ]);
        // L'acheteur tel qu'il etait a l'emission, fige sur la facture.
        self::partie($x, 'cac:AccountingCustomerParty', [
            'peppol' => $facture->buyer_peppol_id,
            'nom' => $facture->buyer_name,
            'rue' => $facture->buyer_address,
            'code_postal' => $facture->buyer_postal_code,
            'localite' => $facture->buyer_city,
            'pays' => $facture->buyer_country ?? 'BE',
            'tva' => str_replace([' ', '.'], '', (string) $facture->buyer_vat_number),
        ]);

        if (! $avoir) {
            self::reglement($x, $facture);
        }

        self::taxes($x, $facture);
        self::totaux($x, $facture);

        foreach ($facture->lines as $rang => $ligne) {
            self::ligne($x, $ligne, $rang + 1, $avoir);
        }

        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }

    private static function periode(XMLWriter $x, Invoice $facture): void
    {
        $x->startElement('cac:InvoicePeriod');
        self::texte($x, 'cbc:StartDate', $facture->period_start->format('Y-m-d'));
        self::texte($x, 'cbc:EndDate', $facture->period_end->format('Y-m-d'));
        $x->endElement();
    }

    /**
     * @param  array<string, string|null>  $partie
     */
    private static function partie(XMLWriter $x, string $balise, array $partie): void
    {
        [$scheme, $identifiant] = self::peppol($partie['peppol'], $partie['tva'], $partie['pays']);

        $x->startElement($balise);
        $x->startElement('cac:Party');

        $x->startElement('cbc:EndpointID');
        $x->writeAttribute('schemeID', $scheme);
        $x->text($identifiant);
        $x->endElement();

        $x->startElement('cac:PartyName');
        self::texte($x, 'cbc:Name', (string) $partie['nom']);
        $x->endElement();

        $x->startElement('cac:PostalAddress');
        self::texte($x, 'cbc:StreetName', (string) $partie['rue']);
        self::texte($x, 'cbc:CityName', (string) $partie['localite']);
        // Le code postal a son propre champ : colle a la ville, il faisait
        // echouer la validation EN 16931 de l'adresse.
        self::texte($x, 'cbc:PostalZone', (string) $partie['code_postal']);
        $x->startElement('cac:Country');
        self::texte($x, 'cbc:IdentificationCode', $partie['pays']);
        $x->endElement();
        $x->endElement();

        $x->startElement('cac:PartyTaxScheme');
        self::texte($x, 'cbc:CompanyID', (string) $partie['tva']);
        $x->startElement('cac:TaxScheme');
        self::texte($x, 'cbc:ID', 'VAT');
        $x->endElement();
        $x->endElement();

        $x->startElement('cac:PartyLegalEntity');
        self::texte($x, 'cbc:RegistrationName', (string) $partie['nom']);

        // Numero d'entreprise belge (BCE, schema 0208) : exige pour une
        // societe belge par les regles Peppol BIS de la Belgique.
        if ($partie['pays'] === 'BE' && preg_match('/^BE(\d{10})$/', (string) $partie['tva'], $m)) {
            $x->startElement('cbc:CompanyID');
            $x->writeAttribute('schemeID', '0208');
            $x->text($m[1]);
            $x->endElement();
        }

        $x->endElement();

        $x->endElement();
        $x->endElement();
    }

    private static function reglement(XMLWriter $x, Invoice $facture): void
    {
        $x->startElement('cac:PaymentMeans');
        self::texte($x, 'cbc:PaymentMeansCode', '30');
        self::texte($x, 'cbc:PaymentID', $facture->payment_reference);
        $x->startElement('cac:PayeeFinancialAccount');
        self::texte($x, 'cbc:ID', str_replace(' ', '', config('entreprise.iban')));
        $x->endElement();
        $x->endElement();

        $x->startElement('cac:PaymentTerms');
        self::texte($x, 'cbc:Note', 'Paiement pour le '.$facture->due_on->format('d/m/Y'));
        $x->endElement();
    }

    private static function taxes(XMLWriter $x, Invoice $facture): void
    {
        $x->startElement('cac:TaxTotal');
        self::montant($x, 'cbc:TaxAmount', (float) $facture->vat_amount);

        // Un sous-total par categorie et par taux (EN 16931, BG-23).
        $groupes = $facture->lines->groupBy(fn (InvoiceLine $l) => $l->vat_category.'|'.number_format((float) $l->vat_rate, 2, '.', ''));

        foreach ($groupes as $cle => $lignes) {
            [$categorie, $taux] = explode('|', $cle);
            $base = round((float) $lignes->sum('amount_excl_tax'), 2);

            $x->startElement('cac:TaxSubtotal');
            self::montant($x, 'cbc:TaxableAmount', $base);
            self::montant($x, 'cbc:TaxAmount', $groupes->count() === 1 ? (float) $facture->vat_amount : round($base * (float) $taux / 100, 2));
            self::categorie($x, $categorie, (float) $taux, avecMotif: true);
            $x->endElement();
        }

        $x->endElement();
    }

    private static function categorie(XMLWriter $x, string $categorie, float $taux, bool $avecMotif): void
    {
        $x->startElement('cac:TaxCategory');
        self::texte($x, 'cbc:ID', $categorie);

        // Hors de l'Union, la prestation est hors du champ de la TVA belge
        // (categorie O, sans taux) ; l'autoliquidation intracommunautaire
        // (AE) ne s'applique qu'a un preneur etabli dans un autre Etat
        // membre.
        if ($categorie !== 'O') {
            self::texte($x, 'cbc:Percent', number_format($taux, 2, '.', ''));
        }

        if ($avecMotif && $categorie === 'O') {
            self::texte($x, 'cbc:TaxExemptionReasonCode', 'VATEX-EU-O');
            self::texte($x, 'cbc:TaxExemptionReason', 'Prestation hors du champ de la TVA belge (art. 21, §2 du Code de la TVA)');
        } elseif ($avecMotif && $categorie === 'AE') {
            self::texte($x, 'cbc:TaxExemptionReasonCode', 'VATEX-EU-AE');
            self::texte($x, 'cbc:TaxExemptionReason', 'Autoliquidation — TVA due par le preneur');
        }

        $x->startElement('cac:TaxScheme');
        self::texte($x, 'cbc:ID', 'VAT');
        $x->endElement();
        $x->endElement();
    }

    private static function totaux(XMLWriter $x, Invoice $facture): void
    {
        $x->startElement('cac:LegalMonetaryTotal');
        self::montant($x, 'cbc:LineExtensionAmount', (float) $facture->amount_excl_tax);
        self::montant($x, 'cbc:TaxExclusiveAmount', (float) $facture->amount_excl_tax);
        self::montant($x, 'cbc:TaxInclusiveAmount', (float) $facture->amount_incl_tax);
        self::montant($x, 'cbc:PayableAmount', (float) $facture->amount_incl_tax);
        $x->endElement();
    }

    private static function ligne(XMLWriter $x, InvoiceLine $ligne, int $rang, bool $avoir): void
    {
        $x->startElement($avoir ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        self::texte($x, 'cbc:ID', (string) $rang);

        $x->startElement($avoir ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity');
        $x->writeAttribute('unitCode', 'C62');
        $x->text(rtrim(rtrim(number_format((float) $ligne->quantity, 2, '.', ''), '0'), '.'));
        $x->endElement();

        self::montant($x, 'cbc:LineExtensionAmount', (float) $ligne->amount_excl_tax);

        $x->startElement('cac:Item');
        self::texte($x, 'cbc:Name', mb_substr($ligne->description, 0, 200));
        self::categorie($x, (string) $ligne->vat_category, (float) $ligne->vat_rate, avecMotif: false);
        $x->endElement();

        $x->startElement('cac:Price');
        self::montant($x, 'cbc:PriceAmount', (float) ($ligne->unit_price ?? $ligne->amount_excl_tax));
        $x->endElement();

        $x->endElement();
    }

    /**
     * @return array{0: string, 1: string}
     */
    /*
     * Schemas Peppol des numeros de TVA, pour un acheteur sans identifiant
     * Peppol enregistre : un EndpointID vide rend le fichier invalide.
     */
    private const SCHEMAS_TVA = [
        'BE' => '9925', 'FR' => '9957', 'NL' => '9944', 'DE' => '9930', 'LU' => '9938',
        'IT' => '9906', 'ES' => '9920', 'AT' => '9914', 'PT' => '9946', 'IE' => '9935',
    ];

    private static function peppol(?string $peppol, ?string $tva = null, ?string $pays = null): array
    {
        if ($peppol && str_contains($peppol, ':')) {
            [$scheme, $identifiant] = explode(':', $peppol, 2);

            return [$scheme, $identifiant];
        }

        if ($peppol) {
            return ['9925', $peppol];
        }

        return [self::SCHEMAS_TVA[strtoupper((string) $pays)] ?? '9925', (string) $tva];
    }

    private static function texte(XMLWriter $x, string $balise, string $valeur): void
    {
        $x->startElement($balise);
        $x->text($valeur);
        $x->endElement();
    }

    private static function montant(XMLWriter $x, string $balise, float $valeur): void
    {
        $x->startElement($balise);
        $x->writeAttribute('currencyID', 'EUR');
        $x->text(number_format($valeur, 2, '.', ''));
        $x->endElement();
    }
}
