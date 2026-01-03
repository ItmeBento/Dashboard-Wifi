<x-app-layout>

    <div class="space-y-6 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen p-6">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-2 gap-6">
            <div
                class="group relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-6 shadow-xl hover:shadow-2xl hover:shadow-blue-500/20 transition-all duration-300 hover:scale-105">
                <div
                    class="absolute inset-0 bg-gradient-to-r from-blue-400/0 to-blue-400/0 group-hover:from-blue-400/10 group-hover:to-blue-400/20 transition-all duration-300">
                </div>
                <div class="relative">
                    <p class="text-blue-200 text-sm font-medium mb-2 uppercase tracking-wider">Connected Users</p>
                    <p class="text-white font-bold text-4xl">{{ $userOnline }}</p>
                </div>
            </div>

            <div
                class="group relative overflow-hidden bg-gradient-to-br from-purple-600 to-purple-800 rounded-xl p-6 shadow-xl hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-300 hover:scale-105">
                <div
                    class="absolute inset-0 bg-gradient-to-r from-purple-400/0 to-purple-400/0 group-hover:from-purple-400/10 group-hover:to-purple-400/20 transition-all duration-300">
                </div>
                <div class="relative">
                    <p class="text-purple-200 text-sm font-medium mb-2 uppercase tracking-wider">Total AP</p>
                    <p class="text-white font-bold text-4xl">{{ $totalAp }}</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid gap-4 xl:grid-cols-[4fr_6fr] lg:grid-cols-2 rounded-xl">
            <!-- KIRI -->
            <div
                class="bg-slate-800/50 backdrop-blur rounded-xl p-4 border border-slate-700/50 flex flex-col  shadow-xl shadow-black/20">
                <p class="text-white font-semibold mb-3 text-center">User Capacity</p>
                <div class="relative w-full h-56">
                    <canvas id="userChart" class="absolute inset-0 h-full block"></canvas>
                </div>
            </div>

            <!-- KANAN -->
            <div
                class="bg-slate-800/50 backdrop-blur rounded-xl p-4 border border-slate-700/50 flex flex-col  shadow-xl shadow-black/20">
                <h3 class="text-white font-semibold mb-3">Grafik Users</h3>
                <div class="w-full h-56">
                    <canvas id="userChartDaily" class=" h-full"></canvas>
                </div>
            </div>
        </div>

        <!-- Detail Table -->
        <!-- Detail Table -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl p-6 shadow-xl border border-slate-700/50 overflow-x-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-white font-semibold text-lg">Detail Connected Devices</h3>

                <!-- Items Per Page Selector -->
                <div class="flex items-center space-x-2">
                    <span class="text-gray-400 text-sm">Show:</span>
                    <select onchange="window.location.href = updateUrlParam('perPage', this.value)"
                        class="bg-slate-700 border border-slate-600 text-gray-300 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="10" {{ request('perPage', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('perPage', 10) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('perPage', 10) == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('perPage', 10) == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto mb-6">
                <table class="w-full text-left text-gray-300 text-sm">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="pb-4 font-semibold text-gray-200">Device Name</th>
                            <th class="pb-4 font-semibold text-gray-200">IP Address</th>
                            <th class="pb-4 font-semibold text-gray-200">MAC Address</th>
                            <th class="pb-4 font-semibold text-gray-200">AP Name</th>
                            <th class="pb-4 font-semibold text-gray-200">Duration</th>
                            <th class="pb-4 font-semibold text-gray-200">Traffic</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Akses data dari array yang telah dipaginasi
                            $clients = $connections['data'] ?? $connections;
                            $currentPage = $connections['current_page'] ?? 1;
                            $perPage = $connections['per_page'] ?? 10;
                            $total = $connections['total'] ?? count($connections);
                            $lastPage = $connections['last_page'] ?? ceil($total / $perPage);
                            $from = $connections['from'] ?? ($currentPage - 1) * $perPage + 1;
                            $to = $connections['to'] ?? min($currentPage * $perPage, $total);
                        @endphp

                        @forelse ($clients as $client)
                            <tr
                                class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors duration-200">
                                <td class="py-4">{{ $client['wifi_terminal_name'] ?? 'Unknown' }}</td>
                                <td class="py-4">{{ $client['wifi_terminal_ip'] ?? '-' }}</td>
                                <td class="py-4">{{ $client['wifi_terminal_mac'] ?? '-' }}</td>
                                <td class="py-4">{{ $client['ap_name'] ?? '-' }}</td>
                                <td class="py-4">-</td>
                                <td class="py-4">-</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">No devices connected</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($total > $perPage)
                <div
                    class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-700 pt-6 space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-400">
                        Showing <span class="font-medium">{{ $from }}</span>
                        to <span class="font-medium">{{ $to }}</span>
                        of <span class="font-medium">{{ $total }}</span> results
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Previous Button -->
                        @if ($currentPage > 1)
                            <a href="?page={{ $currentPage - 1 }}&perPage={{ $perPage }}"
                                class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-gray-300 text-sm font-medium hover:text-white transition-colors duration-200 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                                Previous
                            </a>
                        @else
                            <span
                                class="px-4 py-2 bg-slate-700 rounded-lg text-gray-500 text-sm font-medium flex items-center opacity-50 cursor-not-allowed">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                                Previous
                            </span>
                        @endif

                        <!-- Page Numbers -->
                        <div class="flex items-center space-x-1">
                            @php
                                $startPage = max(1, min($currentPage - 2, $lastPage - 4));
                                $endPage = min($startPage + 4, $lastPage);
                            @endphp

                            @for ($i = $startPage; $i <= $endPage; $i++)
                                @if ($i == $currentPage)
                                    <span
                                        class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium bg-blue-600 text-white">
                                        {{ $i }}
                                    </span>
                                @else
                                    <a href="?page={{ $i }}&perPage={{ $perPage }}"
                                        class="w-10 h-10 flex items-center justify-center bg-slate-700 text-gray-300 hover:bg-slate-600 hover:text-white rounded-lg text-sm font-medium transition-colors duration-200">
                                        {{ $i }}
                                    </a>
                                @endif
                            @endfor

                            @if ($endPage < $lastPage)
                                <span class="px-2 text-gray-400">...</span>
                                <a href="?page={{ $lastPage }}&perPage={{ $perPage }}"
                                    class="w-10 h-10 flex items-center justify-center bg-slate-700 text-gray-300 hover:bg-slate-600 hover:text-white rounded-lg text-sm font-medium transition-colors duration-200">
                                    {{ $lastPage }}
                                </a>
                            @endif
                        </div>

                        <!-- Next Button -->
                        @if ($currentPage < $lastPage)
                            <a href="?page={{ $currentPage + 1 }}&perPage={{ $perPage }}"
                                class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-gray-300 text-sm font-medium hover:text-white transition-colors duration-200 flex items-center">
                                Next
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @else
                            <span
                                class="px-4 py-2 bg-slate-700 rounded-lg text-gray-500 text-sm font-medium flex items-center opacity-50 cursor-not-allowed">
                                Next
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>


    </div>

    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1e293b;
        }

        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        //
        function updateUrlParam(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            url.searchParams.set('page', 1); // Reset ke halaman 1 saat ganti perPage
            return url.toString();
        }

        // Preserve scroll position on pagination
        document.addEventListener('DOMContentLoaded', function() {
            const paginationLinks = document.querySelectorAll('a[href*="page="]');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Simpan posisi scroll
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                });
            });

            // Restore scroll position
            const savedPosition = sessionStorage.getItem('scrollPosition');
            if (savedPosition) {
                window.scrollTo(0, parseInt(savedPosition));
                sessionStorage.removeItem('scrollPosition');
            }
        });

        //chart user
        const userChart = document.getElementById('userChart').getContext('2d');
        new Chart(userChart, {
            type: 'bar',
            data: {
                labels: ['Connected Users'], // Label untuk sumbu X
                datasets: [{
                    label: 'Online Users', // Nama dataset (muncul di legend)
                    data: [@json($userOnline ?? 0)], // Hanya data connected user
                    backgroundColor: '#3b82f6', // Biru cerah
                    borderColor: '#1f2937', // Hitam/abu-abu tua
                    borderWidth: 2,
                    borderRadius: 8 // Opsional: sudut membulat
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#d1d5db',
                            stepSize: 1, // Agar skala naik per 1 unit (jika jumlah kecil)
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#d1d5db',
                            font: {
                                size: 14
                            }
                        },
                        grid: {
                            display: false // Hilangkan grid di sumbu X
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // Sembunyikan legend karena cuma 1 bar
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Connected: ${context.raw}`;
                            }
                        }
                    }
                }
            }
        });


        window.dailyUsersLabels = @json($dailyUsers['labels'] ?? []);
        window.dailyUsersData = @json($dailyUsers['data'] ?? []);
    </script>

</x-app-layout>
