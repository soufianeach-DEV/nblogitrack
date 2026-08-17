<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\Traductions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechercheController extends Controller
{
    private const MAXIMUM = 6;

    public function suggestions(Request $request): JsonResponse
    {
        $terme = trim((string) $request->query('q', ''));

        if (mb_strlen($terme) < 3) {
            return response()->json(['suggestions' => []]);
        }

        $utilisateur = $request->user();
        $personnel = $utilisateur->can('view-all-orders');

        $suggestions = $personnel
            ? $this->pourLePersonnel($terme)
            : $this->pourLeClient($terme, $utilisateur->id);

        return response()->json(['suggestions' => array_slice($suggestions, 0, self::MAXIMUM)]);
    }

    /** @return array<int, array<string, string>> */
    private function pourLePersonnel(string $terme): array
    {
        $filtre = '%'.$terme.'%';

        $entreprises = Client::where(fn ($q) => $q
            ->where('company_name', 'ilike', $filtre)
            ->orWhere('vat_number', 'ilike', $filtre))
            ->orderBy('company_name')
            ->limit(self::MAXIMUM)
            ->get(['id', 'company_name', 'vat_number', 'city'])
            ->map(fn (Client $c) => [
                'type' => 'entreprise',
                'libelle' => $c->company_name,
                'detail' => trim($c->vat_number.' · '.Traductions::vocabulaire('ville', (string) $c->city), ' ·'),
                'url' => route('clients.index', ['etat' => 'tout', 'q' => $c->company_name]),
            ])->all();

        return array_merge($entreprises, $this->expeditions($terme, null));
    }

    /** @return array<int, array<string, string>> */
    private function pourLeClient(string $terme, int $client): array
    {
        return $this->expeditions($terme, $client);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function expeditions(string $terme, ?int $client): array
    {
        $filtre = '%'.$terme.'%';

        return TransportOrder::with('client:id,company_name')
            ->when($client !== null, fn ($q) => $q->where('client_id', $client))
            ->where(fn ($q) => $q
                ->where('tracking_number', 'ilike', $filtre)
                ->orWhere('delivery_address', 'ilike', $filtre))
            ->orderByDesc('id')
            ->limit(self::MAXIMUM)
            ->get()
            ->map(fn (TransportOrder $o) => [
                'type' => 'expedition',
                'libelle' => $o->tracking_number,
                'detail' => Traductions::vocabulaire('ville', Adresse::localite($o->pickup_address))
                    .' → '.Traductions::vocabulaire('ville', Adresse::localite($o->delivery_address))
                    .($client === null && $o->client ? ' · '.$o->client->company_name : ''),
                'url' => route('transport-orders.show', $o->id),
            ])->all();
    }
}
