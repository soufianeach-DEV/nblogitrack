<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    /** Les trois langues servies. Le francais fait foi et ne peut etre vide. */
    public const LANGUES = ['fr' => 'Français', 'nl' => 'Nederlands', 'en' => 'English'];

    protected $fillable = ['cle', 'groupe', 'fr', 'nl', 'en'];

    /**
     * La traduction dans la langue demandee, avec repli sur le francais.
     * Un texte neerlandais absent vaut mieux affiche en francais que
     * remplace par une cle technique sous les yeux du client.
     */
    public function pour(string $langue): string
    {
        return $this->{$langue} !== null && $this->{$langue} !== ''
            ? $this->{$langue}
            : $this->fr;
    }
}
