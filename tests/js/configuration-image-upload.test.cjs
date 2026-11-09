const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');

const source = fs.readFileSync(path.resolve(__dirname, '../../resources/js/views/system/configuration/UploadBgImage.vue'), 'utf8');
const parsed = compiler.parseComponent(source);
const moduleContext = {exports: {}};
class UploadFormData {
    constructor() { this.values = new Map(); }
    append(key, value) { this.values.set(key, value); }
    get(key) { return this.values.get(key); }
}
vm.runInNewContext(parsed.script.content.replace('export default', 'module.exports ='), {module: moduleContext, FormData: UploadFormData});
const component = moduleContext.exports;

function uploader(post) {
    const notices = [];
    const message = data => notices.push(data);
    message.error = text => notices.push({type: 'error', message: text});
    const state = Object.assign(component.data(), {type: 'bg', imageUrl: 'old.png', $http: {post}, $message: message});
    for (const [name, method] of Object.entries(component.methods)) state[name] = method.bind(state);
    return {state, notices};
}

for (const extension of ['webp', 'avif', 'AVIF']) {
    test(`${extension} upload sends the file and selects the server-confirmed preview`, async () => {
        let calls = 0;
        const file = {name: `brand.${extension}`, size: 1024};
        const {state, notices} = uploader(async (url, payload) => {
            calls++;
            assert.equal(url, '/configurations/bg');
            assert.equal(payload.get('image'), file);
            assert.equal(payload.get('type'), 'bg');
            return {data: {success: true, message: 'Guardado', url: 'saved-image.avif'}};
        });
        const event = {target: {files: [file], value: file.name}};
        await state.onGeneratePreview(event);
        assert.equal(calls, 1);
        assert.equal(state.imageUrl, 'saved-image.avif');
        assert.equal(state.loading, false);
        assert.equal(event.target.value, '');
        assert.equal(notices[0].type, 'success');
    });
}

test('oversized and unsupported files leave the old image intact without posting', async () => {
    for (const file of [{name: 'too-large.avif', size: 2097153}, {name: 'image.php', size: 1024}]) {
        const {state, notices} = uploader(() => assert.fail('Invalid files must not be posted'));
        const event = {target: {files: [file], value: file.name}};
        await state.onGeneratePreview(event);
        assert.equal(state.imageUrl, 'old.png');
        assert.equal(state.loading, false);
        assert.equal(notices[0].type, 'error');
        assert.equal(event.target.value, '');
    }
});

test('validation and network errors keep the previous preview and allow retry', async () => {
    for (const failure of [
        {response: {status: 422, data: {errors: {image: ['Archivo inválido']}}}},
        {response: {status: 422, data: {message: {image: ['Archivo inválido']}}}},
        {response: {status: 422, data: {image: ['Archivo inválido']}}},
        new Error('Connection failed'),
    ]) {
        const {state, notices} = uploader(async () => { throw failure; });
        const event = {target: {files: [{name: 'brand.webp', size: 1024}], value: 'brand.webp'}};
        await state.onGeneratePreview(event);
        assert.equal(state.imageUrl, 'old.png');
        assert.equal(state.loading, false);
        assert.equal(event.target.value, '');
        assert.equal(notices[0].type, 'error');
        assert.ok(notices[0].message.length > 0);
        if (failure.response) assert.equal(notices[0].message, 'Archivo inválido');
    }
});
