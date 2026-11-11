from pathlib import Path
import json,collections,re
p=Path(__file__).resolve().parent
out=[];incidents=[]
for label in ('principal-1','principal-2','http'):
 file=p/f'phpunit-{label}.jsonl'
 rows=[]
 if file.exists():
  for line in file.read_text(errors='replace').splitlines():
   try: rows.append(json.loads(line))
   except json.JSONDecodeError: pass
 counts=collections.Counter(x['resultado'] for x in rows)
 out.append({'grupo':label,'completadas':len(rows),'resultados':dict(counts),'ultima':rows[-1]['id'] if rows else None})
 for x in rows:
  for error in x['incidencias']:
   if error['tipo']!='skipped': incidents.append({'id':x['id'],'tipo':error['tipo'],'mensaje':error['mensaje']})
print(json.dumps({'completadas':sum(x['completadas'] for x in out),'grupos':out,'incidencias':incidents},ensure_ascii=False))
