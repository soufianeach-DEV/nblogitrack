<?php

/*
 * Laravel embarque ses messages en anglais mais pas en neerlandais.
 * Sans ce fichier, un client neerlandophone qui remplit mal un
 * formulaire lisait l'erreur en francais, par le fallback_locale.
 */

return [

    'accepted' => 'Het veld :attribute moet worden aanvaard.',
    'active_url' => 'Het veld :attribute is geen geldig adres.',
    'after' => 'Het veld :attribute moet een datum na :date zijn.',
    'after_or_equal' => 'Het veld :attribute mag niet voor :date liggen.',
    'alpha' => 'Het veld :attribute mag enkel letters bevatten.',
    'alpha_dash' => 'Het veld :attribute mag enkel letters, cijfers en streepjes bevatten.',
    'alpha_num' => 'Het veld :attribute mag enkel letters en cijfers bevatten.',
    'array' => 'Het veld :attribute moet een lijst zijn.',
    'before' => 'Het veld :attribute moet een datum voor :date zijn.',
    'before_or_equal' => 'Het veld :attribute mag niet na :date liggen.',

    'between' => [
        'array' => 'Het veld :attribute moet tussen :min en :max elementen tellen.',
        'file' => 'Het bestand :attribute moet tussen :min en :max kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet tussen :min en :max liggen.',
        'string' => 'Het veld :attribute moet tussen :min en :max tekens tellen.',
    ],

    'boolean' => 'Het veld :attribute moet ja of nee zijn.',
    'confirmed' => 'De bevestiging van het veld :attribute komt niet overeen.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'date' => 'Het veld :attribute is geen geldige datum.',
    'date_equals' => 'Het veld :attribute moet gelijk zijn aan :date.',
    'date_format' => 'Het veld :attribute komt niet overeen met het formaat :format.',
    'declined' => 'Het veld :attribute moet worden geweigerd.',
    'different' => 'De velden :attribute en :other moeten verschillen.',
    'digits' => 'Het veld :attribute moet :digits cijfers tellen.',
    'digits_between' => 'Het veld :attribute moet tussen :min en :max cijfers tellen.',
    'email' => 'Het veld :attribute moet een geldig e-mailadres zijn.',
    'ends_with' => 'Het veld :attribute moet eindigen op: :values.',
    'exists' => 'De gekozen waarde voor :attribute bestaat niet.',
    'file' => 'Het veld :attribute moet een bestand zijn.',
    'filled' => 'Het veld :attribute moet ingevuld zijn.',

    'gt' => [
        'array' => 'Het veld :attribute moet meer dan :value elementen tellen.',
        'file' => 'Het bestand :attribute moet meer dan :value kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet groter zijn dan :value.',
        'string' => 'Het veld :attribute moet meer dan :value tekens tellen.',
    ],

    'gte' => [
        'array' => 'Het veld :attribute moet minstens :value elementen tellen.',
        'file' => 'Het bestand :attribute moet minstens :value kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet minstens :value zijn.',
        'string' => 'Het veld :attribute moet minstens :value tekens tellen.',
    ],

    'image' => 'Het veld :attribute moet een afbeelding zijn.',
    'in' => 'De gekozen waarde voor :attribute is niet toegestaan.',
    'integer' => 'Het veld :attribute moet een geheel getal zijn.',
    'ip' => 'Het veld :attribute moet een geldig IP-adres zijn.',
    'json' => 'Het veld :attribute moet een geldig JSON-document zijn.',
    'lowercase' => 'Het veld :attribute moet in kleine letters staan.',

    'lt' => [
        'array' => 'Het veld :attribute moet minder dan :value elementen tellen.',
        'file' => 'Het bestand :attribute moet minder dan :value kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet kleiner zijn dan :value.',
        'string' => 'Het veld :attribute moet minder dan :value tekens tellen.',
    ],

    'lte' => [
        'array' => 'Het veld :attribute mag niet meer dan :value elementen tellen.',
        'file' => 'Het bestand :attribute mag niet meer dan :value kilobytes wegen.',
        'numeric' => 'Het veld :attribute mag niet meer zijn dan :value.',
        'string' => 'Het veld :attribute mag niet meer dan :value tekens tellen.',
    ],

    'max' => [
        'array' => 'Het veld :attribute mag niet meer dan :max elementen tellen.',
        'file' => 'Het bestand :attribute mag niet meer dan :max kilobytes wegen.',
        'numeric' => 'Het veld :attribute mag niet meer zijn dan :max.',
        'string' => 'Het veld :attribute mag niet meer dan :max tekens tellen.',
    ],

    'mimes' => 'Het veld :attribute moet een bestand zijn van het type: :values.',
    'mimetypes' => 'Het veld :attribute moet een bestand zijn van het type: :values.',

    'min' => [
        'array' => 'Het veld :attribute moet minstens :min elementen tellen.',
        'file' => 'Het bestand :attribute moet minstens :min kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet minstens :min zijn.',
        'string' => 'Het veld :attribute moet minstens :min tekens tellen.',
    ],

    'not_in' => 'De gekozen waarde voor :attribute is niet toegestaan.',
    'not_regex' => 'Het formaat van het veld :attribute is ongeldig.',
    'numeric' => 'Het veld :attribute moet een getal zijn.',
    'present' => 'Het veld :attribute moet aanwezig zijn.',
    'prohibited' => 'Het veld :attribute is niet toegestaan.',
    'regex' => 'Het formaat van het veld :attribute is ongeldig.',
    'required' => 'Het veld :attribute is verplicht.',
    'required_if' => 'Het veld :attribute is verplicht wanneer :other gelijk is aan :value.',
    'required_unless' => 'Het veld :attribute is verplicht tenzij :other gelijk is aan :values.',
    'required_with' => 'Het veld :attribute is verplicht wanneer :values is ingevuld.',
    'required_without' => 'Het veld :attribute is verplicht wanneer :values niet is ingevuld.',
    'same' => 'De velden :attribute en :other moeten identiek zijn.',

    'size' => [
        'array' => 'Het veld :attribute moet :size elementen tellen.',
        'file' => 'Het bestand :attribute moet :size kilobytes wegen.',
        'numeric' => 'Het veld :attribute moet :size zijn.',
        'string' => 'Het veld :attribute moet :size tekens tellen.',
    ],

    'starts_with' => 'Het veld :attribute moet beginnen met: :values.',
    'string' => 'Het veld :attribute moet tekst zijn.',
    'unique' => 'Deze waarde voor :attribute is al in gebruik.',
    'uploaded' => 'Het opladen van het bestand :attribute is mislukt.',
    'url' => 'Het veld :attribute moet een geldig webadres zijn.',

    'custom' => [],

    /*
     * Le nom que porte chaque champ dans les messages. Sans cette table,
     * l'utilisateur lit « Het veld pickup_address is verplicht » : le nom
     * de la colonne, pas celui du formulaire.
     */
    'attributes' => [
        'adr' => 'gevaarlijke stof',
        'adr_certified' => 'ADR-certificering',
        'billing_address' => 'facturatieadres',
        'business_sector' => 'activiteitensector',
        'city' => 'gemeente',
        'company_name' => 'handelsnaam',
        'contact_name' => 'contactpersoon',
        'country' => 'land',
        'cp' => 'postcode',
        'current_password' => 'huidig wachtwoord',
        'customer_type' => 'soort klant',
        'date_flexibility' => 'datumflexibiliteit',
        'delivery_address' => 'leveringsadres',
        'delivery_country' => 'land van bestemming',
        'delivery_lat' => 'breedtegraad van levering',
        'delivery_lng' => 'lengtegraad van levering',
        'depart' => 'ophaalgemeente',
        'destination' => 'leveringsgemeente',
        'driver_id' => 'bestuurder',
        'email' => 'e-mailadres',
        'etat' => 'status',
        'first_name' => 'voornaam',
        'frequency' => 'frequentie',
        'goods_type' => 'aard van de goederen',
        'inspection_date' => 'datum van de technische keuring',
        'insurance_value' => 'te verzekeren waarde',
        'internal_note' => 'interne nota',
        'is_available' => 'beschikbaarheid',
        'is_hazardous' => 'gevaarlijke stof',
        'last_name' => 'naam',
        'license_expiry' => 'vervaldatum van het rijbewijs',
        'medical_exam_date' => 'datum van het medisch onderzoek',
        'mileage' => 'kilometerstand',
        'motif' => 'reden',
        'name' => 'naam',
        'needs_ecmr' => 'elektronische vrachtbrief',
        'needs_express' => 'expresdienst',
        'needs_tail_lift' => 'laadklep',
        'password' => 'wachtwoord',
        'password_confirmation' => 'bevestiging van het wachtwoord',
        'pays' => 'land',
        'permis' => 'rijbewijs',
        'phone' => 'telefoon',
        'pickup_address' => 'ophaaladres',
        'pickup_date' => 'ophaaldatum',
        'pickup_lat' => 'breedtegraad van ophaling',
        'pickup_lng' => 'lengtegraad van ophaling',
        'poids' => 'gewicht',
        'position' => 'functie',
        'postal_code' => 'postcode',
        'priority' => 'prioriteit',
        'q' => 'zoekopdracht',
        'requested_delivery_date' => 'gewenste leverdatum',
        'rue' => 'straat',
        'special_instructions' => 'bijzondere instructies',
        'status' => 'status',
        'statut' => 'status',
        'tariff_grid_id' => 'tariefformule',
        'token' => 'token',
        'trip_type' => 'type traject',
        'tva' => 'btw-nummer',
        'type' => 'type',
        'vat_number' => 'btw-nummer',
        'vehicle_registration' => 'voertuig',
        'vehicle_type' => 'type voertuig',
        'ville' => 'gemeente',
        'volume' => 'volume',
        'weight' => 'gewicht',
    ],

];
