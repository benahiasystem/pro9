const {test, before} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
let update;
before(async () => {
    const source = fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/helpers/fiscal-operation.js'), 'utf8');
    update = (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).updateFiscalProfileEstimate;
});
test('confirmed response refreshes only its profile and repeated response does not advance it', () => {
    const profiles = [{id: 1, next_number: 2}, {id: 2, next_number: 10}];
    const response = {profile_id: 1, next_number: 4};
    const result = update(profiles, response);
    assert.deepEqual(result, [{id: 1, next_number: 4}, profiles[1]]);
    assert.deepEqual(update(result, response), result);
    assert.equal(profiles[0].next_number, 2);
});
test('late response cannot move the displayed estimate backwards', () => {
    assert.equal(update([{id: 1, next_number: 8}], {profile_id: 1, next_number: 7})[0].next_number, 8);
});
test('missing and invalid receipt does not invent a number', () => {
    const profiles = [{id: 1, next_number: 2}];
    for (const receipt of [null, {}, {profile_id: 1, next_number: 0}, {profile_id: 1, next_number: 'invalid'}]) {
        assert.strictEqual(update(profiles, receipt), profiles);
    }
});
