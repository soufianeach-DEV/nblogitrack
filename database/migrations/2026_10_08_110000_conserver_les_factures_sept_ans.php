<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * La loi du 18 decembre 2025 (Moniteur belge du 30 decembre 2025) ramene de
 * dix a sept ans la conservation des factures et documents TVA (article 60
 * du Code de la TVA). La comptabilite est aussi a sept ans (article III.88
 * du Code de droit economique). La politique de confidentialite et le
 * registre des traitements deja en base sont mis a jour.
 */
return new class extends Migration
{
    private const PAGES = [
        'corps_fr' => ["- Factures : dix ans, conformément à l'article 60 du Code de la TVA ; autres pièces comptables : sept ans.", '- Factures et autres pièces comptables : sept ans (article 60 du Code de la TVA, modifié par la loi du 18 décembre 2025 ; article III.88 du Code de droit économique).'],
        'corps_nl' => ['- Facturen: tien jaar, conform artikel 60 van het Btw-wetboek; andere boekhoudkundige stukken: zeven jaar.', '- Facturen en andere boekhoudkundige stukken: zeven jaar (artikel 60 van het Btw-wetboek, gewijzigd bij de wet van 18 december 2025; artikel III.88 van het Wetboek van economisch recht).'],
        'corps_en' => ['- Invoices: ten years, under Article 60 of the Belgian VAT Code; other accounting records: seven years.', '- Invoices and other accounting records: seven years (Article 60 of the Belgian VAT Code, as amended by the Law of 18 December 2025; Article III.88 of the Code of Economic Law).'],
    ];

    private const REGISTRE = ['Factures : dix ans (article 60 du Code de la TVA). Autres pièces comptables : sept ans.', 'Factures et autres pièces comptables : sept ans (article 60 du Code de la TVA, modifié par la loi du 18 décembre 2025 ; article III.88 du Code de droit économique).'];

    public function up(): void
    {
        foreach (self::PAGES as $colonne => [$ancien, $nouveau]) {
            DB::table('pages')->where($colonne, 'like', '%'.$ancien.'%')
                ->update([$colonne => DB::raw('replace('.$colonne.', '.DB::getPdo()->quote($ancien).', '.DB::getPdo()->quote($nouveau).')')]);
        }

        DB::table('processing_records')->where('conservation', self::REGISTRE[0])->update(['conservation' => self::REGISTRE[1]]);
    }

    public function down(): void
    {
        foreach (self::PAGES as $colonne => [$ancien, $nouveau]) {
            DB::table('pages')->where($colonne, 'like', '%'.$nouveau.'%')
                ->update([$colonne => DB::raw('replace('.$colonne.', '.DB::getPdo()->quote($nouveau).', '.DB::getPdo()->quote($ancien).')')]);
        }

        DB::table('processing_records')->where('conservation', self::REGISTRE[1])->update(['conservation' => self::REGISTRE[0]]);
    }
};
