<?php

namespace App\Support;

/**
 * Liste exhaustive des secteurs d'activite : les divisions de la
 * nomenclature europeenne NACE Rev. 2, regroupees par section. C'est le
 * code que publient les registres (BCE, INSEE, ARES, KRS, ANAF...) : un
 * numero de TVA verifie choisit donc directement le bon secteur.
 *
 * Le secteur est range en francais ; le neerlandais et l'anglais servent
 * a l'affichage (vocabulaire « secteur » des traductions).
 */
final class Secteurs
{
    /** Section => [fr, nl, en, divisions]. */
    private const SECTIONS = [
        'A' => ['Agriculture, sylviculture et pêche', 'Landbouw, bosbouw en visserij', 'Agriculture, forestry and fishing', ['01', '02', '03']],
        'B' => ['Industries extractives', 'Winning van delfstoffen', 'Mining and quarrying', ['05', '06', '07', '08', '09']],
        'C' => ['Industrie manufacturière', 'Industrie', 'Manufacturing', ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33']],
        'D' => ['Énergie', 'Energie', 'Energy', ['35']],
        'E' => ['Eau, déchets et dépollution', 'Water, afval en sanering', 'Water, waste and remediation', ['36', '37', '38', '39']],
        'F' => ['Construction', 'Bouwnijverheid', 'Construction', ['41', '42', '43']],
        'G' => ['Commerce', 'Handel', 'Trade', ['45', '46', '47', '4791']],
        'H' => ['Transport et entreposage', 'Vervoer en opslag', 'Transport and storage', ['49', '50', '51', '52', '53']],
        'I' => ['Hébergement et restauration', 'Verschaffen van accommodatie en maaltijden', 'Accommodation and food services', ['55', '56']],
        'J' => ['Information et communication', 'Informatie en communicatie', 'Information and communication', ['58', '59', '60', '61', '62', '63']],
        'K' => ['Finance et assurance', 'Financiële activiteiten en verzekeringen', 'Finance and insurance', ['64', '65', '66']],
        'L' => ['Immobilier', 'Exploitatie van en handel in onroerend goed', 'Real estate', ['68']],
        'M' => ['Activités spécialisées, scientifiques et techniques', 'Vrije beroepen en wetenschappelijke en technische activiteiten', 'Professional, scientific and technical activities', ['69', '70', '71', '72', '73', '74', '75']],
        'N' => ['Services administratifs et de soutien', 'Administratieve en ondersteunende diensten', 'Administrative and support services', ['77', '78', '79', '80', '81', '82']],
        'O' => ['Administration publique', 'Openbaar bestuur', 'Public administration', ['84']],
        'P' => ['Enseignement', 'Onderwijs', 'Education', ['85']],
        'Q' => ['Santé humaine et action sociale', 'Menselijke gezondheidszorg en maatschappelijke dienstverlening', 'Health and social work', ['86', '87', '88']],
        'R' => ['Arts, spectacles et loisirs', 'Kunst, amusement en recreatie', 'Arts, entertainment and recreation', ['90', '91', '92', '93']],
        'S' => ['Autres services', 'Overige diensten', 'Other services', ['94', '95', '96']],
        'T' => ['Ménages employeurs', 'Huishoudens als werkgever', 'Households as employers', ['97', '98']],
        'U' => ['Organisations extraterritoriales', 'Extraterritoriale organisaties', 'Extraterritorial organisations', ['99']],
    ];

    /** Division NACE => [fr, nl, en]. */
    private const DIVISIONS = [
        '01' => ['Culture, élevage et chasse', 'Teelt, veeteelt en jacht', 'Crop and animal production, hunting'],
        '02' => ['Sylviculture et exploitation forestière', 'Bosbouw en exploitatie van bossen', 'Forestry and logging'],
        '03' => ['Pêche et aquaculture', 'Visserij en aquacultuur', 'Fishing and aquaculture'],
        '05' => ['Extraction de charbon', 'Winning van steenkool en bruinkool', 'Mining of coal and lignite'],
        '06' => ['Extraction de pétrole et de gaz', 'Winning van aardolie en aardgas', 'Extraction of crude petroleum and natural gas'],
        '07' => ['Extraction de minerais métalliques', 'Winning van metaalertsen', 'Mining of metal ores'],
        '08' => ['Carrières et autres industries extractives', 'Overige winning van delfstoffen', 'Other mining and quarrying'],
        '09' => ['Services de soutien aux industries extractives', 'Diensten ten behoeve van de winning', 'Mining support services'],
        '10' => ['Industries alimentaires', 'Vervaardiging van voedingsmiddelen', 'Manufacture of food products'],
        '11' => ['Fabrication de boissons', 'Vervaardiging van dranken', 'Manufacture of beverages'],
        '12' => ['Industrie du tabac', 'Vervaardiging van tabaksproducten', 'Manufacture of tobacco products'],
        '13' => ['Fabrication de textiles', 'Vervaardiging van textiel', 'Manufacture of textiles'],
        '14' => ['Industrie de l\'habillement', 'Vervaardiging van kleding', 'Manufacture of wearing apparel'],
        '15' => ['Industrie du cuir et de la chaussure', 'Vervaardiging van leer en schoeisel', 'Manufacture of leather and footwear'],
        '16' => ['Travail du bois', 'Houtindustrie', 'Manufacture of wood products'],
        '17' => ['Industrie du papier et du carton', 'Vervaardiging van papier en karton', 'Manufacture of paper and paper products'],
        '18' => ['Imprimerie', 'Drukkerijen', 'Printing'],
        '19' => ['Raffinage du pétrole', 'Aardolieraffinage', 'Petroleum refining'],
        '20' => ['Industrie chimique', 'Chemische industrie', 'Manufacture of chemicals'],
        '21' => ['Industrie pharmaceutique', 'Farmaceutische industrie', 'Manufacture of pharmaceuticals'],
        '22' => ['Caoutchouc et plastique', 'Rubber- en kunststofindustrie', 'Manufacture of rubber and plastic products'],
        '23' => ['Verre, céramique et matériaux de construction', 'Glas, keramiek en bouwmaterialen', 'Glass, ceramics and building materials'],
        '24' => ['Métallurgie', 'Metallurgie', 'Manufacture of basic metals'],
        '25' => ['Fabrication de produits métalliques', 'Vervaardiging van producten van metaal', 'Manufacture of fabricated metal products'],
        '26' => ['Électronique et informatique (fabrication)', 'Vervaardiging van elektronica en computers', 'Manufacture of computers and electronics'],
        '27' => ['Fabrication d\'équipements électriques', 'Vervaardiging van elektrische apparatuur', 'Manufacture of electrical equipment'],
        '28' => ['Fabrication de machines et équipements', 'Vervaardiging van machines en werktuigen', 'Manufacture of machinery and equipment'],
        '29' => ['Industrie automobile', 'Automobielindustrie', 'Manufacture of motor vehicles'],
        '30' => ['Fabrication d\'autres matériels de transport', 'Vervaardiging van andere transportmiddelen', 'Manufacture of other transport equipment'],
        '31' => ['Fabrication de meubles', 'Vervaardiging van meubelen', 'Manufacture of furniture'],
        '32' => ['Autres industries manufacturières', 'Overige industrie', 'Other manufacturing'],
        '33' => ['Réparation et installation de machines', 'Reparatie en installatie van machines', 'Repair and installation of machinery'],
        '35' => ['Électricité, gaz et vapeur', 'Elektriciteit, gas en stoom', 'Electricity, gas and steam supply'],
        '36' => ['Distribution d\'eau', 'Winning en distributie van water', 'Water supply'],
        '37' => ['Assainissement des eaux usées', 'Afvalwaterafvoer', 'Sewerage'],
        '38' => ['Collecte, traitement et recyclage des déchets', 'Afvalinzameling, -verwerking en recyclage', 'Waste collection, treatment and recycling'],
        '39' => ['Dépollution', 'Sanering', 'Remediation'],
        '41' => ['Construction de bâtiments', 'Bouw van gebouwen', 'Construction of buildings'],
        '42' => ['Génie civil', 'Weg- en waterbouw', 'Civil engineering'],
        '43' => ['Travaux de construction spécialisés', 'Gespecialiseerde bouwwerkzaamheden', 'Specialised construction activities'],
        '45' => ['Commerce et réparation automobiles', 'Handel in en reparatie van motorvoertuigen', 'Motor vehicle trade and repair'],
        '46' => ['Commerce de gros', 'Groothandel', 'Wholesale trade'],
        '47' => ['Commerce de détail', 'Detailhandel', 'Retail trade'],
        '4791' => ['Vente à distance (e-commerce)', 'Detailhandel via postorder of internet (e-commerce)', 'Retail via mail order or internet (e-commerce)'],
        '49' => ['Transport routier et ferroviaire', 'Vervoer te land', 'Land transport'],
        '50' => ['Transport maritime et fluvial', 'Vervoer over water', 'Water transport'],
        '51' => ['Transport aérien', 'Luchtvaart', 'Air transport'],
        '52' => ['Entreposage et logistique', 'Opslag en vervoersondersteunende activiteiten', 'Warehousing and logistics'],
        '53' => ['Poste et courrier', 'Post en koeriers', 'Postal and courier activities'],
        '55' => ['Hébergement', 'Verschaffen van accommodatie', 'Accommodation'],
        '56' => ['Restauration', 'Eet- en drinkgelegenheden', 'Food and beverage services'],
        '58' => ['Édition', 'Uitgeverijen', 'Publishing'],
        '59' => ['Production audiovisuelle et musicale', 'Audiovisuele en muziekproductie', 'Film, video and music production'],
        '60' => ['Radio et télévision', 'Radio en televisie', 'Broadcasting'],
        '61' => ['Télécommunications', 'Telecommunicatie', 'Telecommunications'],
        '62' => ['Informatique (programmation et conseil)', 'Informaticadiensten', 'Computer programming and consultancy'],
        '63' => ['Services d\'information', 'Dienstverlenende activiteiten op het gebied van informatie', 'Information services'],
        '64' => ['Services financiers', 'Financiële diensten', 'Financial services'],
        '65' => ['Assurance', 'Verzekeringen', 'Insurance'],
        '66' => ['Services auxiliaires financiers et d\'assurance', 'Ondersteunende financiële diensten', 'Auxiliary financial services'],
        '68' => ['Activités immobilières', 'Exploitatie van en handel in onroerend goed', 'Real estate activities'],
        '69' => ['Activités juridiques et comptables', 'Rechtskundige en boekhoudkundige dienstverlening', 'Legal and accounting activities'],
        '70' => ['Sièges sociaux et conseil de gestion', 'Hoofdkantoren en bedrijfsadvisering', 'Head offices and management consultancy'],
        '71' => ['Architecture, ingénierie et contrôle technique', 'Architecten, ingenieurs en technische keuring', 'Architecture, engineering and technical testing'],
        '72' => ['Recherche et développement', 'Speur- en ontwikkelingswerk', 'Scientific research and development'],
        '73' => ['Publicité et études de marché', 'Reclame en marktonderzoek', 'Advertising and market research'],
        '74' => ['Autres activités spécialisées et techniques', 'Overige gespecialiseerde activiteiten', 'Other professional and technical activities'],
        '75' => ['Activités vétérinaires', 'Veterinaire diensten', 'Veterinary activities'],
        '77' => ['Location et location-bail', 'Verhuur en lease', 'Rental and leasing'],
        '78' => ['Travail intérimaire et recrutement', 'Arbeidsbemiddeling en uitzendbureaus', 'Employment activities'],
        '79' => ['Agences de voyage', 'Reisbureaus', 'Travel agencies'],
        '80' => ['Sécurité et enquêtes', 'Beveiliging en opsporing', 'Security and investigation'],
        '81' => ['Nettoyage et entretien des bâtiments', 'Diensten in verband met gebouwen', 'Services to buildings and landscaping'],
        '82' => ['Services administratifs aux entreprises', 'Administratieve dienstverlening', 'Office administrative and business support'],
        '84' => ['Administration publique et défense', 'Openbaar bestuur en defensie', 'Public administration and defence'],
        '85' => ['Enseignement', 'Onderwijs', 'Education'],
        '86' => ['Santé humaine', 'Menselijke gezondheidszorg', 'Human health activities'],
        '87' => ['Hébergement médico-social', 'Maatschappelijke dienstverlening met huisvesting', 'Residential care'],
        '88' => ['Action sociale sans hébergement', 'Maatschappelijke dienstverlening zonder huisvesting', 'Social work without accommodation'],
        '90' => ['Arts et spectacles', 'Creatieve activiteiten, kunst en amusement', 'Creative, arts and entertainment'],
        '91' => ['Bibliothèques, archives et musées', 'Bibliotheken, archieven en musea', 'Libraries, archives and museums'],
        '92' => ['Jeux de hasard', 'Loterijen en kansspelen', 'Gambling and betting'],
        '93' => ['Sport et loisirs', 'Sport, ontspanning en recreatie', 'Sports and recreation'],
        '94' => ['Associations et organisations', 'Verenigingen', 'Membership organisations'],
        '95' => ['Réparation d\'ordinateurs et de biens personnels', 'Reparatie van computers en consumentenartikelen', 'Repair of computers and household goods'],
        '96' => ['Autres services personnels', 'Overige persoonlijke diensten', 'Other personal services'],
        '97' => ['Ménages employeurs de personnel domestique', 'Huishoudens als werkgever van huispersoneel', 'Households as employers of domestic staff'],
        '98' => ['Production des ménages pour leur usage propre', 'Productie door huishoudens voor eigen gebruik', 'Households producing for own use'],
        '99' => ['Organisations extraterritoriales', 'Extraterritoriale organisaties', 'Extraterritorial organisations'],
    ];

    /** Anciens secteurs (liste courte d'avant) => secteur NACE. */
    public const ANCIENS = [
        'Transport' => 'Transport routier et ferroviaire', 'Logistique' => 'Entreposage et logistique',
        'Construction' => 'Construction de bâtiments', 'Distribution' => 'Commerce de gros',
        'Grande distribution' => 'Commerce de détail', 'E-commerce' => 'Vente à distance (e-commerce)',
        'Chimie' => 'Industrie chimique', 'Pharmaceutique' => 'Industrie pharmaceutique',
        'Agroalimentaire' => 'Industries alimentaires', 'Électronique' => 'Électronique et informatique (fabrication)',
        'Automobile' => 'Industrie automobile', 'Textile' => 'Fabrication de textiles', 'Mobilier' => 'Fabrication de meubles',
        'Plasturgie' => 'Caoutchouc et plastique', 'Recyclage' => 'Collecte, traitement et recyclage des déchets',
        'Énergie' => 'Électricité, gaz et vapeur', 'Santé' => 'Santé humaine', 'Bois et papier' => 'Industrie du papier et du carton',
        'Machines et équipements' => 'Fabrication de machines et équipements',
        'Matériaux de construction' => 'Verre, céramique et matériaux de construction', 'Cosmétique' => 'Industrie chimique',
        'Aéronautique' => 'Fabrication d\'autres matériels de transport', 'Emballage' => 'Industrie du papier et du carton',
        'Informatique' => 'Informatique (programmation et conseil)', 'Finance et assurance' => 'Services financiers',
        'Agriculture' => 'Culture, élevage et chasse',
    ];

    /**
     * Le secteur d'un code NACE (« 29.100 », « 4791 », « 61 »), en francais.
     */
    public static function depuisNace(?string $code): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $code);

        if (strlen($chiffres) < 2) {
            return null;
        }

        return (self::DIVISIONS[substr($chiffres, 0, 4)] ?? self::DIVISIONS[substr($chiffres, 0, 2)] ?? null)[0] ?? null;
    }

    /**
     * La liste pour un menu deroulant, par section, dans la langue de
     * l'interface. La valeur reste le libelle francais.
     *
     * @return list<array{section: string, secteurs: list<array{valeur: string, libelle: string}>}>
     */
    public static function groupes(string $langue): array
    {
        $rang = ['fr' => 0, 'nl' => 1, 'en' => 2][$langue] ?? 0;

        return array_values(array_map(fn (array $section) => [
            'section' => $section[$rang],
            'secteurs' => array_map(fn (string $division) => [
                'valeur' => self::DIVISIONS[$division][0],
                'libelle' => self::DIVISIONS[$division][$rang],
            ], $section[3]),
        ], self::SECTIONS));
    }

    /** @return list<string> Tous les secteurs, en francais. */
    public static function valeurs(): array
    {
        return array_values(array_map(fn (array $d) => $d[0], self::DIVISIONS));
    }

    /**
     * Le vocabulaire « secteur » des traductions : cle => [fr, nl, en].
     *
     * @return array<string, array{string, string, string}>
     */
    public static function vocabulaire(): array
    {
        $entrees = [];

        foreach (self::DIVISIONS as [$fr, $nl, $en]) {
            $entrees[Traductions::cleDepuis($fr)] = [$fr, $nl, $en];
        }

        return $entrees;
    }
}
