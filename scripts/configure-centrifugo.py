#!/usr/bin/env python3
"""Initialize only missing Centrifugo settings. Never print or rotate existing secrets."""
from pathlib import Path
import re
import secrets

env_path = Path(__file__).resolve().parent.parent / '.env'
text = env_path.read_text()
settings = {
    'CENTRIFUGO_URL': lambda: 'http://centrifugo:8000',
    'CENTRIFUGO_WS_URL': lambda: 'ws://ws.localhost',
    'CENTRIFUGO_API_KEY': lambda: secrets.token_hex(32),
    'CENTRIFUGO_TOKEN_HMAC_SECRET_KEY': lambda: secrets.token_hex(32),
    'CENTRIFUGO_ALLOWED_ORIGINS': lambda: '"http://*.localhost"',
}
for key, default in settings.items():
    pattern = re.compile(r'^' + re.escape(key) + r'=(.*)$', re.MULTILINE)
    match = pattern.search(text)
    if match and match.group(1).strip().strip('\"\''):
        print(key + ': conservado')
        continue
    line = key + '=' + default()
    text = pattern.sub(lambda _: line, text) if match else text.rstrip() + '\n' + line + '\n'
    print(key + ': configurado')
env_path.write_text(text)
