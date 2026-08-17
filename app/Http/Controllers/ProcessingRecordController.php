<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DriverAcknowledgement;
use App\Models\ProcessingRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcessingRecordController extends Controller
{
    public function index(): Response
    {
        $note = DriverAcknowledgement::note();
        $conducteurs = User::where('role', 'DRIVER')->where('is_active', true)->count();

        $informes = $note === null ? 0 : DriverAcknowledgement::where('version', $note->updated_at)
            ->whereHas('utilisateur', fn ($q) => $q->where('role', 'DRIVER')->where('is_active', true))
            ->count();

        return Inertia::render('Registre/Index', [
            'traitements' => ProcessingRecord::with('redacteur:id,first_name,last_name')
                ->orderBy('rang')->orderBy('id')->get()
                ->map(fn (ProcessingRecord $t) => [
                    'id' => $t->id,
                    'nom' => $t->nom,
                    'finalite' => $t->finalite,
                    'base_legale' => $t->base_legale,
                    'personnes' => $t->personnes,
                    'donnees' => $t->donnees,
                    'destinataires' => $t->destinataires,
                    'conservation' => $t->conservation,
                    'mesures' => $t->mesures,
                    'transferts' => $t->transferts,
                    'modifie_le' => $t->updated_at?->format('d/m/Y'),
                    'modifie_par' => trim(($t->redacteur?->first_name ?? '').' '.($t->redacteur?->last_name ?? '')),
                ]),
            'bases' => ProcessingRecord::BASES,
            'responsable' => [
                'nom' => 'NBLogiTrack SRL',
                'adresse' => 'Avenue du Port 86C, 1000 Bruxelles, Belgique',
                'entreprise' => 'BE 0123.456.789',
                'contact' => 'info@nblogitrack.be',
            ],
            'information' => [
                'note_existe' => $note !== null,
                'version' => $note?->updated_at?->format('d/m/Y'),
                'conducteurs' => $conducteurs,
                'informes' => $informes,
            ],
        ]);
    }

    public function update(Request $request, ProcessingRecord $processingRecord): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => 'required|string|max:120',
            'finalite' => 'required|string|max:2000',
            'base_legale' => 'required|string|max:120',
            'personnes' => 'required|string|max:1000',
            'donnees' => 'required|string|max:2000',
            'destinataires' => 'required|string|max:1000',
            'conservation' => 'required|string|max:1000',
            'mesures' => 'required|string|max:2000',
            'transferts' => 'nullable|string|max:1000',
        ]);

        $processingRecord->update([...$donnees, 'updated_by' => $request->user()->id]);

        ActivityLog::record(
            'processing_record.updated',
            'Registre des traitements : « '.$processingRecord->nom.' » modifié',
            $processingRecord,
        );

        return back()->with('success', 'Entrée du registre enregistrée.');
    }
}
