<?php

// L'identite de l'emetteur des factures : la page, le PDF et le XML Peppol
// puisent au meme endroit. Le numero d'entreprise porte une cle de controle
// valide, et l'IBAN est le compte d'essai officiel du format belge.
return [
    'nom' => 'NBLogiTrack SA',
    'adresse' => 'Avenue du Port 86C',
    'localite' => '1000 Bruxelles',
    'pays' => 'Belgique',
    'tva' => 'BE 0123.456.749',
    'iban' => 'BE68 5390 0754 7034',
    'peppol' => '0208:0123456749',

    // Le QR de virement suit la norme europeenne EPC. Les banques de ces pays
    // ne la lisent pas : elles ont leur propre schema, Payconiq en Belgique,
    // qui demande un contrat commercant. Afficher un code que la banque du
    // destinataire refuse vaut moins que ne rien afficher : la facture parait
    // cassee et le client perd confiance. Ils reglent en ligne ou recopient
    // le compte et la communication, qui figurent juste a cote.
    'epc_non_lu_par' => [
        'Belgique',
    ],
];
