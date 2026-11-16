"""Annual bundle API acceptance; all records are fictitious in the private isolated clone."""
from isolated_http import IsolatedClient
import json
import subprocess
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
fixture_job = subprocess.run(['docker', 'exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_annual_fixture.php', 'http'], capture_output=True, text=True)
assert fixture_job.returncode == 0 and 'Fixture anual ficticia preparada' in fixture_job.stdout, fixture_job.stdout + fixture_job.stderr
fixture = json.loads((client.root / 'annual-http-fixture.json').read_text())
assert fixture['company_name'].startswith('Cierre anual ficticio HTTP ')
base = '/api/accounting/v1/companies/' + fixture['company_id']
year = fixture['year']
checks = []
screen = client.request(base + '/years/' + str(year) + '/closing', actor=author)['data']
basis = screen['preview']
assert not basis['review']['blockers'] and basis['opening_period'] is None
assert basis['proposal']['result'] == '750.00000000'
assert basis['proposal']['phases']['opening']['total_debit'] == '750.00000000'
payload = {'basis_hash': basis['basis_hash'], 'result_account_id': fixture['result_account_id'], 'reason': 'Cierre anual ficticio con conciliaciones y soportes revisados.',
           'attestations': {k: 'Papel anual ficticio revisado y conciliado.' for k in basis['review']['required_attestations']},
           'exceptions': {k: 'Tratamiento de las operaciones ficticias identificado en sus soportes.' for k in basis['review']['pending']}}
headers = {'If-Match': str(screen['fiscal_year']['version']), 'Idempotency-Key': str(uuid.uuid4())}
path = base + '/years/' + str(year) + '/closing/prepare'
unverified = dict(author)
unverified.pop('verification', None)
assert client.request(path, payload, unverified, headers=headers, expected=403)['code'] == 'MFA_REQUIRED'
assert client.request(path, payload, author, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=428)['code'] == 'VERSION_REQUIRED'
prepared = client.request(path, payload, author, headers=headers)['data']
assert prepared['status'] == 'prepared' and len(prepared['entries']) == 3 and prepared['basis_matches']
assert client.request(path, payload, author, headers=headers)['data'] == prepared
checks.append('revisión anual exacta, segundo factor, versión e identidad de preparación exigidos por SAB')

entry = prepared['entries']['result']
entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
assert client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Intento de aprobar la propia preparación anual.'}, author, headers={'If-Match': str(entry['version'])}, expected=422)['code'] == 'SEPARATE_APPROVAL_REQUIRED'
for phase in ['result', 'balance', 'opening']:
    entry = client.request(base + '/entries/' + prepared['entries'][phase]['id'], actor=author)['data']
    if entry['status'] in ['draft', 'observed']:
        entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
    client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Aprobación independiente del comprobante anual ficticio.'}, reviewer, headers={'If-Match': str(entry['version'])})
approved = client.request(base + '/year-closings/' + prepared['id'], actor=author)['data']
assert approved['can_post']
single = approved['entries']['opening']
assert client.request(base + '/entries/' + single['id'] + '/post', {}, reviewer, headers={'If-Match': str(single['version']), 'Idempotency-Key': str(uuid.uuid4())}, expected=409)['code'] == 'YEAR_CLOSING_BATCH_REQUIRED'
checks.append('aprobación independiente por comprobante y prohibición de contabilizar fases por separado')

post_payload = {'review_token': approved['review_token']}
post_headers = {'If-Match': str(approved['version']), 'Idempotency-Key': str(uuid.uuid4())}
post_path = base + '/year-closings/' + approved['id'] + '/post'
posted = client.request(post_path, post_payload, reviewer, headers=post_headers)['data']
assert posted['status'] == 'posted' and posted['posted_snapshot']['closing_balances_zero']
assert client.request(post_path, post_payload, reviewer, headers=post_headers)['data'] == posted
assert client.request(base + '/operations/year.close/' + post_headers['Idempotency-Key'], actor=reviewer)['data']['status'] == 'confirmed'
next_balance = client.request(base + '/reports/balance-sheet?from=' + str(year + 1) + '-01-01&to=' + str(year + 1) + '-01-31', actor=author)['data']
assert next_balance['totals']['assets'] == next_balance['totals']['equity'] == '750.00'
assert next_balance['totals']['difference'] == '0.00'
assert client.request(base + '/open-items', actor=author)['total'] == 0
checks.append('cierre y apertura atómicos e idempotentes sin duplicar saldos ni crear partidas auxiliares')

entry = posted['entries']['result']
assert client.request(base + '/entries/' + entry['id'] + '/reverse', {'date': str(year + 1) + '-01-15', 'reason': 'Intento ficticio de reversar una sola fase anual.'}, reviewer, expected=409)['code'] == 'YEAR_CLOSING_CONTROLLED'
reopening = client.request(base + '/year-closings/' + posted['id'] + '/reopening', {'reason': 'Reapertura anual ficticia para corregir un papel de trabajo.'}, author, headers={'If-Match': str(posted['version']), 'Idempotency-Key': str(uuid.uuid4())})['data']
assert reopening['status'] == 'reopening' and len(reopening['reopening_review']['entries']) == 3 and not reopening['can_reopen']
assert len(reopening['reopening_review']['affected_periods']) == 12
for phase in ['result', 'balance', 'opening']:
    entry = reopening['reopening_review']['entries'][phase]
    entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
    client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Aprobación independiente del reverso anual ficticio.'}, reviewer, headers={'If-Match': str(entry['version'])})
ready = client.request(base + '/year-closings/' + posted['id'], actor=author)['data']
assert ready['can_reopen']
checks.append('reapertura prepara reversos revisables de las tres fases y presenta todos los períodos afectados')

reopen_payload = {'review_token': ready['review_token']}
reopen_headers = {'If-Match': str(ready['version']), 'Idempotency-Key': str(uuid.uuid4())}
reopen_path = base + '/year-closings/' + ready['id'] + '/reopen'
reopened = client.request(reopen_path, reopen_payload, reviewer, headers=reopen_headers)['data']
assert reopened['status'] == 'reopened'
assert client.request(reopen_path, reopen_payload, reviewer, headers=reopen_headers)['data'] == reopened
assert reopened['posted_snapshot'] == posted['posted_snapshot']
periods = client.request(base + '/periods?year=' + str(year), actor=author)['data']['periods']
assert all(p['status'] == 'open' and p['version'] == 3 for p in periods)
after = client.request(base + '/reports/balance-sheet?from=' + str(year + 1) + '-01-01&to=' + str(year + 1) + '-01-31', actor=author)['data']
assert after['totals']['assets'] == after['totals']['unclosed_result'] == '750.00' and after['totals']['equity'] == '0.00'
assert after['totals']['difference'] == '0.00'
assert client.request(base + '/open-items', actor=author)['total'] == 0
checks.append('reapertura atómica conserva comprobantes e informes anteriores y restaura saldos sin duplicarlos')

members = client.request(base + '/users', actor=reviewer)['data']
membership = next(m for m in members if m['user_id'] == author['id'])
changed = client.request(base + '/users', {'user_id': author['id'], 'roles': ['auxiliary'], 'active': True, 'reason': 'Retiro ficticio del permiso de cierre para verificar su control en servidor.'}, reviewer, headers={'If-Match': str(membership['version'])})
try:
    assert client.request(path, payload, author, headers=headers, expected=403)['code'] == 'FORBIDDEN_ACTION'
finally:
    client.request(base + '/users', {'user_id': author['id'], 'roles': ['accountant'], 'active': True, 'reason': 'Restituir la responsabilidad contable explícita del actor de pruebas.'}, reviewer, headers={'If-Match': str(changed['version'])})
checks.append('un permiso de cierre retirado se rechaza en servidor aunque la sesión siga abierta')

other = client.request('/api/accounting/v1/companies', actor=reviewer)['data']
other_id = next(c['id'] for c in other if c['id'] != fixture['company_id'])
assert client.request('/api/accounting/v1/companies/' + other_id + '/year-closings/' + posted['id'], actor=reviewer, expected=404)['code'] == 'NOT_FOUND'
checks.append('el conjunto anual no puede consultarse desde otra empresa')
result = {'passed': len(checks), 'checks': checks}
(client.root / 'annual-http-tests.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps(result, ensure_ascii=False))
