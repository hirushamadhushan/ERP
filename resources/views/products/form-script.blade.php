
<script>
if(window.tinymce) tinymce.init({
    selector:'#description',
    license_key:'gpl',
    height:320,
    menubar:'file edit view insert format tools table help',
    plugins:'lists table code help wordcount searchreplace visualblocks fullscreen',
    toolbar:'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table | removeformat',
    toolbar_mode:'sliding',
    promotion:false,
    branding:true,
    resize:true,
    block_formats:'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
    valid_elements:'p[style],div[style],span[style],br,strong,b,em,i,u,s,h2[style],h3[style],h4[style],blockquote,ul,ol,li,table,thead,tbody,tfoot,tr,td[colspan|rowspan|style],th[colspan|rowspan|style],caption',
    valid_styles:{'*':'text-align'},
    content_style:'body {font-family:Arial,sans-serif;font-size:14px;color:#263248;margin:16px} table{border-collapse:collapse} td,th{border:1px solid #cbd5e1;padding:6px}',
    setup:editor=>{
        editor.on('change input undo redo',()=>editor.save());
        document.getElementById('product-form').addEventListener('submit',()=>editor.save());
    }
});
</script>
<script>
(()=>{
const byId=id=>document.getElementById(id);
const productForm=byId('product-form');
let productSaving=false, productDirty=false;
productForm.addEventListener('input',()=>productDirty=true);
productForm.addEventListener('change',()=>productDirty=true);
window.addEventListener('beforeunload',event=>{if(productDirty&&!productSaving){event.preventDefault();event.returnValue='';}}, {signal: AppPage.signal});
// Protect unsaved product edits during in-app navigation as well.
document.addEventListener('turbo:before-visit', event => { if(productDirty && !productSaving && !confirm('Discard unsaved product changes?')) event.preventDefault(); }, {signal: AppPage.signal});
const locationChoices=Array.from(document.querySelectorAll('.location-choice'));
const syncLocationRacks=()=>{const checked=locationChoices.filter(input=>input.checked),selected=new Set(checked.map(input=>input.value));const label=byId('selected-location-label');if(label){const names=checked.map(input=>input.dataset.locationName);label.textContent=names.length>2?`${names.slice(0,2).join(', ')} +${names.length-2} more`:(names.join(', ')||'Select business locations');label.title=names.join(', ');}document.querySelectorAll('.location-rack').forEach(card=>{const active=selected.has(card.dataset.location);card.hidden=!active;card.querySelectorAll('input').forEach(input=>input.disabled=!active);});};
locationChoices.forEach(input=>input.addEventListener('change',syncLocationRacks));syncLocationRacks();
const openingStockAction=byId('save-and-open-stock'),rawMaterial=byId('is_raw_material'),manageStock=byId('manage_stock'),productType=byId('product_type');
const syncOpeningStockAction=()=>{const automaticFinishedLot=manageStock?.checked&&productType?.value==='single'&&!rawMaterial?.checked;if(openingStockAction)openingStockAction.textContent=automaticFinishedLot?'Save & Receive Initial Lot':'Save & Add Opening Stock';};
[rawMaterial,manageStock,productType].forEach(field=>field?.addEventListener('change',syncOpeningStockAction));syncOpeningStockAction();
let scanBuffer='',lastScanAt=0;
document.addEventListener('keydown',event=>{const target=document.activeElement?.tagName;if(event.ctrlKey||event.altKey||event.metaKey||['INPUT','TEXTAREA','SELECT'].includes(target))return;const now=Date.now();if(now-lastScanAt>80)scanBuffer='';lastScanAt=now;if(event.key==='Enter'){if(scanBuffer.length>=6){byId('sku').value=scanBuffer;byId('sku').dispatchEvent(new Event('input',{bubbles:true}));event.preventDefault();}scanBuffer='';}else if(event.key.length===1)scanBuffer+=event.key;}, {signal: AppPage.signal});
productForm.addEventListener('submit',async event=>{
    event.preventDefault();
    if(productSaving)return;
    window.tinymce?.triggerSave();
    const data=new FormData(productForm);
    data.set('save_action',event.submitter?.value||'save');
    const buttons=Array.from(productForm.querySelectorAll('[name="save_action"]'));
    productSaving=true;buttons.forEach(button=>button.disabled=true);
    let errorBox=byId('product-save-error');
    if(!errorBox){
        errorBox=document.createElement('div');errorBox.id='product-save-error';errorBox.setAttribute('role','alert');
        errorBox.style.cssText='padding:16px;margin-bottom:20px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:12px';
        productForm.prepend(errorBox);
    }
    errorBox.hidden=true;
    try {
        const result=await AppErrors.request(productForm.action,{method:'POST',body:data});
        productDirty=false;location.assign(result.redirect);
    }catch(error){
        errorBox.textContent=error.message;errorBox.hidden=false;
        errorBox.scrollIntoView({behavior:'smooth',block:'center'});
    }finally{productSaving=false;buttons.forEach(button=>button.disabled=false);}
});
const categories=()=>{const parent=byId('category_id').value, sub=byId('subcategory_id');
    Array.from(sub.options).forEach(option=>{if(option.value){option.hidden=option.dataset.parent!==parent;option.disabled=option.hidden;}});
    if(sub.selectedOptions[0]?.disabled)sub.value='';
    sub.disabled = !parent;
    document.getElementById('subcategory-path').textContent = sub.selectedOptions[0]?.dataset.path || (parent ? 'Choose any level below the main category.' : 'Select a main category first.');
};
if (byId('category_id') && byId('subcategory_id')) {
    byId('category_id').addEventListener('change',categories);
    byId('subcategory_id').addEventListener('change',categories);
    categories();
}
const stock = () => {
    const manageStock = byId('manage_stock').checked;
    const serialInput = byId('enable_serial');
    byId('alert_quantity').disabled = !manageStock;
    // Business settings can hide serial tracking entirely from this form.
    if (!serialInput) return;
    const decimalUnit = byId('unit_id').selectedOptions[0]?.dataset.decimal === '1';
    serialInput.setCustomValidity(serialInput.checked && (!manageStock || decimalUnit)
        ? 'Serial tracking requires stock management and a whole-number unit.' : '');
};
['manage_stock','enable_serial','unit_id'].forEach(id=>byId(id)?.addEventListener('change',stock));stock();
let previousTax=Number(byId('tax_rate').value)||0, previousType=byId('selling_price_tax_type').value;
const n=id=>Number(byId(id).value)||0, empty=id=>byId(id).value.trim()==='', put=(id,v)=>byId(id).value=(Math.round((v+Number.EPSILON)*10000)/10000).toFixed(4);
const factor=()=>1+n('tax_rate')/100;
const inc=()=>{if(empty('purchase_price')){byId('purchase_price_inc').value='';return;}put('purchase_price_inc',n('purchase_price')*factor());};
const sellingFromMargin=()=>{if(empty('purchase_price')){byId('selling_price').value='';return;}put('selling_price',n('purchase_price')*(1+n('margin')/100)*(byId('selling_price_tax_type').value==='inclusive'?factor():1));};
const marginFromSelling=()=>{if(empty('purchase_price')||empty('selling_price'))return;const sell=n('selling_price')/(byId('selling_price_tax_type').value==='inclusive'?factor():1);if(n('purchase_price')>0)put('margin',((sell/n('purchase_price'))-1)*100);};
byId('purchase_price').addEventListener('input',()=>{inc();sellingFromMargin();});
byId('purchase_price_inc').addEventListener('input',()=>{put('purchase_price',n('purchase_price_inc')/factor());sellingFromMargin();});
byId('margin').addEventListener('input',sellingFromMargin);
byId('selling_price').addEventListener('input',marginFromSelling);
const taxChange=()=>{
    if(empty('selling_price')){previousTax=n('tax_rate');previousType=byId('selling_price_tax_type').value;byId('selling-label').textContent=previousType==='inclusive'?'Inc. tax *':'Exc. tax *';byId('variant-selling-tax-label').textContent=previousType==='inclusive'?'Inc. Tax':'Exc. Tax';Array.from(byId('variant-rows').rows).forEach(row=>row.refreshTax?.());inc();return;}
    const exclusive=n('selling_price')/(previousType==='inclusive'?1+previousTax/100:1);
    put('selling_price',exclusive*(byId('selling_price_tax_type').value==='inclusive'?factor():1));
    previousTax=n('tax_rate');previousType=byId('selling_price_tax_type').value;
    byId('selling-label').textContent=previousType==='inclusive'?'Inc. tax *':'Exc. tax *';
    byId('variant-selling-tax-label').textContent=previousType==='inclusive'?'Inc. Tax':'Exc. Tax';
    Array.from(byId('variant-rows').rows).forEach(row=>row.refreshTax?.());
    inc();marginFromSelling();
};
byId('selling-label').textContent=previousType==='inclusive'?'Inc. tax *':'Exc. tax *';
byId('tax_rate').addEventListener('input',taxChange);byId('selling_price_tax_type').addEventListener('change',taxChange);
const taxMode=()=>{const mode=byId('tax_mode'),option=mode.selectedOptions[0],configured=mode.value.startsWith('tax:');byId('tax_rate_id').value=configured?mode.value.slice(4):'';byId('tax_rate').readOnly=configured||mode.value==='none';byId('tax_rate').hidden=mode.value==='none';if(configured)byId('tax_rate').value=option.dataset.rate;if(mode.value==='none')byId('tax_rate').value=0;taxChange();};
byId('tax_mode').addEventListener('change',taxMode);taxMode();inc();
let imageUrl;
byId('image').addEventListener('change',event=>{
    if(imageUrl)URL.revokeObjectURL(imageUrl);
    const file=event.target.files[0];byId('image-preview').classList.toggle('hidden',!file);
    if(file){imageUrl=URL.createObjectURL(file);byId('image-preview').src=imageUrl;}
});
const dialog=byId('reference-dialog');let kind;
document.querySelectorAll('[data-reference]').forEach(button=>button.addEventListener('click',()=>{
    kind=button.dataset.reference;
    if(kind==='subcategory' && !byId('category_id').value){byId('category_id').focus();byId('category_id').setCustomValidity('Select a category first.');byId('category_id').reportValidity();byId('category_id').setCustomValidity('');return;}
    byId('reference-form').reset();byId('reference-error').textContent='';
    byId('reference-title').textContent='Add '+kind;
    document.querySelectorAll('[data-ref-kind]').forEach(section=>{section.hidden=section.dataset.refKind!==kind;section.querySelectorAll('input,select').forEach(input=>{input.disabled=section.hidden;input.required=!section.hidden;});});
    dialog.showModal();byId('reference-name').focus();
}));
byId('reference-close').addEventListener('click',()=>dialog.close());
byId('reference-form').addEventListener('submit',async event=>{
    event.preventDefault();const button=byId('reference-save');button.disabled=true;
    const data=new FormData(event.target);data.set('kind',kind==='subcategory'?'category':kind);if(kind==='subcategory')data.set('parent_id',byId('category_id').value);
    try{
        const result=await AppErrors.request(@json(route('products.catalog.reference')),{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data});
        const select=byId(kind==='location'?'location_ids':kind==='subcategory'?'subcategory_id':kind+'_id');
        const option=new Option(result.name+(result.code?' ('+result.code+')':''),result.id,true,true);
        if(kind==='unit')option.dataset.decimal=result.allow_decimal?'1':'0';
        if(kind==='subcategory')option.dataset.parent=result.parent_id;
        select.add(option);categories();stock();dialog.close();
    }catch(error){byId('reference-error').textContent=error.message;}
    finally{button.disabled=false;}
});

const productType=byId('product_type'), singlePanel=byId('single-pricing'), variablePanel=byId('variable-pricing'), comboPanel=byId('combo-pricing');
const templateSelect=byId('variation_template_id'), variantBody=byId('variant-rows'), comboBody=byId('combo-rows');
const savedVariants=JSON.parse(byId('saved-variants')?.textContent||'[]');
const savedComboItems=JSON.parse(byId('saved-combo-items')?.textContent||'[]');
const comboProducts=@json($comboProductOptions);
const comboSearch=byId('combo-product-search'), comboResults=byId('combo-search-results');
const money=value=>Number(value||0).toFixed(4);
const currency=value=>'Rs '+Number(value||0).toLocaleString('en-LK',{minimumFractionDigits:2,maximumFractionDigits:2});

function variantRow(value,index,saved={}){
    const defaultMarg = (typeof window.__defaultMargin !== 'undefined' && window.__defaultMargin !== null) ? window.__defaultMargin : 25;
    const tr=document.createElement('tr');
    tr.innerHTML=`<td><input name="variants[${index}][sku]" maxlength="100" placeholder="" aria-label="Variation SKU"></td><td><input name="variants[${index}][value]" ${value ? 'readonly' : ''} placeholder="Value" aria-label="Variation value"></td><td><div class="variant-purchase-fields"><input type="number" name="variants[${index}][purchase_price]" min="0" step="0.0001" placeholder="Exc. tax" aria-label="Purchase price excluding tax"><input type="number" class="variant-purchase-inc" min="0" step="0.0001" placeholder="Inc. tax" aria-label="Purchase price including tax"></div></td><td><div class="variant-margin-fields"><input class="variant-margin" type="number" min="-100" max="999999" step="any" value="${defaultMarg}" aria-label="Variation margin percentage"></div></td><td><input type="number" name="variants[${index}][selling_price]" min="0" step="0.0001" placeholder="${byId('selling_price_tax_type').value==='inclusive'?'Inc. tax':'Exc. tax'}" aria-label="Variation selling price"></td><td><input type="file" name="variant_images[${index}]" accept=".jpg,.jpeg,.png,.webp" aria-label="Variation images"></td><td><button type="button" class="row-remove" aria-label="Remove variation">âˆ’</button></td>`;
    const sku=tr.querySelector('[name$="[sku]"]'), variantValue=tr.querySelector('[name$="[value]"]');
    const purchase=tr.querySelector('[name$="[purchase_price]"]'), purchaseInc=tr.querySelector('.variant-purchase-inc');
    const margin=tr.querySelector('.variant-margin'), selling=tr.querySelector('[name$="[selling_price]"]');
    sku.value=saved.sku??'';variantValue.value=value||saved.value||'';
    purchase.value=(saved.purchase_price!==undefined&&saved.purchase_price!==null&&saved.purchase_price!==''&&Number(saved.purchase_price)!==0)?saved.purchase_price:'';
    selling.value=(saved.selling_price!==undefined&&saved.selling_price!==null&&saved.selling_price!==''&&Number(saved.selling_price)!==0)?saved.selling_price:'';
    const refreshTax=()=>{purchaseInc.value=purchase.value===''?'':money((Number(purchase.value)||0)*factor());};
    const refreshMargin=()=>{if(purchase.value===''||selling.value===''){margin.value=defaultMarg;return;}const base=Number(purchase.value)||0, net=(Number(selling.value)||0)/(byId('selling_price_tax_type').value==='inclusive'?factor():1);margin.value=base?money((net/base-1)*100):money(defaultMarg);};
    const refreshSelling=()=>{if(purchase.value===''){selling.value='';syncSummary();return;}const base=Number(purchase.value)||0;selling.value=money(base*(1+(Number(margin.value)||0)/100)*(byId('selling_price_tax_type').value==='inclusive'?factor():1));syncSummary();};
    purchase.addEventListener('input',()=>{refreshTax();refreshSelling();});
    purchaseInc.addEventListener('input',()=>{purchase.value=purchaseInc.value===''?'':money((Number(purchaseInc.value)||0)/factor());refreshSelling();});
    margin.addEventListener('input',refreshSelling);
    selling.addEventListener('input',()=>{refreshMargin();syncSummary();});
    tr.querySelector('.row-remove').addEventListener('click',()=>{tr.remove();renumberRows(variantBody,'variants');syncSummary();});
    tr.refreshTax=()=>{
        selling.placeholder=byId('selling_price_tax_type').value==='inclusive'?'Inc. tax':'Exc. tax';
        refreshTax();refreshMargin();
    };
    refreshTax();refreshMargin();return tr;
}
function templateValues(){let values=[];try{values=JSON.parse(templateSelect.selectedOptions[0]?.dataset.values||'[]')}catch{}return values;}
function loadVariationRows(){
    variantBody.innerHTML='';const values=templateValues();
    values.forEach((value,index)=>variantBody.appendChild(variantRow(value,index,savedVariants.find(item=>item.value===value)||{})));
}
function addVariationRow(){
    const currentValues=Array.from(variantBody.rows).map(row=>row.querySelector('[name$="[value]"]')?.value).filter(Boolean);
    const unusedValue=templateValues().find(item=>!currentValues.includes(item));
    const valueToAdd=unusedValue||'';
    const row=variantRow(valueToAdd,variantBody.rows.length,{});
    variantBody.appendChild(row);
    const valInput=row.querySelector('[name$="[value]"]');
    if(valInput&&!valueToAdd){
        valInput.readOnly=false;
        valInput.placeholder='Value';
        valInput.focus();
    }
    syncSummary();
}
function comboPricing(){
    if(!comboBody.rows.length){
        byId('purchase_price').value='';
        byId('purchase_price_inc').value='';
        byId('selling_price').value='';
        byId('combo-net-total').textContent=currency(0);
        byId('combo_selling_price').value='';
        return;
    }
    let purchase=0;
    Array.from(comboBody.rows).forEach(row=>{
        const product=comboProducts.find(item=>String(item.id)===row.dataset.productId);
        const quantity=Number(row.querySelector('[name$="[quantity]"]').value)||0;
        const total=(Number(product?.purchase_price)||0)*quantity;
        row.querySelector('.combo-row-total').textContent=currency(total);
        purchase+=total;
    });
    put('purchase_price',purchase);
    sellingFromMargin();
    byId('combo-net-total').textContent=currency(purchase);
    byId('combo_margin').value=byId('margin').value;
    byId('combo_selling_price').value=byId('selling_price').value;
}
function addComboProduct(product){
    const existing=Array.from(comboBody.rows).find(row=>String(row.dataset.productId)===String(product.id));
    if(existing){
        const quantity=existing.querySelector('[name$="[quantity]"]');
        quantity.value=(Number(quantity.value)||0)+1;
        quantity.focus();
    }else{
        comboBody.appendChild(comboRow(comboBody.rows.length,{},product));
    }
    comboSearch.value='';comboResults.hidden=true;syncSummary();
}
function showComboResults(){
    const term=comboSearch.value.trim().toLowerCase();
    const matches=term?comboProducts.filter(product=>`${product.name} ${product.code}`.toLowerCase().includes(term)).slice(0,8):[];
    comboResults.innerHTML='';
    matches.forEach(product=>{
        const button=document.createElement('button');button.type='button';button.className='combo-result';button.setAttribute('role','option');
        button.innerHTML=`${product.name}<small>${product.code} Â· ${currency(product.purchase_price)}</small>`;
        button.addEventListener('click',()=>addComboProduct(product));comboResults.appendChild(button);
    });
    comboResults.hidden=matches.length===0;
}
function comboRow(index,saved={},product=null){
    const tr=document.createElement('tr');
    product=product||comboProducts.find(item=>String(item.id)===String(saved.product_id));
    if(!product)return tr;
    tr.dataset.productId=product.id;
    tr.innerHTML=`<td><input type="hidden" name="combo_items[${index}][product_id]" value="${product.id}"><strong>${product.name}</strong><small class="block text-slate-500 mt-1">${product.code}</small></td><td><input type="number" name="combo_items[${index}][quantity]" required min="0.0001" step="0.0001" value="${saved.quantity||1}" aria-label="Quantity for ${product.name}"></td><td class="combo-money">${currency(product.purchase_price)}</td><td class="combo-money combo-row-total">Rs 0.00</td><td><button type="button" class="row-remove" aria-label="Remove ${product.name}"><i class="bi bi-trash-fill" aria-hidden="true"></i></button></td>`;
    const quantity=tr.querySelector('[name$="[quantity]"]');
    quantity.addEventListener('input',syncSummary);quantity.addEventListener('change',syncSummary);
    tr.querySelector('button').addEventListener('click',()=>{tr.remove();renumberRows(comboBody,'combo_items');syncSummary();});
    return tr;
}
function renumberRows(body,prefix){Array.from(body.rows).forEach((row,index)=>row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(new RegExp(`^${prefix}\\[\\d+\\]`),`${prefix}[${index}]`);if(prefix==='variants')input.name=input.name.replace(/^variant_images\[\d+\]/,`variant_images[${index}]`);}));}
function syncSummary(){
    if(productType.value==='variable'&&variantBody.rows.length){
        const rows=Array.from(variantBody.rows);
        const purchases=rows.map(row=>row.querySelector('[name$="[purchase_price]"]').value).filter(value=>value!=='').map(Number);
        const sales=rows.map(row=>row.querySelector('[name$="[selling_price]"]').value).filter(value=>value!=='').map(Number);
        byId('purchase_price').value=purchases.length?Math.min(...purchases):'';
        byId('selling_price').value=sales.length?Math.min(...sales):'';
    }
    if(productType.value==='combo'){comboPricing();inc();return;}
    inc();marginFromSelling();
}
function switchProductType(){
    const type=productType.value;singlePanel.hidden=type!=='single';variablePanel.hidden=type!=='variable';comboPanel.hidden=type!=='combo';
    if(type==='combo'){if(byId('enable_serial')) byId('enable_serial').checked=false;byId('manage_stock').checked=false;}stock();
    templateSelect.disabled=type!=='variable';Array.from(variablePanel.querySelectorAll('input')).forEach(input=>input.disabled=type!=='variable');Array.from(comboPanel.querySelectorAll('input,select')).forEach(input=>input.disabled=type!=='combo');
    if(type==='variable'&&!variantBody.rows.length&&templateSelect.value)loadVariationRows();syncSummary();
}
templateSelect.addEventListener('change',loadVariationRows);productType.addEventListener('change',switchProductType);
byId('focus-variation')?.addEventListener('click',()=>templateSelect.focus());
byId('add-variant-row').addEventListener('click',addVariationRow);
byId('variant-selling-tax-label').textContent=byId('selling_price_tax_type').value==='inclusive'?'Inc. Tax':'Exc. Tax';
comboSearch.addEventListener('input',showComboResults);
comboSearch.addEventListener('keydown',event=>{if(event.key==='Enter'&&!comboResults.hidden){event.preventDefault();comboResults.querySelector('button')?.click();}});
document.addEventListener('click',event=>{if(!event.target.closest('.combo-search'))comboResults.hidden=true;}, {signal: AppPage.signal});
byId('combo_margin').addEventListener('input',()=>{put('margin',Number(byId('combo_margin').value)||0);sellingFromMargin();byId('combo_selling_price').value=byId('selling_price').value;});
byId('combo_selling_price').addEventListener('input',()=>{put('selling_price',Number(byId('combo_selling_price').value)||0);marginFromSelling();byId('combo_margin').value=byId('margin').value;});
byId('margin').addEventListener('input',()=>{if(productType.value==='combo')comboPricing();});
if(templateSelect.value)loadVariationRows();savedComboItems.forEach((item,index)=>{const row=comboRow(index,item);if(row.dataset.productId)comboBody.appendChild(row);});switchProductType();
})();
</script>
<script>
(()=>{
const productTooltip=document.getElementById('product-tooltip');
let activeHelp=null;
const positionProductTooltip=()=>{
    if(!activeHelp||!productTooltip)return;
    const anchor=activeHelp.getBoundingClientRect(),tip=productTooltip.getBoundingClientRect(),gap=10,pad=12;
    const belowFits=anchor.bottom+gap+tip.height<=window.innerHeight-pad;
    const side=belowFits?'bottom':'top';
    let left=anchor.left+anchor.width/2-tip.width/2;
    left=Math.max(pad,Math.min(left,window.innerWidth-tip.width-pad));
    const top=side==='bottom'?anchor.bottom+gap:anchor.top-tip.height-gap;
    productTooltip.dataset.side=side;
    productTooltip.style.left=`${left}px`;
    productTooltip.style.top=`${Math.max(pad,top)}px`;
    productTooltip.style.setProperty('--arrow-left',`${Math.max(12,Math.min(tip.width-12,anchor.left+anchor.width/2-left))}px`);
};
const showProductTooltip=target=>{
    if(!productTooltip||!target.dataset.help)return;
    activeHelp=target;
    productTooltip.textContent=target.dataset.help;
    productTooltip.setAttribute('aria-hidden','false');
    productTooltip.classList.add('is-visible');
    positionProductTooltip();
};
const hideProductTooltip=target=>{
    if(target&&activeHelp!==target)return;
    activeHelp=null;
    productTooltip?.classList.remove('is-visible');
    productTooltip?.setAttribute('aria-hidden','true');
};
document.querySelectorAll('.product-info[data-help]').forEach(info=>{
    info.addEventListener('mouseenter',()=>showProductTooltip(info));
    info.addEventListener('mouseleave',()=>hideProductTooltip(info));
    info.addEventListener('focus',()=>showProductTooltip(info));
    info.addEventListener('blur',()=>hideProductTooltip(info));
    info.addEventListener('keydown',event=>{if(event.key==='Escape'){hideProductTooltip(info);info.blur();}});
});
window.addEventListener('resize',positionProductTooltip);
window.addEventListener('scroll',positionProductTooltip,true);
})();
</script>
