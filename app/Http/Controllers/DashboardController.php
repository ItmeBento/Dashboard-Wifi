<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\Sheets;

class DashboardController extends Controller
{
    public function index()
    {
        /* ---------- default ---------- */
        $onus        = [];
        $connections = [];
        $totalAp     = 0;
        $userOnline  = 0;
        $totalUser   = 0;
        $logActivity = collect();

        /* ---------- hitung user & total AP ---------- */
        try {
            $responseOnu  = Http::timeout(5)->get('http://172.16.100.26:67/api/onu');
            $responseConn = Http::timeout(5)->get('http://172.16.100.26:67/api/onu/connect');

            if ($responseOnu->ok()) {
                $onus    = $responseOnu->json();
                $totalAp = is_array($onus) ? count($onus) : 0;
            }

            if ($responseConn->ok()) {
                $connections = $responseConn->json();
                if (is_array($connections)) {
                    foreach ($connections as $c) {
                        if (!is_array($c) || !isset($c['wifiClients'])) {
                            continue;
                        }
                        $wifi = $c['wifiClients'];
                        $userOnline += count($wifi['5G']    ?? []);
                        $userOnline += count($wifi['2_4G'] ?? []);
                        $userOnline += count($wifi['unknown'] ?? []);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('DashboardController@index : '.$e->getMessage());
        }
        $totalUser = $userOnline;

        /* ---------- log activity (dummy) ---------- */
        if (is_array($connections)) {
            foreach ($connections as $c) {
                foreach ($c['wifiClients']['unknown'] ?? [] as $cl) {
                    $logActivity->push((object)[
                        'time'   => now()->subMinutes(rand(1,30)),
                        'user'   => $cl['wifi_terminal_name'] ?? 'Unknown',
                        'ap'     => $c['sn'] ?? null,
                        'action' => 'connected',
                    ]);
                }
            }
        }

        /* ---------- Google-Sheet sebagai sumber utama chart ---------- */
        $dayLabels  = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
        $weeklyData = array_fill(0, 7, 0);

        $sheetRows  = cache()->remember('sheet_users_weekly', 300,
                                        fn() => $this->readSheetWeekly());

        foreach ($sheetRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0])) {
                $weeklyData[$idx] = (int) $row[0];
            }
        }

        /* fallback DB mingguan kalau Sheet kosong */
        if (array_sum($weeklyData) === 0) {
            $today  = now();
            $monday = $today->copy()->startOfWeek();
            $stats  = DB::table('daily_user_stats')
                        ->whereBetween('date', [$monday, $today])
                        ->pluck('user_count', 'date');

            foreach ($dayLabels as $i => $day) {
                $date = $monday->copy()->addDays($i)->toDateString();
                $weeklyData[$i] = (int) ($stats[$date] ?? 0);
            }
        }

        $dailyUsers = [
            'labels' => $dayLabels,
            'data'   => $weeklyData,
        ];

        /* ---------- baca Sheet paket 200 untuk mapping SN ---------- */
        $ontMap = cache()->remember('ont_map_paket200', 600,
                                    fn() => $this->readOntMap());

        /* ---------- susun clients + detail lokasi dari Sheet ---------- */
        $allClients = collect();
        if (is_array($connections)) {
            foreach ($connections as $c) {
                $sn   = strtoupper(trim($c['sn'] ?? ''));
                $info = $ontMap[$sn] ?? null;   // detail lokasi

                $clients = array_merge(
                    $c['wifiClients']['5G']    ?? [],
                    $c['wifiClients']['2_4G']  ?? [],
                    $c['wifiClients']['unknown'] ?? []
                );

                foreach ($clients as $cl) {
                    $cl['ap_sn']         = $c['sn']      ?? null;
                    $cl['ap_name']       = $info['location'] ?? '-';
                    $cl['ap_ip']         = $info['ip']     ?? '-';
                    $cl['ap_pic']        = $info['pic']    ?? '-';
                    $cl['ap_coordinate'] = $info['coordinate'] ?? '-';
                    $allClients->push($cl);
                }
            }
        }

        $perPage = request('perPage', 10);
        $page    = request('page', 1);
        $clients = new LengthAwarePaginator(
            $allClients->forPage($page, $perPage),
            $allClients->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('dashboard', compact(
            'totalUser','totalAp','userOnline','logActivity','clients','dailyUsers'
        ));
    }

    /* ---------- Google Client ---------- */
    private function getClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName('Laravel Dashboard');
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig(config_path('google/service-accounts.json'));
        return $client;
    }

    /* ---------- Baca Sheet mingguan (7 baris) ---------- */
    private function readSheetWeekly(): array
    {
        $service = new Sheets($this->getClient());
        $id      = config('services.google.sheet_id');
        $range   = 'Rapi1!B4:B10';   // 7 cell = Sen–Min
        return $service->spreadsheets_values->get($id, $range)->getValues() ?? [];
    }

    /* ---------- Mapping SN -> lokasi (paket 200) ---------- */
    private function readOntMap(): array
    {
        $service = new Sheets($this->getClient());
        $id      = config('services.google.sheet_id');
        $range   = "'paket 200'!A2:F201";   // Lokasi,-,SN,IP,PIC,Koordinat
        $rows    = $service->spreadsheets_values->get($id, $range)->getValues() ?? [];

        $map = [];
        foreach ($rows as $row) {
            $sn = trim($row[2] ?? '');                // kolom C = SN
            if ($sn === '') continue;

            $map[strtoupper($sn)] = [
                'location'   => trim($row[0] ?? ''),
                'ip'         => trim($row[3] ?? ''),
                'pic'        => trim($row[4] ?? ''),
                'coordinate' => trim($row[5] ?? ''),
            ];
        }
        return $map;
    }
}