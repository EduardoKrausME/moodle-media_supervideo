// This file is part of Moodle - http://moodle.org/
// GPL v3 or later.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../amd/src/history.js'), 'utf8');

function fixture() {
    const calls = [];
    let History;
    const result = {duration: 0, currenttime: 0, percent: 0, map: '[]'};
    const Ajax = {call: function(requests) {
        calls.push(requests[0]);
        return [Promise.resolve(result)];
    }};
    vm.runInNewContext(source, {define: (deps, factory) => { History = factory(Ajax); }});
    function root(hash, contextid = 10, ticket = 'signed') {
        return {
            dataset: {contextid: String(contextid), mediahash: hash, ticket},
            querySelector: () => ({hidden: true})
        };
    }
    return {History, calls, root};
}

test('two player instances keep separate identities and progress', async () => {
    const {History, calls, root} = fixture();
    const first = History.create(root('first', 10));
    const second = History.create(root('second', 11));
    first.attach({seek() {}});
    second.attach({seek() {}});
    first.sample(10, 100);
    assert.equal(calls.length, 2, 'no write before playback');
    first.play();
    second.play();
    first.sample(10, 100);
    second.sample(30, 200);
    await new Promise(resolve => setImmediate(resolve));
    const saves = calls.filter(call => call.methodname.endsWith('_save'));
    assert.equal(saves.length, 2);
    assert.equal(saves[0].args.mediahash, 'first');
    assert.equal(saves[0].args.currenttime, 10);
    assert.equal(saves[0].args.contextid, 10);
    assert.equal(saves[1].args.mediahash, 'second');
    assert.equal(saves[1].args.currenttime, 30);
    assert.equal(saves[1].args.contextid, 11);
});

test('missing ticket and infinite duration do not write history', () => {
    const {History, calls, root} = fixture();
    const guest = History.create(root('guest', 10, ''));
    guest.play();
    guest.sample(10, 100);
    const live = History.create(root('live', 10));
    live.play();
    live.sample(10, Infinity);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].methodname, 'media_supervideo_history_load');
});

test('disposed player does not send additional writes', () => {
    const {History, calls, root} = fixture();
    const item = History.create(root('removed'));
    item.play();
    item.stop();
    item.sample(10, 100);
    assert.equal(calls.length, 1);
});
