import fs from 'node:fs';
import assert from 'node:assert/strict';

// Execute the production callback against the DataTables API shape with raw
// amounts, including search-filtered rows and hidden columns.
const source=fs.readFileSync('resources/views/contacts/scripts.blade.php','utf8');
const callback=source.match(/footerCallback: function \(\) \{([\s\S]*?)\n            \}/)?.[1];
assert.ok(callback,'Contacts footer callback must exist');
function calculate(amounts) {
    const footer={hasAttribute:name=>name==='data-total',textContent:''};
    const column={footer:()=>footer,index:()=>8};
    const api={columns:()=>({every:fn=>fn.call(column)}),column:(index,options)=>{
        assert.equal(index,8);assert.equal(options.search,'applied');
        return {nodes:()=>({toArray:()=>amounts.map(amount=>({dataset:{amount:String(amount)}}))})};
    }};
    new Function(callback).call({api:()=>api});
    return footer.textContent;
}
assert.equal(calculate([6500,12500,15000]),'Rs. 34,000.00');
assert.equal(calculate([6500]),'Rs. 6,500.00');
assert.equal(calculate([]),'Rs. 0.00');
assert.equal(calculate([0.01,0.02]),'Rs. 0.03');
console.log('Contacts totals: full list, search, empty results and decimals passed.');
