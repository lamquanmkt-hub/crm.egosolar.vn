(function(){
  'use strict';
  function money(v){return Math.round(Number(v)||0).toLocaleString('vi-VN')+' đ';}
  function updateReceiptTotals(){
    var root=document.querySelector('.ego-inventory-enterprise #grItems');
    if(!root) return;
    var subtotal=0,vatTotal=0;
    root.querySelectorAll('.gr-item').forEach(function(row){
      var qty=parseFloat((row.querySelector('[data-name="qty"]')||{}).value||0);
      var price=parseFloat((row.querySelector('[data-name="unit_price"]')||{}).value||0);
      var vat=parseFloat((row.querySelector('[data-name="vat_percent"]')||{}).value||0);
      var base=qty*price; subtotal+=base; vatTotal+=base*vat/100;
    });
    var a=document.querySelector('[data-receipt-subtotal]'),b=document.querySelector('[data-receipt-vat]'),c=document.querySelector('[data-receipt-total]');
    if(a)a.textContent=money(subtotal);if(b)b.textContent=money(vatTotal);if(c)c.textContent=money(subtotal+vatTotal);
  }
  function init(){
    if(!document.querySelector('.ego-inventory-enterprise')) return;
    document.body.classList.add('ego-inventory-enterprise-active');
    var items=document.getElementById('grItems');
    if(items){
      items.addEventListener('input',updateReceiptTotals);
      items.addEventListener('change',updateReceiptTotals);
      new MutationObserver(updateReceiptTotals).observe(items,{childList:true,subtree:true});
      updateReceiptTotals();
    }
    document.querySelectorAll('.ego-inventory-nav__tab.is-active').forEach(function(tab){
      requestAnimationFrame(function(){tab.scrollIntoView({block:'nearest',inline:'center'});});
    });
  }
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();
