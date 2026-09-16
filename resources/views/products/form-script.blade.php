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
    byId('selling-label').textContent=previousType==='inclusive'?'Inc. tax *':'Exc. tax *';inc();marginFromSelling();
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
const money=value=>Number(value||0).toFixed(4);
const safeSku=value=>String(value).toUpperCase().replace(/[^A-Z0-9.-]+/g,'-').replace(/^-|-$/g,'');

function variantRow(value,index,saved={}){
    const tr=document.createElement('tr');
    const defaultSku=(byId('sku').value.trim()||'VARIANT')+'-'+safeSku(value);
    tr.innerHTML=`<td><input name="variants[${index}][sku]" required maxlength="100" value=""></td><td><input name="variants[${index}][value]" readonly value=""></td><td><input type="number" name="variants[${index}][purchase_price]" required min="0" step="0.0001" value=""></td><td><input class="variant-margin" type="number" readonly value="0.0000"></td><td><input type="number" name="variants[${index}][selling_price]" required min="0" step="0.0001" value=""></td><td><input type="file" name="variant_images[${index}]" accept=".jpg,.jpeg,.png,.webp"></td><td><button type="button" class="row-remove" aria-label="Remove variation">−</button></td>`;
    const inputs=tr.querySelectorAll('input');inputs[0].value=saved.sku||defaultSku;inputs[1].value=value;inputs[2].value=saved.purchase_price??byId('purchase_price').value;inputs[4].value=saved.selling_price??byId('selling_price').value;
    const calculate=()=>{const purchase=Number(inputs[2].value)||0,selling=Number(inputs[4].value)||0;inputs[3].value=money(purchase?((selling/purchase)-1)*100:0);syncSummary();};
    inputs[2].addEventListener('input',calculate);inputs[4].addEventListener('input',calculate);tr.querySelector('button').addEventListener('click',()=>{tr.remove();renumberRows(variantBody,'variants');syncSummary();});calculate();return tr;
}
function loadVariationRows(){
    variantBody.innerHTML='';let values=[];try{values=JSON.parse(templateSelect.selectedOptions[0]?.dataset.values||'[]')}catch{}
    values.forEach((value,index)=>variantBody.appendChild(variantRow(value,index,savedVariants.find(item=>item.value===value)||{})));
}
function comboRow(index,saved={}){
    const tr=document.createElement('tr');
    const options=comboProducts.map(item=>`<option value="${item.id}">${item.name} (${item.code})</option>`).join('');
    tr.innerHTML=`<td><select name="combo_items[${index}][product_id]" required><option value="">Please Select</option>${options}</select></td><td><input type="number" name="combo_items[${index}][quantity]" required min="0.0001" step="0.0001" value="${saved.quantity||1}"></td><td><button type="button" class="row-remove" aria-label="Remove combo item">−</button></td>`;
    tr.querySelector('select').value=saved.product_id||'';tr.querySelectorAll('select,input').forEach(input=>input.addEventListener('change',syncSummary));tr.querySelector('input').addEventListener('input',syncSummary);tr.querySelector('button').addEventListener('click',()=>{tr.remove();renumberRows(comboBody,'combo_items');syncSummary();});return tr;
}
function renumberRows(body,prefix){Array.from(body.rows).forEach((row,index)=>row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(new RegExp(`^${prefix}\\[\\d+\\]`),`${prefix}[${index}]`);if(prefix==='variants')input.name=input.name.replace(/^variant_images\[\d+\]/,`variant_images[${index}]`);}));}
function syncSummary(){
    if(productType.value==='variable'&&variantBody.rows.length){const rows=Array.from(variantBody.rows);byId('purchase_price').value=Math.min(...rows.map(row=>Number(row.querySelector('[name$="[purchase_price]"]').value)||0));byId('selling_price').value=Math.min(...rows.map(row=>Number(row.querySelector('[name$="[selling_price]"]').value)||0));}
    if(productType.value==='combo'){let purchase=0,selling=0;Array.from(comboBody.rows).forEach(row=>{const item=comboProducts.find(product=>String(product.id)===row.querySelector('select').value),quantity=Number(row.querySelector('input').value)||0;if(item){purchase+=Number(item.purchase_price)*quantity;selling+=Number(item.selling_price)*quantity;}});put('purchase_price',purchase);put('selling_price',selling);}
    inc();marginFromSelling();
}
function switchProductType(){
    const type=productType.value;singlePanel.hidden=type!=='single';variablePanel.hidden=type!=='variable';comboPanel.hidden=type!=='combo';
    if(type==='combo'){byId('enable_serial').checked=false;byId('manage_stock').checked=false;}stock();
    templateSelect.disabled=type!=='variable';Array.from(variablePanel.querySelectorAll('input')).forEach(input=>input.disabled=type!=='variable');Array.from(comboPanel.querySelectorAll('input,select')).forEach(input=>input.disabled=type!=='combo');
    if(type==='variable'&&!variantBody.rows.length&&templateSelect.value)loadVariationRows();if(type==='combo'&&!comboBody.rows.length)comboBody.appendChild(comboRow(0));syncSummary();
}
templateSelect.addEventListener('change',loadVariationRows);byId('add-combo-row').addEventListener('click',()=>comboBody.appendChild(comboRow(comboBody.rows.length)));productType.addEventListener('change',switchProductType);
if(templateSelect.value)loadVariationRows();savedComboItems.forEach((item,index)=>comboBody.appendChild(comboRow(index,item)));switchProductType();
})();
</script>
