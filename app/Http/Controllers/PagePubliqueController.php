<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le versant public de A13.
 *
 * Il est separe de l'administration a dessein : ces deux methodes sont
 * les seules ouvertes a un visiteur anonyme, et cela se voit d'un coup
 * d'oeil au lieu de dependre de la lecture des routes.
 */
class PagePubliqueController extends Controller
{
    public function show(Page $page): Response
    {
        // Un brouillon n'existe pas pour le visiteur. On repond 404 et non
        // 403 : dire « cette page existe mais vous n'y avez pas droit »
        // renseignerait sur un texte qui n'est pas encore arrete.
        abort_unless($page->publiee, 404);

        $langue = app()->getLocale();

        return Inertia::render('Pages/Show', [
            'page' => [
                'slug' => $page->slug,
                'titre' => $page->titre($langue),
                'corps' => $page->corps($langue),
                // Le visiteur doit savoir qu'il lit un repli : une page
                // legale servie en francais sur une interface neerlandaise
                // se signale, elle ne se dissimule pas.
                'repli' => ! $page->traduiteEn($langue),
                'mise_a_jour' => $page->updated_at?->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * Le fichier est servi par l'application, pas depuis le dossier
     * public : rien n'est expose par le serveur web, et un document
     * supprime cesse d'etre joignable meme si son adresse circule.
     */
    public function document(PageDocument $pageDocument): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($pageDocument->chemin), 404);

        return Storage::disk('local')->response(
            $pageDocument->chemin,
            $pageDocument->nom_origine,
            [
                'Content-Type' => $pageDocument->mime,
                // Le navigateur affiche, il n'execute pas : un fichier
                // servi depuis notre domaine ne doit pas pouvoir devenir
                // du script aux yeux du navigateur.
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
