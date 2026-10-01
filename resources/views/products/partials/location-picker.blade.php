<div class="wide">
    <label id="location-picker-label" class="mb-1">Business Locations * <span class="product-info" tabindex="0" title="Select every branch or warehouse that stocks this product.">i</span></label>
    <details id="location-picker" class="relative open:z-30">
        <summary aria-labelledby="location-picker-label selected-location-label" class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 [&::-webkit-details-marker]:hidden">
            <span id="selected-location-label" class="min-w-0 truncate">Select business locations</span>
            <span aria-hidden="true" class="shrink-0 text-slate-500">&#9662;</span>
        </summary>
        <div class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
            @foreach($locations as $location)
                <label style="display:flex;flex-direction:row;align-items:center;gap:8px;cursor:pointer;margin:0;padding:6px 8px" class="rounded-md text-sm leading-tight hover:bg-purple-50">
                    <input style="display:block;flex:0 0 16px;width:16px;height:16px;margin:0" class="location-choice accent-purple-600" type="checkbox" name="location_ids[]" value="{{ $location->id }}" data-location-name="{{ $location->name }}" @checked(in_array($location->id, $selectedLocations))>
                    <span style="min-width:0;flex:1"><strong class="block truncate text-slate-700">{{ $location->name }}</strong><small class="block truncate text-slate-400">{{ $location->code }}</small></span>
                </label>
            @endforeach
        </div>
    </details>
    <p class="product-help">Select one or more locations. Rack fields appear for each selected location.</p>
    <div id="location-racks" class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach($locations as $location)
            @php $detail = ($locationDetails ?? collect())->get($location->id); @endphp
            <div class="location-rack rounded-xl border border-purple-100 bg-purple-50/40 p-3" data-location="{{ $location->id }}" @if(!in_array($location->id, $selectedLocations)) hidden @endif>
                <strong class="mb-2 block text-xs text-purple-700">{{ $location->name }}</strong>
                <div class="grid grid-cols-3 gap-2">
                    <input name="location_details[{{ $location->id }}][rack]" maxlength="100" placeholder="Rack" value="{{ old('location_details.'.$location->id.'.rack', $detail->rack ?? '') }}">
                    <input name="location_details[{{ $location->id }}][row]" maxlength="100" placeholder="Row" value="{{ old('location_details.'.$location->id.'.row', $detail->row ?? '') }}">
                    <input name="location_details[{{ $location->id }}][position]" maxlength="100" placeholder="Position" value="{{ old('location_details.'.$location->id.'.position', $detail->position ?? '') }}">
                </div>
            </div>
        @endforeach
    </div>
</div>
