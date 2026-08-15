<?php

namespace App\Http\Controllers;

use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LangueController extends Controller
{
    /**
     * Change la langue et revient d'ou l'on vient.
     *
     * Un utilisateur connecte la garde sur son compte, elle le suit d'un
     * poste a l'autre. Un visiteur la garde en session : lui creer un
     * compte pour choisir sa langue serait absurde.
     */
    public function __invoke(Request $request, string $langue): RedirectResponse
    {
        abort_unless(Traductions::estServie($langue), 404);

        if ($utilisateur = $request->user()) {
            $utilisateur->update(['locale' => $langue]);
        } else {
            $request->session()->put('langue', $langue);
        }

        return back();
    }
}
