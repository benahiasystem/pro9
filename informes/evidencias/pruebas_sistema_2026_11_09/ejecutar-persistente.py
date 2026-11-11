from pathlib import Path
import subprocess,json,os,time,signal,sys,re,datetime,collections
run=Path(__file__).resolve().parent
plan=json.loads((run/'plan-persistente.json').read_text())
root=Path(plan['root'])
expected=[s[3:] for s in (run/'inventario-php.txt').read_text().splitlines() if s.startswith(' - ')]
byclass=collections.defaultdict(list)
for case in expected: byclass[case.split('::')[0]].append(case)
processes={};streams={};results={};commands=[]
def canonical(name): return name.replace(' with data set ','')
def records(path):
    out=[]
    if path.exists():
        for line in path.read_text(errors='replace').splitlines():
            try: out.append(json.loads(line))
            except json.JSONDecodeError: pass
    return out
def start(group,label):
    checkpoint=run/f'phpunit-{label}.jsonl'
    done={canonical(x['id']) for x in records(checkpoint)}
    parts=[];selected=[]
    for cls in group['classes']:
        methods=collections.defaultdict(list)
        for case in byclass[cls]: methods[re.match(r'^(.*::[A-Za-z0-9_]+)',case)[1]].append(case)
        pending=[method for method,cases in methods.items() if not set(cases)<=done]
        if not pending: continue
        selected.extend(c for method in pending for c in methods[method])
        if len(pending)==len(methods): parts.append(re.escape(cls)+'::.*')
        else: parts.append(re.escape(cls)+'::(?:'+'|'.join(re.escape(m.split('::')[1]) for m in pending)+')(?: with data set .*)?')
    if not selected:
        print(f'{label}: ya consta completo en el registro persistente.',flush=True)
        return
    pattern='/^(?:'+'|'.join(parts)+')$/'
    attempt=1+len(list(run.glob(f'phpunit-{label}-intento-*.log')))
    logpath=run/f'phpunit-{label}-intento-{attempt}.log'
    junit=run/f'phpunit-{label}-intento-{attempt}.xml'
    cmd=['php','scripts/test_exact_isolated_mysql.php','--configuration',plan['configuration'],'--colors=never','--do-not-cache-result','--printer','Tests\\Support\\FiscalProgressPrinter','--log-junit',str(junit),'--filter',pattern]
    env=os.environ.copy();env['PRO9_AUDIT_CHECKPOINT']=str(checkpoint)
    stream=logpath.open('w');streams[label]=stream
    process=subprocess.Popen(cmd,cwd=root,env=env,stdout=stream,stderr=subprocess.STDOUT,start_new_session=True)
    processes[label]=(process,attempt)
    commands.append({'grupo':label,'intento':attempt,'pid':process.pid,'casos_seleccionados':len(selected),'comando':cmd,'log':str(logpath),'junit':str(junit),'checkpoint':str(checkpoint)})
    (run/'comandos-persistentes.json').write_text(json.dumps(commands,ensure_ascii=False,indent=2)+'\n')
    print(f'{label}: {len(selected)} casos; MySQL temporal propio; intento {attempt}.',flush=True)
def collect():
    while processes:
        for label,(process,attempt) in list(processes.items()):
            code=process.poll()
            if code is None: continue
            streams.pop(label).close();results[label]=code;del processes[label]
            (run/f'phpunit-{label}-intento-{attempt}.exit').write_text(str(code)+'\n')
            print(f'{label} finalizado: {code}',flush=True)
        if processes: time.sleep(.25)
def interrupt(sig,frame): raise KeyboardInterrupt
signal.signal(signal.SIGTERM,interrupt);signal.signal(signal.SIGINT,interrupt)
try:
    for i,group in enumerate(plan['groups'],1): start(group,f'principal-{i}')
    collect()
    start(plan['solo'],'http');collect()
    allrecords=[]
    for label in ('principal-1','principal-2','http'): allrecords.extend(records(run/f'phpunit-{label}.jsonl'))
    latest={canonical(x['id']):x for x in allrecords}
    missing=sorted(set(expected)-set(latest));extra=sorted(set(latest)-set(expected))
    summary={'expected_tests':len(expected),'recorded_tests':len(latest),'missing':missing,'extra':extra,'results':dict(collections.Counter(x['resultado'] for x in latest.values())),'exits':results,'http_executed_without_other_test_workers':True,'completed_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()}
    (run/'resumen-php-persistente.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(summary,ensure_ascii=False),flush=True)
    sys.exit(2 if missing or extra else 1 if any(x['resultado'] not in ('passed','skipped') for x in latest.values()) else 0)
finally:
    for process,attempt in processes.values():
        if process.poll() is None:
            try: os.killpg(process.pid,signal.SIGTERM)
            except ProcessLookupError: pass
    for process,attempt in processes.values():
        try: process.wait(timeout=15)
        except subprocess.TimeoutExpired:
            try: os.killpg(process.pid,signal.SIGKILL)
            except ProcessLookupError: pass
    for stream in streams.values(): stream.close()
