<?php

namespace App\Http\Middleware;

use App\Support\Traductions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class DefinirLangue
{
    public function handle(Request $request, Closure $suivant): Response
    {
        $langue = $request->route('langue')
            ?? $request->user()?->locale
            ?? $request->session()->get('langue');

        $langue = Traductions::estServie($langue) ? $langue : 'fr';

        App::setLocale($langue);

        URL::defaults(['langue' => $langue]);

        $request->route()?->forgetParameter('langue');

        return $suivant($request);
    }
}
