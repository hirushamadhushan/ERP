<div id="transfer-status" class="mt-3 text-sm text-purple-700" role="status"></div>
<dialog id="transfer-modal" class="w-[calc(100%-2rem)] max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl p-0 shadow-2xl">
    <form id="transfer-form" action="{{ route('payment-accounts.transfers.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="request_id">
        <header class="flex justify-between border-b bg-purple-50 p-5 font-bold text-purple-900">Fund Transfer<button type="button" data-close aria-label="Close">&times;</button></header>
        <div class="space-y-4 p-5">
            <label>Transfer from:*<select name="from_account_id" required></select></label>
            <label>Transfer To:*<select name="to_account_id" required></select></label>
            <label>Amount:*<input name="amount" type="number" min="0.01" max="999999999999.99" step="0.01" value="0" required></label>
            <label>Date:*<input name="date" type="datetime-local" required></label>
            <label>Note<textarea name="note" maxlength="5000" rows="4" placeholder="Note" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
            <label>Attach Document:<input name="document" type="file" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png"></label>
            <p class="text-xs text-slate-500">Max File size: 5MB<br>Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</p>
            <p id="transfer-error" role="alert" class="text-sm text-rose-600"></p>
        </div>
        <footer class="flex justify-end gap-2 border-t p-5"><button type="submit" class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Submit</button><button type="button" data-close class="rounded-xl bg-slate-100 px-4 py-2 text-sm">Close</button></footer>
    </form>
</dialog>
@push('scripts')
<script>
window.showPaymentSuccess=(message)=>{let alert=document.getElementById('payment-success-alert');if(!alert){alert=document.createElement('div');alert.id='payment-success-alert';alert.className='fixed left-1/2 top-24 z-[100] -translate-x-1/2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-bold text-white shadow-lg';document.body.append(alert)}alert.textContent=message;alert.hidden=false;clearTimeout(window.paymentSuccessTimer);window.paymentSuccessTimer=setTimeout(()=>alert.hidden=true,4500)};document.addEventListener('DOMContentLoaded',()=>{
    const modal=document.getElementById('transfer-modal'),form=document.getElementById('transfer-form');
    const source=form.elements.from_account_id,destination=form.elements.to_account_id;
    const error=document.getElementById('transfer-error'),submit=form.querySelector('[type=submit]');
    let accounts=[],saving=false;
    function destinations(){
        const selected=destination.value;
        destination.replaceChildren(new Option('Please Select',''));
        accounts.filter(a=>String(a.id)!==source.value).forEach(a=>destination.add(new Option(a.name+' ('+a.account_number+')',a.id)));
        if([...destination.options].some(o=>o.value===selected))destination.value=selected;
    }
    source.addEventListener('change',destinations);
    modal.addEventListener('cancel',event=>{if(saving)event.preventDefault()});
    document.addEventListener('click',async event=>{
        const button=event.target.closest('.fund-transfer');if(!button)return;
        form.reset();error.textContent='';submit.disabled=true;
        source.replaceChildren(new Option('Loading...',''));destination.replaceChildren(new Option('Please Select',''));
        form.elements.request_id.value=crypto.randomUUID();
        const now=new Date();now.setMinutes(now.getMinutes()-now.getTimezoneOffset());form.elements.date.value=now.toISOString().slice(0,16);
        modal.showModal();
        try{
            const response=await fetch(@json(route('payment-accounts.transfers.options')),{headers:{Accept:'application/json'}});
            if(!response.ok)throw new Error('Could not load accounts. Close and try again.');
            accounts=await response.json();source.replaceChildren(new Option('Please Select',''));
            accounts.forEach(a=>source.add(new Option(a.name+' ('+a.account_number+')',a.id)));
            source.value=button.dataset.accountId;destinations();
            if(accounts.length<2)throw new Error('Add at least two active accounts to transfer funds.');
            submit.disabled=false;
        }catch(e){error.textContent=e.message}
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving)return;
        error.textContent='';
        if(form.elements.document.files[0]?.size>5*1024*1024){error.textContent='The document must not exceed 5MB.';return}
        saving=true;submit.disabled=true;form.querySelectorAll('[data-close]').forEach(b=>b.disabled=true);
        let saved=false;
        try{
            const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});
            const result=await response.json();
            if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message);
            saved=true;modal.close();
            document.getElementById('transfer-status').textContent=result.message;window.showPaymentSuccess(result.message);
            const state=document.getElementById('account-state').value,search=document.getElementById('account-search').value;
            const page=await fetch(@json(route('payment-accounts.index')));
            if(!page.ok)throw new Error('Transfer saved. Could not refresh the balances; reload the accounts page.');
            const doc=new DOMParser().parseFromString(await page.text(),'text/html');
            document.getElementById('accounts').innerHTML=doc.getElementById('accounts').innerHTML;
            document.getElementById('account-state').value=state;document.getElementById('account-search').value=search;
            document.getElementById('account-state').dispatchEvent(new Event('change',{bubbles:true}));
        }catch(e){if(saved)document.getElementById('transfer-status').textContent='Transfer saved. Refresh the page to see updated balances.';else error.textContent=e.message}
        finally{saving=false;submit.disabled=false;form.querySelectorAll('[data-close]').forEach(b=>b.disabled=false)}
    });
});
</script>
@endpush
