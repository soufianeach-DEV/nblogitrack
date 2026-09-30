<?php

namespace App\Http\Controllers;

use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LangueController extends Controller
{
    public function __invoke(Request $request, string $vers): RedirectResponse
    {
        abort_unless(Traductions::estServie($vers), 404);

        // La langue du compte (celle de ses courriels) ne change que depuis
        // le site lui-meme : un lien pose sur une autre page ne la modifie
        // plus a l'insu de l'utilisateur.
        $depuisLeSite = in_array($request->header('Sec-Fetch-Site'), [null, 'same-origin', 'none'], true);

        if (($utilisateur = $request->user()) && $depuisLeSite) {
            $utilisateur->update(['locale' => $vers]);
        } elseif (! $utilisateur) {
            $request->session()->put('langue', $vers);
        }

        return redirect($this->memePage($request->headers->get('referer'), $vers));
    }

    private function memePage(?string $provenance, string $langue): string
    {
        if ($provenance === null || ! str_starts_with($provenance, $prefixe = url('/'))) {
            return '/'.$langue;
        }

        $chemin = trim(parse_url($provenance, PHP_URL_PATH) ?? '', '/');
        $segments = $chemin === '' ? [] : explode('/', $chemin);

        if ($segments !== [] && Traductions::estServie($segments[0])) {
            array_shift($segments);
        }

        $requete = parse_url($provenance, PHP_URL_QUERY);

        return '/'.$langue
            .($segments === [] ? '' : '/'.implode('/', $segments))
            .($requete === null ? '' : '?'.$requete);
    }
}
