@props(['activeStep' => 1])
<div class="mb-6 rounded-2xl p-5 sm:p-6 text-white shadow-xl" style="background:linear-gradient(110deg,#0f172a,#312e81,#581c87);color:#fff">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-white/10 pb-5">
        <div class="flex items-center gap-3.5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-purple-500 to-indigo-500 text-white shadow-lg shadow-purple-500/30">
                <i class="bi bi-truck text-2xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">ERP Delivery Module</h1>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" style="background:rgba(16,185,129,.2);color:#a7f3d0">Delivery workflow</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-300">From Warehouse to Customer — End to End Delivery Visibility</p>
            </div>
        </div>
        
    </div>

    <!-- 6-Step Visual Delivery Pipeline -->
    <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $steps = [
                1 => ['Dashboard', 'Delivery records', 'bi-speedometer2', 'emerald'],
                2 => ['Vehicle Store', 'Available stock', 'bi-box-seam', 'blue'],
                3 => ['Loading', 'Warehouse to vehicle', 'bi-truck-front-fill', 'amber'],
                4 => ['In Transit', 'Departure recorded', 'bi-geo-alt-fill', 'cyan'],
                5 => ['Customer Receipt', 'Record outcomes', 'bi-building-fill-check', 'purple'],
                6 => ['Proof of Delivery', 'Signature and photos', 'bi-check-circle-fill', 'rose'],
            ];
        @endphp

        @foreach($steps as $num => [$title, $subtitle, $icon, $color])
            @php
                $isActive = $activeStep == $num;
                $isPassed = $activeStep > $num;
            @endphp
            <div class="relative flex items-center gap-2.5 rounded-xl border p-2.5 transition-all" style="background:rgba(255,255,255,{{ $isActive ? '.22' : '.10' }});border-color:rgba(255,255,255,{{ $isActive ? '.55' : '.22' }});color:#fff">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold" style="background:{{ $isActive ? '#9333ea' : 'rgba(255,255,255,.18)' }};color:#fff">
                    {{ $num }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold" style="color:#fff">{{ $title }}</p>
                    <p class="truncate text-[10px]" style="color:#dbeafe">{{ $subtitle }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
