const { nanoToSeconds } = require('../../../wwwroot/scripts/global.js');

QUnit.module('global.js');

QUnit.test('nanoToSeconds', function (assert) {
    const actual = nanoToSeconds(1705477902827283177);
    const expected = 1705477902;
    assert.equal(actual, expected);
});