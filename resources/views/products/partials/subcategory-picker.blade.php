<div class="subcategory-picker" id="subcategory-picker">
    <label for="subcategory-trigger">Sub category</label>
    <select id="subcategory_id" name="subcategory_id" hidden aria-hidden="true" tabindex="-1">
        <option value="">Please Select</option>
        @foreach($categories->whereNotNull('parent_id') as $category)
        <option value="{{ $category->id }}" data-parent="{{ $category->tree_root_id }}" data-branch="{{ $category->parent_id }}" data-path="{{ $category->tree_path }}" @selected($value('subcategory_id') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <button type="button" id="subcategory-trigger" class="subcategory-trigger" aria-expanded="false" aria-controls="subcategory-panel">
        <i class="bi bi-folder2" aria-hidden="true"></i><span id="subcategory-label">Choose sub-category</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <div id="subcategory-panel" class="subcategory-panel" hidden>
        <label class="subcategory-search" for="subcategory-search"><i class="bi bi-search" aria-hidden="true"></i><input id="subcategory-search" type="search" placeholder="Search sub-categories..." autocomplete="off"></label>
        <div class="subcategory-toolbar"><span id="subcategory-root"></span><button type="button" id="subcategory-clear">Clear selection</button></div>
        <nav id="subcategory-tree" aria-label="Choose a sub-category"></nav>
        <p class="subcategory-empty" id="subcategory-empty" hidden>No matching sub-categories.</p>
    </div>
    <p id="subcategory-path" class="product-help" style="overflow-wrap:anywhere" aria-live="polite"></p>
</div>
@push('scripts')
<style>
.subcategory-picker{position:relative;min-width:0}
#product-form .subcategory-trigger{display:flex;align-items:center;gap:9px;width:100%;min-height:42px;text-align:left;padding:10px 13px;border:1px solid #ddd6fe;border-radius:11px;background:#fff;color:#334155;font:inherit;font-size:13px}
.subcategory-trigger>span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.subcategory-trigger>i:first-child{color:#9333ea}
.subcategory-trigger:disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed}
.subcategory-trigger:focus-visible,.subcategory-trigger[aria-expanded="true"]{outline:2px solid #c084fc;outline-offset:2px}
.subcategory-panel{position:absolute;top:70px;left:0;width:min(440px,calc(100vw - 40px));max-width:100%;z-index:60;border:1px solid #e9d5ff;border-radius:13px;background:white;box-shadow:0 16px 40px #0f172a26;overflow:hidden}
.subcategory-panel[hidden],#subcategory_id[hidden],.subcategory-empty[hidden]{display:none!important}
#product-form .subcategory-search{display:flex;align-items:center;gap:8px;margin:10px;padding:0 10px;border:1px solid #e2e8f0;border-radius:8px;color:#94a3b8}
#product-form #subcategory-search{border:0;box-shadow:none;background:transparent;padding:9px 0;min-width:0;outline:none}
.subcategory-toolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:0 13px 9px;color:#64748b;font-size:11px}.subcategory-toolbar span{overflow-wrap:anywhere}.subcategory-toolbar button{color:#9333ea;white-space:nowrap}
#subcategory-tree{max-height:280px;overflow:auto;padding:4px 10px 12px}
.subcategory-children{margin:0 0 0 10px;padding:0 0 0 15px;border-left:1px solid #ddd6fe;list-style:none}
.subcategory-tree-list{list-style:none;margin:0;padding:0}
.subcategory-item-row{display:flex;align-items:center;gap:4px;min-height:34px;border-radius:6px;position:relative}
.subcategory-children>li>.subcategory-item-row:before{content:'';position:absolute;left:-15px;top:50%;width:14px;border-top:1px solid #ddd6fe}
.subcategory-branch-toggle{flex:0 0 22px;width:22px;height:24px;color:#9333ea;font-size:11px;border-radius:5px}
.subcategory-choice{display:flex;align-items:center;gap:7px;flex:1;text-align:left;padding:7px 6px;min-width:0;color:#475569;font-size:12px;border-radius:6px;overflow-wrap:anywhere}
.subcategory-choice i{color:#a78bfa}.subcategory-choice:hover,.subcategory-branch-toggle:hover{background:#faf5ff}
.subcategory-choice[aria-current="true"]{background:#f3e8ff;color:#7e22ce;font-weight:600}
.subcategory-choice:focus-visible,.subcategory-branch-toggle:focus-visible{outline:2px solid #a855f7}
.subcategory-empty{padding:12px 16px;font-size:12px;color:#64748b}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker=document.getElementById('subcategory-picker'), select=document.getElementById('subcategory_id');
    const root=document.getElementById('category_id'), trigger=document.getElementById('subcategory-trigger');
    const panel=document.getElementById('subcategory-panel'), search=document.getElementById('subcategory-search');
    const tree=document.getElementById('subcategory-tree'), collapsed=new Set();
    const close=()=>{panel.hidden=true;trigger.setAttribute('aria-expanded','false');};
    function sync(){
        trigger.disabled=!root.value;
        document.getElementById('subcategory-label').textContent=select.value?select.selectedOptions[0].textContent:'Choose sub-category';
        document.getElementById('subcategory-root').textContent=root.selectedOptions[0]?.textContent || '';
    }
    function render(){
        tree.replaceChildren();
        const options=Array.from(select.options).filter(option=>option.value && option.dataset.parent===root.value);
        const term=search.value.trim().toLowerCase(), visible=new Set();
        const map=new Map(options.map(option=>[option.value,option]));
        options.forEach(option=>{
            if(!term || option.textContent.toLowerCase().includes(term)){
                let current=option;
                while(current){visible.add(current.value);current=map.get(current.dataset.branch);}
            }
        });
        const build=(parent, nested=false)=>{
            const list=document.createElement('ul');list.className=nested?'subcategory-children':'subcategory-tree-list';
            options.filter(option=>(option.dataset.branch || option.dataset.parent)===parent && visible.has(option.value)).forEach(option=>{
                const li=document.createElement('li'),row=document.createElement('div');row.className='subcategory-item-row';
                const children=options.some(child=>child.dataset.branch===option.value && visible.has(child.value));
                if(children){
                    const toggle=document.createElement('button');toggle.type='button';toggle.className='subcategory-branch-toggle';
                    const expanded=!!term || !collapsed.has(option.value);
                    toggle.textContent=expanded?'−':'+';toggle.setAttribute('aria-expanded',String(expanded));toggle.setAttribute('aria-label','Expand or collapse '+option.textContent);
                    toggle.addEventListener('click',()=>{collapsed.has(option.value)?collapsed.delete(option.value):collapsed.add(option.value);render();tree.querySelector('[data-toggle="'+option.value+'"]')?.focus();});
                    toggle.dataset.toggle=option.value;row.append(toggle);
                }else{const spacer=document.createElement('span');spacer.className='subcategory-branch-toggle';row.append(spacer);}
                const button=document.createElement('button');button.type='button';button.className='subcategory-choice';button.title=option.dataset.path || option.textContent;
                button.setAttribute('aria-current',String(select.value===option.value));
                const icon=document.createElement('i');icon.className='bi bi-folder2';icon.setAttribute('aria-hidden','true');
                const label=document.createElement('span');label.textContent=option.textContent;
                button.append(icon,label);button.addEventListener('click',()=>{select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();close();trigger.focus();});
                row.append(button);li.append(row);
                if(children && (term || !collapsed.has(option.value)))li.append(build(option.value,true));
                list.append(li);
            });
            return list;
        };
        tree.append(build(root.value));
        document.getElementById('subcategory-empty').hidden=visible.size>0;
    }
    trigger.addEventListener('click',()=>{
        if(!panel.hidden){close();return;}
        search.value='';sync();render();panel.hidden=false;trigger.setAttribute('aria-expanded','true');search.focus();
    });
    search.addEventListener('input',render);
    root.addEventListener('change',()=>{collapsed.clear();sync();close();});
    select.addEventListener('change',sync);
    document.getElementById('subcategory-clear').addEventListener('click',()=>{select.value='';select.dispatchEvent(new Event('change',{bubbles:true}));sync();close();trigger.focus();});
    document.addEventListener('click',event=>{if(!picker.contains(event.target))close();});
    picker.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();trigger.focus();}});
    picker.addEventListener('focusout',()=>{setTimeout(()=>{if(!picker.contains(document.activeElement))close();},0);});
    sync();
});
</script>
@endpush
