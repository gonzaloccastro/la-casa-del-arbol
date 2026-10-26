/* Display-only totals. The server re-reads prices and stock under the event lock. */
(function () {
	'use strict';

	function money(cents, currency) {
		var amount = cents / 100;
		try {
			return new Intl.NumberFormat('es-AR', {
				style: 'currency',
				currency: currency,
				currencyDisplay: 'code',
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(amount);
		} catch (error) {
			return currency + ' ' + amount.toFixed(2);
		}
	}

	function update(form) {
		var rows = form.querySelectorAll('.casa-ticket-selector__type');
		var total = form.querySelector('[data-casa-ticket-total]');
		var feedback = form.querySelector('[data-casa-ticket-feedback]');
		var button = form.querySelector('button[type="submit"]');
		var count = 0;
		var cents = 0;
		var currency = '';
		var problem = '';
		rows.forEach(function (row) {
			var input = row.querySelector('input[type="number"]');
			if (!input || input.disabled) { return; }
			var quantity = Number(input.value);
			if (!Number.isInteger(quantity) || quantity < 0 || quantity > Number(input.max)) {
				problem = 'Revisá la cantidad de entradas.';
				return;
			}
			if (!quantity) { return; }
			var rowCurrency = row.getAttribute('data-currency') || '';
			var priceCents = Math.round(Number(row.getAttribute('data-price')) * 100);
			if (!Number.isSafeInteger(priceCents) || priceCents < 0) {
				problem = 'No se puede calcular el total.';
				return;
			}
			if (currency && currency !== rowCurrency) {
				problem = 'Elegí entradas de una sola moneda por compra.';
				return;
			}
			currency = rowCurrency;
			count += quantity;
			cents += priceCents * quantity;
		});
		if (count > Number(form.getAttribute('data-global-available')) || count > 10) {
			problem = 'La cantidad supera el aforo disponible o el máximo de 10 entradas.';
		}
		if (problem) {
			total.textContent = 'Total no disponible';
			feedback.textContent = problem;
			button.disabled = true;
			return;
		}
		total.textContent = count ? money(cents, currency) : 'Elegí una cantidad para ver el total.';
		feedback.textContent = '';
		button.disabled = count === 0;
	}

	function init() {
		document.querySelectorAll('[data-casa-ticket-selector]').forEach(function (form) {
			form.addEventListener('input', function () { update(form); });
			form.addEventListener('change', function () { update(form); });
			update(form);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());