<?php

namespace App\Support;

use Stringable;
use Tighten\Ziggy\Ziggy;

/**
 * Le script des routes de Ziggy, sans l'attribut type="text/javascript"
 * que le validateur du W3C signale comme inutile.
 */
class ScriptZiggy implements Stringable
{
    public function __construct(
        protected Ziggy $ziggy,
        protected string $function,
        protected string $nonce = '',
    ) {}

    public function __toString(): string
    {
        return '<script'.$this->nonce.'>const Ziggy='.$this->ziggy->toJson().';'.$this->function.'</script>';
    }
}
