"""Local alternative history is applied only inside SAB's authorized draft transaction."""
from isolated_http import IsolatedClient
from copy import deepcopy
import datetime
import json
import time
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
checks = []
today = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4))).date().isoformat()
organization = client.request('/api/accounting/v1/organizations', actor=reviewer)['data'][0]
company = client.request('/api/accounting/v1/companies', {
    'organization_id': organization['id'], 'name': 'Selecciones locales ficticias ' + str(time.time_ns()),
    'tax_id': 'J-' + str(time.time_ns() % 100000000).zfill(8) + '-0', 'initial_year': int(today[:4]),
    'tax_profile': 'ordinary', 'approval_mode': 'self_control',
}, reviewer)['data']
base = '/api/accounting/v1/companies/' + company['id']
members = client.request(base + '/users', actor=reviewer)['data']
membership = next(row for row in members if row['user_id'] == reviewer['id'])
client.request(base + '/users', {'user_id': reviewer['id'], 'roles': ['admin', 'accountant'], 'active': True,
    'reason': 'Responsabilidades expresas de la prueba de alternativas locales.'}, reviewer, headers={'If-Match': str(membership['version'])})
client.request(base + '/users', {'user_id': author['id'], 'roles': ['accountant'], 'active': True,
    'reason': 'Preparación de alternativas ficticias dentro del clon aislado.'}, reviewer)
chart = {row['code']: row for row in client.request(base + '/catalogs/accounts', actor=author)['data']}
device, logical, selected, other, resulting, decision = [str(uuid.uuid4()) for _ in range(6)]
client.request(base + '/offline/prepare', {'device_id': device, 'name': 'Prueba aislada de alternativas', 'period': today[:7]}, author)
body = {'accounting_date': today, 'concept': 'Alternativa local elegida', 'entry_type': 'regular', 'local_id': logical,
    'lines': [{'account_id': chart['1.1.02']['id'], 'debit': '75.20', 'credit': '0'}, {'account_id': chart['3.1']['id'], 'debit': '0', 'credit': '75.20'}]}
snapshot = {'id': 'local-' + selected, **deepcopy(body), 'version': 1, 'created_by': author['id'], 'status': 'draft', 'reference': None,
            'total_debit': '75.20000000', 'total_credit': '75.20000000'}
alternative = deepcopy(snapshot)
alternative['concept'] = 'Alternativa anterior conservada'
alternative['lines'][0]['debit'] = alternative['lines'][1]['credit'] = '9007199254740993.12345678'
packet = {'schema': 1, 'tip_id': decision, 'draft_id': resulting, 'decisions': [{
    'id': decision, 'client_id': logical, 'kind': 'entry', 'selected_id': selected, 'result_id': resulting,
    'reported_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'reason': 'Elegí la alternativa conciliada y conservo la otra como evidencia local.', 'previous_ids': [],
    'variants': [{'id': selected, 'revision': 2, 'base_version': None, 'server_id': None, 'entry': snapshot},
                 {'id': other, 'revision': 1, 'base_version': None, 'server_id': None, 'entry': alternative}],
}]}
payload = {**body, 'device_id': device, 'entity_id': None, 'base_version': None, 'local_selection': packet}
key = str(uuid.uuid4())
entry = client.request(base + '/offline/sync', payload, author, headers={'Idempotency-Key': key})['data']
assert entry['status'] == 'draft' and entry['reference'] is None
assert entry['total_debit'] == '75.20000000'
assert entry['local_selection_receipts'][0]['selection_id'] == decision
same = client.request(base + '/offline/sync', payload, author, headers={'Idempotency-Key': key})['data']
assert same == entry
receipt = client.request(base + '/operations/offline.sync/' + key, actor=author)['data']
assert receipt['request']['local_selection']['decisions'][0]['variants'][1]['entry']['lines'][0]['debit'] == '9007199254740993.12345678'
checks.append('la elección y ambas alternativas se confirman con el borrador; reintentar conserva la misma auditoría y no contabiliza')

duplicate = client.request(base + '/offline/sync', payload, author, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=409)
assert duplicate['code'] == 'LOCAL_ENTRY_EXISTS' and duplicate['details']['current']['id'] == entry['id']
checks.append('otra identidad de transporte no duplica el comprobante lógico elegido')

server = client.request(base + '/entries/' + entry['id'], {**body, 'concept': 'Cambio posterior en SAB'}, reviewer, method='PATCH', headers={'If-Match': str(entry['version'])})['data']
resolved = client.request(base + '/entries/' + entry['id'] + '/resolve', {
    'base_version': 1, 'decision': 'keep_server', 'reason': 'Conservo el cambio de SAB tras contrastarlo con la elección local.',
    'local': {**body, 'local_selection': packet},
}, author, headers={'If-Match': str(server['version']), 'Idempotency-Key': str(uuid.uuid4())})['data']
assert resolved['entity']['version'] == server['version'] and resolved['entity']['concept'] == server['concept']
assert resolved['resolution']['local_selection_receipts'] == entry['local_selection_receipts']
checks.append('comparar con SAB conserva la misma elección local sin volver a registrarla ni cambiar el asiento al conservar servidor')

tampered = deepcopy(packet)
tampered['decisions'][0]['reason'] = 'Un texto modificado intenta reutilizar la identidad ya confirmada.'
failed = client.request(base + '/entries/' + entry['id'] + '/resolve', {
    'base_version': server['version'], 'decision': 'apply_local', 'reason': 'El escenario debe rechazar reemplazar la decisión anterior.',
    'local': {**body, 'concept': 'Cambio que debe revertirse por conflicto de evidencia', 'local_selection': tampered},
}, author, headers={'If-Match': str(server['version']), 'Idempotency-Key': str(uuid.uuid4())}, expected=409)
assert failed['code'] == 'IDEMPOTENCY_CONFLICT'
current = client.request(base + '/entries/' + entry['id'], actor=author)['data']
assert current['version'] == server['version'] and current['concept'] == server['concept']
checks.append('alterar una elección confirmada revierte toda la solicitud y conserva los datos vigentes')

wrong = deepcopy(payload)
wrong['local_selection']['decisions'][0]['client_id'] = str(uuid.uuid4())
failed = client.request(base + '/offline/sync', wrong, author, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=422)
assert failed['code'] == 'LOCAL_SELECTION_SCOPE'
checks.append('una elección de otra identidad local se rechaza antes de escribir')

client.root.joinpath('local-selections-fixture.json').write_text(json.dumps({'company_id': company['id'], 'company_name': company['name'], 'entry_id': entry['id'], 'logical_id': logical, 'decision_id': decision, 'date': today}, indent=2))
result = {'passed': len(checks), 'checks': checks}
client.root.joinpath('local-selections-http-tests.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result, ensure_ascii=False, indent=2))
