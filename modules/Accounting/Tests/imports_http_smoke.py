"""Real multipart/permission/receipt acceptance in the guarded private PRO9 clone."""
from isolated_http import IsolatedClient
import csv
import io
import json
import subprocess
import urllib.error
import urllib.request
import uuid
import zipfile
import xml.etree.ElementTree as ET

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
prepared = subprocess.run(['docker', 'exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_imports_fixture.php', 'http'], capture_output=True, text=True)
assert prepared.returncode == 0 and 'Fixture de importaciones ficticias preparada' in prepared.stdout, prepared.stdout + prepared.stderr
fixture = json.loads((client.root / 'imports-http-fixture.json').read_text())
assert fixture['company_name'].startswith('Importaciones ficticias HTTP ')
base = '/api/accounting/v1/companies/' + fixture['company_id']
checks = []


def raw(path, actor, data=None, content_type=None, expected=200):
    headers = {'Host': 'contabilidad.localhost:9080', 'Accept': 'application/json', 'Authorization': 'Bearer ' + actor['token'], 'X-Accounting-Verification': actor['verification']}
    if content_type:
        headers['Content-Type'] = content_type
    request = urllib.request.Request('http://127.0.0.1:9080' + path, data=data, headers=headers)
    try:
        response = urllib.request.urlopen(request, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read()
    assert response.headers.get('X-Accounting-Isolated-Test') == '1'
    assert response.status == expected, f'HTTP {response.status}; {body[:240]!r}'
    return body, response.headers


def csv_file(kind, rows):
    header, _ = raw(base + '/imports/templates/' + kind + '/csv', reviewer)
    names = next(csv.reader(io.StringIO(header.decode('utf-8-sig')), delimiter=';'))
    out = io.StringIO(newline='')
    writer = csv.DictWriter(out, names, delimiter=';', lineterminator='\r\n')
    writer.writeheader()
    writer.writerows(rows)
    return out.getvalue().encode('utf-8')


def upload(kind, contents, actor=reviewer, filename='ficticio.csv', expected=200):
    boundary = 'accounting-fictitious-' + uuid.uuid4().hex
    segments = []
    for name, value in [('kind', kind), ('delimiter', 'semicolon'), ('decimal_mark', 'dot')]:
        segments.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    segments.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + contents + b'\r\n')
    segments.append(f'--{boundary}--\r\n'.encode())
    body, _ = raw(base + '/imports/preview', actor, b''.join(segments), 'multipart/form-data; boundary=' + boundary, expected)
    return json.loads(body)


def line(reference, account, debit, credit, **extra):
    return dict(empresa_rif=fixture['company_rif'], referencia_importacion=reference, fecha_contable=fixture['date'], concepto='Asiento ficticio HTTP', cuenta=account, debe_ves=debit, haber_ves=credit, **extra)


def apply(job, actor=author, key=None, selected=None, expected=200):
    selected = selected if selected is not None else [unit['id'] for unit in job['units'] if unit['status'] == 'valid']
    return client.request(base + '/imports/' + job['id'] + '/apply', {'preview_hash': job['preview_hash'], 'units': selected}, actor, headers={'If-Match': str(job['version']), 'Idempotency-Key': key or str(uuid.uuid4())}, expected=expected)


for kind in ['third-parties', 'entries', 'opening', 'documents']:
    book, headers = raw(base + '/imports/templates/' + kind + '/xlsx', reviewer)
    assert set(headers['Cache-Control'].replace(' ', '').split(',')) == {'private', 'no-store'} and headers['X-Content-Type-Options'] == 'nosniff'
    with zipfile.ZipFile(io.BytesIO(book)) as archive:
        assert 'xl/workbook.xml' in archive.namelist()
        assert b'Instrucciones' in archive.read('xl/workbook.xml')
    assert upload(kind, book, filename='plantilla-vacia.xlsx', expected=422)['code'] == 'IMPORT_EMPTY'
checks.append('cuatro plantillas XLSX privadas con instrucciones, sin registros de ejemplo importables')

party = dict(empresa_rif=fixture['company_rif'], referencia_importacion='CLIENTE-01', rif='J-00000123-0', nombre='Cliente ficticio HTTP', tipo='customer')
party_bytes = csv_file('third-parties', [party])
assert upload('third-parties', party_bytes, actor=author, expected=403)['code'] == 'FORBIDDEN_ACTION'
party_job = upload('third-parties', party_bytes)['data']
assert party_job['summary']['valid'] == 1
party_result = apply(party_job, reviewer)['data']
assert party_result['summary']['imported'] == 1
checks.append('permiso de importación y permiso específico del catálogo exigidos al cargar y confirmar')

rows = [line('CORRECTO-01', '1.1.02', '10.10', '0'), line('CORRECTO-01', '3.1', '0', '10.10'), line('RECHAZADO-01', '1.1.02', '7', '0'), line('RECHAZADO-01', '3.1', '0', '6')]
original_bytes = csv_file('entries', rows)
job = upload('entries', original_bytes, author)['data']
assert job['summary'] == {'total': 2, 'valid': 1, 'invalid': 1, 'imported': 0, 'already_imported': 0}
assert job['units'][1]['rows'] == [4, 5] and job['units'][1]['errors'][0]['code'] == 'UNBALANCED_ENTRY'
assert client.request(base + '/entries', actor=author)['total'] == 0
assert 'path' not in job and 'parameters_hash' not in job and 'data_hash' not in job
assert upload('entries', original_bytes, author, filename='mismo-con-otro-nombre.csv')['data']['id'] == job['id']
checks.append('multipart real con prevalidación indivisible, sin asientos persistidos ni rutas privadas en la respuesta')

assert apply(job, selected=[job['units'][1]['id']], expected=422)['code'] == 'IMPORT_SELECTION'
assert client.request(base + '/entries', actor=author)['total'] == 0
key = str(uuid.uuid4())
accepted = apply(job, key=key)['data']
assert accepted['status'] == 'partial' and accepted['summary']['imported'] == 1
entry_id = accepted['units'][0]['resource']['id']
entry = client.request(base + '/entries/' + entry_id, actor=author)['data']
assert entry['status'] == 'draft' and entry['total_debit'] == '10.10000000' and entry['posted_at'] is None
assert apply(job, key=key)['data'] == accepted
receipt = client.request(base + '/operations/import.apply/' + key, actor=author)['data']
assert receipt['status'] == 'confirmed' and receipt['result'] == accepted
assert apply(job, expected=412)['code'] == 'IMPORT_PREVIEW_CHANGED'
checks.append('aceptación parcial explícita, borrador sin contabilizar y recibo idempotente ante respuesta incierta')

errors_book, error_headers = raw(base + '/imports/' + job['id'] + '/errors', author)
assert set(error_headers['Cache-Control'].replace(' ', '').split(',')) == {'private', 'no-store'}
with zipfile.ZipFile(io.BytesIO(errors_book)) as archive:
    sheet = ET.fromstring(archive.read('xl/worksheets/sheet1.xml'))
    namespace = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
    assert len(sheet.findall('.//s:row', namespace)) == 3
    assert not sheet.findall('.//s:f', namespace)
rows[3]['haber_ves'] = '7'
fixed = upload('entries', csv_file('entries', rows), author, filename='corregido.csv')['data']
assert [unit['status'] for unit in fixed['units']] == ['already_imported', 'valid']
assert fixed['units'][0]['resource']['id'] == entry_id
done = apply(fixed)['data']
assert done['status'] == 'complete' and client.request(base + '/entries', actor=author)['total'] == 2
checks.append('descarga de todas las líneas rechazadas y archivo corregido sin duplicar unidades aceptadas')

rows[0]['debe_ves'] = rows[1]['haber_ves'] = '11.10'
conflict = upload('entries', csv_file('entries', rows), author)['data']
assert conflict['units'][0]['errors'][0]['code'] == 'IMPORT_REFERENCE_CONFLICT'
checks.append('una referencia aceptada con contenido cambiado exige revisión y no sobrescribe el registro')

bad_company = [line('OTRO-RIF', '1.1.02', '1', '0'), line('OTRO-RIF', '3.1', '0', '1')]
bad_company[0]['empresa_rif'] = 'J-99999999-9'
denied = upload('entries', csv_file('entries', bad_company), author)['data']
assert denied['units'][0]['errors'][0]['code'] == 'IMPORT_WRONG_COMPANY'
other_company = next(company['id'] for company in client.request('/api/accounting/v1/companies', actor=reviewer)['data'] if company['id'] != fixture['company_id'])
assert client.request('/api/accounting/v1/companies/' + other_company + '/imports/' + job['id'], actor=reviewer, expected=404)['code'] == 'NOT_FOUND'
checks.append('RIF por fila y búsqueda de archivos aislada por empresa, incluso con el mismo usuario')

payload = {'preview_hash': denied['preview_hash']}
rechecked = client.request(base + '/imports/' + denied['id'] + '/revalidate', payload, author, headers={'If-Match': str(denied['version']), 'Idempotency-Key': str(uuid.uuid4())})['data']
assert rechecked['version'] == denied['version'] + 1 and rechecked['summary']['invalid'] == 1
assert upload('entries', b'empresa_rif;referencia_importacion\n"sin cerrar', author, expected=422)['code'] == 'IMPORT_CSV'
assert client.request(base + '/imports/preview', {'kind': 'entries', 'file': '/etc/passwd', 'delimiter': 'semicolon', 'decimal_mark': 'dot'}, author, expected=422)['code'] == 'VALIDATION_ERROR'
checks.append('revalidación versionada y rechazo de CSV incompleto o rutas enviadas como supuestos archivos')

memberships = client.request(base + '/users', actor=reviewer)['data']
member = next(member for member in memberships if member['user_id'] == author['id'])
client.request(base + '/users', {'user_id': author['id'], 'roles': ['viewer'], 'active': True, 'reason': 'Prueba ficticia de revocación de importaciones.'}, reviewer, headers={'If-Match': str(member['version'])})
assert client.request(base + '/imports', actor=author, expected=403)['code'] == 'FORBIDDEN_ACTION'
assert apply(done, actor=author, selected=[done['units'][0]['id']], expected=403)['code'] == 'FORBIDDEN_ACTION'
checks.append('revocación efectiva en servidor impide consultar y confirmar importaciones desde una sesión anterior')

result = {'result': 'passed', 'checks': checks, 'database': 'private isolated clone; fictitious books only'}
(client.root / 'imports-http-result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps(result, ensure_ascii=False, indent=2))
