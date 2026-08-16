<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une entree du registre des traitements (article 30 du RGPD).
 *
 * Le registre est le document que l'Autorite demande en premier lors
 * d'un controle. Le tenir dans l'application plutot que dans un tableur
 * a un avantage : il se met a jour au meme endroit que ce qu'il decrit,
 * et sa derniere modification est datee.
 */
class ProcessingRecord extends Model
{
    protected $fillable = [
        'nom', 'rang', 'finalite', 'base_legale', 'personnes', 'donnees',
        'destinataires', 'conservation', 'mesures', 'transferts', 'updated_by',
    ];

    /** Les bases legales de l'article 6, dans l'ordre du texte. */
    public const BASES = [
        'consentement' => 'Consentement (art. 6.1.a)',
        'contrat' => 'Exécution du contrat (art. 6.1.b)',
        'obligation' => 'Obligation légale (art. 6.1.c)',
        'vital' => 'Sauvegarde des intérêts vitaux (art. 6.1.d)',
        'public' => 'Mission d\'intérêt public (art. 6.1.e)',
        'legitime' => 'Intérêt légitime (art. 6.1.f)',
    ];

    public function redacteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
