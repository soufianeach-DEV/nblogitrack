<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Verification des numeros de TVA britanniques (HMRC, API « Check a
    // UK VAT number », application declaree sur developer.service.hmrc.gov.uk).
    'hmrc' => [
        'client_id' => env('HMRC_CLIENT_ID'),
        'client_secret' => env('HMRC_CLIENT_SECRET'),
        'base' => env('HMRC_API_BASE', 'https://api.service.hmrc.gov.uk'),
    ],

    // Pages de l'entreprise sur les reseaux sociaux : liens du pied de page
    // et fiche schema.org. Vides : rien n'est affiche.
    'reseaux' => [
        'linkedin' => env('RESEAU_LINKEDIN'),
        'facebook' => env('RESEAU_FACEBOOK'),
        'instagram' => env('RESEAU_INSTAGRAM'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Vide en production : l'API de Stripe. Renseigne pour un emulateur.
        'api_base' => env('STRIPE_API_BASE'),
    ],

];
