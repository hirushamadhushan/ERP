@props(['activeStep' => 1])

<div class="mb-5 rounded-2xl sm:rounded-3xl p-5 sm:p-6 relative overflow-hidden border border-indigo-100/80 shadow-xs" style="background: linear-gradient(135deg, #f8faff 0%, #f2f0fe 40%, #e9ecff 100%);">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        {{-- Left Info --}}
        <div class="flex items-center gap-3.5 z-10">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-purple-100/90 text-purple-600 shadow-sm border border-purple-200/60">
                <svg class="h-6 w-6 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                    <path d="M15 18H9"/>
                    <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>
                    <circle cx="17" cy="18.5" r="2.5"/>
                    <circle cx="7" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 mb-0.5">Delivery Management</h1>
                <p class="text-xs sm:text-sm text-slate-500 font-medium mb-0">From Warehouse to Customer — End to End Delivery Visibility</p>
            </div>
        </div>

        {{-- Right Illustration (Delivery truck, packages & map pin) --}}
        <div class="hidden md:flex items-center justify-end shrink-0 pointer-events-none pr-2">
            <svg class="h-24 w-64 select-none" viewBox="0 0 280 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Soft background clouds / cityscape -->
                <path d="M190 75 L200 45 L220 45 L225 75 Z" fill="#e0e7ff" opacity="0.6"/>
                <path d="M220 75 L230 35 L255 35 L260 75 Z" fill="#ede9fe" opacity="0.7"/>
                <path d="M250 75 L258 50 L275 50 L280 75 Z" fill="#e0e7ff" opacity="0.6"/>
                
                <!-- Floating Map Pin -->
                <g transform="translate(185, 10)">
                    <path d="M12 0 C5.37 0 0 5.37 0 12 C0 21 12 32 12 32 C12 32 24 21 24 12 C24 5.37 18.63 0 12 0 Z" fill="url(#pinGrad)"/>
                    <circle cx="12" cy="11" r="4.5" fill="#ffffff"/>
                </g>
                <defs>
                    <linearGradient id="pinGrad" x1="0" y1="0" x2="24" y2="32">
                        <stop offset="0%" stop-color="#8b5cf6"/>
                        <stop offset="100%" stop-color="#6366f1"/>
                    </linearGradient>
                </defs>

                <!-- Cardboard Shipping Boxes Stacked -->
                <g transform="translate(170, 50)">
                    <rect x="0" y="8" width="22" height="20" rx="2" fill="#d97706" opacity="0.85"/>
                    <rect x="0" y="8" width="22" height="6" fill="#f59e0b" opacity="0.9"/>
                    <rect x="9" y="8" width="4" height="20" fill="#fef3c7" opacity="0.9"/>
                    
                    <rect x="18" y="0" width="16" height="15" rx="1.5" fill="#f59e0b" opacity="0.9"/>
                    <rect x="18" y="0" width="16" height="4" fill="#fbbf24"/>
                    <rect x="25" y="0" width="3" height="15" fill="#fef3c7"/>
                    
                    <rect x="19" y="14" width="20" height="14" rx="2" fill="#d97706" opacity="0.95"/>
                    <rect x="28" y="14" width="3" height="14" fill="#fef3c7"/>
                </g>

                <!-- Modern Delivery Van / Truck -->
                <g transform="translate(208, 36)">
                    <ellipse cx="36" cy="46" rx="34" ry="4" fill="#6366f1" opacity="0.15"/>
                    
                    <rect x="0" y="8" width="40" height="30" rx="3" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.5"/>
                    <rect x="2" y="10" width="36" height="18" rx="2" fill="#f8fafc"/>
                    <path d="M0 24 L40 24" stroke="#8b5cf6" stroke-width="3"/>
                    
                    <path d="M40 18 L55 18 C58 18 61 21 62 24 L66 32 C67 34 67 36 67 38 L40 38 Z" fill="#6366f1"/>
                    <path d="M43 21 L53 21 C55 21 57 23 58 25 L61 31 L43 31 Z" fill="#38bdf8"/>
                    <line x1="50" y1="21" x2="50" y2="38" stroke="#4338ca" stroke-width="1"/>
                    <rect x="65" y="32" width="2.5" height="4" rx="1" fill="#fef08a"/>
                    <rect x="64" y="36" width="4" height="4" rx="1" fill="#475569"/>

                    <g transform="translate(10, 34)">
                        <circle cx="6" cy="6" r="6" fill="#1e293b"/>
                        <circle cx="6" cy="6" r="2.5" fill="#94a3b8"/>
                    </g>
                    <g transform="translate(46, 34)">
                        <circle cx="6" cy="6" r="6" fill="#1e293b"/>
                        <circle cx="6" cy="6" r="2.5" fill="#94a3b8"/>
                    </g>
                </g>
            </svg>
        </div>
    </div>

    {{-- 6-Step Visual Delivery Pipeline --}}
    <div class="mt-5 flex flex-wrap lg:flex-nowrap items-center justify-between gap-1.5 sm:gap-2">
        @php
            $steps = [
                1 => ['Dashboard', 'Delivery records', 'bi-speedometer2', route('delivery.consignments.index')],
                2 => ['Vehicle Store', 'Available stock', 'bi-box-seam', route('delivery.store')],
                3 => ['Loading', 'Warehouse to vehicle', 'bi-truck-front-fill', route('delivery.loading')],
                4 => ['In Transit', 'Departure recorded', 'bi-geo-alt-fill', route('delivery.consignments.index')],
                5 => ['Customer Receipt', 'Record outcomes', 'bi-building-fill-check', route('delivery.consignments.index')],
                6 => ['Proof of Delivery', 'Signature and photos', 'bi-check-circle-fill', route('delivery.consignments.index')],
            ];
        @endphp

        @foreach($steps as $num => [$title, $subtitle, $icon, $url])
            @php
                $isActive = $activeStep == $num;
            @endphp
            <a href="{{ $url }}" class="flex-1 min-w-[130px] flex items-center gap-2 rounded-xl p-2.5 transition-all text-decoration-none {{ $isActive ? 'shadow-md shadow-indigo-500/20' : 'hover:bg-white/80 border border-slate-200/80 bg-white shadow-2xs' }}" style="{{ $isActive ? 'background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff;' : 'color: #1e293b;' }}">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-black {{ $isActive ? 'bg-white text-indigo-600' : 'bg-slate-100 text-slate-600' }}">
                    {{ $num }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold leading-tight mb-0.5 {{ $isActive ? 'text-white' : 'text-slate-800' }}">{{ $title }}</p>
                    <p class="truncate text-[10px] leading-tight mb-0 {{ $isActive ? 'text-white/80' : 'text-slate-400' }}">{{ $subtitle }}</p>
                </div>
            </a>
            @if(!$loop->last)
                <i class="bi bi-chevron-right text-slate-300 text-xs shrink-0 hidden lg:inline-block"></i>
            @endif
        @endforeach
    </div>
</div>
