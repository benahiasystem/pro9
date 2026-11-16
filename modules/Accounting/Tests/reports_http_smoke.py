"""Report queries and actual downloads, only in the private isolated clone."""
from isolated_http import IsolatedClient
import csv
import hashlib
import io
import json
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile
import xml.etree.ElementTree as ET

client = IsolatedClient()
viewer, reviewer = client.actors['autor'], client.actors['revisor']
client.login(viewer)
client.login(reviewer)
prepared = subprocess.run(['docker', 'exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_reports_fixture.php', 'http'], capture_output=True, text=True)
assert prepared.returncode == 0 and 'Fixture de informes ficticios preparada' in prepared.stdout, prepared.stdout + prepared.stderr
fixture = json.loads((client.root / 'reports-http-fixture.json').read_text())
assert fixture['company_name'].startswith('Exportaciones ficticias HTTP ')
base = '/api/accounting/v1/companies/' + fixture['company_id']
checks, jobs, evidence = [], {}, {}


def raw(path, actor=reviewer, data=None, expected=200, method=None, headers=None):
    h = {'Host': 'contabilidad.localhost:9080', 'Accept': 'application/json', 'Authorization': 'Bearer ' + actor['token'], 'X-Accounting-Verification': actor['verification']}
    h.update(headers or {})
    if data is not None:
        h['Content-Type'] = 'application/json'
    for attempt in range(3):
        request = urllib.request.Request('http://127.0.0.1:9080' + path, data=json.dumps(data).encode() if data is not None else None, headers=h, method=method)
        try:
            response = urllib.request.urlopen(request, timeout=120)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read()
        assert response.headers.get('X-Accounting-Isolated-Test') == '1'
        if response.status == 429 and expected != 429 and attempt < 2:
            wait = int(response.headers.get('Retry-After', '60'))
            assert 0 < wait <= 60
            print('Se respeta el límite de solicitudes del módulo.', flush=True)
            time.sleep(wait)
            continue
        assert response.status == expected, f'{path}: HTTP {response.status}, esperado {expected}; {body[:300]!r}'
        return body, response.headers
    raise AssertionError('No se obtuvo una respuesta dentro de los reintentos permitidos.')


def request(path, actor=reviewer, **kw):
    return json.loads(raw(path, actor, **kw)[0])


def query(kind, actor=reviewer, **filters):
    return request(base + '/reports/' + kind + '?' + urllib.parse.urlencode({'from': fixture['from'], 'to': fixture['to'], **filters}), actor)['data']


def capture(report, actor=reviewer, key=None, expected=200):
    return request(base + '/report-exports', actor, data={'kind': report['kind'], 'filters': report['filters'], 'review_token': report['review_token']}, headers={'Idempotency-Key': key or str(uuid.uuid4())}, expected=expected)


first = query('journal', per_page=100)
assert len(first['rows']) == 100 and first['total'] == fixture['journal_rows'] and first['last_page'] == 6
assert first['totals'] == {'debit': fixture['debit'], 'credit': fixture['debit']}
last = query('journal', viewer, page=6, per_page=100)
assert len(last['rows']) == 7 and last['review_token'] == first['review_token']
assert all(row['concept'] != 'Borrador ficticio excluido de los libros' for row in first['rows'] + last['rows'])
checks.append('Diario paginado de 507 líneas, controles completos y borradores excluidos')

for kind, extra in [('ledger', {'account_id': fixture['bank_id']}), ('auxiliary', {'third_party_id': fixture['party_id'], 'account_id': fixture['bank_id']})]:
    report = query(kind, **extra)
    assert report['opening'] == '1000.00' and report['totals']['balance'] == ('4432.50' if kind == 'ledger' else '4430.00')
assert query('journal', viewer, cost_center_id=fixture['center_id'])['total'] == 4
checks.append('Mayor y auxiliar conservan la apertura; filtros de tercero y centro afectan filas y controles')

for end, expected in [('11-11', '4000.00'), ('11-30', '2400.00'), ('12-31', '4000.00')]:
    report = query('open-items', to=str(fixture['year']) + '-' + end, status='open')
    assert report['totals']['book'] == expected and report['date_basis'] == 'cutoff'
assert query('documents')['total'] == 0
assert query('documents', include_pending=1)['data_scope'] == 'control_with_pending'
checks.append('Partidas históricas reconstruidas antes y después de aplicación y reverso; documentos pendientes separados')

for lookup, text, expected_id in [('accounts', '1.1.02', fixture['bank_id']), ('third-parties', 'Mérida', fixture['party_id']), ('cost-centers', 'CC-ADM', fixture['center_id'])]:
    result = request(base + '/report-lookups/' + lookup + '?' + urllib.parse.urlencode({'q': text}), viewer)
    assert result['data'][0]['id'] == expected_id
assert request(base + '/report-lookups/accounts?id=' + fixture['foreign_account_id'], viewer, expected=404)
assert request(base + '/reports/ledger?account_id=' + fixture['foreign_account_id'], expected=404)
assert request(base + '/reports/journal?include_pending=1', expected=422)['code'] == 'REPORT_FILTER'
checks.append('Búsquedas autorizadas por código, nombre y RIF; filtros ajenos rechazados')

kinds = ['journal', 'ledger', 'auxiliary', 'trial-balance', 'open-items', 'documents', 'incidents', 'balance-sheet', 'income-statement']
for index, kind in enumerate(kinds):
    actor = reviewer if index < 4 else viewer
    filters = {}
    if kind in ['ledger', 'auxiliary']:
        filters['account_id'] = fixture['bank_id']
    if kind == 'documents':
        filters['include_pending'] = 1
    if kind in ['balance-sheet', 'income-statement']:
        filters.update(compare_from=str(fixture['year'] - 1) + '-01-01', compare_to=str(fixture['year'] - 1) + '-11-30')
    report = query(kind, actor, **filters)
    if kind == 'income-statement':
        assert report['totals']['result'] == fixture['result'] and report['comparison']['totals']['result'] == '1000.00'
    if kind == 'balance-sheet':
        assert report['totals']['assets'] == fixture['assets'] and report['totals']['difference'] == '0.00'
    key = str(uuid.uuid4())
    job = capture(report, actor, key)['data']
    assert job['row_count'] == report.get('total', len(report['rows'])) and 'path' not in job
    assert job['snapshot']['company']['id'] == fixture['company_id'] and job['snapshot']['format_version'] == '2.0'
    assert request(base + '/operations/report.capture/' + key, actor)['data']['status'] == 'confirmed'
    if kind == 'journal':
        assert capture(report, actor, key)['data']['id'] == job['id']
    hashes = {}
    for format in ['csv', 'xlsx', 'pdf']:
        body, headers = raw(base + '/report-exports/' + job['id'] + '/download/' + format, actor)
        assert 'private' in headers['Cache-Control'] and 'no-store' in headers['Cache-Control'] and headers['X-Content-Type-Options'] == 'nosniff'
        assert headers['X-Accounting-Snapshot-SHA256'] == job['sha256']
        hashes[format] = hashlib.sha256(body).hexdigest()
        assert hashes[format] == headers['X-Accounting-Artifact-SHA256']
        assert 'attachment' in headers['Content-Disposition'] and len(body) > 100
        assert b'REPORT_PRIVATE_HTTP_SENTINEL' not in body
        target = client.root / ('reports-http-' + kind + '.' + format)
        target.write_bytes(body); target.chmod(0o600)
        if format == 'pdf':
            assert body.startswith(b'%PDF-') and headers['Content-Type'] == 'application/pdf'
        elif format == 'csv':
            rows = list(csv.reader(io.StringIO(body.decode('utf-8-sig')), delimiter=';'))
            assert sum(row[0] == 'detalle' for row in rows[1:]) == job['row_count']
            assert any(row[0] == 'control' for row in rows[1:])
        else:
            with zipfile.ZipFile(io.BytesIO(body)) as archive:
                assert b'Identificaci' in archive.read('xl/workbook.xml')
                for name in archive.namelist():
                    if name.startswith('xl/worksheets/'):
                        xml = ET.fromstring(archive.read(name))
                        assert not xml.findall('.//{*}f') and b'REPORT_PRIVATE_HTTP_SENTINEL' not in archive.read(name)
    current = request(base + '/report-exports/' + job['id'], actor)['data']
    assert {file['format']: file['sha256'] for file in current['artifacts']} == hashes
    jobs[kind] = job
    evidence[kind] = {'rows': job['row_count'], 'formats': list(hashes), 'artifactHashesVerified': True}
    print('Consultas y tres formatos verificados: ' + kind, flush=True)
checks.append('Nueve informes por tres formatos, misma huella de datos, archivos privados y sin fórmulas XLSX')

frozen = request(base + '/report-exports/' + jobs['journal']['id'] + '/rows?page=6&per_page=100', viewer)['data']
assert len(frozen['rows']) == 7 and frozen['total'] == 507
assert request('/api/accounting/v1/companies/' + fixture['foreign_company_id'] + '/report-exports/' + jobs['journal']['id'], reviewer, expected=404)
before = query('journal', viewer)
request(base, reviewer, method='PATCH', data={'name': fixture['company_name'] + ' · edición posterior'})
assert capture(before, viewer, expected=412)['code'] == 'REPORT_VIEW_CHANGED'
old = request(base + '/report-exports/' + jobs['journal']['id'] + '/rows?per_page=1', viewer)['data']
assert old['company']['name'] == fixture['company_name'] and old['totals']['debit'] == fixture['debit']
assert len(request(base + '/report-exports?kind=journal', viewer)['data']) == 1
checks.append('Captura idempotente, consulta del recibo, rechazo de versión desactualizada e historial inmutable después de editar la empresa')

capital = request(base + '/report-lookups/accounts?q=3.1', reviewer)['data'][0]['id']
entry = request(base + '/entries', reviewer, data={'accounting_date': str(fixture['year']) + '-11-28', 'concept': '=HYPERLINK("https://example.invalid")', 'lines': [{'account_id': fixture['bank_id'], 'debit': '9007199254740993.12', 'credit': '0'}, {'account_id': capital, 'debit': '0', 'credit': '9007199254740993.12'}]}, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
for action, body in [('submit', {}), ('approve', {'comment': 'Autocontrol explícito de la prueba ficticia de precisión.'}), ('post', {})]:
    result = request(base + '/entries/' + entry['id'] + '/' + action, reviewer, data=body, headers={'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())})['data']
    if action != 'post':
        entry = result
exact = query('journal', viewer, **{'from': str(fixture['year']) + '-11-28', 'to': str(fixture['year']) + '-11-28'})
assert exact['totals']['debit'] == '9007199254740993.12'
precision = capture(exact, viewer)['data']
for format in ['csv', 'xlsx', 'pdf']:
    contents, headers = raw(base + '/report-exports/' + precision['id'] + '/download/' + format, viewer)
    assert hashlib.sha256(contents).hexdigest() == headers['X-Accounting-Artifact-SHA256']
    (client.root / ('reports-http-precision.' + format)).write_bytes(contents)
    if format == 'csv':
        assert b'9007199254740993.12' in contents and b"'=HYPERLINK" in contents
    if format == 'xlsx':
        with zipfile.ZipFile(io.BytesIO(contents)) as archive:
            assert b'9007199254740993.12' in archive.read('xl/worksheets/sheet2.xml') and b'<f>' not in archive.read('xl/worksheets/sheet2.xml')
assert request(base + '/report-exports/' + jobs['journal']['id'] + '/rows?per_page=1', viewer)['data']['total'] == 507
checks.append('Importe superior al entero seguro de JavaScript y texto de fórmula conservados exactamente en HTTP y tres formatos')

users = request(base + '/users', reviewer)['data']
member = next(user for user in users if user['user_id'] == 2)
request(base + '/users', reviewer, data={'user_id': 2, 'roles': ['viewer'], 'active': False, 'reason': 'Revocación ficticia para verificar una descarga conservada.'}, headers={'If-Match': str(member['version'])})
assert request(base + '/report-exports/' + jobs['journal']['id'] + '/download/csv', viewer, expected=403)['code'] == 'FORBIDDEN_COMPANY'
checks.append('Una descarga ya generada vuelve a exigir pertenencia vigente a la empresa')

result = {'passed': len(checks), 'checks': checks, 'formats': evidence, 'precisionFormats': 3, 'pageErrors': [], 'realDataUploaded': False}
(client.root / 'reports-http-tests.json').write_text(json.dumps(result, indent=2, ensure_ascii=False))
print(json.dumps({'passed': len(checks), 'formats': len(evidence) * 3 + 3}, ensure_ascii=False))
