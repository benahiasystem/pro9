"""Real multipart/private downloads and audit HTTP, only in the guarded clone."""
from isolated_http import IsolatedClient
import hashlib
import json
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author); client.login(reviewer)
prepared = subprocess.run(['docker', 'exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_supports_fixture.php', 'http'], capture_output=True, text=True)
assert prepared.returncode == 0 and 'Fixture de soportes ficticios preparada' in prepared.stdout, prepared.stdout + prepared.stderr
fixture = json.loads((client.root / 'supports-http-fixture.json').read_text())
assert fixture['company_name'].startswith('Soportes ficticios HTTP ')
base = '/api/accounting/v1/companies/' + fixture['company_id']
foreign = '/api/accounting/v1/companies/' + fixture['foreign_company_id']
checks = []


def raw(path, actor=reviewer, *, body=None, headers=None, method=None, expected=200):
    h = {'Host': 'contabilidad.localhost:9080', 'Accept': 'application/json', 'Authorization': 'Bearer ' + actor['token'], 'X-Accounting-Verification': actor['verification']}
    h.update(headers or {})
    for attempt in range(3):
        req = urllib.request.Request('http://127.0.0.1:9080' + path, data=body, headers=h, method=method)
        try: response = urllib.request.urlopen(req, timeout=90)
        except urllib.error.HTTPError as error: response = error
        content = response.read()
        assert response.headers.get('X-Accounting-Isolated-Test') == '1'
        if response.status == 429 and expected != 429 and attempt < 2:
            delay = int(response.headers.get('Retry-After', '60')); assert 0 < delay <= 60
            print('Se respeta el límite de solicitudes del clon aislado.', flush=True); time.sleep(delay); continue
        assert response.status == expected, f'{path}: HTTP {response.status}, esperado {expected}; {content[:300]!r}'
        return content, response.headers
    raise AssertionError('No se obtuvo respuesta tras respetar el límite.')


def request(path, actor=reviewer, **kw): return json.loads(raw(path, actor, **kw)[0])


def upload(target, content, name, version, *, actor=reviewer, key=None, previous=None, expected=200, title='Soporte ficticio HTTP'):
    boundary = 'AccountingFixture' + uuid.uuid4().hex
    fields = {'title': title, 'reason': 'Evidencia ficticia enviada desde la prueba HTTP de soportes.'}
    if previous: fields['supersedes_id'] = previous
    parts = []
    for field, value in fields.items(): parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"\r\n\r\n{value}\r\n'.encode())
    parts.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode(), content, f'\r\n--{boundary}--\r\n'.encode()])
    return request(target, actor, body=b''.join(parts), method='POST', expected=expected, headers={'Content-Type': 'multipart/form-data; boundary=' + boundary, 'If-Match': str(version), 'Idempotency-Key': key or str(uuid.uuid4())})


entry = fixture['pending_id']; route = base + '/evidence/journal_entry/' + entry
before = request(base + '/entries/' + entry)['data']
content = b'Soporte ficticio HTTP. Cantidad de control: 1160.00 VES.\n'
key = str(uuid.uuid4())
first = upload(route + '/attachments', content, 'evidencia-ficticia.txt', before['version'], key=key)['data']
again = upload(route + '/attachments', content, 'evidencia-ficticia.txt', before['version'], key=key)['data']
assert first['attachment']['id'] == again['attachment']['id'] and first['review_invalidated']
after = request(base + '/entries/' + entry)['data']
assert after['status'] == 'observed' and after['version'] == before['version'] + 1
assert first['attachment']['sha256'] == hashlib.sha256(content).hexdigest()
receipt = request(base + '/operations/attachment.add/' + key)['data']
assert receipt['status'] == 'confirmed' and receipt['result']['attachment']['id'] == first['attachment']['id']
assert 'path' not in json.dumps(first) and 'accounting-av' not in json.dumps(first)
checks.append('Multipart real, archivo analizado, aprobación retirada y confirmación idempotente sin duplicados')

case = request(route)
assert case['meta']['company_id'] == fixture['company_id'] and case['meta']['total'] == 1
download, headers = raw(base + '/attachments/' + first['attachment']['id'] + '/download', author)
assert download == content and headers['X-Accounting-Attachment-SHA256'] == hashlib.sha256(download).hexdigest()
assert 'no-store' in headers['Cache-Control'] and headers['X-Content-Type-Options'] == 'nosniff'
assert headers['Content-Disposition'].startswith('attachment;') and 'sandbox' in headers['Content-Security-Policy']
checks.append('Descarga privada autenticada, bytes originales, SHA-256 y cabeceras sin almacenamiento público')

fresh = upload(route + '/attachments', content, 'evidencia-ficticia.txt', after['version'])['data']
assert fresh['duplicate'] and fresh['attachment']['id'] == first['attachment']['id']
assert request(route)['meta']['total'] == 1
assert upload(route + '/attachments', b'Otra evidencia ficticia', 'otra.txt', before['version'], expected=412)['code'] == 'VERSION_CONFLICT'
changed = upload(route + '/attachments', b'Soporte ficticio con aclaracion posterior.', 'aclaracion.txt', after['version'], previous=first['attachment']['id'])['data']
assert changed['attachment']['supersedes_id'] == first['attachment']['id']
case = request(route)
assert len(case['data']) == 2 and next(v for v in case['data'] if v['id'] == first['attachment']['id'])['superseded_by'] == changed['attachment']['id']
checks.append('Versiones de soporte lineales conservadas; concurrencia obsoleta rechazada; identidad nueva tampoco duplica el original')

version = changed['resource']['version']
assert upload(route + '/attachments', b'<!DOCTYPE html><html><script>alert(1)</script></html>', 'falso.txt', version, expected=422)['code'] == 'ATTACHMENT_TYPE_MISMATCH'
assert upload(route + '/attachments', b'<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/hostname">]><a>&x;</a>', 'entidades.xml', version, expected=422)['code'] == 'ATTACHMENT_FORMAT_XML'
eicar = b'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
assert upload(route + '/attachments', eicar, 'prueba-inerte-antivirus.txt', version, expected=422)['code'] == 'ATTACHMENT_SCAN_REJECTED'
upload(route + '/attachments', b'x' * (5 * 1024 * 1024 + 1), 'sobre-limite.txt', version, expected=422)
assert request(route)['meta']['total'] == 2
checks.append('Rechazo real de tipo falso, entidades XML, archivo inerte EICAR y tamaño superior a 5 MiB sin modificar el registro')

posted_before = request(base + '/entries/' + fixture['posted_id'])['data']
later = upload(base + '/evidence/journal_entry/' + fixture['posted_id'] + '/attachments', b'Anexo ficticio posterior al asiento.', 'anexo.txt', posted_before['version'])['data']
assert later['attachment']['after_posting'] and not later['review_invalidated']
assert request(base + '/entries/' + fixture['posted_id'])['data'] == posted_before
image, image_headers = raw(base + '/attachments/' + fixture['attachment_id'] + '/preview')
assert image.startswith(b'\x89PNG\r\n\x1a\n') and image_headers['Content-Type'] == 'image/png'
assert hashlib.sha256(image).hexdigest() == image_headers['X-Accounting-Attachment-SHA256']
checks.append('Anexo posterior sin reescribir el asiento; vista de imagen recodificada con huella propia')

doc_before = request(base + '/documents/' + fixture['document_id'])['data']
upload(base + '/evidence/source_document/' + fixture['document_id'] + '/attachments', b'Factura ficticia adjunta al documento.', 'factura.txt', doc_before['version'])
doc_after = request(base + '/documents/' + fixture['document_id'])['data']
assert doc_after['source_revision'] == doc_before['source_revision'] and doc_after['amounts'] == doc_before['amounts'] and doc_after['version'] == doc_before['version'] + 1
checks.append('Soporte documental conserva los importes y la revisión del origen')

request(foreign + '/attachments/' + first['attachment']['id'] + '/download', expected=404)
request(foreign + '/evidence/journal_entry/' + entry, expected=404)
upload(foreign + '/evidence/journal_entry/' + entry + '/attachments', content, 'sin-permiso.txt', version, actor=author, expected=403)
request(base + '/audit', author, expected=403)
checks.append('Separación de empresas y permisos de soportes y auditoría comprobados por HTTP')

params = {'resource_type': 'journal_entry', 'resource_id': fixture['posted_id'], 'per_page': 25, 'from': '2000-01-01', 'to': str(fixture['year']) + '-12-31'}
audit = request(base + '/audit?' + urllib.parse.urlencode(params))
assert len(audit['data']) == 25 and audit['meta']['last_page'] >= 3 and audit['meta']['company_id'] == fixture['company_id']
params.update(page=2, until_sequence=audit['meta']['until_sequence'])
second = request(base + '/audit?' + urllib.parse.urlencode(params))
assert not {r['id'] for r in audit['data']} & {r['id'] for r in second['data']}
event = request(base + '/audit/' + audit['data'][0]['id'])['data']
assert isinstance(event['details_json'], str) and event['hash_version'] == 2
assert request(base + '/audit/integrity')['data']['state'] == 'verified'
request(foreign + '/audit/' + event['id'], expected=404)
legacy = request(foreign + '/audit/' + fixture['legacy_event_id'])['data']
assert legacy['hash_version'] == 1 and 'FICTICIO_NO_MOSTRAR' not in legacy['details_json'] and 'Dato privado omitido' in legacy['details_json']
assert request(foreign + '/audit/integrity')['data']['state'] == 'legacy'
checks.append('Auditoría paginada por secuencia, detalle privado, verificación v2 y antecedentes v1 sin certificación falsa')

out = client.root / 'supports-http-tests.json'
out.write_text(json.dumps({'passed': len(checks), 'checks': checks, 'isolated': True, 'data': 'fictitious'}, indent=2, ensure_ascii=False)); out.chmod(0o600)
print(json.dumps({'passed': len(checks), 'checks': checks}, ensure_ascii=False))
