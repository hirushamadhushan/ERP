<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Nexus ERP') – Nexus ERP</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        }
    </script>

    <!-- DataTables CSS & Buttons -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        svg { max-width: 100%; max-height: 100%; }

        /* Modern DataTables Styling */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100% !important;
            font-size: 0.875rem;
        }
        table.dataTable thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0 !important;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        table.dataTable tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        table.dataTable tbody tr:hover {
            background-color: #f8fafc !important;
        }
        .dt-buttons .dt-button {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            color: #475569 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            padding: 6px 12px !important;
            margin-right: 4px !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s !important;
        }
        .dt-buttons .dt-button:hover {
            background: #7c3aed !important;
            color: #ffffff !important;
            border-color: #7c3aed !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 12px;
            outline: none;
            font-size: 0.875rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 4px 8px;
            outline: none;
        }
    </style>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased selection:bg-purple-500 selection:text-white">

    <div class="flex h-full">

        <!-- ================================ SIDEBAR ================================ -->
        <aside id="main-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 hidden lg:flex flex-col bg-white border-r border-purple-100 shadow-sm">

            <!-- Brand Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-purple-100">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl text-white shadow-md shadow-purple-500/20 shrink-0" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);">
                    <i class="bi bi-box-seam-fill text-lg"></i>
                </div>
                <div>
                    <span class="font-bold text-lg text-slate-900 leading-tight">Nexus ERP</span>
                    <span class="block text-[10px] font-semibold text-purple-500 uppercase tracking-wider">Enterprise v4.2</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Main Menu</p>

                <!-- Home -->
                <a href="{{ route('home') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                          {{ request()->routeIs('home') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                    <i class="bi bi-speedometer2 text-base"></i>
                    Home / Dashboard
                </a>

                <!-- User Management (Single sidebar item, no vertical submenus) -->
                <a href="{{ route('users.index') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                          {{ request()->routeIs(['users.*', 'roles.*']) ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                    <i class="bi bi-people-fill text-base"></i>
                    User Management
                </a>

                <a href="{{ route('contacts.index', 'customer') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('contacts.*') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                    <i class="bi bi-person-lines-fill text-base" aria-hidden="true"></i>
                    Contacts
                </a>

                <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-4 mb-2">System</p>

                <!-- Settings -->
                <a href="#"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-purple-50 hover:text-purple-700 transition-all duration-150">
                    <i class="bi bi-gear-fill text-base"></i>
                    Settings
                </a>
            </nav>

            <!-- Logged In User Card -->
            <div class="px-3 py-4 border-t border-purple-100">
                <div class="flex items-center gap-3 px-3 py-3 rounded-xl bg-purple-50 border border-purple-100">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background: linear-gradient(135deg, #7c3aed, #4f46e5);">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-800 truncate">{{ auth()->user()->name ?? 'User' }}</p>
                        <p class="text-[10px] text-slate-500 truncate">{{ auth()->user()->role ?? 'Staff' }}</p>
                    </div>
                    <a href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();"
                       class="text-slate-400 hover:text-rose-500 transition-colors" title="Logout">
                        <i class="bi bi-box-arrow-right text-base"></i>
                    </a>
                    <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>

        </aside>

        <button id="sidebar-backdrop" type="button" class="hidden fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-label="Close navigation"></button>

        <!-- ================================ MAIN AREA ================================ -->
        <div class="flex-1 flex flex-col min-w-0 min-h-full lg:ml-64">

            <!-- Top Header Bar -->
            <header class="sticky top-0 z-30 bg-white border-b border-purple-100 shadow-xs">
                <div class="flex items-center justify-between gap-2 min-h-14 px-3 py-1.5 sm:px-6">
                    <div class="flex items-center gap-2 min-w-0">
                        <button id="sidebar-toggle" type="button" class="lg:hidden inline-flex items-center justify-center w-9 h-9 shrink-0 rounded-xl text-slate-600 hover:bg-purple-50 hover:text-purple-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500" aria-label="Open navigation" aria-controls="main-sidebar" aria-expanded="false">
                            <i class="bi bi-list text-xl" aria-hidden="true"></i>
                        </button>
                    @if(request()->routeIs(['users.*', 'roles.*']))
                    <nav aria-label="User Management" class="flex items-center gap-1 sm:gap-2">
                        <a href="{{ route('users.index') }}"
                           @if(request()->routeIs('users.*')) aria-current="page" @endif
                           class="inline-flex items-center justify-center gap-2 px-3 sm:px-5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2
                                  {{ request()->routeIs('users.*') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                            Users
                        </a>
                        <a href="{{ route('roles.index') }}"
                           @if(request()->routeIs('roles.*')) aria-current="page" @endif
                           class="inline-flex items-center justify-center gap-2 px-3 sm:px-5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2
                                  {{ request()->routeIs('roles.*') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            Roles
                        </a>
                    </nav>
                    @elseif(request()->routeIs('contacts.*'))
                    <nav aria-label="Contacts" class="flex items-center gap-1 sm:gap-2 min-w-0 overflow-x-auto py-1">
                        @foreach(['customer' => ['Customers', 'bi-person-fill'], 'supplier' => ['Suppliers', 'bi-truck'], 'commission' => ['Commission', 'bi-percent']] as $contactType => [$contactLabel, $contactIcon])
                            <a href="{{ route('contacts.index', $contactType) }}"
                               @if(request()->route('type') === $contactType) aria-current="page" @endif
                               class="inline-flex shrink-0 items-center justify-center gap-2 px-3 sm:px-5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-inset
                                      {{ request()->route('type') === $contactType ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                                <i class="bi {{ $contactIcon }}" aria-hidden="true"></i>
                                {{ $contactLabel }}
                            </a>
                        @endforeach
                        <a href="{{ route('contacts.groups.index') }}"
                           @if(request()->routeIs('contacts.groups.*')) aria-current="page" @endif
                           class="inline-flex shrink-0 items-center justify-center gap-2 px-3 sm:px-5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-inset {{ request()->routeIs('contacts.groups.*') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>Customer Groups
                        </a>
                        <a href="{{ route('contacts.import.index') }}"
                           @if(request()->routeIs('contacts.import.*')) aria-current="page" @endif
                           class="inline-flex shrink-0 items-center justify-center gap-2 px-3 sm:px-5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-inset {{ request()->routeIs('contacts.import.*') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
                            <i class="bi bi-upload" aria-hidden="true"></i>Import Contacts
                        </a>
                    </nav>
                    @else
                    <div>
                        <h1 class="text-base font-bold text-slate-900">@yield('title', 'Dashboard')</h1>
                        <p class="text-[11px] text-slate-400">@yield('subtitle', 'Nexus ERP System')</p>
                    </div>
                    @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="sr-only sm:not-sr-only text-xs font-medium text-slate-500">System Online</span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 min-w-0 overflow-x-auto p-6 lg:p-8">

                @yield('content')
            </main>

        </div>
    </div>

    <!-- Scripts -->
    <script>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('main-sidebar');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');

        function setSidebarOpen(open) {
            sidebar.classList.toggle('hidden', !open);
            sidebar.classList.toggle('flex', open);
            sidebarBackdrop.classList.toggle('hidden', !open);
            sidebarToggle.setAttribute('aria-expanded', String(open));
            sidebarToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            if (open) sidebar.querySelector('a').focus();
            else sidebarToggle.focus();
        }

        sidebarToggle.addEventListener('click', () => setSidebarOpen(sidebarToggle.getAttribute('aria-expanded') !== 'true'));
        sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebarToggle.getAttribute('aria-expanded') === 'true') setSidebarOpen(false);
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <!-- DataTables Buttons extensions for Export PDF, Excel, CSV, Print, ColVis -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

    @stack('scripts')
</body>
</html>
