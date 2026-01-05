<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        // default
        $onus = [];
        $connections = [];
        $totalAp = 0;
        $userOnline = 0;
        $totalUser = 0;
        $logActivity = collect();
        $dailyUsers = [
            'labels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
            'data' => [],
        ];

        try {
            $responseOnu = Http::timeout(5)->get('http://172.16.100.26:67/api/onu');
            if ($responseOnu->ok()) {
                $onus = $responseOnu->json();
                $totalAp = is_array($onus) ? count($onus) : 0;
            }

            $responseConn = Http::timeout(5)->get('http://172.16.100.26:67/api/onu/connect');
            if ($responseConn->ok()) {
                $connections = $responseConn->json();
                if (is_array($connections)) {
                    foreach ($connections as $c) {
                        if (! is_array($c) || ! isset($c['wifiClients']) || ! is_array($c['wifiClients'])) {
                            continue;
                        }
                        $wifi = $c['wifiClients'];
                        $userOnline += isset($wifi['5G']) ? count($wifi['5G']) : 0;
                        $userOnline += isset($wifi['2_4G']) ? count($wifi['2_4G']) : 0;
                        $userOnline += isset($wifi['unknown']) ? count($wifi['unknown']) : 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('DashboardController@index error: '.$e->getMessage());
            // keep defaults so view still renders
        }

        $totalUser = $userOnline;

        // simple logActivity from connections
        if (is_array($connections)) {
            foreach ($connections as $c) {
                $sn = $c['sn'] ?? null;
                $wifi = $c['wifiClients'] ?? [];
                foreach ($wifi['unknown'] ?? [] as $client) {
                    $logActivity->push((object) [
                        'time' => now()->subMinutes(rand(1, 30)),
                        'user' => $client['wifi_terminal_name'] ?? 'Unknown',
                        'ap' => $sn,
                        'action' => 'connected',
                    ]);
                }
            }
        }

        // Mulai dari Senin minggu ini, tampilkan sampai hari ini, sisanya kosong sampai Minggu
        $today = now();
        $dayOfWeek = $today->dayOfWeek; // 0=Sunday, 1=Monday, ..., 6=Saturday
        
        // Hitung: Senin minggu ini adalah hari berapa
        $daysToMonday = ($dayOfWeek == 0) ? 1 : ($dayOfWeek == 1 ? 0 : $dayOfWeek - 1);
        $mondayThisWeek = $today->copy()->subDays($daysToMonday);
        
        // Build array: Senin (hari 0) sampai hari ini, sisa hari minggu kosong (0)
        $base = collect();
        for ($i = 0; $i < 7; $i++) {
            $date = $mondayThisWeek->copy()->addDays($i)->toDateString();
            $base[$date] = 0; // default 0
        }

        // timpa dengan data yang ADA (hanya untuk Senin sampai hari ini)
        $stats = DB::table('daily_user_stats')
            ->where('date', '>=', $mondayThisWeek->toDateString())
            ->where('date', '<=', $today->toDateString())
            ->pluck('user_count', 'date');

        $base = $base->merge($stats); // hari ada → ditimpa

        $labels = $base->keys();  // kirim tanggal YYYY-MM-DD
        $data = $base->values();

        $dailyUsers = [
            'labels' => $labels,
            'data' => $data,
        ];

        // ============== PAGINATION LOGIC ==============
        // Flatten semua client devices
        $allClients = collect();

        if (is_array($connections)) {
            foreach ($connections as $c) {
                if (! is_array($c) || ! isset($c['wifiClients']) || ! is_array($c['wifiClients'])) {
                    continue;
                }

                $wifiClients = $c['wifiClients'];

                // Gabungkan semua band (5G, 2.4G, unknown)
                $clients = array_merge(
                    $wifiClients['5G'] ?? [],
                    $wifiClients['2_4G'] ?? [],
                    $wifiClients['unknown'] ?? []
                );

                // Tambahkan informasi AP jika ada
                foreach ($clients as $client) {
                    $client['ap_sn'] = $c['sn'] ?? null;
                    $client['ap_name'] = $c['name'] ?? null;
                    $allClients->push($client);
                }
            }
        }

        $perPage = request('perPage', 10);
        $page = request('page', 1);

        $clients = new LengthAwarePaginator(
            $allClients->forPage($page, $perPage),
            $allClients->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return view('dashboard', [
            'totalUser' => $totalUser,
            'totalAp' => $totalAp,
            'userOnline' => $userOnline,
            'logActivity' => $logActivity,
            'clients' => $clients,
            'dailyUsers' => $dailyUsers,
        ]);
    }
}
