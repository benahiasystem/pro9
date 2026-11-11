"""Exercise the invoice workflow through PRO9's real routing/authentication stack."""
from isolated_http import IsolatedClient
import datetime
import json
import time
import uuid

client = IsolatedClient()
author, reviewer = client.actors['autor'], client.actors['revisor']
client.login(author)
client.login(reviewer)
checks = []
organizations = client.request('/api/accounting/v1/organizations', actor=reviewer)['data']
today = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4))).date().isoformat()
year = today[:4]
company = client.request('/api/accounting/v1/companies', {
    'organization_id': organizations[0]['id'], 'name': 'Flujo documental ficticio ' + str(time.time_ns()),
    'tax_id': 'J-' + str(time.time_ns() % 100000000).zfill(8) + '-0', 'initial_year': int(year), 'tax_profile': 'ordinary', 'approval_mode': 'separate',
}, reviewer)['data']
base = '/api/accounting/v1/companies/' + company['id']
members = client.request(base + '/users', actor=reviewer)['data']
self_membership = next(m for m in members if m['user_id'] == reviewer['id'])
client.request(base + '/users', {'user_id': reviewer['id'], 'roles': ['admin', 'accountant'], 'active': True, 'reason': 'Responsabilidades explícitas para el escenario documental aislado.'}, reviewer, headers={'If-Match': str(self_membership['version'])})
client.request(base + '/users', {'user_id': author['id'], 'roles': ['accountant'], 'active': True, 'reason': 'Preparación de documentos ficticios en una empresa de prueba aislada.'}, reviewer)
checks.append('empresa ficticia con responsabilidades expresas y revisión separada')
profile = client.request(base + '/tax/profiles', {'profile': 'ordinary', 'effective_from': year + '-01-01', 'source_reference': 'Caso didáctico aislado, sin valor de configuración normativa.', 'details': {'input_tax_treatment': 'deductible', 'spe': False}}, reviewer)['data']
rule_data = {'code': 'IVA-PRUEBA', 'name': 'Regla didáctica de prueba', 'tax': 'IVA', 'scenario': 'invoice', 'rate_percent': '16', 'effective_from': year + '-01-01', 'source_reference': 'Porcentaje ficticio del escenario, no alícuota predeterminada.'}
rule = client.request(base + '/tax/rules', rule_data, reviewer)['data']
rule = client.request(base + '/tax/rules/' + rule['id'] + '/approve', {}, reviewer, headers={'If-Match': str(rule['version'])})['data']
party = client.request(base + '/catalogs/third-parties', {'tax_id': 'J-11111111-1', 'name': 'Cliente ficticio original', 'kind': 'customer'}, reviewer)['data']
chart = {a['code']: a for a in client.request(base + '/catalogs/accounts', actor=author)['data']}
payload = {'kind': 'sale_invoice', 'number': 'FV-000001', 'fiscal_control': '00-000001', 'issue_date': today, 'fiscal_date': today, 'third_party_id': party['id'], 'currency': 'VES', 'exchange_rate': '1', 'exchange_source': 'Moneda del libro', 'exchange_date': today, 'amounts': {'base': '1000.00', 'vat': '160.00', 'other_taxes': '0.00', 'total': '1160.00'}, 'tax_components': [{'tax': 'IVA', 'rule_id': rule['id'], 'base': '1000.00', 'amount': '160.00'}]}
key = str(uuid.uuid4())
document = client.request(base + '/documents', payload, author, headers={'Idempotency-Key': key})['data']
same = client.request(base + '/documents', payload, author, headers={'Idempotency-Key': key})['data']
assert same['id'] == document['id']
duplicate = client.request(base + '/documents', payload, author, headers={'Idempotency-Key': str(uuid.uuid4())}, expected=409)
assert duplicate['code'] == 'POSSIBLE_DUPLICATE_DOCUMENT'
checks.append('borrador idempotente y rechazo de duplicados entre reintentos diferentes')
stale = client.request(base + '/documents/' + document['id'] + '/validate', {}, author, headers={'If-Match': str(document['version'] + 1)}, expected=412)
assert stale['code'] == 'VERSION_CONFLICT'
document = client.request(base + '/documents/' + document['id'] + '/validate', {}, author, headers={'If-Match': str(document['version'])})['data']
assert document['metadata']['validation']['profile']['id'] == profile['id']
checks.append('validación versionada con perfil y regla fiscal conservados')
selected = {name: chart[code]['id'] for name, code in {'counterparty': '1.1.03', 'base': '4.1', 'vat': '2.1.02', 'rounding': '6.4'}.items()}
entry = client.request(base + '/documents/' + document['id'] + '/propose', {'accounting_date': today, 'accounts': selected}, author, headers={'If-Match': str(document['version'])})['data']
assert entry['total_debit'] == entry['total_credit'] == '1160.00000000'
entry = client.request(base + '/entries/' + entry['id'] + '/submit', {}, author, headers={'If-Match': str(entry['version'])})['data']
entry = client.request(base + '/entries/' + entry['id'] + '/approve', {'comment': 'Revisión independiente de la factura didáctica.'}, reviewer, headers={'If-Match': str(entry['version'])})['data']
headers = {'If-Match': str(entry['version']), 'Idempotency-Key': str(uuid.uuid4())}
posted = client.request(base + '/entries/' + entry['id'] + '/post', {}, reviewer, headers=headers)['data']
retry = client.request(base + '/entries/' + entry['id'] + '/post', {}, reviewer, headers=headers)['data']
assert posted['reference'] == retry['reference'] and posted['status'] == 'posted'
checks.append('propuesta explicada, revisión separada y una sola contabilización al reintentar')
client.request(base + '/catalogs/third-parties/' + party['id'], {'tax_id': party['tax_id'], 'name': 'Nombre posterior del cliente ficticio', 'kind': 'customer', 'active': True}, reviewer, method='PATCH')
register = client.request(base + '/tax/registers?from=' + year + '-01-01&to=' + today + '&side=sales', actor=reviewer)['data']
section = register['sections'][0]
assert section['profile'] == 'ordinary' and section['totals']['total'] == '1160.00'
assert section['rows'][0]['party']['name'] == 'Cliente ficticio original'
assert section['rows'][0]['document_id'] == document['id']
checks.append('registro fiscal conciliado que conserva la identidad histórica del tercero')
client.root.joinpath('documents-fixture.json').write_text(json.dumps({'company_id': company['id'], 'company_name': company['name'], 'document_id': document['id'], 'entry_id': entry['id'], 'rule_id': rule['id'], 'party_id': party['id']}, indent=2))
result = {'passed': len(checks), 'checks': checks}
client.root.joinpath('documents-http-tests.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result, ensure_ascii=False, indent=2))
