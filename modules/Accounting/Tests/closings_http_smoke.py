"""Monthly close acceptance against fake books inside the private isolated SAB clone."""
from isolated_http import IsolatedClient
from concurrent.futures import ThreadPoolExecutor
import datetime
import json
import time
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
year = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4))).year - 1
organization = client.request('/api/accounting/v1/organizations', actor=reviewer)['data'][0]
checks = []


def new_book(name):
    company = client.request('/api/accounting/v1/companies', {
        'organization_id': organization['id'], 'name': name + ' ' + str(time.time_ns()),
        'tax_id': 'J-' + str(time.time_ns() % 100000000).zfill(8) + '-0',
        'initial_year': year, 'tax_profile': 'ordinary', 'approval_mode': 'self_control',
    }, reviewer)['data']
    base = '/api/accounting/v1/companies/' + company['id']
    members = client.request(base + '/users', actor=reviewer)['data']
    membership = next(m for m in members if m['user_id'] == reviewer['id'])
    client.request(base + '/users', {'user_id': reviewer['id'], 'roles': ['admin', 'accountant'], 'active': True, 'reason': 'Responsabilidades expresas de las pruebas aisladas de cierre.'}, reviewer, headers={'If-Match': str(membership['version'])})
    return company, base


def close_input(preview):
    return {'review_token': preview['review_token'], 'reason': 'Cierre ficticio con papeles de trabajo revisados.',
            'attestations': {k: 'Conciliación ficticia revisada en papel de trabajo.' for k in preview['required_attestations']},
            'exceptions': {k: 'Tratamiento ficticio de los pendientes documentado y revisado.' for k in preview['pending']}}


company, base = new_book('Cierres ficticios HTTP')
client.request(base + '/users', {'user_id': author['id'], 'roles': ['auxiliary'], 'active': True, 'reason': 'Autor de borradores sin facultad de cierre para la prueba aislada.'}, reviewer)
periods = client.request(base + '/periods?year=' + str(year), actor=author)['data']['periods']
january, february = periods[0], periods[1]
accounts = {a['code']: a for a in client.request(base + '/catalogs/accounts', actor=author)['data']}
entry = client.request(base + '/entries', {'accounting_date': str(year) + '-01-15', 'concept': 'Movimiento ficticio de cierre HTTP', 'lines': [
    {'account_id': accounts['1.1.02']['id'], 'debit': '100.25', 'credit': '0'},
    {'account_id': accounts['3.1']['id'], 'debit': '0', 'credit': '100.25'},
]}, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
entry = client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Revisión expresa del comprobante ficticio de cierre.'}, reviewer, headers={'If-Match': str(entry['version'])})['data']
preview = client.request(base + '/periods/' + january['id'] + '/review', actor=author)['data']
assert 'APPROVED_ENTRIES_PENDING' in [b['code'] for b in preview['blockers']]
def race_close():
    return client.request(base + '/periods/' + january['id'] + '/close', close_input(preview), reviewer,
                          headers={'If-Match': '1', 'Idempotency-Key': str(uuid.uuid4())}, expected=409)


def race_post():
    return client.request(base + '/entries/' + entry['id'] + '/post', {}, reviewer,
                          headers={'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())})


with ThreadPoolExecutor(max_workers=2) as pool:
    close_future = pool.submit(race_close)
    post_future = pool.submit(race_post)
    assert close_future.result()['code'] in ['CLOSE_BLOCKED', 'CLOSE_REVIEW_CHANGED']
    assert post_future.result()['data']['status'] == 'posted'
checks.append('cierre concurrente con contabilización exige revisar otra vez los pendientes o el nuevo saldo')
preview = client.request(base + '/periods/' + january['id'] + '/review', actor=author)['data']
assert not preview['blockers'] and preview['trial']['totals']['debit'] == '100.25000000'
checks.append('revisión exacta y bloqueo de comprobantes aprobados sin contabilizar')

payload = close_input(preview)
path = base + '/periods/' + january['id'] + '/close'
headers = {'If-Match': '1', 'Idempotency-Key': str(uuid.uuid4())}
assert client.request(path, payload, author, headers=headers, expected=403)['code'] == 'FORBIDDEN_ACTION'
unverified = dict(reviewer)
unverified.pop('verification', None)
assert client.request(path, payload, unverified, headers=headers, expected=403)['code'] == 'MFA_REQUIRED'
assert client.request(path, payload, reviewer, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=428)['code'] == 'VERSION_REQUIRED'
checks.append('permiso, MFA y versión exigidos por SAB para cerrar')

invalid = dict(payload)
invalid['exceptions'] = {}
assert client.request(path, invalid, reviewer, headers=headers, expected=422)['code'] == 'CLOSE_EXCEPTION_REQUIRED'
closed = client.request(path, payload, reviewer, headers=headers)['data']
assert closed['period']['status'] == 'closed' and closed['period']['version'] == 2
assert client.request(path, payload, reviewer, headers=headers)['data'] == closed
receipt = client.request(base + '/operations/period.close/' + headers['Idempotency-Key'], actor=reviewer)['data']
assert receipt['status'] == 'confirmed' and receipt['result'] == closed
saved = client.request(base + '/periods/' + january['id'] + '/history/' + closed['review_id'], actor=author)['data']
assert saved['snapshot_sha256'] == closed['snapshot_sha256']
assert saved['snapshot']['reports']['trial-balance']['totals']['debit'] == '100.25'
assert client.request(path, payload, reviewer, headers={'If-Match': '1', 'Idempotency-Key': str(uuid.uuid4())}, expected=412)['code'] == 'PERIOD_VERSION_CONFLICT'
checks.append('cierre idempotente, recibo recuperable e instantánea íntegra de informes')

reverse = client.request(base + '/entries/' + entry['id'] + '/reverse', {'date': str(year) + '-01-20', 'reason': 'Intento ficticio de reverso en período cerrado.'}, reviewer, expected=422)
assert reverse['code'] == 'PERIOD_CLOSED'
late = client.request(base + '/entries', {'accounting_date': str(year) + '-01-20', 'concept': 'Borrador ficticio recibido después del cierre', 'lines': [
    {'account_id': accounts['1.1.02']['id'], 'debit': '1.00', 'credit': '0'},
    {'account_id': accounts['3.1']['id'], 'debit': '0', 'credit': '1.00'},
]}, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
assert late['status'] == 'observed' and not late['posted_at']
checks.append('período cerrado rechaza reversos y recibe borradores como observados')

feb_review = client.request(base + '/periods/' + february['id'] + '/review', actor=author)['data']
assert not feb_review['blockers']
feb_input = close_input(feb_review)


def close_february():
    return client.request(base + '/periods/' + february['id'] + '/close', feb_input, reviewer, headers={'If-Match': '1', 'Idempotency-Key': 'concurrent-monthly-close-001'})['data']


with ThreadPoolExecutor(max_workers=2) as pool:
    results = list(pool.map(lambda _: close_february(), range(2)))
assert results[0] == results[1]
assert len(client.request(base + '/periods/' + february['id'] + '/history', actor=author)['data']) == 1
checks.append('dos cierres concurrentes con la misma identidad producen una sola revisión')

reopen_review = client.request(base + '/periods/' + january['id'] + '/reopening', actor=author)['data']
assert len(reopen_review['affected_periods']) == 2
reopened = client.request(base + '/periods/' + january['id'] + '/reopen', {'review_token': reopen_review['review_token'], 'reason': 'Reapertura ficticia para corregir soporte y revisar los meses siguientes.'}, reviewer, headers={'If-Match': '2', 'Idempotency-Key': str(uuid.uuid4())})['data']
assert [p['version'] for p in reopened['periods']] == [3, 3]
assert all(p['status'] == 'open' for p in reopened['periods'])
report = client.request(base + '/reports/trial-balance?from=' + str(year) + '-01-01&to=' + str(year) + '-02-28', actor=author)['data']
assert [p['version'] for p in report['period_versions']] == [3, 3]
assert client.request(base + '/periods/' + january['id'] + '/history/' + closed['review_id'], actor=author)['data']['snapshot_sha256'] == saved['snapshot_sha256']
checks.append('reapertura invalida cierres posteriores y conserva sus informes anteriores')

other, other_base = new_book('Cierres ficticios UI')
assert client.request(other_base + '/periods/' + january['id'] + '/history/' + closed['review_id'], actor=reviewer, expected=404)['code'] == 'NOT_FOUND'
checks.append('las revisiones de otra empresa no son accesibles cambiando identificadores')
(client.root / 'closings-fixture.json').write_text(json.dumps({'company_id': other['id'], 'company_name': other['name'], 'year': year, 'other_company_id': company['id']}, indent=2))
(client.root / 'closings-fixture.json').chmod(0o600)
result = {'passed': len(checks), 'checks': checks}
(client.root / 'closings-http-tests.json').write_text(json.dumps(result, indent=2, ensure_ascii=False))
print(json.dumps(result, ensure_ascii=False))
