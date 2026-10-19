/* Focused E3.2 DOM/media stand-ins. Native picker behavior needs browser QA. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/admin/operational.js'), 'utf8');
let passed = 0;
function test(name, fn) { fn(); passed++; console.log('PASS ' + name); }
function element(value = '') {
	return { value, hidden: false, children: [], listeners: {}, attributes: {},
		addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); },
		emit(name, event = {}) { event.defaultPrevented ||= false; event.preventDefault ||= function () { this.defaultPrevented = true; }; (this.listeners[name] || []).forEach(fn => fn(event)); return event; },
		dispatchEvent(event) { this.emit(event.type, event); },
		replaceChildren() { this.children = []; },
		appendChild(child) { this.children.push(child); },
		setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; }, getAttribute(name) { return this.attributes[name] ?? null; }
	};
}
function setup({ media = true, mode = 'whatsapp', protectedContent = false, pendingContent = false } = {}) {
	const input = element('7'), preview = element(), choose = element(), clear = element();
	const poster = { querySelector(selector) { return ({ 'input[name="fields[poster_id]"]': input, '[data-poster-preview]': preview, '[data-poster-select]': choose, '[data-poster-clear]': clear })[selector]; } };
	const access = element(mode), url = element(), tickets = element(), editorial = element(), action = element(), commercial = element();
	const controls = { '[data-poster]': poster, '#e32-access_mode': access, '[data-external-url]': url, '[data-tickets-note]': tickets, '.casa-operational-editorial': editorial };
	const content = element(); if (!protectedContent) { content.attributes.name = 'fields[content]'; } editorial.querySelector = () => protectedContent ? null : content;
	editorial.attributes['data-content-pending'] = pendingContent ? '1' : '0';
	const root = { querySelector(selector) { return controls[selector]; }, querySelectorAll(selector) { return selector === 'form' ? [editorial, action] : [action]; } };
	let frameCount = 0, selected, selection, config;
	const frame = { openCount: 0, open() { this.openCount++; }, on(name, callback) { selected = callback; }, state() { return { get() { return { first() { return { toJSON() { return selection; } }; } }; } }; } };
	const document = { querySelector() { return root; }, querySelectorAll(selector) { return selector === '.casa-ticket-form' ? [commercial] : selector === '.casa-operational-action' ? [action, commercial] : [editorial, action, commercial]; }, createElement(tag) { return { tag }; } };
	const window = { wp: media ? { media(opts) { config = opts; frameCount++; return frame; } } : undefined, confirm: () => false, tinymce: protectedContent ? undefined : { get: () => ({ isDirty: () => false }) } };
	vm.runInNewContext(source, { document, window, Event: class { constructor(type, opts) { this.type = type; Object.assign(this, opts); } } });
	return { input, preview, choose, clear, access, url, tickets, editorial, content, action, commercial, frame, window, config: () => config, frameCount: () => frameCount, select(image) { selection = image; selected(); } };
}
test('image-only single selection uses native picker', () => { const t = setup(); t.choose.emit('click'); assert.equal(t.config().library.type, 'image'); assert.equal(t.config().multiple, false); });
test('picker frame reused', () => { const t = setup(); t.choose.emit('click'); t.choose.emit('click'); assert.equal(t.frameCount(), 1); assert.equal(t.frame.openCount, 2); });
test('selection sets association and thumbnail', () => { const t = setup(); t.choose.emit('click'); t.select({ id: 42, sizes: { thumbnail: { url: 'https://image.test/thumb.jpg' } }, url: 'https://image.test/full.jpg', alt: '<unsafe>' }); assert.equal(t.input.value, '42'); assert.equal(t.preview.children[0].src, 'https://image.test/thumb.jpg'); assert.equal(t.preview.children[0].alt, '<unsafe>'); });
test('preview uses DOM image instead of HTML injection', () => { const t = setup(); t.choose.emit('click'); t.select({ id: 42, url: 'https://image.test/full.jpg', alt: '"><script>' }); assert.equal(t.preview.children[0].tag, 'img'); assert.equal(t.preview.children.length, 1); });
test('explicit clear only removes association', () => { const t = setup(); t.clear.emit('click'); assert.equal(t.input.value, '0'); assert.equal(t.preview.children.length, 0); });
test('missing media API preserves current value', () => { const t = setup({ media: false }); assert.equal(t.input.value, '7'); });
test('external URL visible only for external mode', () => { const t = setup(); assert.equal(t.url.hidden, true); t.access.value = 'external'; t.access.emit('change'); assert.equal(t.url.hidden, false); });
test('switching mode never disables URL submission', () => { const t = setup(); t.url.value = 'https://tickets.test'; t.access.value = 'tickets'; t.access.emit('change'); assert.equal(t.url.value, 'https://tickets.test'); assert.equal(t.url.disabled, undefined); });
test('tickets limitation hint follows mode', () => { const t = setup({ mode: 'tickets' }); assert.equal(t.tickets.hidden, false); t.access.value = 'external'; t.access.emit('change'); assert.equal(t.tickets.hidden, true); });
test('dirty editorial form prompts before separate action', () => { const t = setup(); t.editorial.emit('input'); const e = t.action.emit('submit'); assert.equal(e.defaultPrevented, true); assert.equal(t.action.attributes['aria-busy'], undefined); });
test('TinyMCE changes also prompt', () => { const t = setup(); t.window.tinymce.get = () => ({ isDirty: () => true }); assert.equal(t.action.emit('submit').defaultPrevented, true); });
test('confirmed dirty action can submit', () => { const t = setup(); t.editorial.emit('change'); t.window.confirm = () => true; assert.equal(t.action.emit('submit').defaultPrevented, false); });
test('double submit blocked without dropping button intent', () => { const t = setup(); assert.equal(t.editorial.emit('submit').defaultPrevented, false); assert.equal(t.editorial.emit('submit').defaultPrevented, true); assert.equal(t.editorial.attributes['aria-busy'], 'true'); });
test('protected content has no TinyMCE dependency', () => { const t = setup({ protectedContent: true }); assert.equal(t.action.emit('submit').defaultPrevented, false); });
test('script safely ignores unrelated admin pages', () => { vm.runInNewContext(source, { document: { querySelector: () => null }, window: {} }); });
test('untouched TinyMCE description is omitted on unrelated save', () => { const t = setup(); t.editorial.emit('submit'); assert.equal(t.content.attributes.name, undefined); });
test('changed TinyMCE description remains submitted', () => { const t = setup(); t.window.tinymce.get = () => ({ isDirty: () => true }); t.editorial.emit('submit'); assert.equal(t.content.attributes.name, 'fields[content]'); });
test('retained edit survives clean editor retry', () => { const t = setup({ pendingContent: true }); t.content.value = '<p>Retained</p>'; t.editorial.emit('submit'); assert.equal(t.content.attributes.name, 'fields[content]'); assert.equal(t.content.value, '<p>Retained</p>'); });
test('retained clear survives clean editor retry', () => { const t = setup({ pendingContent: true }); t.content.value = ''; t.editorial.emit('submit'); assert.equal(t.content.attributes.name, 'fields[content]'); });
test('retained edit warns before separate action', () => { const t = setup({ pendingContent: true }); assert.equal(t.action.emit('submit').defaultPrevented, true); });
test('protected content remains absent on submit', () => { const t = setup({ protectedContent: true }); t.editorial.emit('submit'); assert.equal(t.content.attributes.name, undefined); });
test('textarea retry works without TinyMCE', () => { const t = setup({ pendingContent: true }); t.window.tinymce = undefined; t.editorial.emit('submit'); assert.equal(t.content.attributes.name, 'fields[content]'); });
test('dirty editorial form warns before commercial submit outside root', () => { const t = setup(); t.editorial.emit('input'); assert.equal(t.commercial.emit('submit').defaultPrevented, true); });
test('commercial double submit is blocked', () => { const t = setup(); assert.equal(t.commercial.emit('submit').defaultPrevented, false); assert.equal(t.commercial.emit('submit').defaultPrevented, true); });
test('commercial edits warn before editorial save without dropping content field', () => { const t = setup(); t.commercial.emit('input'); assert.equal(t.editorial.emit('submit').defaultPrevented, true); assert.equal(t.content.attributes.name, 'fields[content]'); });
test('saving own dirty commercial row needs no discard confirmation', () => { const t = setup(); t.commercial.emit('change'); assert.equal(t.commercial.emit('submit').defaultPrevented, false); });
console.log(passed + ' operational JS tests passed');
