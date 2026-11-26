// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { registerHooks } from 'node:module';

test('downloads the authorized SAB PDF and sends its original bytes to BúhoPrinter', async () => {
    const hook = registerHooks({
        resolve(specifier, context, nextResolve) {
            if (specifier === 'public-ip') return { url: 'data:text/javascript,export const publicIpv4 = () => null', shortCircuit: true };
            return nextResolve(specifier, context);
        },
    });
    const source = await readFile(new URL('../../resources/js/utils/buhoFunctions.js', import.meta.url), 'utf8');
    const { startConnection, buhoFetchAndPrint } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    hook.deregister();

    const bytes = Buffer.from('%PDF-1.4\nfixture de BúhoPrinter\n%%EOF');
    const url = 'https://tenant.example.test/downloads/fiscal-document';
    const fetchBeforeTest = globalThis.fetch;
    let allowed = false;
    const jobs = [];
    globalThis.fetch = async (endpoint, options) => {
        if (endpoint === 'https://localhost:8181/status') return new Response();
        if (endpoint === 'https://localhost:8181/printers') return Response.json([{ name: 'Impresora QA', is_default: true }]);
        if (endpoint === url) {
            assert.equal(options.credentials, 'include');
            return allowed ? new Response(bytes, { headers: { 'content-type': 'application/pdf' } }) : new Response(null, { status: 403 });
        }
        assert.equal(endpoint, 'https://localhost:8181/print');
        jobs.push(JSON.parse(options.body));
        return new Response();
    };
    try {
        await startConnection();
        await assert.rejects(() => buhoFetchAndPrint(url), /403/);
        assert.equal(jobs.length, 0);
        allowed = true;
        await buhoFetchAndPrint(url);
        assert.deepEqual(jobs, [{ printer: 'Impresora QA', data: bytes.toString('base64'), format: 'pdf' }]);
    } finally {
        globalThis.fetch = fetchBeforeTest;
    }
});
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
