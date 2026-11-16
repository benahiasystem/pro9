"""Private fixture access. Never target the user's running SAB installation."""
import fcntl
import base64
import hashlib
import hmac
import json
import os
from pathlib import Path
import struct
import subprocess
import time
import urllib.error
import urllib.request


class IsolatedClient:
    def __init__(self):
        self.root = Path(os.environ.get('ACCOUNTING_RUNTIME_DIR', '.accounting-isolated')).resolve()
        assert self.root.name == '.accounting-isolated'
        label = subprocess.check_output(['docker', 'inspect', '--format', '{{index .Config.Labels "contabilidad.goal"}}', 'contabilidad-api-v1-web'], text=True).strip()
        assert label == 'api-v1', 'El contenedor debe ser el clon aislado.'
        self._lock = (self.root / 'fixture.lock').open('a')
        fcntl.flock(self._lock, fcntl.LOCK_EX)
        self.actors = json.loads((self.root / 'clone-access.json').read_text())
        self.request('/api/accounting/v1/health', expected=401)

    def persist(self):
        path = self.root / 'clone-access.json'
        path.write_text(json.dumps(self.actors, indent=2))
        path.chmod(0o600)

    def request(self, path, data=None, actor=None, *, expected=200, method=None, headers=None):
        h = {'Host': 'contabilidad.localhost:9080', 'Accept': 'application/json'}
        if actor:
            h['Authorization'] = 'Bearer ' + actor['token']
            if actor.get('verification'):
                h['X-Accounting-Verification'] = actor['verification']
        h.update(headers or {})
        if data is not None:
            h['Content-Type'] = 'application/json'
        req = urllib.request.Request('http://127.0.0.1:9080' + path, data=json.dumps(data).encode() if data is not None else None, headers=h, method=method)
        try:
            response = urllib.request.urlopen(req, timeout=45)
        except urllib.error.HTTPError as error:
            response = error
        assert response.headers.get('X-Accounting-Isolated-Test') == '1' if path.startswith('/api/accounting/') else True, 'El servidor no acredita ser el clon aislado.'
        body = json.loads(response.read())
        assert response.status == expected, f'{path}: HTTP {response.status}, esperado {expected}; {body.get("code")}: {body.get("message")}; campos: {list(body.get("errors", {}))}'
        return body

    def login(self, actor):
        auth = self.request('/api/login', {'email': actor['email'], 'password': actor['password']})
        assert auth['success'] and auth['token']
        actor['token'] = auth['token']
        actor.pop('verification', None)
        self.persist()
        code = actor['recovery_codes'].pop(0)
        self.persist()
        verified = self.request('/api/accounting/v1/auth/mfa/verify', {'code': code}, actor)
        actor['verification'] = verified['verification_token']
        self.persist()
        if len(actor['recovery_codes']) < 4:
            enrollment = self.request('/api/accounting/v1/auth/mfa/enroll', {'password': actor['password']}, actor)
            secret = base64.b32decode(enrollment['secret'] + '=' * ((8 - len(enrollment['secret']) % 8) % 8))
            digest = hmac.new(secret, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
            offset = digest[-1] & 15
            code = str((int.from_bytes(digest[offset:offset + 4], 'big') & 0x7fffffff) % 1000000).zfill(6)
            confirmed = self.request('/api/accounting/v1/auth/mfa/confirm', {'code': code}, actor)
            actor['verification'] = confirmed['verification_token']
            actor['recovery_codes'] = confirmed['recovery_codes']
            self.persist()
