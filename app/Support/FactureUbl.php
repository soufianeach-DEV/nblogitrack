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

        $x->startElement('Invoice');
        $x->writeAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $x->writeAttribute('xmlns:cbc', self::CBC);
        $x->writeAttribute('xmlns:cac', self::CAC);

        self::texte($x, 'cbc:CustomizationID', self::CUSTOMISATION);
        self::texte($x, 'cbc:ProfileID', self::PROFIL);
        self::texte($x, 'cbc:ID', $facture->reference);
        self::texte($x, 'cbc:IssueDate', $facture->issued_on->format('Y-m-d'));
        self::texte($x, 'cbc:DueDate', $facture->due_on->format('Y-m-d'));
        self::texte($x, 'cbc:InvoiceTypeCode', '380');
        self::texte($x, 'cbc:Note', 'Prestations de transport du '
            .$facture->period_start->format('d/m/Y').' au '.$facture->period_end->format('d/m/Y'));
        self::texte($x, 'cbc:DocumentCurrencyCode', 'EUR');
        self::texte($x, 'cbc:BuyerReference', $facture->reference);

        self::periode($x, $facture);
        self::partie($x, 'cac:AccountingSupplierParty', [
            'peppol' => config('entreprise.peppol'),
            'nom' => config('entreprise.nom'),
            'rue' => config('entreprise.adresse'),
            'localite' => config('entreprise.localite'),
            'pays' => 'BE',
            'tva' => str_replace([' ', '.'], '', config('entreprise.tva')),
        ]);
        self::partie($x, 'cac:AccountingCustomerParty', [
            'peppol' => $facture->client->peppol_id,
            'nom' => $facture->client->company_name,
            'rue' => $facture->client->billing_address,
            'localite' => trim($facture->client->postal_code.' '.$facture->client->city),
            'pays' => Pays::code($facture->client->country),
            'tva' => str_replace([' ', '.'], '', (string) $facture->client->vat_number),
        ]);

        self::reglement($x, $facture);
        self::taxes($x, $facture);
        self::totaux($x, $facture);

        foreach ($facture->lines as $rang => $ligne) {
            self::ligne($x, $facture, $ligne, $rang + 1);
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
        [$scheme, $identifiant] = self::peppol($partie['peppol']);

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

        $x->startElement('cac:TaxSubtotal');
        self::montant($x, 'cbc:TaxableAmount', (float) $facture->amount_excl_tax);
        self::montant($x, 'cbc:TaxAmount', (float) $facture->vat_amount);
        self::categorie($x, $facture, avecMotif: true);
        $x->endElement();

        $x->endElement();
    }

    private static function categorie(XMLWriter $x, Invoice $facture, bool $avecMotif): void
    {
        $x->startElement('cac:TaxCategory');
        self::texte($x, 'cbc:ID', $facture->reverse_charge ? 'AE' : 'S');
        self::texte($x, 'cbc:Percent', number_format((float) $facture->vat_rate, 2, '.', ''));

        if ($avecMotif && $facture->reverse_charge) {
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

    private static function ligne(XMLWriter $x, Invoice $facture, InvoiceLine $ligne, int $rang): void
    {
        $x->startElement('cac:InvoiceLine');
        self::texte($x, 'cbc:ID', (string) $rang);

        $x->startElement('cbc:InvoicedQuantity');
        $x->writeAttribute('unitCode', 'C62');
        $x->text('1');
        $x->endElement();

        self::montant($x, 'cbc:LineExtensionAmount', (float) $ligne->amount_excl_tax);

        $x->startElement('cac:Item');
        self::texte($x, 'cbc:Name', mb_substr($ligne->description, 0, 200));
        self::categorie($x, $facture, avecMotif: false);
        $x->endElement();

        $x->startElement('cac:Price');
        self::montant($x, 'cbc:PriceAmount', (float) $ligne->amount_excl_tax);
        $x->endElement();

        $x->endElement();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function peppol(?string $peppol): array
    {
        if ($peppol && str_contains($peppol, ':')) {
            [$scheme, $identifiant] = explode(':', $peppol, 2);

            return [$scheme, $identifiant];
        }

        return ['9925', (string) $peppol];
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
