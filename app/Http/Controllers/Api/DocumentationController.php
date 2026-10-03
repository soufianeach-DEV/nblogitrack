<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function page(): View
    {
        return view('api.docs');
    }

    /**
     * La specification docs/openapi.yaml, avec ce serveur en premier : les
     * essais partent vers le site qui sert la page, en local comme en ligne.
     */
    public function specification(): Response
    {
        $yaml = (string) file_get_contents(base_path('docs/openapi.yaml'));
        $ici = url('/api/v1');

        if (! str_contains($yaml, "url: {$ici}\n")) {
            $yaml = preg_replace('/^servers:\n/m', "servers:\n  - url: {$ici}\n    description: Ce serveur\n", $yaml, 1);
        }

        return response($yaml, 200, ['Content-Type' => 'application/yaml; charset=UTF-8']);
    }
}
