#!/usr/bin/env python3
"""Exercise actual compiled assets in a fresh headless browser; no database access."""
import argparse
import hashlib
import html
import http.server
import json
import mimetypes
import pathlib
import re
import shutil
import subprocess
import tempfile
import threading

ROOT = pathlib.Path(__file__).resolve().parent.parent


def fingerprint():
    return json.loads(subprocess.check_output([
        'php', '-r', "require 'scripts/postgresql_source_manifest.php';echo json_encode(postgresqlSourceManifest(getcwd()));"
    ], cwd=ROOT, text=True))['sha256']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=pathlib.Path, required=True)
    args = parser.parse_args()
    browser = next((shutil.which(name) for name in ['google-chrome', 'chromium', 'chromium-browser'] if shutil.which(name)), None)
    if not browser:
        parser.error('Se requiere Chrome/Chromium para verificar el bundle; no se omite esta prueba.')
    public = ROOT / 'public'
    manifest = json.loads((public / 'build/manifest.json').read_text())
    build_sources = json.loads((public/'build/frontend-sources.json').read_text())
    stale_sources = [name for name, expected in build_sources['files'].items()
                     if not (ROOT/name).is_file() or hashlib.sha256((ROOT/name).read_bytes()).hexdigest() != expected]
    if build_sources.get('version') != 1 or not build_sources['files'] or stale_sources:
        parser.error('El bundle no corresponde a las fuentes actuales: '+', '.join(stale_sources))
    entry = manifest['resources/js/storefront-amounts.js']['file']
    checkout_entry = manifest['modules/Ecommerce/Resources/assets/js/frontend/cart-app.js']['file']
    source_before = fingerprint()
    page = r'''<!doctype html><html lang="es"><head><meta charset="utf-8">
<script>window.__storefront_show_prices=true;window.bundleErrors=[];
window.addEventListener('error',e=>{if(e.message)window.bundleErrors.push(e.message)});
window.addEventListener('unhandledrejection',e=>window.bundleErrors.push(String(e.reason)));
// Capture compiled checkout methods without mounting Vue or issuing checkout requests.
window.Vue=function(options){window.bundleCartOptions=options;return {}};
window.moment=()=>({format:()=>'2026-01-01'});
</script><script type="module" src="/build/ENTRY"></script>
<script type="module" src="/build/CHECKOUT_ENTRY"></script></head><body>
<div class="dropdown-cart-products"></div><span class="cart-count"></span><span class="cart-total-price"></span>
<div class="modal" id="moda-succes-add-product"><div class="modal-dialog"><div class="modal-content">
<div id="cart-confirm-alert"></div><span id="cart-confirm-alert-text"></span><span id="cart-confirm-title"></span>
<div id="product_added_image"></div><span id="cart-confirm-price-current"></span><span id="cart-confirm-price-old"></span>
<button id="cart-confirm-qty-minus" type="button">-</button><input id="cart-confirm-qty-input" type="text" inputmode="decimal" value="1">
<button id="cart-confirm-qty-plus" type="button">+</button></div></div></div><pre id="pro9-result">pending</pre>
<script src="/porto-ecommerce/assets/js/jquery.min.js"></script>
<script src="/porto-ecommerce/assets/js/bootstrap.bundle.min.js"></script>
<script src="/porto-ecommerce/assets/js/cart.js"></script>
<script src="/porto-ecommerce/assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded',async()=>{
 const results=[];
 const assert=(condition,label)=>{if(!condition)throw new Error(label);results.push(label)};
 const wait=()=>new Promise(resolve=>setTimeout(resolve,100));
 try {
  await wait();
  assert(!!window.Pro9StorefrontAmounts,'module ready before catalog initialization');
  assert(window.bundleErrors.length===0,'classic catalog starts without JavaScript errors');
  const product={id:'9007199254740993',description:'Producto sintético',sale_unit_price:'9007199254740993.01',
   currency_type_id:'VES',quantity:'1',stock:'10000.25'};
  localStorage.setItem('products_cart','[]');
  cartAddOrUpdateItem(product,{quantity:'0.333333333333'});
  let row=JSON.parse(localStorage.getItem('products_cart'))[0];
  assert(row.sale_unit_price==='9007199254740993.01','compiled catalog preserves large price text');
  assert(row.quantity==='0.333333333333','compiled catalog preserves fractional quantity');
  document.getElementById('cart-confirm-qty-plus').click();
  row=JSON.parse(localStorage.getItem('products_cart'))[0];
  assert(row.quantity==='1.333333333333','real quantity button adds without truncation');
  document.getElementById('cart-confirm-qty-minus').click();
  row=JSON.parse(localStorage.getItem('products_cart'))[0];
  assert(row.quantity==='0.333333333333','real quantity button subtracts without truncation');
  const below={...product,id:'8',sale_unit_price:'1.005',campaign_pricing_snapshot:{values:{final_price:{n:'10049999999999999',d:'10000000000000000'}}}};
  cartConfirmRenderPrices(below);
  assert(document.getElementById('cart-confirm-price-current').textContent.includes('1.00'),'DOM rounds original fraction without double rounding');
  const rows=[{...product,id:'9',sale_unit_price:'0.005',quantity:'1'},
   {...product,id:'10',sale_unit_price:'0.005',quantity:'1'},
   {...product,id:'11',sale_unit_price:'0.1',currency_type_id:'USD',quantity:'3'}];
  localStorage.setItem('products_cart',JSON.stringify(rows));calculateTotalCart();
  assert(document.querySelector('.cart-total-price').textContent==='Bs. 0.01 + USD 0.30','real header separates currencies and sums before rounding');
  window.__active_campaign={sp_discount_price:true,discount_type:'percentage',discount_value:'10'};
  const priced=cartPrepareItemForStorage({...product,sale_unit_price:'100',original_price:'100',compare_at_price:'110'});
  assert(priced.sale_unit_price==='100','social proof never applies a second discount');
  assert(cartAddOrUpdateItem({...product,id:'12'},{quantity:'20000'}).quantity==='10000.25','real modal enforces fractional stock above 9999');
  window.__storefront_show_prices=false;
  const hidden=cartPrepareItemForStorage({...product,sale_unit_price:null});
  assert(hidden.sale_unit_price===null,'hidden price stays unavailable rather than free');
  calculateTotalCart();assert(document.querySelector('.cart-total-price').textContent==='','hidden prices are not displayed in totals');
  const methods=window.bundleCartOptions?.methods;
  const identifiers=window.bundleCartOptions.computed.cartFbtItemIds.call({records:[{id:41},{id:'9007199254740993'},{id:'9007199254740994'}]});
  assert(JSON.stringify(identifiers)==='[41,"9007199254740993","9007199254740994"]','compiled recommendations preserve distinct bigint identifiers and ordinary key types');
  assert(methods.formatOrderNumber('9007199254740993')==='#9007199254740993' && methods.formatOrderNumber('9007199254740994')==='#9007199254740994','compiled order labels preserve adjacent bigint identifiers');
  assert(methods.formatOrderNumber(Number('9007199254740993'))==='#—','compiled order label rejects an already-rounded numeric identifier');
  const recommendationComponent=window.bundleCartOptions.components.FrequentlyBoughtTogether;
  const recommendation=recommendationComponent.options || recommendationComponent;
  assert(JSON.stringify(recommendation.computed.resolvedIds.call({itemIds:identifiers,itemId:null}))===JSON.stringify(identifiers),'compiled recommendation component preserves identifiers through the request boundary');
  assert(recommendation.methods.formatPrice('9007199254740993.01')==='9007199254740993.01' && recommendation.methods.formatPrice('1.005',{values:{sale_unit_price:{n:'10049999999999999',d:'10000000000000000'}}})==='1.00','compiled recommendation prices retain large values and original fractions');
  assert(!!methods?.buildSuccessOrder,'actual compiled checkout methods are registered without mounting Vue');
  const order={id:41,total:'0.90',reference_payment:'transferencia',purchase:{checkout:{calculation:{
   items:[{item_id:7,description:'Confirmado',quantity:{n:'3',d:'1'},subtotal_ves:{n:'1',d:'1'}}],
   total_payable:'0.90',shipping_amount:{n:'0',d:'1'},document:{totals:{total_exonerated:{n:'9',d:'10'},total_igv:{n:'0',d:'1'},total_taxed:{n:'0',d:'1'}}}
  }}}};
  const ctx={summary:{total:'999'},records:[{id:999,sale_unit_price:'999',cantidad:'99'}],
   selectedPaymentMethod:'cash',formatOrderNumber:()=>'#41',getPaymentMethodLabel:methods.getPaymentMethodLabel};
  const before=JSON.stringify(order),confirmed=methods.buildSuccessOrder.call(ctx,order);
  assert(confirmed.total==='0.90' && confirmed.items[0].description==='Confirmado','compiled confirmation uses the frozen order instead of the current cart');
  ctx.getSelectedReferencePayment=()=> 'efectivo';ctx.getSuccessOrderItemsCount=()=>1;ctx.formatMoney=methods.formatMoney;
  methods.syncSuccessModalSummary.call(ctx,{total:'9007199254740993.01'},confirmed);
  assert(ctx.successOrderTotal==='Bs. 9.007.199.254.740.993,01','compiled success modal preserves the actual large confirmed total');
  assert(JSON.stringify(order)===before,'compiled confirmation does not mutate its source snapshot');
  await wait();
  assert(window.bundleErrors.length===0,'no delayed browser errors');
  document.getElementById('pro9-result').textContent=JSON.stringify({ok:true,checks:results,errors:window.bundleErrors});
 } catch(error) {document.getElementById('pro9-result').textContent=JSON.stringify({ok:false,checks:results,error:String(error),errors:window.bundleErrors});}
});
</script></body></html>'''.replace('CHECKOUT_ENTRY', checkout_entry).replace('ENTRY', entry)

    class Handler(http.server.BaseHTTPRequestHandler):
        def do_GET(self):
            path = self.path.split('?', 1)[0]
            if path == '/':
                content, content_type = page.encode(), 'text/html; charset=utf-8'
            else:
                relative = path.lstrip('/')
                allowed = relative.startswith('build/assets/') or relative in {
                    'porto-ecommerce/assets/js/jquery.min.js', 'porto-ecommerce/assets/js/bootstrap.bundle.min.js',
                    'porto-ecommerce/assets/js/cart.js', 'porto-ecommerce/assets/js/main.js',
                }
                target = (public / relative).resolve()
                if not allowed or not target.is_relative_to(public) or not target.is_file():
                    self.send_error(404)
                    return
                content, content_type = target.read_bytes(), mimetypes.guess_type(target)[0] or 'application/octet-stream'
            self.send_response(200)
            self.send_header('Content-Type', content_type)
            self.send_header('Content-Length', str(len(content)))
            self.end_headers()
            self.wfile.write(content)

        def log_message(self, *unused):
            pass

    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        with tempfile.TemporaryDirectory(prefix='pro9-storefront-browser-') as profile:
            process = subprocess.run([browser, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
                '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
                '--user-data-dir='+profile, '--virtual-time-budget=6000', '--dump-dom',
                'http://127.0.0.1:'+str(server.server_port)+'/'], capture_output=True, text=True, timeout=45)
        match = re.search(r'<pre id="pro9-result">(.*?)</pre>', process.stdout, re.S)
        result = json.loads(html.unescape(match[1])) if match and match[1] != 'pending' else {'ok':False,'error':'El navegador no completó las comprobaciones.'}
        result.update(browser=subprocess.check_output([browser, '--version'], text=True).strip(),
            exit_code=process.returncode, databases='none; synthetic browser fixture only',
            source_sha256=source_before, source_unchanged=source_before == fingerprint(),
            manifest_sha256=hashlib.sha256((public/'build/manifest.json').read_bytes()).hexdigest(),
            entry=entry, checkout_entry=checkout_entry,
            checkout_scope='Compiled methods captured with a constructor stub; no full Vue mounting or checkout HTTP requests.',
            classic_sources={name:hashlib.sha256((public/'porto-ecommerce/assets/js'/name).read_bytes()).hexdigest() for name in ['cart.js','main.js']})
        result['compiled_source_files'] = len(build_sources['files'])
        result['ok'] = result['ok'] and result['exit_code'] == 0 and result['source_unchanged']
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(result, indent=2, ensure_ascii=False)+'\n')
        if not result['ok']:
            args.output.with_suffix('.chrome.log').write_text(process.stderr)
            args.output.with_suffix('.html').write_text(process.stdout)
        print(json.dumps(result, ensure_ascii=False))
        return 0 if result['ok'] else 1
    finally:
        server.shutdown()
        server.server_close()
        thread.join()


if __name__ == '__main__':
    raise SystemExit(main())
