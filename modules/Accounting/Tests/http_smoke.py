import fcntl
import json,urllib.request,urllib.error,base64,time,hmac,hashlib,struct,uuid,subprocess
from pathlib import Path
import sys
root=Path(sys.argv[1] if len(sys.argv)>1 else '.accounting-isolated').resolve()
assert root.name=='.accounting-isolated', 'Usa el directorio privado del entorno aislado.'
assert subprocess.check_output(['docker','inspect','--format','{{index .Config.Labels "contabilidad.goal"}}','contabilidad-api-v1-web'],text=True).strip()=='api-v1'
fixture_lock=(root/'fixture.lock').open('a');fcntl.flock(fixture_lock,fcntl.LOCK_EX)
accounts=json.loads((root/'clone-access.json').read_text());checks=[]
def call(path,body=None,token=None,verification=None,headers=None,host='contabilidad.localhost',expected=200):
 h={'Host':host+':9080','Accept':'application/json'}
 if token:h['Authorization']='Bearer '+token
 if verification is None and token:
  verification=next((a.get('verification') for a in accounts.values() if a.get('token')==token),None)
 if verification:h['X-Accounting-Verification']=verification
 if body is not None:h['Content-Type']='application/json'
 h.update(headers or {})
 req=urllib.request.Request('http://127.0.0.1:9080'+path,data=json.dumps(body).encode() if body is not None else None,headers=h)
 try:
  with urllib.request.urlopen(req,timeout=45) as r:status=r.status;data=r.read();response_headers=dict(r.headers)
 except urllib.error.HTTPError as r:status=r.code;data=r.read();response_headers=dict(r.headers)
 try:result=json.loads(data)
 except:result={}
 assert status==expected,f'{path}: HTTP {status} esperado {expected}, código {result.get("code")}'
 if path.startswith('/api/accounting/'):
  assert 'no-store' in response_headers.get('Cache-Control',''),path+' requiere no-store'
 return result
probe=urllib.request.Request('http://127.0.0.1:9080/api/accounting/v1/health',headers={'Host':'contabilidad.localhost:9080','Accept':'application/json'})
try:
 response=urllib.request.urlopen(probe,timeout=10)
except urllib.error.HTTPError as error:response=error
assert response.headers.get('X-Accounting-Isolated-Test')=='1','El destino no acredita ser el clon aislado.'
for role,account in accounts.items():
 response=call('/api/login',{'email':account['email'],'password':account['password']})
 assert response['success'] and response['token'];account['token']=response['token']
 me=call('/api/accounting/v1/auth/me',token=account['token'])
 if not me['mfa_enabled']:
  enrollment=call('/api/accounting/v1/auth/mfa/enroll',{'password':account['password']},token=account['token'])
  secret=base64.b32decode(enrollment['secret']+'='*((8-len(enrollment['secret'])%8)%8));counter=int(time.time())//30
  digest=hmac.new(secret,struct.pack('>Q',counter),hashlib.sha1).digest();offset=digest[-1]&15;code=str((int.from_bytes(digest[offset:offset+4],'big')&0x7fffffff)%1000000).zfill(6)
  confirmed=call('/api/accounting/v1/auth/mfa/confirm',{'code':code},token=account['token']);account['recovery_codes']=confirmed['recovery_codes']
 else:
  confirmed=call('/api/accounting/v1/auth/mfa/verify',{'code':account['recovery_codes'].pop(0)},token=account['token'])
 account['verification']=confirmed['verification_token']
(root/'clone-access.json').write_text(json.dumps(accounts,indent=2))
checks.append('SAB login y segundo factor de ambos usuarios')
a=accounts['autor'];r=accounts['revisor'];books=call('/api/accounting/v1/companies',token=a['token'])['data'];book=next(b for b in books if b['tax_id']=='J-00000000-0');other=next(b for b in books if b['tax_id']=='J-00000000-1');base='/api/accounting/v1/companies/'+book['id']
chart={x['code']:x for x in call(base+'/catalogs/accounts',token=a['token'])['data']}
local_id=str(uuid.uuid4());key=str(uuid.uuid4());payload={'accounting_date':'2026-11-11','concept':'Prueba HTTP integral del módulo','local_id':local_id,'lines':[{'account_id':chart['1.1.02']['id'],'debit':'100.10','credit':'0.00'},{'account_id':chart['3.1']['id'],'debit':'0.00','credit':'100.10'}]}
entry=call(base+'/entries',payload,token=a['token'],headers={'Idempotency-Key':key})['data'];same=call(base+'/entries',payload,token=a['token'],headers={'Idempotency-Key':key})['data'];assert same['id']==entry['id'];checks.append('creación idempotente de borrador')
missing=call('/api/accounting/v1/companies/'+other['id']+'/entries/'+entry['id'],token=a['token'],expected=404);assert missing['code']=='NOT_FOUND';checks.append('aislamiento de comprobantes entre empresas')
entry=call(base+'/entries/'+entry['id']+'/submit',{},token=a['token'],headers={'If-Match':str(entry['version'])})['data']
blocked=call(base+'/entries/'+entry['id']+'/approve',{},token=r['token'],verification=False,expected=403);assert blocked['code']=='MFA_REQUIRED';checks.append('operación definitiva bloqueada sin verificación adicional')
entry=call(base+'/entries/'+entry['id']+'/approve',{'comment':'Revisión independiente'},token=r['token'],verification=r['verification'],headers={'If-Match':str(entry['version'])})['data']
headers={'If-Match':str(entry['version']),'Idempotency-Key':str(uuid.uuid4())};posted=call(base+'/entries/'+entry['id']+'/post',{},token=r['token'],verification=r['verification'],headers=headers)['data'];retry=call(base+'/entries/'+entry['id']+'/post',{},token=r['token'],verification=r['verification'],headers=headers)['data'];assert retry['reference']==posted['reference'];assert posted['status']=='posted';checks.append('revisión separada y contabilización idempotente')
device=str(uuid.uuid4());package=call(base+'/offline/prepare',{'device_id':device,'name':'Prueba HTTP aislada','period':'2026-11'},token=a['token'])['data'];assert package['user']['id']==a['id'] and package['company']['id']==book['id'];assert 'token' not in package;checks.append('paquete local acotado a empresa, usuario y período')
sync_key=str(uuid.uuid4());sync={**payload,'concept':'Borrador de prueba sin conexión','local_id':str(uuid.uuid4()),'device_id':device}
saved=call(base+'/offline/sync',sync,token=a['token'],headers={'Idempotency-Key':sync_key})['data'];again=call(base+'/offline/sync',sync,token=a['token'],headers={'Idempotency-Key':sync_key})['data'];assert saved['id']==again['id'] and saved['status']=='draft' and saved['reference'] is None;checks.append('sincronización repetida solo crea un borrador sin correlativo')
blocked=call('/api/accounting/v1/health',token=a['token'],host='wrong.localhost',expected=404);assert blocked['code']=='TENANT_REQUIRED'
blocked=call('/api/accounting/v1/companies',expected=401);assert blocked['code']=='UNAUTHENTICATED';checks.append('rechazo de dominio incorrecto y peticiones anónimas')
(root/'http-tests.json').write_text(json.dumps({'passed':len(checks),'checks':checks},indent=2));print(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2))
