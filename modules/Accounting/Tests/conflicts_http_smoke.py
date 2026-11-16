"""Exercise recoverable requests and explicit conflict decisions in private SAB."""
from isolated_http import IsolatedClient
from copy import deepcopy
import datetime
import json
import time
import uuid
import urllib.request
import urllib.error

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
checks = []
today = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4))).date().isoformat()
organization = client.request('/api/accounting/v1/organizations', actor=reviewer)['data'][0]
company = client.request('/api/accounting/v1/companies', {
    'organization_id': organization['id'], 'name': 'Conflictos ficticios ' + str(time.time_ns()),
    'tax_id': 'J-' + str(time.time_ns() % 100000000).zfill(8) + '-0',
    'initial_year': int(today[:4]), 'tax_profile': 'ordinary', 'approval_mode': 'self_control',
}, reviewer)['data']
base = '/api/accounting/v1/companies/' + company['id']
members = client.request(base + '/users', actor=reviewer)['data']
membership = next(m for m in members if m['user_id'] == reviewer['id'])
client.request(base + '/users', {'user_id': reviewer['id'], 'roles': ['admin', 'accountant'], 'active': True, 'reason': 'Responsabilidades expresas de la prueba de conflictos aislada.'}, reviewer, headers={'If-Match': str(membership['version'])})
client.request(base + '/users', {'user_id': author['id'], 'roles': ['accountant'], 'active': True, 'reason': 'Edición de alternativas ficticias en la prueba aislada.'}, reviewer)
accounts = {a['code']: a for a in client.request(base + '/catalogs/accounts', actor=author)['data']}
payload = {'accounting_date': today, 'concept': 'Primera alternativa aislada', 'local_id': str(uuid.uuid4()), 'lines': [
    {'account_id': accounts['1.1.02']['id'], 'debit': '9007199254740993.12345678', 'credit': '0'},
    {'account_id': accounts['3.1']['id'], 'debit': '0', 'credit': '9007199254740993.12345678'},
]}
key = str(uuid.uuid4())
operation = base + '/operations/entry.create/' + key
assert client.request(operation, actor=author)['data']['status'] == 'not_found'
entry = client.request(base + '/entries', payload, author, headers={'Idempotency-Key': key})['data']
receipt = client.request(operation, actor=author)['data']
assert receipt['status'] == 'confirmed' and receipt['result']['id'] == entry['id']
assert receipt['request']['lines'][0]['debit'] == '9007199254740993.12345678'
assert client.request(operation, actor=reviewer)['data']['status'] == 'not_found'
assert client.request(operation + '/cancel', {'reason': 'Comprobar recepción de un envío ya confirmado.'}, author)['data']['status'] == 'confirmed'
checks.append('confirmación propia recuperable conserva importes exactos y no cancela lo recibido')

cancelled_key = str(uuid.uuid4())
cancelled_path = base + '/operations/entry.create/' + cancelled_key
assert client.request(cancelled_path + '/cancel', {'reason': 'Decisión explícita para impedir la ejecución de una solicitud tardía.'}, author)['data']['status'] == 'cancelled'
late = client.request(base + '/entries', payload, author, headers={'Idempotency-Key': cancelled_key}, expected=409)
assert late['code'] == 'OPERATION_CANCELLED'
assert client.request(base + '/operations/arbitrary.execute/' + key, actor=author, expected=422)['code'] == 'UNKNOWN_OPERATION'
checks.append('cancelación auditada impide el envío tardío con la misma identidad')

duplicate = client.request(base + '/entries', payload, author, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=409)
assert duplicate['code'] == 'LOCAL_ENTRY_EXISTS' and duplicate['details']['current']['id'] == entry['id']
checks.append('otra clave de transporte no duplica la identidad lógica del borrador')

local = deepcopy(payload)
local['concept'] = 'Alternativa local conservada para comparar'
server = deepcopy(payload)
server['concept'] = 'Otra edición realizada en SAB'
entry = client.request(base + '/entries/' + entry['id'], server, reviewer, method='PATCH', headers={'If-Match': str(entry['version'])})['data']
comparison = client.request(base + '/entries/' + entry['id'] + '/comparison', actor=author)['data']
resolution_key = str(uuid.uuid4())
decision = {'base_version': 1, 'decision': 'apply_local', 'reason': 'Revisión expresa de ambas alternativas por el contador de prueba.', 'local': local}
headers = {'If-Match': str(comparison['version']), 'Idempotency-Key': resolution_key}
resolved = client.request(base + '/entries/' + entry['id'] + '/resolve', decision, author, headers=headers)['data']
replayed = client.request(base + '/entries/' + entry['id'] + '/resolve', decision, author, headers=headers)['data']
assert resolved['entity'] == replayed['entity']
assert resolved['entity']['concept'] == local['concept'] and resolved['entity']['status'] == 'draft'
assert resolved['entity']['total_debit'] == '9007199254740993.12345678'
assert resolved['resolution']['reviewed_version'] == comparison['version']
assert resolved['resolution']['resulting_version'] == comparison['version'] + 1
stale_key = str(uuid.uuid4())
stale = client.request(base + '/entries/' + entry['id'] + '/resolve', decision, author, headers={**headers, 'Idempotency-Key': stale_key}, expected=412)
assert stale['code'] == 'VERSION_CONFLICT'
assert client.request(base + '/operations/entry.resolve/' + stale_key, actor=author)['data']['status'] == 'not_found'
checks.append('comparación vigente, decisión idempotente y nuevo cambio concurrente rechazado sin sobrescribir')

document_input = {'kind': 'sale_invoice', 'number': 'CONFLICTO-' + str(time.time_ns()), 'issue_date': today, 'fiscal_date': today,
                  'currency': 'VES', 'exchange_rate': '1', 'exchange_source': 'Moneda del libro', 'exchange_date': today,
                  'amounts': {'base': '100.123456789012', 'vat': '0', 'other_taxes': '0', 'total': '100.123456789012'}, 'tax_components': []}
document = client.request(base + '/documents', document_input, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
doc_local = deepcopy(document_input)
doc_local['amounts']['base'] = doc_local['amounts']['total'] = '101.123456789012'
document_input['number'] += '-SAB'
document = client.request(base + '/documents/' + document['id'], document_input, reviewer, method='PATCH', headers={'If-Match': str(document['version'])})['data']
comparison = client.request(base + '/documents/' + document['id'] + '/comparison', actor=author)['data']
doc_key = str(uuid.uuid4())
resolved_document = client.request(base + '/documents/' + document['id'] + '/resolve', {'base_version': 1, 'decision': 'apply_local', 'reason': 'Selección expresa del documento local tras revisar los cambios de SAB.', 'local': doc_local}, author, headers={'If-Match': str(comparison['version']), 'Idempotency-Key': doc_key})['data']
assert resolved_document['entity']['amounts']['total'] == '101.123456789012'
assert resolved_document['entity']['source_revision'] == 3
assert client.request(base + '/operations/document.resolve/' + doc_key, actor=author)['data']['result']['entity']['id'] == document['id']
checks.append('documento manual conserva doce decimales, versiones de origen y confirmación de su decisión')

membership = next(m for m in client.request(base + '/users', actor=reviewer)['data'] if m['user_id'] == author['id'])
client.request(base + '/users', {'user_id': author['id'], 'roles': ['viewer'], 'active': True, 'reason': 'Verificar la resolución conservadora con permiso de consulta solamente.'}, reviewer, headers={'If-Match': str(membership['version'])})
entry = resolved['entity']
viewer_decision = {'base_version': 1, 'decision': 'keep_server', 'reason': 'Conservar la versión vigente y archivar expresamente la alternativa local.', 'local': local}
kept = client.request(base + '/entries/' + entry['id'] + '/resolve', viewer_decision, author, headers={'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())})['data']
assert kept['entity']['version'] == entry['version'] and kept['entity']['concept'] == entry['concept']
viewer_decision['decision'] = 'apply_local'
client.request(base + '/entries/' + entry['id'] + '/resolve', viewer_decision, author, headers={'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())}, expected=403)
checks.append('consulta permite conservar SAB; aplicar la alternativa exige permiso de edición vigente')

for _ in range(65):
    req = urllib.request.Request('http://127.0.0.1:9080/api/accounting/v1/health', headers={
        'Host': 'contabilidad.localhost:9080', 'Accept': 'application/json', 'Authorization': 'Bearer ' + author['token'],
    })
    try:
        response = urllib.request.urlopen(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        assert response.headers.get('X-Accounting-Isolated-Test') == '1'
        if response.status == 429:
            assert 0 <= int(response.headers['Retry-After']) <= 60
            assert 'no-store' in response.headers['Cache-Control']
            break
        assert response.status == 200
else:
    raise AssertionError('No se alcanzó el límite de lectura del escenario aislado.')
checks.append('el límite de solicitudes conserva Retry-After y las respuestas privadas sin caché')

fixture = {'company_id': company['id'], 'company_name': company['name'], 'entry_id': entry['id'], 'document_id': document['id'], 'date': today}
client.root.joinpath('conflicts-fixture.json').write_text(json.dumps(fixture, indent=2))
result = {'passed': len(checks), 'checks': checks}
client.root.joinpath('conflicts-http-tests.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result, ensure_ascii=False, indent=2))
