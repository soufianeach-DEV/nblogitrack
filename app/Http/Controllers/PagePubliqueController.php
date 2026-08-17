<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagePubliqueController extends Controller
{
    public function show(Page $page): Response
    {
        abort_unless($page->publiee, 404);

        $langue = app()->getLocale();

        return Inertia::render('Pages/Show', [
            'page' => [
                'slug' => $page->slug,
                'titre' => $page->titre($langue),
                'corps' => $page->corps($langue),
                'repli' => ! $page->traduiteEn($langue),
                'mise_a_jour' => $page->updated_at?->format('d/m/Y'),
            ],
        ]);
    }

    public function document(PageDocument $pageDocument): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($pageDocument->chemin), 404);

        return Storage::disk('local')->response(
            $pageDocument->chemin,
            $pageDocument->nom_origine,
            [
                'Content-Type' => $pageDocument->mime,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
