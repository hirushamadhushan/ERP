<script src="{{ asset('js/vendor/tinymce/tinymce.min.js') }}"></script>
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
let productSaving=false;
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
        location.assign(result.redirect);
    }catch(error){
        errorBox.textContent=error.message;errorBox.hidden=false;
        errorBox.scrollIntoView({behavior:'smooth',block:'center'});
    }finally{productSaving=false;buttons.forEach(button=>button.disabled=false);}
});
const categories=()=>{const parent=byId('category_id').value, sub=byId('subcategory_id');
    Array.from(sub.options).forEach(option=>{if(option.value){option.hidden=option.dataset.parent!==parent;option.disabled=option.hidden;}});
    if(sub.selectedOptions[0]?.disabled)sub.value='';
};
byId('category_id').addEventListener('change',categories);categories();
const stock=()=>{byId('alert_quantity').disabled=!byId('manage_stock').checked;byId('enable_serial').setCustomValidity(byId('enable_serial').checked && (!byId('manage_stock').checked || byId('unit_id').selectedOptions[0]?.dataset.decimal==='1')?'Serial tracking requires stock management and a whole-number unit.':'');};
['manage_stock','enable_serial','unit_id'].forEach(id=>byId(id).addEventListener('change',stock));stock();
let previousTax=Number(byId('tax_rate').value)||0, previousType=byId('selling_price_tax_type').value;
const n=id=>Number(byId(id).value)||0, put=(id,v)=>byId(id).value=(Math.round((v+Number.EPSILON)*10000)/10000).toFixed(4);
const factor=()=>1+n('tax_rate')/100;
const inc=()=>put('purchase_price_inc',n('purchase_price')*factor());
const sellingFromMargin=()=>put('selling_price',n('purchase_price')*(1+n('margin')/100)*(byId('selling_price_tax_type').value==='inclusive'?factor():1));
const marginFromSelling=()=>{const sell=n('selling_price')/(byId('selling_price_tax_type').value==='inclusive'?factor():1);if(n('purchase_price')>0)put('margin',((sell/n('purchase_price'))-1)*100);};
byId('purchase_price').addEventListener('input',()=>{inc();sellingFromMargin();});
byId('purchase_price_inc').addEventListener('input',()=>{put('purchase_price',n('purchase_price_inc')/factor());sellingFromMargin();});
byId('margin').addEventListener('input',sellingFromMargin);
byId('selling_price').addEventListener('input',marginFromSelling);
const taxChange=()=>{
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
const taxMode=()=>{byId('tax_rate').readOnly=byId('tax_mode').value==='none';byId('tax_rate').hidden=byId('tax_rate').readOnly;if(byId('tax_rate').readOnly){byId('tax_rate').value=0;taxChange();}};
byId('tax_mode').addEventListener('change',taxMode);byId('tax_rate').readOnly=byId('tax_mode').value==='none';byId('tax_rate').hidden=byId('tax_rate').readOnly;inc();
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
    tr.innerHTML=`<td><input name="variants[${index}][sku]" maxlength="100" placeholder="" aria-label="Variation SKU"></td><td><input name="variants[${index}][value]" ${value ? 'readonly' : ''} placeholder="Value" aria-label="Variation value"></td><td><div class="variant-purchase-fields"><input type="number" name="variants[${index}][purchase_price]" min="0" step="0.0001" placeholder="Exc. tax" aria-label="Purchase price excluding tax"><input type="number" class="variant-purchase-inc" min="0" step="0.0001" placeholder="Inc. tax" aria-label="Purchase price including tax"><button type="button" class="variant-sync" data-sync="purchase" title="Calculate tax-inclusive purchase price" aria-label="Calculate tax-inclusive purchase price">✓</button></div></td><td><div class="variant-margin-fields"><input class="variant-margin" type="number" min="-100" max="999999" step="any" value="${defaultMarg}" aria-label="Variation margin percentage"><button type="button" class="variant-sync" data-sync="margin" title="Calculate selling price from margin" aria-label="Calculate selling price from margin">✓</button></div></td><td><input type="number" name="variants[${index}][selling_price]" min="0" step="0.0001" placeholder="${byId('selling_price_tax_type').value==='inclusive'?'Inc. tax':'Exc. tax'}" aria-label="Variation selling price"></td><td><input type="file" name="variant_images[${index}]" accept=".jpg,.jpeg,.png,.webp" aria-label="Variation images"></td><td><button type="button" class="row-remove" aria-label="Remove variation">−</button></td>`;
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
    tr.querySelector('[data-sync="purchase"]').addEventListener('click',refreshTax);
    tr.querySelector('[data-sync="margin"]').addEventListener('click',refreshSelling);
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
        button.innerHTML=`${product.name}<small>${product.code} · ${currency(product.purchase_price)}</small>`;
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
    if(productType.value==='variable'&&variantBody.rows.length){const rows=Array.from(variantBody.rows);byId('purchase_price').value=Math.min(...rows.map(row=>Number(row.querySelector('[name$="[purchase_price]"]').value)||0));byId('selling_price').value=Math.min(...rows.map(row=>Number(row.querySelector('[name$="[selling_price]"]').value)||0));}
    if(productType.value==='combo'){comboPricing();inc();return;}
    inc();marginFromSelling();
}
function switchProductType(){
    const type=productType.value;singlePanel.hidden=type!=='single';variablePanel.hidden=type!=='variable';comboPanel.hidden=type!=='combo';
    if(type==='combo'){byId('enable_serial').checked=false;byId('manage_stock').checked=false;}stock();
    templateSelect.disabled=type!=='variable';Array.from(variablePanel.querySelectorAll('input')).forEach(input=>input.disabled=type!=='variable');Array.from(comboPanel.querySelectorAll('input,select')).forEach(input=>input.disabled=type!=='combo');
    if(type==='variable'&&!variantBody.rows.length&&templateSelect.value)loadVariationRows();syncSummary();
}
templateSelect.addEventListener('change',loadVariationRows);productType.addEventListener('change',switchProductType);
byId('focus-variation')?.addEventListener('click',()=>templateSelect.focus());
byId('add-variant-row').addEventListener('click',addVariationRow);
byId('variant-selling-tax-label').textContent=byId('selling_price_tax_type').value==='inclusive'?'Inc. Tax':'Exc. Tax';
comboSearch.addEventListener('input',showComboResults);
comboSearch.addEventListener('keydown',event=>{if(event.key==='Enter'&&!comboResults.hidden){event.preventDefault();comboResults.querySelector('button')?.click();}});
document.addEventListener('click',event=>{if(!event.target.closest('.combo-search'))comboResults.hidden=true;});
byId('combo_margin').addEventListener('input',()=>{put('margin',Number(byId('combo_margin').value)||0);sellingFromMargin();byId('combo_selling_price').value=byId('selling_price').value;});
byId('combo_selling_price').addEventListener('input',()=>{put('selling_price',Number(byId('combo_selling_price').value)||0);marginFromSelling();byId('combo_margin').value=byId('margin').value;});
byId('margin').addEventListener('input',()=>{if(productType.value==='combo')comboPricing();});
if(templateSelect.value)loadVariationRows();savedComboItems.forEach((item,index)=>{const row=comboRow(index,item);if(row.dataset.productId)comboBody.appendChild(row);});switchProductType();
})();
</script>
