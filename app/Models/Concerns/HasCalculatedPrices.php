<?php
namespace App\Models\Concerns;

trait HasCalculatedPrices
{
    public function getPurchasePriceIncAttribute(): string {
        $tax=$this instanceof \App\Models\Product ? $this->tax_rate : $this->product?->tax_rate;
        return bcmul((string)($this->purchase_price ?? 0),bcadd('1',bcdiv((string)($tax ?? 0),'100',8),8),4);
    }
    public function getMarginAttribute(): string {
        $purchase=(string)($this->purchase_price ?? 0);
        return bccomp($purchase,'0',4)>0 ? bcmul(bcdiv(bcsub((string)($this->selling_price ?? 0),$purchase,4),$purchase,8),'100',4) : '0';
    }
}
