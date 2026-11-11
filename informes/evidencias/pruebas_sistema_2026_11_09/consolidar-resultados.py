from pathlib import Path
import json,collections,xml.etree.ElementTree as E,copy,subprocess,datetime
run=Path(__file__).resolve().parent
root=Path(json.loads((run/'plan-persistente.json').read_text())['root'])
expected=[s[3:] for s in (run/'inventario-php.txt').read_text().splitlines() if s.startswith(' - ')]
def canonical(s): return s.replace(' with data set ','')
latest={};attempts=[]
for label in ('principal-1','principal-2','http'):
 f=run/f'phpunit-{label}.jsonl'
 if f.exists():
  for line in f.read_text().splitlines():
   row=json.loads(line);row['grupo']=label;latest[canonical(row['id'])]=row;attempts.append(row)
missing=sorted(set(expected)-set(latest));extra=sorted(set(latest)-set(expected))
if missing or extra: raise SystemExit(f'Cobertura incompleta: faltan {len(missing)}, sobran {len(extra)}.')
junit={}
for label in ('principal-1','principal-2','http'):
 for f in sorted(run.glob(f'phpunit-{label}-intento-*.xml')):
  try: document=E.parse(f)
  except E.ParseError: continue
  for c in document.iter('testcase'): junit[canonical(c.get('class','')+'::'+c.get('name',''))]=c
if set(expected)!=set(junit): raise SystemExit('Los JUnit no cubren exactamente el inventario; revisar intentos y clases abortadas.')
rows=[]
for name in expected:
 row=copy.deepcopy(latest[name]);c=junit[name]
 row['detalle_junit']=[{'tipo':x.tag,'clase':x.get('type'),'texto':x.text or ''} for x in c if x.tag in ('error','failure','warning','skipped')]
 row['aserciones_junit']=int(c.get('assertions','0'))
 rows.append(row)
failed=[r for r in rows if r['resultado'] not in ('passed','skipped')]
php=r'''require $argv[1].'/vendor/autoload.php';$rows=json_decode(stream_get_contents(STDIN),true);$out=[];foreach($rows as $r){try{$method=explode(' with data set ',$r['nombre'])[0];$m=new ReflectionMethod($r['clase'],$method);$out[$r['id']]=['archivo'=>$m->getFileName(),'linea'=>$m->getStartLine()];}catch(Throwable $e){$out[$r['id']]=['archivo'=>$r['archivo'],'linea'=>null];}}echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);'''
proc=subprocess.run(['php','-r',php,str(root)],input=json.dumps(failed),text=True,capture_output=True,check=True)
refs=json.loads(proc.stdout)
for r in rows:
 if r['id'] in refs: r['fuente_prueba']=refs[r['id']]
counts=dict(collections.Counter(r['resultado'] for r in rows))
summary={'pruebas_inventariadas':len(expected),'pruebas_registradas':len(rows),'pruebas_junit':len(junit),'resultados':counts,'aserciones':sum(r['aserciones_junit'] for r in rows),'segundos_acumulados_casos':sum(r['segundos'] for r in rows),'cobertura_completa':True,'casos_fallidos':len(failed),'capturas_por_prueba':len(attempts),'consolidado_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()}
(run/'resultados-php.json').write_text(json.dumps({'resumen':summary,'pruebas':rows},ensure_ascii=False,indent=2)+'\n')
(run/'errores-pruebas-php.json').write_text(json.dumps(failed,ensure_ascii=False,indent=2)+'\n')
(run/'resumen-pruebas-php.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
r=E.Element('testsuites');s=E.SubElement(r,'testsuite',{'name':'Pro9 completo','tests':str(len(rows)),'assertions':str(summary['aserciones']),'errors':str(sum(len(c.findall('error')) for c in junit.values())),'failures':str(sum(len(c.findall('failure')) for c in junit.values())),'warnings':str(sum(len(c.findall('warning')) for c in junit.values())),'skipped':str(sum(len(c.findall('skipped')) for c in junit.values())),'time':str(summary['segundos_acumulados_casos'])})
for name in expected:s.append(copy.deepcopy(junit[name]))
tree=E.ElementTree(r);E.indent(tree,space='  ');tree.write(run/'phpunit-completo.xml',encoding='utf-8',xml_declaration=True)
print(json.dumps(summary,ensure_ascii=False,indent=2))
