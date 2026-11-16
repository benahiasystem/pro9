from isolated_http import IsolatedClient
import datetime
import json
import time
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
today = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4))).date()
date, before = today.isoformat(), (today - datetime.timedelta(days=1)).isoformat()
organization = client.request('/api/accounting/v1/organizations', actor=reviewer)['data'][0]['id']


def company_fixture(label):
    company = client.request('/api/accounting/v1/companies', {'organization_id': organization, 'name': label + ' ' + str(time.time_ns()), 'tax_id': 'J-' + str(time.time_ns() % 100000000).zfill(8) + '-0', 'initial_year': today.year, 'tax_profile': 'ordinary', 'approval_mode': 'separate'}, reviewer)['data']
    base = '/api/accounting/v1/companies/' + company['id']
    member = next(m for m in client.request(base + '/users', actor=reviewer)['data'] if m['user_id'] == reviewer['id'])
    client.request(base + '/users', {'user_id': reviewer['id'], 'roles': ['admin', 'accountant'], 'active': True, 'reason': 'Revisión expresa de apertura y auxiliares del caso aislado.'}, reviewer, headers={'If-Match': str(member['version'])})
    client.request(base + '/users', {'user_id': author['id'], 'roles': ['accountant'], 'active': True, 'reason': 'Preparación expresa de operaciones ficticias de auxiliares.'}, reviewer)
    party = client.request(base + '/catalogs/third-parties', {'tax_id': 'J-11111111-1', 'name': 'Cliente ficticio de migración', 'kind': 'both'}, reviewer)['data']
    chart = {a['code']: a for a in client.request(base + '/catalogs/accounts', actor=author)['data']}
    document = client.request(base + '/documents', {'kind': 'sale_invoice', 'number': 'FV-HISTORICA-1', 'issue_date': before, 'fiscal_date': before, 'third_party_id': party['id'], 'currency': 'USD', 'exchange_rate': '40', 'exchange_source': 'Tasa didáctica histórica', 'exchange_date': before, 'amounts': {'base': '100', 'vat': '0', 'other_taxes': '0', 'total': '100'}}, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
    return {'company_id': company['id'], 'company_name': company['name'], 'base': base, 'party_id': party['id'], 'document_id': document['id'], 'chart': chart, 'before': before, 'date': date}


def post(entry, base):
    entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
    entry = client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Revisión independiente del caso de auxiliares.'}, reviewer, headers={'If-Match': str(entry['version'])})['data']
    headers = {'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())}
    posted = client.request(base + '/entries/' + entry['id'] + '/post', {}, reviewer, headers=headers)['data']
    retry = client.request(base + '/entries/' + entry['id'] + '/post', {}, reviewer, headers=headers)['data']
    assert posted['reference'] == retry['reference']
    return posted


fixture = company_fixture('Auxiliares HTTP ficticios')
base, chart = fixture['base'], fixture['chart']
opening = {'cutoff_date': date, 'reason': 'Arranque ficticio a partir de un saldo anterior conciliado.', 'evidence_reference': 'Balance anterior ficticio de USD 100 y VES 4000.', 'balances': [{'account_id': chart['1.1.03']['id'], 'debit': '4000.00', 'credit': '0.00'}, {'account_id': chart['3.1']['id'], 'debit': '0.00', 'credit': '4000.00'}], 'open_items': [{'account_id': chart['1.1.03']['id'], 'third_party_id': fixture['party_id'], 'source_document_id': fixture['document_id'], 'reference': 'FV-HISTORICA-1', 'issue_date': before, 'currency': 'USD', 'original_amount': '100', 'book_amount': '4000.00', 'exchange_rate': '40', 'exchange_date': before, 'exchange_source': 'Tasa ficticia histórica', 'evidence_reference': 'Saldo anterior ficticio conciliado.'}]}
entry = client.request(base + '/opening/propose', opening, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
assert client.request(base + '/open-items', actor=author)['total'] == 0
post(entry, base)
same = client.request(base + '/opening/propose', opening, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
assert same['id'] == entry['id']
items = client.request(base + '/open-items', actor=author)['data']
assert len(items) == 1 and items[0]['is_opening'] and items[0]['remaining_original'] == '100.000000000000'
checks = ['apertura revisada sin efectos antes de contabilizar ni duplicados al reintentar']
document = client.request(base + '/documents/' + fixture['document_id'], actor=author)['data']
assert document['status'] == 'opening_antecedent'
blocked = client.request(base + '/documents/' + document['id'] + '/propose', {'accounting_date': date, 'accounts': {}}, author, headers={'If-Match': str(document['version'])}, expected=422)
assert blocked['code'] == 'OPENING_ANTECEDENT'
checks.append('factura anterior al inicio enlazada sin reconocer otra venta')
payload = {'direction': 'receipt', 'accounting_date': date, 'reference': 'RC-HTTP-PARCIAL', 'third_party_id': fixture['party_id'], 'cash_account_id': chart['1.1.02']['id'], 'currency': 'USD', 'amount': '40', 'exchange_rate': '42', 'exchange_date': date, 'exchange_source': 'Tasa didáctica de liquidación', 'evidence_reference': 'Comprobante bancario ficticio', 'rate_reason': 'Diferencia didáctica respecto del saldo histórico.', 'tax_review_reference': 'Revisión documentada del caso ficticio, sin inferir una exención.', 'gain_account_id': chart['4.2']['id'], 'loss_account_id': chart['6.2']['id'], 'applications': [{'open_item_id': items[0]['id'], 'original_amount': '40'}]}
entry = client.request(base + '/settlements/propose', payload, author, headers={'Idempotency-Key': str(uuid.uuid4())})['data']
post(entry, base)
item = client.request(base + '/open-items/' + items[0]['id'], actor=author)['data']
assert item['remaining_original'] == '60.000000000000' and item['remaining_book'] == '2400.00000000'
assert len(item['allocations']) == 1 and item['allocations'][0]['exchange_difference'] == '80.00000000'
income = client.request(base + '/reports/income-statement?from=' + date + '&to=' + date, actor=author)['data']
assert income['totals']['result'] == '80.00' and not any(row['code'] == '4.1' for row in income['rows'])
checks.append('cobro parcial con diferencia de cambio y sin repetir el ingreso histórico')
foreign = client.request('/api/accounting/v1/companies/' + company_fixture('Aislamiento de auxiliares')['company_id'] + '/open-items/' + item['id'], actor=author, expected=404)
assert foreign['code'] == 'NOT_FOUND'
checks.append('partidas inaccesibles desde otro libro contable')
account = chart['1.1.03']
blocked = client.request(base + '/catalogs/accounts/' + account['id'], {**{key: account[key] for key in ['code', 'name', 'parent_id', 'kind', 'nature', 'posting', 'active', 'requires_party']}, 'auxiliary': None}, reviewer, method='PATCH', expected=422)
assert blocked['code'] == 'ACCOUNT_USED'
blocked = client.request(base + '/catalogs/third-parties/' + fixture['party_id'], {'tax_id': 'J-22222222-2', 'name': 'Otra entidad distinta', 'kind': 'both'}, reviewer, method='PATCH', expected=422)
assert blocked['code'] == 'PARTY_IDENTITY_USED'
checks.append('clasificación del auxiliar e identificación del tercero protegidas tras contabilizar')
ui = company_fixture('Auxiliares UI ficticios')
client.root.joinpath('auxiliary-fixture.json').write_text(json.dumps({'http': fixture, 'ui': ui}, indent=2))
result = {'passed': len(checks), 'checks': checks}
client.root.joinpath('auxiliary-http-tests.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps(result, ensure_ascii=False, indent=2))
