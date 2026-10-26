const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function row(price, currency, max) {
	const input = { value: '0', max: String(max), disabled: false };
	return {
		input,
		getAttribute(name) { return { 'data-price': price, 'data-currency': currency }[name]; },
		querySelector() { return input; }
	};
}

const first = row('2500.00', 'ARS', 3);
const second = row('1000.00', 'ARS', 3);
const rows = [first, second];
const total = { textContent: '' };
const feedback = { textContent: '' };
const button = { disabled: false };
const listeners = {};
const form = {
	getAttribute() { return '3'; },
	querySelectorAll() { return rows; },
	querySelector(selector) {
		return {
			'[data-casa-ticket-total]': total,
			'[data-casa-ticket-feedback]': feedback,
			'button[type="submit"]': button
		}[selector];
	},
	addEventListener(event, listener) { listeners[event] = listener; }
};
const document = {
	readyState: 'complete',
	querySelectorAll() { return [form]; }
};
const script = fs.readFileSync(path.join(__dirname, '..', 'assets', 'public', 'ticket-selector.js'), 'utf8');
vm.runInNewContext(script, { document, Intl, Number });
assert.equal(button.disabled, true, 'zero quantity cannot proceed');
first.input.value = '2';
listeners.input();
assert.match(total.textContent, /ARS.*5.000,00/, 'two ARS tickets show their total');
assert.equal(button.disabled, false, 'valid selection can proceed');
second.input.value = '2';
listeners.input();
assert.equal(button.disabled, true, 'combined selection respects global capacity');
assert.match(feedback.textContent, /aforo/, 'global limit is explained');
second.input.value = '1';
second.getAttribute = name => ({ 'data-price': '1000.00', 'data-currency': 'USD' })[name];
listeners.change();
assert.equal(button.disabled, true, 'mixed currencies cannot proceed');
assert.match(feedback.textContent, /moneda/, 'currency mismatch is explained');
second.input.value = '0';
first.input.value = '4';
listeners.input();
assert.equal(button.disabled, true, 'per-type maximum cannot proceed');
console.log('ticket selector: 7 assertions passed');