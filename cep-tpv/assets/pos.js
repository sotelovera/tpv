/* global CEP, jQuery */
(() => {
    'use strict';
    const $ = jQuery, el = id => document.getElementById(id);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const uuid = () => crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    const makeTicket = () => ({id:uuid(),items:[],client:'0',discount:'0',pending:null});
    let state={active:0,tickets:[makeTicket()]}, quote=null, revision=0, searchSeq=0, searchTimer, quoteTimer, busy=false, storageOK=true, lastReceipt=null;
    try { lastReceipt=JSON.parse(localStorage.getItem(CEP.storage+'_last')); const saved=JSON.parse(localStorage.getItem(CEP.storage)); if (saved && Array.isArray(saved.tickets) && saved.tickets.length && Number.isInteger(saved.active) && saved.tickets[saved.active] && saved.tickets.every(t => Array.isArray(t.items))) state=saved; } catch (_) { /* Retain a usable empty ticket; report storage failure on write. */ }
    const ticket=()=>state.tickets[state.active];
    const message=(text,error=false)=> { el('cep-message').className='notice '+(error?'notice-error':'notice-info'); el('cep-message').textContent=text; };
    const save=()=> { try { localStorage.setItem(CEP.storage,JSON.stringify(state)); storageOK=true; return true; } catch (_) { storageOK=false; message('No se puede guardar la recuperación del ticket. Habilita almacenamiento del navegador antes de cobrar.',true); return false; } };
    const post=(action,data,search=false)=> $.ajax({url:CEP.ajax,method:'POST',data:{action,nonce:search?CEP.searchNonce:CEP.nonce,...data},dataType:'json',timeout:30000});
    function lockUI() {
        const locked=busy || !!ticket().pending;
        el('cep-pos').querySelectorAll('input,select,button').forEach(n=> { n.disabled=locked; });
        el('cep-charge').disabled=busy || (!ticket().pending && (!quote || !storageOK));
        el('cep-charge').textContent=busy?'Registrando…':(ticket().pending?'Reintentar cobro':'Confirmar cobro · F8');
        el('cep-print').disabled=busy || !lastReceipt;
    }
    function render() {
        el('cep-tickets').innerHTML=state.tickets.map((t,i)=>`<option value="${i}">Ticket ${i+1} (${t.items.length})</option>`).join('');
        el('cep-tickets').value=state.active; el('cep-client').value=ticket().client; el('cep-discount').value=ticket().discount;
        el('cep-cart').innerHTML=ticket().items.length?`<table class="widefat striped"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Importe</th><th></th></tr></thead><tbody>${ticket().items.map((p,i)=>`<tr><td><strong>${escape(p.nombre)}</strong><br><small>${escape(p.sku)}</small></td><td><input aria-label="Cantidad de ${escape(p.nombre)}" data-qty="${i}" type="number" min="${p.esGranel?'0.001':'1'}" step="${p.esGranel?'0.001':'1'}" value="${escape(p.cantidad)}"></td><td>$${escape(p.precio_venta)}</td><td>$${escape(quote?.detalles?.[i]?.subtotal ?? "…")}</td><td><button class="button" data-remove="${i}" aria-label="Quitar ${escape(p.nombre)}">×</button></td></tr>`).join('')}</tbody></table>`:'<div class="cep-ticket-empty"><strong>Tu ticket está vacío</strong><p>Busca un producto o utiliza el lector de códigos.</p></div>';
        save(); lockUI();
    }
    function refreshQuote() {
        const version=++revision; quote=null; el('cep-total').textContent='Calculando…'; lockUI(); clearTimeout(quoteTimer);
        if (!ticket().items.length) { el('cep-total').textContent='$0.00'; el('cep-tax').textContent='0.00'; return; }
        if (ticket().pending) { message('Hay un cobro pendiente de confirmar. Reintenta la misma operación; no vuelvas a capturar la venta.'); return; }
        const t=ticket();
        quoteTimer=setTimeout(()=>post('cep_cotizar',{carrito:JSON.stringify(t.items),descuento:t.discount}).done(r=>{
            if (version!==revision) return;
            if (!r.success) { message(String(r.data),true); return; }
            quote=r.data; quote.detalles.forEach((d,i)=> { if(t.items[i]) { t.items[i].cantidad=d.cantidad; t.items[i].precio_venta=d.precio_unitario; } }); render(); el('cep-total').textContent='$'+quote.total; el('cep-tax').textContent=quote.impuestos; change(); message('Ticket actualizado con precios e impuestos del servidor.'); lockUI();
        }).fail(xhr=> { if (version===revision) message(xhr.responseJSON?.data || 'No se pudo calcular el ticket. Revisa cantidades o recarga para reintentar.',true); }),180);
    }
    function add(p) {
        if (busy || ticket().pending) return;
        let qty='1', byAmount=null; const bulk=Number(p.maneja_granel)===1;
        if (bulk) { qty=window.prompt(`${p.nombre}: escribe el peso en kg (hasta 3 decimales), o un importe con $ (ejemplo: $15.00)`, '1.000'); if (!qty) return; if (qty.trim().startsWith('$')) { byAmount=qty.trim().slice(1).trim(); qty='0.001'; } }
        const found=ticket().items.find(i=>!i.esComun && i.id===p.id && !bulk);
        if (found) found.cantidad=String(Number(found.cantidad)+1);
        else ticket().items.push({id:p.id,nombre:p.nombre,sku:p.sku,precio_venta:p.precio_venta,cantidad:qty,esGranel:bulk,esComun:!!p.esComun,modoGranel:byAmount?'importe':'peso',importeGranel:byAmount});
        render(); refreshQuote(); el('cep-search').value=''; el('cep-results').innerHTML=''; el('cep-search').focus();
    }
    const search=(exact=false)=> {
        const term=el('cep-search').value.trim(), seq=++searchSeq; if (!term) { el('cep-results').innerHTML=''; return; }
        el('cep-results').textContent='Buscando…';
        post(exact?'cep_buscar_producto_sku':'cep_buscar_producto',exact?{sku:term}:{termino:term},true).done(r=>{
            if (seq!==searchSeq) return;
            if (!r.success) { el('cep-results').textContent=String(r.data); return; }
            if (exact) { add(r.data); return; }
            el('cep-results').innerHTML='';
            if (!r.data.length) el('cep-results').textContent='No se encontraron productos.';
            r.data.forEach(p=> { const b=document.createElement('button'); b.type='button'; b.className='cep-result'; b.innerHTML=`<strong>${escape(p.nombre)}</strong><small>${escape(p.sku)} · Existencias: ${escape(p.stock)}</small><span>$${escape(p.precio_venta)}</span>`; b.onclick=()=>add(p); el('cep-results').append(b); }); lockUI();
        }).fail(()=> { if (seq===searchSeq) el('cep-results').textContent='No se pudo buscar. Comprueba tu conexión.'; });
    };
    function change() { el('cep-change').textContent=Math.max(0,Number(el('cep-received').value||0)-Number(quote?.total||0)).toFixed(2); }
    function receipt(r) {
        const width=r.ancho==='58'?'50mm':'72mm';
        return `<article class="cep-printed-ticket" style="width:${width}"><h2>${escape(r.tienda)}</h2><p>${escape(r.folio)}<br>${escape(r.fecha)}<br>${escape(r.caja_id)} · ${escape(r.cajero)}</p><hr>${r.detalles.map(d=>`<p><strong>${escape(d.producto)}</strong><br>${escape(d.cantidad)} ${escape(d.unidad)} × $${escape(d.precio_unitario)}<br>Importe: $${escape(d.subtotal)}</p>`).join('')}<hr><p>Descuento: $${escape(r.descuento)}<br>Impuestos: $${escape(r.impuestos)}<br><strong>Total: $${escape(r.total)}</strong><br>${escape(r.metodo_pago)}<br>Cambio: $${escape(r.cambio)}</p><p>${escape(r.pie)}</p></article>`;
    }
    function charge() {
        if (busy) return;
        const t=ticket();
        if (!t.pending) {
            if (!quote || !t.items.length) return message('Espera el cálculo del ticket antes de cobrar.',true);
            if (!window.confirm(`¿Registrar el cobro por $${quote.total}?`)) return;
            const payments={}; document.querySelectorAll('[data-payment]').forEach(n=> { payments[n.dataset.payment]=n.value||'0'; });
            t.pending={operacion:t.id,carrito:JSON.stringify(t.items),descuento:t.discount,cliente_id:t.client,metodo_pago:el('cep-method').value,efectivo_recibido:el('cep-received').value||'0',pagos:JSON.stringify(payments),total_confirmado:quote.total};
        }
        if (!save()) return;
        busy=true; lockUI();
        post('cep_procesar_venta',t.pending).done(r=>{
            if (!r.success) { t.pending=null; save(); message(String(r.data),true); return; }
            lastReceipt=r.data; try { localStorage.setItem(CEP.storage+'_last',JSON.stringify(lastReceipt)); } catch (_) {} el('cep-receipt').innerHTML=receipt(r.data);
            const index=state.tickets.indexOf(t); state.tickets.splice(index,1); if (!state.tickets.length) state.tickets.push(makeTicket()); state.active=0;
            quote=null; save(); render(); refreshQuote(); message(`Venta registrada: ${r.data.folio}. Total $${r.data.total}. Cambio $${r.data.cambio}. Puedes imprimir el ticket.`);
        }).fail(xhr=>{
            if (xhr.responseJSON?.success===false && xhr.status===400) { t.pending=null; save(); message(String(xhr.responseJSON.data),true); }
            else message('No se pudo confirmar el resultado. Reintenta cobro: se conservará el mismo identificador para evitar duplicados.',true);
        }).always(()=> { busy=false; lockUI(); });
    }
    el('cep-search').oninput=()=> { clearTimeout(searchTimer); searchTimer=setTimeout(()=>search(),200); };
    el('cep-search').onkeydown=e=> { if (e.key==='Enter') { e.preventDefault(); clearTimeout(searchTimer); search(true); } };
    el('cep-cart').onchange=e=> { if (e.target.dataset.qty!==undefined && !ticket().pending && !busy) { const item=ticket().items[Number(e.target.dataset.qty)]; item.cantidad=e.target.value; item.modoGranel='peso'; item.importeGranel=null; render(); refreshQuote(); } };
    el('cep-cart').onclick=e=> { const b=e.target.closest('[data-remove]'); if (b && !busy && !ticket().pending) { ticket().items.splice(Number(b.dataset.remove),1); render(); refreshQuote(); } };
    el('cep-client').onchange=()=> { ticket().client=el('cep-client').value; save(); };
    el('cep-discount').onchange=()=> { ticket().discount=el('cep-discount').value||'0'; save(); refreshQuote(); };
    el('cep-method').onchange=()=> { el('cep-cash').hidden=el('cep-method').value!=='Efectivo'; el('cep-mixed').hidden=el('cep-method').value!=='Mixto'; };
    el('cep-received').oninput=change; el('cep-charge').onclick=charge;
    el('cep-new').onclick=()=> { if (state.tickets.length>=20) return message('Recupera o descarta uno de los 20 tickets pendientes.',true); state.tickets.push(makeTicket()); state.active=state.tickets.length-1; render(); refreshQuote(); };
    el('cep-tickets').onchange=()=> { state.active=Number(el('cep-tickets').value); render(); refreshQuote(); };
    el('cep-cancel').onclick=()=> { if (ticket().pending || busy) return; if (window.confirm('¿Descartar este ticket sin cobrar?')) { state.tickets.splice(state.active,1); if (!state.tickets.length) state.tickets.push(makeTicket()); state.active=0; render(); refreshQuote(); } };
    if (el('cep-common')) el('cep-common').onclick=()=> { const name=el('cep-common-name').value.trim(), price=el('cep-common-price').value; if (!name || !(Number(price)>0)) return message('Introduce nombre y precio.',true); add({id:0,nombre:name,sku:'COMUN',precio_venta:price,esComun:true,maneja_granel:0}); };
    el('cep-print').onclick=()=> { if (lastReceipt) { el('cep-receipt').innerHTML=receipt(lastReceipt); el('cep-receipt').hidden=false; window.print(); el('cep-receipt').hidden=true; } };
    document.addEventListener('keydown',e=> { if (e.key==='F2') { e.preventDefault(); el('cep-search').focus(); } if (e.key==='F8') { e.preventDefault(); charge(); } });
    window.addEventListener('storage',e=> { if (e.key===CEP.storage) { message('El TPV cambió en otra pestaña. Recarga esta página antes de operar.',true); busy=true; lockUI(); } });
    render(); refreshQuote();
})();
