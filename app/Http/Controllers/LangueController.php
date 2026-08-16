<?php

namespace App\Http\Controllers;

use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LangueController extends Controller
{
    /**
     * Change la langue et revient sur la meme page, dans sa nouvelle
     * adresse.
     *
     * Un simple back() renverrait vers l'URL d'ou l'on vient, qui porte
     * encore l'ancien prefixe : la langue serait aussitot reprise a cette
     * adresse et le bouton n'aurait aucun effet. Le premier segment du
     * chemin est donc reecrit.
     *
     * Un utilisateur connecte garde son choix sur son compte, il le suit
     * d'un poste a l'autre. Un visiteur le garde en session : lui creer
     * un compte pour choisir sa langue serait absurde.
     */
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

    /**
     * La page d'ou l'on vient, exprimee dans la nouvelle langue. Une
     * provenance inconnue ou exterieure ramene a l'accueil.
     */
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
