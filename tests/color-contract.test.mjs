import assert from 'node:assert/strict'; import fs from 'node:fs'; import test from 'node:test';
const css = fs.readFileSync(new URL('../assets/public.css', import.meta.url), 'utf8');
test('alerts consume the shared brand and status contract', () => { for (const role of ['primary','surface','foreground','text-muted','border','focus','info','success','warning','danger']) assert.match(css, new RegExp(`--mc-color-${role}`)); assert.doesNotMatch(css, /sanctuary-burgundy/); });
