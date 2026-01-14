<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Carbon\Carbon;

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

        /* ---------- hitung user & total AP (cached external API calls) ---------- */
        try {
            $onus = cache()->remember('dashboard_api_onu', 5, function () {
                try {
                    $resp = Http::timeout(5)->get('http://172.16.105.26:6767/api/onu');
                    return $resp->ok() ? ($resp->json() ?: []) : [];
                } catch (\Throwable $e) {
                    Log::warning('dashboard_api_onu fetch failed: '.$e->getMessage());
                    return [];
                }
            });

            $connections = cache()->remember('dashboard_api_onu_connections', 5, function () {
                try {
                    $resp = Http::timeout(5)->get('http://172.16.105.26:6767/api/onu/connect');
                    return $resp->ok() ? ($resp->json() ?: []) : [];
                } catch (\Throwable $e) {
                    Log::warning('dashboard_api_onu_connections fetch failed: '.$e->getMessage());
                    return [];
                }
            });

            $totalAp = is_array($onus) ? count($onus) : 0;

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
        // Build ISO date labels for the current week (Mon...Sun)
        $today = now();
        $monday = $today->copy()->startOfWeek();
        $weekDates = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDates[] = $monday->copy()->addDays($i)->toDateString();
        }

        $weeklyData = array_fill(0, 7, 0);

        $sheetRows  = cache()->remember('sheet_users_weekly', 300,
                                        fn() => $this->readSheetWeekly());

        foreach ($sheetRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0])) {
                $weeklyData[$idx] = (int) $row[0];
            }
        }

        /* fallback DB mingguan kalau Sheet kosong - tetap ambil data minggu ini saja */
        if (array_sum($weeklyData) === 0) {
            $stats  = DB::table('daily_user_stats')
                        ->whereBetween('date', [$monday->toDateString(), $today->toDateString()])
                        ->pluck('user_count', 'date');

            foreach ($weekDates as $i => $dateStr) {
                $weeklyData[$i] = (int) ($stats[$dateStr] ?? 0);
            }
        }

        // Jika masih 0, ambil semua data untuk ditampilkan (fallback untuk data minggu lalu)
        if (array_sum($weeklyData) === 0) {
            $allStats = DB::table('daily_user_stats')
                        ->orderBy('date')
                        ->pluck('user_count', 'date');
            if (count($allStats) > 0) {
                $weekDates = array_keys($allStats->toArray());
                $weeklyData = array_values($allStats->toArray());
            }
        }

        $dailyUsers = [
            'labels' => $weekDates,
            'data'   => $weeklyData,
        ];

        /* ---------- baca Sheet paket 110 & 200 untuk mapping SN ---------- */
        $ontMap = cache()->remember('ont_map_paket_all', 600,
                                    fn() => $this->readOntMap());

        /* ---------- susun summary lokasi + preview clients (optimize memory) ---------- */
        $locationsMap = [];
        if (is_array($connections)) {
            foreach ($connections as $c) {
                $sn   = strtoupper(trim($c['sn'] ?? ''));
                $info = $ontMap[$sn] ?? null;   // detail lokasi

                $clients = array_merge(
                    $c['wifiClients']['5G']    ?? [],
                    $c['wifiClients']['2_4G']  ?? [],
                    $c['wifiClients']['unknown'] ?? []
                );

                if (!isset($locationsMap[$sn])) {
                    $locationsMap[$sn] = [
                        'sn' => $sn,
                        'location' => $info['location'] ?? '-',
                        'kemantren' => $info['kemantren'] ?? '-',
                        'kelurahan' => $info['kelurahan'] ?? '-',
                        'rt' => $info['rt'] ?? '-',
                        'rw' => $info['rw'] ?? '-',
                        'clients_preview' => [],
                        'count' => 0,
                    ];
                }

                foreach ($clients as $cl) {
                    $locationsMap[$sn]['count']++;

                    if (count($locationsMap[$sn]['clients_preview']) < 5) {
                        $locationsMap[$sn]['clients_preview'][] = [
                            'wifi_terminal_name' => $cl['wifi_terminal_name'] ?? 'Unknown',
                            'wifi_terminal_ip' => $cl['wifi_terminal_ip'] ?? '-',
                            'wifi_terminal_mac' => $cl['wifi_terminal_mac'] ?? '-',
                        ];
                    }
                }
            }
        }

        // Convert map to collection and sort by count desc
        $locationsCollection = collect(array_values($locationsMap))->sortByDesc('count')->values();

        $locPerPage = request('locPerPage', 5);
        $locPage = request('locPage', 1);
        $paginated = $locationsCollection->forPage($locPage, $locPerPage)->values()->all();

        // Prepare final locations for view: include limited clients (max 5) in key 'clients' for compatibility
        $locations = new LengthAwarePaginator(
            array_map(function ($row) {
                return [
                    'sn' => $row['sn'],
                    'location' => $row['location'],
                    'kemantren' => $row['kemantren'],
                    'kelurahan' => $row['kelurahan'],
                    'rt' => $row['rt'],
                    'rw' => $row['rw'],
                    'clients' => $row['clients_preview'] ?? [],
                    'count' => $row['count'] ?? 0,
                ];
            }, $paginated),
            $locationsCollection->count(),
            $locPerPage,
            $locPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Get unique kemantren list untuk filter
        $kemantrenList = $locationsCollection->pluck('kemantren')->unique()->sort()->values();

        // Get data rekap per lokasi (mingguan/bulanan)
        $today = now();
        $monday = $today->copy()->startOfWeek();
        $firstDayOfMonth = $today->copy()->startOfMonth();
        $currentMonth = $today->month;
        $currentYear = $today->year;

        // Data per hari (Senin-Minggu) untuk mingguan
        $dayLabels = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        // Cache weekly per-day aggregated location stats for short period
        $weeklyLocationByDay = cache()->remember(sprintf('weekly_location_by_day_%s', $monday->toDateString()), 120, function () use ($monday) {
            $result = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $monday->copy()->addDays($i);
                $rows = DB::table('daily_location_stats')
                    ->where('date', $date->toDateString())
                    ->groupBy('location', 'kemantren')
                    ->selectRaw('location, kemantren, SUM(user_count) as total')
                    ->get()
                    ->map(function ($r) {
                        return [
                            'location' => $r->location,
                            'kemantren' => $r->kemantren,
                            'total' => (int) $r->total,
                        ];
                    })->toArray();

                $result[$date->toDateString()] = $rows;
            }
            return $result;
        });

        // Aggregate untuk bulan ini (default current month)
        $monthlyLocationData = cache()->remember(sprintf('monthly_location_data_%d_%d', $currentYear, $currentMonth), 300, function () use ($firstDayOfMonth, $today, $currentYear, $currentMonth) {
            return DB::table('daily_location_stats')
                ->whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->whereBetween('date', [$firstDayOfMonth, $today])
                ->groupBy('location', 'kemantren')
                ->selectRaw('location, kemantren, SUM(user_count) as total')
                ->get()
                ->map(fn($r) => [
                    'location' => $r->location,
                    'kemantren' => $r->kemantren,
                    'total' => (int) $r->total,
                ])->toArray();
        });

        // Semua bulan untuk dropdown
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return view('dashboard', compact(
            'totalUser','totalAp','userOnline','logActivity','clients','dailyUsers','locations',
            'kemantrenList','monthlyLocationData','dayLabels','weeklyLocationByDay',
            'months','currentMonth','currentYear'
        ));
    }

    /**
     * Return monthly aggregated location data as JSON for given month/year
     */
    public function monthlyLocationData(Request $request)
    {
        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);
        $top   = (int) $request->query('top', 10);
        $kemantren = $request->query('kemantren', null);
        $search = $request->query('search', null);

        $start = Carbon::createFromDate($year, $month, 1)->toDateString();
        $end   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $today = now()->toDateString();
        if ($end > $today) {
            $end = $today;
        }

        $cacheKey = sprintf('monthly_location_data_%d_%d_top%d_k%s_s%s', $year, $month, $top, $kemantren ?? 'all', $search ?? 'all');

        $data = cache()->remember($cacheKey, 300, function () use ($year, $month, $start, $end, $top, $kemantren, $search) {
            $query = DB::table('daily_location_stats')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->whereBetween('date', [$start, $end])
                ->groupBy('location', 'kemantren')
                ->selectRaw('location, kemantren, SUM(user_count) as total');

            if ($kemantren) {
                $query->where('kemantren', $kemantren);
            }

            // allow simple search on location
            if ($search) {
                $query->havingRaw("LOWER(location) LIKE ?", ['%'.strtolower($search).'%']);
            }

            $rows = $query->orderByDesc('total')->limit(max(1, $top))->get()
                ->map(fn($r) => [
                    'location' => $r->location,
                    'kemantren' => $r->kemantren,
                    'total' => (int) $r->total,
                ])->toArray();

            return $rows;
        });

        return response()->json($data);
    }

    /**
     * Return full clients list for a given AP SN (used by AJAX on-demand)
     */
    public function locationClients(Request $request)
    {
        $sn = strtoupper(trim($request->query('sn', '')));
        if ($sn === '') {
            return response()->json(['error' => 'sn required'], 400);
        }

        // Try to get cached connections (same cache key as used in index)
        $connections = cache()->get('dashboard_api_onu_connections', null);
        if ($connections === null) {
            try {
                $resp = Http::timeout(5)->get('http://172.16.105.26:6767/api/onu/connect');
                $connections = $resp->ok() ? ($resp->json() ?: []) : [];
                cache()->put('dashboard_api_onu_connections', $connections, 5);
            } catch (\Throwable $e) {
                Log::warning('locationClients fetch failed: '.$e->getMessage());
                $connections = [];
            }
        }

        $ontMap = cache()->remember('ont_map_paket_all', 600, fn() => $this->readOntMap());

        // Find connection by SN and build clients
        $found = null;
        foreach ($connections as $c) {
            if (strtoupper(trim($c['sn'] ?? '')) === $sn) {
                $found = $c;
                break;
            }
        }

        if (!$found) {
            return response()->json([]);
        }

        $clients = array_merge(
            $found['wifiClients']['5G']    ?? [],
            $found['wifiClients']['2_4G']  ?? [],
            $found['wifiClients']['unknown'] ?? []
        );

        $info = $ontMap[$sn] ?? [];

        $result = array_map(function ($cl) use ($found, $info) {
            return [
                'wifi_terminal_name' => $cl['wifi_terminal_name'] ?? 'Unknown',
                'wifi_terminal_ip' => $cl['wifi_terminal_ip'] ?? '-',
                'wifi_terminal_mac' => $cl['wifi_terminal_mac'] ?? '-',
                'ap_sn' => $found['sn'] ?? null,
                'ap_name' => $info['location'] ?? '-',
                'ap_kemantren' => $info['kemantren'] ?? '-',
            ];
        }, $clients);

        return response()->json(array_values($result));
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
        $range   = 'Rapi1!B4:B10';   // 7 cell = SenMin
        return $service->spreadsheets_values->get($id, $range)->getValues() ?? [];
    }

    /* ---------- Mapping SN -> lokasi (paket 110 & 200) ---------- */
    private function readOntMap(): array
    {
        $service = new Sheets($this->getClient());
        $map = [];

        // Baca paket 110 dari sheet terpisah
        $paket110_id = '1Wtkfylu-BbdIzvV7ZT_M7rEOg2ANBh5ylvea1sp37m8';
        try {
            $range = "'paket 110'!B2:I201";
            $rows  = $service->spreadsheets_values->get($paket110_id, $range)->getValues() ?? [];
            
            foreach ($rows as $row) {
                $sn = trim($row[7] ?? '');
                if ($sn === '') continue;

                $map[strtoupper($sn)] = [
                    'location'   => trim($row[0] ?? ''),
                    'kemantren'  => trim($row[1] ?? ''),
                    'kelurahan'  => trim($row[2] ?? ''),
                    'rt'         => trim($row[3] ?? ''),
                    'rw'         => trim($row[4] ?? ''),
                    'ip'         => trim($row[5] ?? ''),
                    'pic'        => trim($row[6] ?? ''),
                    'coordinate' => '',
                ];
            }
        } catch (\Throwable $e) {
            Log::error('readOntMap - paket 110: ' . $e->getMessage());
        }

        // Baca paket 200 dari sheet lama
        try {
            $id    = config('services.google.sheet_id');
            $range = "'paket 200'!B2:I201";
            $rows  = $service->spreadsheets_values->get($id, $range)->getValues() ?? [];

            foreach ($rows as $row) {
                $sn = trim($row[7] ?? '');
                if ($sn === '') continue;

                $map[strtoupper($sn)] = [
                    'location'   => trim($row[0] ?? ''),
                    'kemantren'  => trim($row[1] ?? ''),
                    'kelurahan'  => trim($row[2] ?? ''),
                    'rt'         => trim($row[3] ?? ''),
                    'rw'         => trim($row[4] ?? ''),
                    'ip'         => trim($row[5] ?? ''),
                    'pic'        => trim($row[6] ?? ''),
                    'coordinate' => trim($row[8] ?? '') ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Log::error('readOntMap - paket 200: ' . $e->getMessage());
        }

        return $map;
    }
}
