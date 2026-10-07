// ######## INICIO PRUEBAS API BCV ########
const assert = require('node:assert/strict');
const { resolve } = require('node:path');
const { execFileSync } = require('node:child_process');
const php = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$token = config('services.bcv.token');
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) throw new RuntimeException('Token no configurado');
function requestBcv($path, $expected, $token = null, $method = 'GET') {
    $curl = curl_init('http://api-bcv:3000'.$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $token === null ? [] : ['Authorization: Bearer '.$token]]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException('Fallo de conexión');
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($status !== $expected) throw new RuntimeException('HTTP inesperado: '.$status);
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
$health = requestBcv('/health', 200);
if (($health['status'] ?? null) !== 'ok') throw new RuntimeException('Salud inválida');
requestBcv('/', 401);
requestBcv('/', 401, str_repeat('0', 64));
requestBcv('/login', 404, null, 'POST');
echo json_encode(requestBcv('/', 200, $token), JSON_THROW_ON_ERROR);
`;
try {
    const output = execFileSync('docker', ['compose', 'exec', '-T', 'php', 'php', '-r', php], {
        cwd: resolve(__dirname, '..'), encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'pipe'],
    });
    const rates = JSON.parse(output);
    for (const key of ['euro', 'dolar']) {
        assert.equal(typeof rates[key], 'string');
        assert.match(rates[key], /^\d+\.\d{8}$/);
        assert.ok(/[1-9]/.test(rates[key]));
    }
    console.log(JSON.stringify({ origin: 'pro9-php', queriedAt: new Date().toISOString(), ...rates }));
    console.log('OK: token fijo, rechazos 401, login retirado, salud y consulta real.');
} catch {
    console.error('Falló la prueba API BCV. Revisar configuración, salud y conectividad; no se muestran secretos.');
    process.exitCode = 1;
}
// ######## FIN PRUEBAS API BCV ########
