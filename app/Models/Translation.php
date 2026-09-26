<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use DatesHeureDeBruxelles;

    public const LANGUES = ['fr' => 'Français', 'nl' => 'Nederlands', 'en' => 'English'];

    protected $fillable = ['cle', 'groupe', 'fr', 'nl', 'en', 'modifiee_a_la_main'];

    public function pour(string $langue): string
    {
        return $this->{$langue} !== null && $this->{$langue} !== ''
            ? $this->{$langue}
            : $this->fr;
    }
}
