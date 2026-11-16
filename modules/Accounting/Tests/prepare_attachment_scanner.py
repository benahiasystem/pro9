"""Start the owned scanner on the existing internal test network, with no host port."""
from pathlib import Path
import hashlib
import json
import subprocess
import time

root = Path.cwd()
runtime = root / '.accounting-isolated'
assert root.name == 'Pro9' and runtime.is_dir()
network = json.loads(subprocess.check_output(['docker', 'network', 'inspect', 'contabilidad-api-v1-tests'], text=True))[0]
assert network['Internal'] is True
web = json.loads(subprocess.check_output(['docker', 'inspect', 'contabilidad-api-v1-web'], text=True))[0]
assert web['Config']['Labels']['contabilidad.goal'] == 'api-v1'
image = 'clamav/clamav@sha256:45fe5149fbee5a46509c9848ef20d0c354b7b2323c9369f285939a37cf33422a'
subprocess.run(['docker', 'image', 'inspect', image], check=True, stdout=subprocess.DEVNULL)
config = runtime / 'clamd-accounting.conf'
text = '\n'.join(['Foreground yes', 'User clamav', 'TCPSocket 3310', 'TCPAddr 0.0.0.0', 'DatabaseDirectory /var/lib/clamav', 'TemporaryDirectory /tmp', 'StreamMaxLength 6M', 'MaxFileSize 10M', 'MaxScanSize 50M', 'MaxRecursion 12', 'MaxFiles 1000', 'MaxScanTime 10000', 'MaxThreads 2', 'ConcurrentDatabaseReload no', 'AlertExceedsMax yes', 'AlertEncrypted yes', 'HeuristicAlerts yes', 'ExitOnOOM yes', 'LogTime yes', ''])
config.write_text(text); config.chmod(0o644)
digest = hashlib.sha256(config.read_bytes()).hexdigest()
name = 'contabilidad-api-v1-av'
existing = subprocess.run(['docker', 'inspect', name], capture_output=True, text=True)
if existing.returncode == 0:
    container = json.loads(existing.stdout)[0]
    assert container['Config']['Labels']['contabilidad.goal'] == 'api-v1'
    assert container['Config']['Labels']['contabilidad.scanner-config'] == digest
    assert container['State']['Running'], 'Inspecciona la causa terminal antes de recrear el escáner.'
else:
    subprocess.run(['docker', 'run', '-d', '--name', name, '--label', 'contabilidad.goal=api-v1', '--label', 'contabilidad.scanner-config=' + digest,
                    '--network', 'contabilidad-api-v1-tests', '--network-alias', 'accounting-av', '--memory=4g', '--cpus=1.5', '--pids-limit=96', '--read-only',
                    '--tmpfs', '/tmp:rw,nosuid,nodev,size=128m', '--user', 'clamav', '-v', str(config) + ':/etc/clamav/clamd-accounting.conf:ro',
                    '--entrypoint', 'clamd', image, '--config-file=/etc/clamav/clamd-accounting.conf', '--foreground=true'], check=True, stdout=subprocess.DEVNULL)
probe = '$s=@stream_socket_client("tcp://accounting-av:3310",$e,$m,2); if(!$s)exit(2); stream_set_timeout($s,2); fwrite($s,"zVERSION\\0"); $r=stream_get_contents($s); fclose($s); echo trim($r,"\\0\\r\\n");'
for attempt in range(40):
    state = json.loads(subprocess.check_output(['docker', 'inspect', name], text=True))[0]
    assert state['State']['Running'], 'El proceso de análisis terminó; inspecciona su registro.'
    tested = subprocess.run(['docker', 'exec', '-u', '1000:1000', 'contabilidad-api-v1-web', 'php', '-r', probe], capture_output=True, text=True)
    if tested.returncode == 0 and tested.stdout.startswith('ClamAV '):
        assert not state['HostConfig']['PortBindings']
        result = {'image': image, 'configSha256': digest, 'version': tested.stdout.strip(), 'internalNetwork': True, 'hostPorts': False, 'state': 'ready'}
        (runtime / 'attachment-scanner-state.json').write_text(json.dumps(result, indent=2))
        print(json.dumps(result)); break
    time.sleep(3)
else:
    raise RuntimeError('El proceso sigue vivo, pero aún no responde. Vuelve a observar el mismo contenedor; no lo reinicies por el timeout.')
