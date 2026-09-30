<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Des comptes du personnel crees avec une majuscule dans l'adresse ne
 * pouvaient pas se connecter : la connexion cherche l'adresse en
 * minuscules. Ils y sont ramenes, sauf si une autre ligne porte deja
 * cette adresse (doublon a regler a la main).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users u SET email = lower(u.email)
            WHERE u.email <> lower(u.email)
              AND NOT EXISTS (SELECT 1 FROM users v WHERE lower(v.email) = lower(u.email) AND v.id <> u.id)
        SQL);
    }

    public function down(): void
    {
        //
    }
};
