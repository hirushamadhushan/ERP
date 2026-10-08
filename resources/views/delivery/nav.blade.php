<nav aria-label="Delivery" class="flex items-center gap-2 overflow-x-auto py-1">
@foreach([
    ['delivery.consignments.index','Dashboard','bi-speedometer2',request()->routeIs('delivery.consignments.index','delivery.consignments.create')],
    ['delivery.vehicles.index','Vehicles','bi-truck',request()->routeIs('delivery.vehicles.*')],
    ['delivery.drivers.index','Drivers','bi-person-badge',request()->routeIs('delivery.drivers.*')],
    ['delivery.store','Vehicle Store','bi-box-seam',request()->routeIs('delivery.store','delivery.loading','delivery.unloading','delivery.transfers.*')],
    ['delivery.routes.index','Routes','bi-signpost-split',request()->routeIs('delivery.routes.*')]
] as [$route,$label,$icon,$active])
<a href="{{ route($route) }}" @if($active) aria-current="page" @endif class="inline-flex shrink-0 items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 {{ $active ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-slate-600 hover:bg-purple-50 hover:text-purple-700' }}">
    <i class="bi {{ $icon }}"></i>{{ $label }}
</a>
@endforeach
</nav>
