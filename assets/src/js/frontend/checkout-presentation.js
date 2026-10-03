(function () {
 'use strict';
 if (window.eilmoCfPresentationReady) return;
 window.eilmoCfPresentationReady = true;
 function selectedItem(root) {
  const items = Array.from(root.querySelectorAll('[data-eilmo-item]'));
  if(root.matches('[data-eilmo-item]'))items.unshift(root);
  return items.find(item => {
   const input=item.querySelector('[data-eilmo-quantity-input]');
   return input && Number(input.value)>0 && item.dataset.purchasable!=='no';
  });
 }
 function update(checkout) {
  checkout.querySelectorAll('[data-eilmo-reference-quantity]').forEach(strip=>{
   const root=strip.closest('[data-eilmo-single-product]');
   if(!root)return;
   const item=selectedItem(root); const input=item && item.querySelector('[data-eilmo-quantity-input]');
   const qty=input?Number(input.value):0;
   strip.querySelector('output').textContent=window.eilmoCfLanguage.digits(qty,checkout.dataset.checkoutLanguage);
   strip.querySelector('[data-reference-delta="-1"]').disabled=!item || qty<=1;
   strip.querySelector('[data-reference-delta="1"]').disabled=!item || (Number(input.max)>0 && qty>=Number(input.max));
  });
 }
 function init(){document.querySelectorAll('[data-eilmo-checkout]').forEach(update);}
 document.addEventListener('click',event=>{
  const button=event.target.closest && event.target.closest('[data-reference-delta]');
  if(!button)return;
  const root=button.closest('[data-eilmo-single-product]'),item=root && selectedItem(root);
  if(!item)return;
  const input=item.querySelector('[data-eilmo-quantity-input]');
  root.dispatchEvent(new CustomEvent('eilmo:setQuantity',{bubbles:true,detail:{productId:Number(item.dataset.productId),variationId:Number(item.dataset.variationId||0),quantity:Math.max(1,Number(input.value)+Number(button.dataset.referenceDelta))}}));
  update(root.closest('[data-eilmo-checkout]'));
 });
 document.addEventListener('eilmo:quantityChange',event=>{const checkout=event.target.closest && event.target.closest('[data-eilmo-checkout]');if(checkout)update(checkout);});
 document.addEventListener('eilmo:summaryChange',init);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
 new MutationObserver(records=>{if(records.some(record=>Array.from(record.addedNodes).some(node=>node.nodeType===1 && (node.matches('[data-eilmo-checkout]')||node.querySelector('[data-eilmo-checkout]')))))init();}).observe(document.documentElement,{childList:true,subtree:true});
})();
