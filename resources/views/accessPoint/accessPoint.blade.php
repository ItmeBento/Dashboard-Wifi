@section('title', 'Access Point')

@section('content')
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#0f1419] p-6">
        <!-- Logo -->
        <div class="mb-10">
            <h1 class="text-2xl font-bold text-white">WiFi</h1>
        </div>

        <!-- Navigation Menu -->
        <nav class="space-y-2">
            <a href="" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-[#1a2332] hover:text-white transition">
                <i class="fas fa-home w-5"></i>
                <span>Overview</span>
            </a>
            
            <a href="{{ route('access-point') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-[#1e3a5f] text-blue-400 transition">
                <i class="fas fa-wifi w-5"></i>
                <span>Access Point</span>
            </a>
            
            <a href="" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-[#1a2332] hover:text-white transition">
                <i class="fas fa-users w-5"></i>
                <span>Connected Users</span>
            </a>
            
            <a href="" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-[#1a2332] hover:text-white transition">
                <i class="fas fa-bell w-5"></i>
                <span>Alert</span>
            </a>
            
            <a href="" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-[#1a2332] hover:text-white transition">
                <i class="fas fa-cog w-5"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1">
        <!-- Header -->
        <header class="bg-[#0f1419] px-8 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-white">Access Point</h2>
            
            <div class="flex items-center space-x-4">
                <!-- Search Bar -->
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500"></i>
                    <input 
                        type="text" 
                        placeholder="search" 
                        class="bg-[#1a2332] text-gray-300 pl-10 pr-4 py-2 rounded-lg w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                
                <!-- Admin Button -->
                <button class="flex items-center space-x-2 text-gray-400 hover:text-white transition">
                    <i class="fas fa-bell"></i>
                    <span>Admin</span>
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <div class="p-8">
            <!-- Access Point Card -->
            <div class="bg-[#1e2937] rounded-xl p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-gray-400 text-sm mb-2">Name</p>
                        <h3 class="text-3xl font-bold text-white">{{ $accessPoint->name ?? 'AP - 01' }}</h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-green-400 font-medium">{{ $accessPoint->status ?? 'Online' }}</span>
                    </div>
                </div>
            </div>

            <!-- Details Card -->
            <div class="bg-[#1e2937] rounded-xl p-6">
                <h4 class="text-xl font-semibold text-white mb-6">Details</h4>
                
                <div class="grid grid-cols-2 gap-8">
                    <!-- IP Address -->
                    <div>
                        <p class="text-gray-400 text-sm mb-2">IP Address</p>
                        <p class="text-white font-medium">{{ $accessPoint->ip_address ?? '192.168.100.1' }}</p>
                    </div>
                    
                    <!-- MAC Address -->
                    <div>
                        <p class="text-gray-400 text-sm mb-2">MAC Address</p>
                        <p class="text-white font-medium">{{ $accessPoint->mac_address ?? 'AA:BB:CC:DD:EE' }}</p>
                    </div>
                    
                    <!-- Channel -->
                    <div>
                        <p class="text-gray-400 text-sm mb-2">Channel</p>
                        <p class="text-white font-medium">{{ $accessPoint->channel ?? '6' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection