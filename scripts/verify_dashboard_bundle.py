#!/usr/bin/env python3
"""Mount the real dashboard bundle with DTOs produced in a marked temporary PG cluster.

The browser uses a private loopback fixture server, not the application databases.
This verifies compiled Vue/Apex/Chart.js rendering, not authenticated HTTP business flows.
"""
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
import urllib.parse

ROOT = pathlib.Path(__file__).resolve().parent.parent


def source_manifest():
    return json.loads(subprocess.check_output([
        'php', '-r', "require 'scripts/postgresql_source_manifest.php';echo json_encode(postgresqlSourceManifest(getcwd()));"
    ], cwd=ROOT, text=True))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--fixture', type=pathlib.Path, required=True)
    parser.add_argument('--output', type=pathlib.Path, required=True)
    args = parser.parse_args()
    args.fixture = args.fixture.resolve()
    args.output = args.output.resolve()
    browser = next((shutil.which(name) for name in ['google-chrome', 'chromium', 'chromium-browser'] if shutil.which(name)), None)
    if not browser:
        parser.error('Se requiere Chrome/Chromium; esta prueba no se omite.')
    fixture = json.loads(args.fixture.read_text())
    run_id = fixture.get('run_id', '')
    if fixture.get('version') != 1 or not re.fullmatch(r'sab-pgtest-[a-f0-9]{12}', run_id):
        parser.error('La muestra debe proceder del ejecutor PostgreSQL temporal.')
    evidence = args.fixture.parent
    recorded = json.loads((evidence / (run_id + '-fuentes.json')).read_text())
    native = json.loads((evidence / (run_id + '.json')).read_text()) if (evidence / (run_id + '.json')).is_file() else None
    # In the full runner the cluster is still private and the final status is written afterwards.
    if fixture['source_sha256'] != recorded['sha256'] or (native and (
            native['exit_code'] != 0 or not native['source_unchanged'] or not native['cleanup_ok'])):
        parser.error('La muestra no tiene procedencia válida de pruebas temporales.')
    before = source_manifest()
    product = re.compile(r'^(app/|modules/|database/|config/|routes/|resources/|public/build/|composer\.)')
    changed = [name for name, digest in recorded['files'].items()
               if product.match(name) and before['files'].get(name) != digest]
    if changed:
        parser.error('Cambió el código productor o compilado desde la muestra: ' + ', '.join(changed))
    public = ROOT / 'public'
    manifest_path = public / 'build/manifest.json'
    manifest = json.loads(manifest_path.read_text())
    sources = json.loads((public / 'build/frontend-sources.json').read_text())
    stale = [name for name, expected in sources['files'].items()
             if not (ROOT / name).is_file() or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected]
    if sources.get('version') != 1 or not sources['files'] or stale:
        parser.error('La compilación no corresponde a las fuentes actuales: ' + ', '.join(stale))
    entry = manifest['resources/js/app.js']
    stylesheets = []
    visited = set()

    def collect_css(key):
        if key in visited:
            return
        visited.add(key)
        for imported in manifest[key].get('imports', []):
            collect_css(imported)
        for name in manifest[key].get('css', []):
            if name not in stylesheets:
                stylesheets.append(name)

    collect_css('resources/js/app.js')
    css = ''.join('<link rel="stylesheet" href="/build/' + name + '">' for name in stylesheets)
    config = {'dashboard_clients': True, 'dashboard_general': True, 'dashboard_goal_enabled': True,
              'dashboard_products': False, 'dashboard_sales': True, 'visual': {'show_welcome_panel': False},
              'company': {'name': 'Empresa temporal', 'number': 'J000000000'},
              'establishment': {'id': fixture['establishment_id'], 'description': 'Sucursal temporal'}}
    page = r'''<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="csrf-token" content="temporary-browser-fixture"><meta name="viewport" content="width=device-width,initial-scale=1">
CSS<style>body{padding:20px;background:#f4f6fa}#sab-dashboard-result{font-size:10px;white-space:pre-wrap}*{animation-duration:0s!important;transition-duration:0s!important}</style>
<script>
window.dashboardFixture=FIXTURE;window.dashboardConfig=CONFIG;window.dashboardErrors=[];window.dashboardPending=0;
window.addEventListener('error',e=>{if(e.message)window.dashboardErrors.push(e.message)});
window.addEventListener('unhandledrejection',e=>window.dashboardErrors.push(String(e.reason)));
const originalError=console.error;console.error=function(...args){window.dashboardErrors.push(args.map(String).join(' '));originalError.apply(console,args)};
const originalSend=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.send=function(...args){
 window.dashboardPending++;this.addEventListener('loadend',()=>window.dashboardPending--,{once:true});return originalSend.apply(this,args)};
localStorage.clear();localStorage.setItem('dashboard_use_widgets',new URLSearchParams(location.search).get('mode')==='legacy'?'0':'1');
localStorage.setItem('config',JSON.stringify(window.dashboardConfig));localStorage.setItem('customers','[]');
localStorage.setItem('company',JSON.stringify(window.dashboardConfig.company));localStorage.setItem('establishment',JSON.stringify(window.dashboardConfig.establishment));
localStorage.setItem('userType','admin');
</script></head><body>
<div id="main-wrapper"><tenant-dashboard-index type-user="admin" company-environment="demo" :configuration='CONFIG_ATTRIBUTE'></tenant-dashboard-index></div>
<pre id="sab-dashboard-result">pending</pre><script src="/porto-light/vendor/jquery/jquery.js"></script><script type="module" src="/build/ENTRY"></script>
<script>
document.addEventListener('DOMContentLoaded',async()=>{
 const checks=[],assert=(ok,label)=>{if(!ok)throw new Error(label);checks.push(label)};
 const wait=()=>new Promise(resolve=>setTimeout(resolve,100));
 const until=async(predicate,label)=>{for(let attempt=0;attempt<180;attempt++){if(predicate())return;await wait()}throw new Error('Timeout: '+label)};
 const children=vm=>[vm,...(vm.$children||[]).flatMap(children)];
 const mode=new URLSearchParams(location.search).get('mode')||'widgets';let root;
 try{
  await until(()=>document.getElementById('main-wrapper').__vue__?.$children[0]?.filtersReady,'Vue dashboard mount');
  root=document.getElementById('main-wrapper').__vue__.$children[0];
  assert(root.$options.components.WidgetGrid && root.$options.components.RowTop,'real dashboard component registered and mounted');
  root.form={...root.form,period:'date',date_start:window.dashboardFixture.date,date_end:window.dashboardFixture.date};
  root.showLegacyCards=mode==='legacy';root.loadAll();await root.$nextTick();
  await until(()=>window.dashboardPending===0 && (mode==='legacy'?!Object.values(root.loaders).some(Boolean):root.widgetStore.state.ready),'dashboard responses');
  await wait();await wait();
  const descendants=children(root);
  assert(root.form.date_start===window.dashboardFixture.date,'selected filter matches actual temporary report date');
  assert(root.showLegacyDashboard===(mode==='legacy'),'selected dashboard mode mounted');
  assert([...document.querySelectorAll('.row')].some(el=>getComputedStyle(el).display==='flex'),'compiled shared framework styles are loaded as in the Vite manifest');
  assert(window.dashboardErrors.length===0,'mounted dashboard has no Vue or browser errors');
  if(mode==='widgets'){
   const state=root.widgetStore.state;
   assert(state.layout.length>=12,'actual default catalog layout contains at least twelve widgets');
   assert(!Object.values(state.loading).some(Boolean),'all visible widget requests settled');
   const sales=state.datasets['ventas.ventas_totales|{}'];
   assert(sales?.totals.calculation_snapshot.values.current.n==='1' && sales.totals.calculation_snapshot.values.current.d==='200','browser receives the actual one-two-hundredth sales fraction');
   const first=descendants.find(vm=>vm.$options.name==='KpiRenderer' && vm.dataset===sales);
   assert(!!first,'actual sales KPI renderer mounted');
   assert(first.valueStr==='Bs. 0.01','mounted KPI rounds the original fraction only at presentation');
   assert(first.$el.textContent.includes('Bs. 0.01'),'visible KPI DOM matches original fraction');
   assert(state.filters.date_start===window.dashboardFixture.date && state.filters.period==='date','catalog initialization retains the latest selected filter');
   assert(document.querySelectorAll('svg.apexcharts-svg').length>=3,'real ApexCharts render SVGs');
   const ranking=descendants.find(vm=>vm.$options.name==='RankingRenderer');
   assert(!!ranking && ranking.$el.textContent.includes('0,01'),'actual top-products ranking renders the exact allocation');
   assert(document.querySelectorAll('.wg-cell').length===state.layout.length,'each default widget mounts its actual cell');
   assert(document.querySelectorAll('.wg-title').length===state.layout.filter(widget=>widget.type!=='custom').length,'generic cards render their titles while custom cards retain their own headings');
   const productWidget=state.layout.find(widget=>widget.source==='ventas.top_productos');
   root.widgetStore.setType(productWidget.id,'table');await root.$nextTick();await wait();
   const tableRenderer=children(root).find(vm=>vm.$options.name==='TableRenderer');
   assert(!!tableRenderer && tableRenderer.$el.textContent.includes('0,01'),'changing the actual product widget to table preserves original totals');
   const documentWidget=state.layout.find(widget=>widget.source==='ventas.comprobantes');
   root.widgetStore.setType(documentWidget.id,'radial');await root.$nextTick();await wait();
   const radial=children(root).find(vm=>vm.$options.name==='ApexRenderer' && vm.type==='radial');
   assert(!!radial && radial.chartSeries[0]===0 && radial.valueTooltip(999,{seriesIndex:0})==='0%','actual radial renderer calculates from the unpaid documentary snapshot');
   assert(document.querySelectorAll('.apexcharts-radialbar').length>=1,'real ApexCharts radial SVG mounts after type change');
  }else{
   const top=descendants.find(vm=>'monthly_sales' in vm && 'trendFormatter' in vm);
   assert(!!top,'actual classic RowTop component mounted');
   assert(top.monthly_sales?.n==='1' && top.monthly_sales.d==='200','classic KPI retains the actual raw sales fraction');
   const headings=[...document.querySelectorAll('.kpi-amount')].map(el=>el.textContent.trim());
   assert(headings.length===4 && headings[0]==='Bs. 0.01','four classic KPI headings mount with exact sale');
   assert(root.document.totals.calculation_snapshot.values.total.n==='1','classic source retains its actual header snapshot');
   const graphs=descendants.filter(vm=>vm.$options._componentTag==='x-graph');
   assert(graphs.length>=2 && graphs.every(vm=>!!vm.chart),'real Chart.js balance and utility charts initialize');
   const balance=graphs.find(vm=>vm.totalLabel==='Balance');
   assert(!!balance && JSON.stringify(balance.exactTotal)===JSON.stringify(root.originalTotal(root.balance,'all_totals')),'balance center uses the original balance rather than summing slices');
   assert(document.querySelectorAll('canvas').length>=2,'actual Chart.js canvases mount');
   assert(document.querySelectorAll('svg.apexcharts-svg').length>=3,'classic Apex charts render SVGs');
   const table=[...document.querySelectorAll('table')].find(el=>el.textContent.includes('Total pagos') && el.textContent.includes('Totales'));
   assert(!!table && table.textContent.includes(String(root.balance.totals.all_totals)),'classic balance table displays the computed total');
  }
  await wait();assert(window.dashboardErrors.length===0,'no delayed rendering errors');
  document.getElementById('sab-dashboard-result').textContent=JSON.stringify({ok:true,mode,checks,errors:window.dashboardErrors});
 }catch(error){document.getElementById('sab-dashboard-result').textContent=JSON.stringify({ok:false,mode,checks,error:String(error),errors:window.dashboardErrors,pending:window.dashboardPending,components:root?children(root).map(vm=>vm.$options.name||vm.$options._componentTag):[]});}
});
</script></body></html>'''.replace('CONFIG_ATTRIBUTE', html.escape(json.dumps(config), quote=True)).replace('FIXTURE', json.dumps(fixture).replace('<', '\\u003c')).replace('CONFIG', json.dumps(config)).replace('ENTRY', entry['file']).replace('CSS', css)
    requests = []
    unexpected = []

    class Handler(http.server.BaseHTTPRequestHandler):
        def send_bytes(self, content, content_type='application/json; charset=utf-8', code=200):
            self.send_response(code)
            self.send_header('Content-Type', content_type)
            self.send_header('Content-Length', str(len(content)))
            self.end_headers()
            try:
                self.wfile.write(content)
            except BrokenPipeError:
                pass

        def do_GET(self):
            path = urllib.parse.urlsplit(self.path).path
            if path == '/':
                self.send_bytes(page.encode(), 'text/html; charset=utf-8')
                return
            responses = {'/dashboard/filter': {'establishment_id': fixture['establishment_id'],
                          'establishments': [{'id': fixture['establishment_id'], 'name': 'Sucursal temporal'}]},
                         '/dashboard/widgets/catalog': fixture['catalog'], '/dashboard/widgets/layout': {'layout': []},
                         '/companies/record': {'data': config['company']}, '/keep-alive': {'ok': True}, **fixture['api']}
            if path in responses:
                requests.append({'method': 'GET', 'path': path, 'query': urllib.parse.urlsplit(self.path).query})
                self.send_bytes(json.dumps(responses[path]).encode())
                return
            if path == '/favicon.ico':
                self.send_bytes(b'', code=204)
                return
            relative = path.lstrip('/')
            target = (public / relative).resolve()
            if (relative.startswith('build/assets/') or relative == 'porto-light/vendor/jquery/jquery.js') and target.is_relative_to(public) and target.is_file():
                self.send_bytes(target.read_bytes(), mimetypes.guess_type(target)[0] or 'application/octet-stream')
                return
            unexpected.append({'method': 'GET', 'path': path})
            self.send_bytes(b'{"error":"unexpected_fixture_request"}', code=404)

        def do_POST(self):
            path = urllib.parse.urlsplit(self.path).path
            payload = json.loads(self.rfile.read(int(self.headers.get('Content-Length', '0'))) or b'{}')
            requests.append({'method': 'POST', 'path': path, 'payload': payload})
            if path == '/dashboard/widgets/data':
                response = {'data': {item['key']: fixture['datasets'][item['source']] for item in payload['widgets']}}
            elif path in fixture['api']:
                response = fixture['api'][path]
            else:
                unexpected.append({'method': 'POST', 'path': path})
                self.send_bytes(b'{"error":"unexpected_fixture_request"}', code=404)
                return
            self.send_bytes(json.dumps(response).encode())

        def do_PUT(self):
            path = urllib.parse.urlsplit(self.path).path
            payload = json.loads(self.rfile.read(int(self.headers.get('Content-Length', '0'))) or b'{}')
            requests.append({'method': 'PUT', 'path': path, 'payload': payload})
            if path != '/dashboard/widgets/layout':
                unexpected.append({'method': 'PUT', 'path': path})
                self.send_bytes(b'{"error":"unexpected_fixture_request"}', code=404)
                return
            self.send_bytes(json.dumps({'layout': payload.get('layout', [])}).encode())

        def log_message(self, *unused):
            pass

    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    results = []
    try:
        for mode in ['widgets', 'legacy']:
            screenshot = args.output.with_name(args.output.stem + '-' + mode + '.png')
            with tempfile.TemporaryDirectory(prefix='sab-dashboard-browser-') as profile:
                process = subprocess.run([browser, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
                    '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
                    '--user-data-dir=' + profile, '--virtual-time-budget=24000', '--window-size=1440,2200',
                    '--screenshot=' + str(screenshot.resolve()), '--dump-dom',
                    'http://127.0.0.1:' + str(server.server_port) + '/?mode=' + mode],
                    capture_output=True, text=True, timeout=55)
            match = re.search(r'<pre id="sab-dashboard-result">(.*?)</pre>', process.stdout, re.S)
            result = json.loads(html.unescape(match[1])) if match and match[1] != 'pending' else {
                'ok': False, 'mode': mode, 'error': 'El navegador no terminó las comprobaciones.'}
            result.update(exit_code=process.returncode, screenshot=str(screenshot.relative_to(ROOT)))
            if not result['ok'] or process.returncode:
                args.output.with_name(args.output.stem + '-' + mode + '.chrome.log').write_text(process.stderr)
                args.output.with_name(args.output.stem + '-' + mode + '.html').write_text(process.stdout)
            results.append(result)
        result = {'ok': all(item['ok'] and item['exit_code'] == 0 for item in results) and not unexpected,
                  'modes': results, 'requests': requests, 'unexpected_requests': unexpected,
                  'browser': subprocess.check_output([browser, '--version'], text=True).strip(),
                  'source_sha256': before['sha256'], 'fixture_source_sha256': recorded['sha256'],
                  'source_unchanged': before['sha256'] == source_manifest()['sha256'],
                  'fixture_run_id': run_id, 'fixture_sha256': hashlib.sha256(args.fixture.read_bytes()).hexdigest(),
                  'manifest_sha256': hashlib.sha256(manifest_path.read_bytes()).hexdigest(),
                  'entry': entry['file'], 'compiled_source_files': len(sources['files']),
                  'stylesheets': stylesheets,
                  'databases': 'no browser database access; actual public DTOs from the marked temporary PostgreSQL run',
                  'scope': 'Real compiled Vue, ApexCharts and Chart.js mounting over captured producer DTOs; no authenticated HTTP business-server test.'}
        result['ok'] = result['ok'] and result['source_unchanged']
        args.output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + '\n')
        print(json.dumps({'ok': result['ok'], 'modes': [{key: value for key, value in item.items() if key != 'components'} for item in results],
                          'unexpected_requests': unexpected, 'source_unchanged': result['source_unchanged']}, ensure_ascii=False))
        return 0 if result['ok'] else 1
    finally:
        server.shutdown()
        server.server_close()
        thread.join()


if __name__ == '__main__':
    raise SystemExit(main())
