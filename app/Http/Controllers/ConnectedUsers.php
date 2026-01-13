<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;

class ConnectedUsers extends Controller
{
    public function index(Request $request)
    {
        try {
            $response = Http::timeout(10)->get(config('services.onu_api.url'));

            if (!is_object($response) || !method_exists($response, 'successful') || ! $response->successful()) {
                $perPage = $request->get('perPage', 5);
                $page = $request->get('page', 1);

                $emptyPaginator = new LengthAwarePaginator(
                    [],
                    0,
                    $perPage,
                    $page,
                    [
                        'path' => $request->url(),
                        'query' => $request->query(),
                    ]
                );

                return view('connectedUsers.connectUsers', [
                    'aps' => $emptyPaginator,
                    'error' => 'Gagal mengambil data dari API',
                ]);
            }

            // Process API JSON without collecting everything into memory.
            $raw = $response->json();

            $search = $request->get('search');
            $perPage = (int) $request->get('perPage', 5);
            $page = (int) $request->get('page', 1);

            $start = max(0, ($page - 1) * $perPage);
            $collected = 0; // total matched items
            $pageItems = [];

            foreach ($raw as $ap) {
                $clients = $ap['wifiClients'] ?? [];
                $ap['connected'] =
                    count($clients['5G'] ?? []) +
                    count($clients['2_4G'] ?? []) +
                    count($clients['unknown'] ?? []);

                // Apply search filter if provided
                if ($search) {
                    $hay = strtolower(($ap['sn'] ?? '') . ' ' . ($ap['model'] ?? ''));
                    if (strpos($hay, strtolower($search)) === false) {
                        continue;
                    }
                }

                // This item matches search (or no search). Count it and add to page buffer if within range.
                if ($collected >= $start && count($pageItems) < $perPage) {
                    $pageItems[] = $ap;
                }

                $collected++;
            }

            $aps = new LengthAwarePaginator(
                $pageItems,
                $collected,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return view('connectedUsers.connectUsers', [
                'aps' => $aps,
                'error' => null,
                'search' => $search ?? null,
            ]);

        } catch (ConnectionException) {
            $perPage = $request->get('perPage', 5);
            $page = $request->get('page', 1);

            $emptyPaginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return view('connectedUsers.connectUsers', [
                'aps' => $emptyPaginator,
                'error' => 'Koneksi ke API timeout',
            ]);
        }
    }
}
