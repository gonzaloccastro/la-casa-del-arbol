/* E3.2 progressive enhancement: native media picker, no Event writes. */
(function (document, wp) {
	'use strict';
	var root = document.querySelector('.casa-operational');
	if (!root) { return; }
	var poster = root.querySelector('[data-poster]');
	if (poster && wp && wp.media) {
		var input = poster.querySelector('input[name="fields[poster_id]"]');
		var preview = poster.querySelector('[data-poster-preview]');
		var frame;
		poster.querySelector('[data-poster-select]').addEventListener('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'Elegir afiche', library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var image = frame.state().get('selection').first().toJSON();
					input.value = String(image.id);
					preview.replaceChildren();
					var element = document.createElement('img');
					element.src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
					element.alt = image.alt || '';
					preview.appendChild(element);
					input.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}
			frame.open();
		});
		poster.querySelector('[data-poster-clear]').addEventListener('click', function () {
			input.value = '0';
			preview.replaceChildren();
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});
	}
	var mode = root.querySelector('#e32-access_mode');
	var url = root.querySelector('[data-external-url]');
	var tickets = root.querySelector('[data-tickets-note]');
	function modeHint() {
		if (!mode) { return; }
		if (url) {
			url.hidden = mode.value !== 'external';
			// Hidden URL stays enabled and present: it is never implicitly cleared.
		}
		if (tickets) { tickets.hidden = mode.value !== 'tickets'; }
	}
	if (mode) { mode.addEventListener('change', modeHint); modeHint(); }
	var editorial = root.querySelector('.casa-operational-editorial');
	var dirty = false;
	if (editorial) {
		var pendingContent = editorial.getAttribute('data-content-pending') === '1';
		editorial.addEventListener('input', function () { dirty = true; });
		editorial.addEventListener('change', function () { dirty = true; });
		// TinyMCE changes do not necessarily emit DOM input events.
		function editorDirty() {
			var editor = window.tinymce && window.tinymce.get('e32-content');
			return editor && editor.isDirty();
		}
		editorial.addEventListener('submit', function (event) {
			if (event.defaultPrevented) { return; }
			var editor = window.tinymce && window.tinymce.get('e32-content');
			var textarea = editorial.querySelector('[name="fields[content]"]');
			// TinyMCE can normalize HTML on initialization. An untouched editor
			// must not rewrite the saved description during unrelated edits.
			if (editor && !editor.isDirty() && !pendingContent && textarea) {
				textarea.removeAttribute('name');
			}
		});
		root.querySelectorAll('.casa-operational-action').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if ((dirty || pendingContent || editorDirty()) && !window.confirm('Hay cambios editoriales sin guardar. Esta acción solo modifica el evento guardado. ¿Continuar sin guardar esos cambios?')) {
					event.preventDefault();
				}
			});
		});
	}
	root.querySelectorAll('form').forEach(function (form) {
		var submitting = false;
		form.addEventListener('submit', function (event) {
			if (event.defaultPrevented) { return; }
			if (submitting) { event.preventDefault(); return; }
			submitting = true;
			// Keep the clicked button enabled: its intent must reach the server.
			form.setAttribute('aria-busy', 'true');
		});
	});
})(document, window.wp);
