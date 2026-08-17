<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    public const LANGUES = ['fr' => 'Français', 'nl' => 'Nederlands', 'en' => 'English'];

    protected $fillable = ['cle', 'groupe', 'fr', 'nl', 'en'];

    public function pour(string $langue): string
    {
        return $this->{$langue} !== null && $this->{$langue} !== ''
            ? $this->{$langue}
            : $this->fr;
    }
}
