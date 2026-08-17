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

        if ($utilisateur = $request->user()) {
            $utilisateur->update(['locale' => $vers]);
        } else {
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
