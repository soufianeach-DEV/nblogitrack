<?php

namespace App\Http\Controllers;

use App\Jobs\EnvoyerNoteAuxConducteurs;
use App\Models\ActivityLog;
use App\Models\DriverAcknowledgement;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pages/Index', [
            'pages' => Page::with('redacteur:id,first_name,last_name')
                ->orderBy('rang')->orderBy('slug')->get()
                ->map(fn (Page $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    // La liste suit la langue de l'interface, avec le
                    // francais en repli comme sur le site public. Les
                    // trois titres restent envoyes pour le formulaire.
                    'titre' => $p->titre(app()->getLocale()),
                    'titre_fr' => $p->titre_fr,
                    'titre_nl' => $p->titre_nl,
                    'titre_en' => $p->titre_en,
                    'corps_fr' => $p->corps_fr,
                    'corps_nl' => $p->corps_nl,
                    'corps_en' => $p->corps_en,
                    'publiee' => $p->publiee,
                    'publiee_le' => $p->publiee_le?->format('d/m/Y'),
                    'au_pied' => $p->au_pied,
                    'rang' => $p->rang,
                    'traduite' => [
                        'nl' => $p->traduiteEn('nl'),
                        'en' => $p->traduiteEn('en'),
                    ],
                    'modifiee_par' => trim(($p->redacteur?->first_name ?? '').' '.($p->redacteur?->last_name ?? '')),
                    'modifiee_le' => $p->updated_at?->format('d/m/Y H:i'),
                ]),
            'documents' => PageDocument::with('auteur:id,first_name,last_name')
                ->latest('id')->get()
                ->map(fn (PageDocument $d) => [
                    'id' => $d->id,
                    'titre' => $d->titre,
                    'nom' => $d->nom_origine,
                    'mime' => $d->mime,
                    'type' => PageDocument::TYPES[$d->mime] ?? $d->mime,
                    'image' => $d->estImage(),
                    'taille' => $this->poids($d->taille),
                    'url' => route('pages.documents.show', $d->id),
                    'depose_par' => trim(($d->auteur?->first_name ?? '').' '.($d->auteur?->last_name ?? '')),
                    'depose_le' => $d->created_at?->format('d/m/Y'),
                ]),
            'types' => array_values(PageDocument::TYPES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);

        $page = Page::create([
            ...$donnees,
            'slug' => Str::slug($donnees['slug']),
            'updated_by' => $request->user()->id,
        ]);

        ActivityLog::record('page.created', 'Page « '.$page->slug.' » créée', $page);

        return back()->with('success', Traductions::t('msg.page_creee', 'Page créée.'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $donnees = $this->valider($request, $page);

        $page->update([
            ...$donnees,
            'slug' => Str::slug($donnees['slug']),
            'updated_by' => $request->user()->id,
        ]);

        ActivityLog::record('page.updated', 'Page « '.$page->slug.' » modifiée', $page);

        return back()->with('success', Traductions::t('msg.page_enregistree', 'Page enregistrée.'));
    }

    public function publier(Page $page): RedirectResponse
    {
        $publiee = ! $page->publiee;

        $page->update([
            'publiee' => $publiee,
            'publiee_le' => $publiee ? now() : null,
        ]);

        ActivityLog::record(
            $publiee ? 'page.published' : 'page.unpublished',
            'Page « '.$page->slug.' » '.($publiee ? 'publiée' : 'retirée'),
            $page,
        );

        return back()->with('success', $publiee
            ? Traductions::t('msg.page_publiee', 'Page publiée.')
            : Traductions::t('msg.page_retiree', 'Page retirée du site.'));
    }

    public function envoyerNote(Page $page): RedirectResponse
    {
        if ($page->slug !== DriverAcknowledgement::NOTE) {
            return back()->with('error', Traductions::t('msg.note_seule_envoyable', 'Seule la note aux conducteurs peut être envoyée.'));
        }

        // Un brouillon ne part pas chez les conducteurs.
        if (! $page->publiee) {
            return back()->with('error', Traductions::t('msg.note_brouillon', 'Publiez la note avant de l\'envoyer aux conducteurs.'));
        }

        $nombre = User::where('role', 'DRIVER')->where('is_active', true)->count();

        // Envoi en file d'attente : le journal d'activite dira qui l'a recue.
        EnvoyerNoteAuxConducteurs::dispatch($page);

        return back()->with('success', Traductions::t('msg.note_en_cours', 'Envoi de la note en cours à :n conducteur(s). Le journal d\'activité indiquera les éventuels échecs.', ['n' => $nombre]));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $slug = $page->slug;
        $page->delete();

        ActivityLog::record('page.deleted', 'Page « '.$slug.' » supprimée');

        return back()->with('success', Traductions::t('msg.page_supprimee', 'Page supprimée.'));
    }

    public function televerser(Request $request): RedirectResponse
    {
        $request->validate([
            'titre' => 'required|string|max:120',
            'fichier' => 'required|file|max:10240|mimetypes:'.implode(',', array_keys(PageDocument::TYPES)),
        ], [
            'fichier.mimetypes' => Traductions::t('msg.document_types', 'Seuls les PDF et les images (JPEG, PNG, WebP) sont acceptés.'),
            'fichier.max' => Traductions::t('msg.document_taille', 'Le fichier ne peut pas dépasser 10 Mo.'),
        ]);

        $fichier = $request->file('fichier');

        $chemin = $fichier->store('documents', 'local');

        $document = PageDocument::create([
            'titre' => $request->string('titre'),
            'nom_origine' => mb_substr((string) $fichier->getClientOriginalName(), 0, 255),
            'chemin' => $chemin,
            'mime' => $fichier->getMimeType(),
            'taille' => $fichier->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        ActivityLog::record('document.uploaded', 'Document « '.$document->titre.' » déposé', $document);

        return back()->with('success', Traductions::t('msg.document_depose', 'Document déposé.'));
    }

    public function supprimerDocument(PageDocument $pageDocument): RedirectResponse
    {
        Storage::disk('local')->delete($pageDocument->chemin);
        $titre = $pageDocument->titre;
        $pageDocument->delete();

        ActivityLog::record('document.deleted', 'Document « '.$titre.' » supprimé');

        return back()->with('success', Traductions::t('msg.document_supprime', 'Document supprimé.'));
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'slug' => 'required|string|max:80|regex:/^[a-z0-9\-]+$/|unique:pages,slug'
                .($page ? ','.$page->id : ''),
            'titre_fr' => 'required|string|max:150',
            'titre_nl' => 'nullable|string|max:150',
            'titre_en' => 'nullable|string|max:150',
            'corps_fr' => 'required|string|max:40000',
            'corps_nl' => 'nullable|string|max:40000',
            'corps_en' => 'nullable|string|max:40000',
            'au_pied' => 'boolean',
            'rang' => 'nullable|integer|min:0|max:999',
        ], [
            'slug.regex' => Traductions::t('msg.page_slug', 'L\'adresse ne peut contenir que des minuscules, des chiffres et des tirets.'),
        ]);
    }

    private function poids(int $octets): string
    {
        if ($octets < 1024) {
            return $octets.' o';
        }

        return $octets < 1048576
            ? round($octets / 1024).' Ko'
            : round($octets / 1048576, 1).' Mo';
    }
}
