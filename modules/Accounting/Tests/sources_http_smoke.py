"""Historical evidence and explicit source decisions through the real SAB API."""
from isolated_http import IsolatedClient
import json
import subprocess
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author); client.login(reviewer)
prepared = subprocess.run(['docker', 'exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_sources_fixture.php', 'http'], capture_output=True, text=True)
assert prepared.returncode == 0 and 'Fixture de origen ficticio preparada' in prepared.stdout, prepared.stdout + prepared.stderr
fixture = json.loads((client.root / 'sources-http-fixture.json').read_text())
assert fixture['company_name'].startswith('Origen ficticio HTTP ')
base = '/api/accounting/v1/companies/' + fixture['company_id']
foreign = '/api/accounting/v1/companies/' + fixture['foreign_company_id']
checks = []
def request(path, data=None, actor=reviewer, **kwargs):
    return client.request(base + path, data, actor, **kwargs)

case = fixture['cases']['monetary']
original = request('/documents/' + case['document_id'] + '/revisions/1')['data']
observed = request('/documents/' + case['document_id'] + '/revisions/2')['data']
assert original['integrity']['state'] == observed['integrity']['state'] == 'verified'
assert original['summary']['amounts']['total'] == '1160.000000000000'
assert observed['summary']['amounts']['total'] == '2320.000000000000'
assert original['posted_entries'][0]['id'] == case['entry_id'] and not observed['posted_entries']
assert request('/documents/' + case['document_id'] + '/versions')['meta']['total'] == 2
checks.append('Versiones originales y observadas íntegras, importes exactos y vínculo al asiento de su revisión')

review = request('/sab/incidents/' + case['incident_id'])['data']
assert review['document']['source_revision'] == 1 and review['observed']['revision'] == 2
assert json.loads(review['registered_snapshot_json'])['amounts']['total'] == '1160.000000000000'
assert not review['actions']['apply_after_reversal'] and review['ledger'][0]['active_effect']
payload = {'source_version_id': review['observed']['id'], 'document_version': review['document']['version'], 'reason': 'Revisión ficticia del cambio con sus antecedentes conservados.', 'action': 'apply_after_reversal'}
headers = {'If-Match': str(review['incident']['version']), 'Idempotency-Key': str(uuid.uuid4())}
assert request('/sab/incidents/' + case['incident_id'] + '/resolve', payload, headers=headers, expected=409)['code'] == 'SOURCE_EFFECTS_ACTIVE'
checks.append('Una corrección monetaria conserva los valores anteriores y exige reversos contabilizados')

request('/documents/' + case['document_id'] + '/revisions/1', actor=author, expected=403)
request('/sab/incidents/' + case['incident_id'], actor=author, expected=403)
client.request(foreign + '/sab/incidents/' + case['incident_id'], actor=reviewer, expected=404)
client.request(foreign + '/documents/' + case['document_id'] + '/revisions/1', actor=reviewer, expected=404)
checks.append('Permiso de auditoría y propiedad de empresa comprobados en el servidor')

case = fixture['cases']['metadata']; review = request('/sab/incidents/' + case['incident_id'])['data']
before = request('/documents/' + case['document_id'])['data']; before_entry = request('/entries/' + case['entry_id'])['data']
payload = {'source_version_id': review['observed']['id'], 'document_version': review['document']['version'], 'reason': 'Reconocer una actualización ficticia sin cambiar el documento registrado.', 'action': 'acknowledge_metadata'}
key = str(uuid.uuid4()); headers = {'If-Match': str(review['incident']['version']), 'Idempotency-Key': key}
unverified = {k: v for k, v in reviewer.items() if k != 'verification'}
assert request('/sab/incidents/' + case['incident_id'] + '/resolve', payload, actor=unverified, headers=headers, expected=403)['code'] == 'MFA_REQUIRED'
checks.append('Confirmación crítica exige MFA vigente además del login de SAB')

first = request('/sab/incidents/' + case['incident_id'] + '/resolve', payload, headers=headers)['data']
again = request('/sab/incidents/' + case['incident_id'] + '/resolve', payload, headers=headers)['data']
assert first['resolution']['id'] == again['resolution']['id']
assert first['resolution']['integrity'] == 'verified' and first['resolution']['company_id'] == fixture['company_id']
receipt = request('/operations/source.resolve/' + key)['data']
assert receipt['status'] == 'confirmed' and receipt['result']['resolution']['id'] == first['resolution']['id']
after = request('/documents/' + case['document_id'])['data']
assert all(before[field] == after[field] for field in ['version', 'source_revision', 'amounts', 'status'])
assert request('/entries/' + case['entry_id'])['data'] == before_entry
assert request('/documents/' + case['document_id'] + '/revisions/1')['data']['resolutions'][0]['id'] == first['resolution']['id']
checks.append('Resolución idempotente y recibo recuperable conservan el documento y asiento definitivos')

request('/operations/source.resolve/' + key, actor=author, expected=403)
assert client.request(foreign + '/operations/source.resolve/' + key, actor=reviewer)['data']['status'] == 'not_found'
checks.append('Recibos históricos protegidos por cuenta y empresa actuales')

case = fixture['cases']['missing']; before = request('/documents/' + case['document_id'])['data']
missing = request('/sab/sources/sales/' + str(case['native_id']) + '/receive', {})
again = request('/sab/sources/sales/' + str(case['native_id']) + '/receive', {})
assert missing['requires_reconciliation'] and again['unchanged'] and missing['incident_id'] == again['incident_id']
review = request('/sab/incidents/' + missing['incident_id'])['data']
assert json.loads(review['observed_snapshot_json'])['availability'] == 'missing'
assert not any(review['actions'].values()) and review['projection_issues']
after = request('/documents/' + case['document_id'])['data']
assert all(before[field] == after[field] for field in ['version', 'source_revision', 'amounts', 'status'])
checks.append('Origen ausente observado una sola vez sin inferir anulación ni cambiar el asiento')
assert request('/sab/status')['data']['pending'] == 2
checks.append('Pendientes incluyen documentos contabilizados con incidencias sin duplicar el conteo')

result = {'checks': len(checks), 'passed': checks, 'isolated': True, 'privatePro9Clone': True}
(client.root / 'sources-http-tests.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps(result, ensure_ascii=False))
