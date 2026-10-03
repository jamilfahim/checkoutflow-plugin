/**
 * Eilmo Checkout Flow - live order button amount.
 *
 * Keeps every Order Now UI instance inside one checkout synchronized with
 * the authoritative Summary state. This includes below-payment, summary and
 * mobile sticky buttons rendered at the same time.
 *
 * @package EilmoCheckout
 */
(function () {
	'use strict';

	function decodeHtmlEntities(value) {
		const textarea = document.createElement('textarea');
		textarea.innerHTML = String(value || '');
		return textarea.value.replace(/\u00a0/g, ' ').trim();
	}

	function formatPrice(amount) {
		const config = window.eilmoCf && window.eilmoCf.currency
			? window.eilmoCf.currency
			: {};
		const parsedDecimals = Number.parseInt(config.decimals, 10);
		const decimals = Number.isFinite(parsedDecimals) ? Math.max(0, parsedDecimals) : 2;
		const decimalSeparator = decodeHtmlEntities(config.decimalSeparator || '.');
		const thousandSeparator = decodeHtmlEntities(config.thousandSeparator || ',');
		const symbol = decodeHtmlEntities(config.symbol || '৳');
		const position = String(config.position || 'left');
		const number = Math.max(0, Number.parseFloat(amount) || 0);
		const parts = number.toFixed(decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
		const formatted = decimals > 0 ? parts[0] + decimalSeparator + parts[1] : parts[0];
		if (position === 'right') return formatted + symbol;
		if (position === 'right_space') return formatted + '\u00A0' + symbol;
		if (position === 'left_space') return symbol + '\u00A0' + formatted;
		return symbol + formatted;
	}

	function toNumber(value) {
		const parsed = Number.parseFloat(String(value ?? ''));
		return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
	}

	function readValues(checkout) {
		return {
			grandTotal: toNumber(checkout.dataset.grandTotal),
			payNow: toNumber(checkout.dataset.payNow),
			paymentType: String(checkout.dataset.paymentType || ''),
		};
	}

	function chooseAmount(source, values) {
		const grandTotal = toNumber(values && (values.grandTotal ?? values.grand_total));
		const payNow = toNumber(values && (values.payNow ?? values.pay_now));
		const paymentType = String(values && (values.paymentType ?? values.payment_type) || '');

		if (source === 'grand_total') return grandTotal;
		if (source === 'pay_now') return payNow;

		/* Auto follows the amount the customer actually pays now. COD is the
		 * exception: Pay Now is intentionally zero, while the actionable order
		 * value is the Grand Total. */
		return paymentType === 'cash_on_delivery'
			? grandTotal
			: (payNow > 0 ? payNow : grandTotal);
	}

	function update(checkout, values) {
		if (!(window.eilmoCfDom.isElement(checkout))) return;
		if (checkout.dataset.emptySelection === 'yes') {
			checkout.querySelectorAll('[data-eilmo-mobile-summary-amount]').forEach(function (element) {
				element.textContent = '—';
			});
		}

        const state = values || readValues(checkout);
        const paymentType = String(state && (state.paymentType || state.payment_type) || 'cash_on_delivery');
        checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
            const text = button.querySelector('[data-eilmo-order-submit-text]');
            const language = checkout.dataset.checkoutLanguage || 'en';
            const amount = chooseAmount('auto', state);
            const formatted = window.eilmoCfLanguage ? window.eilmoCfLanguage.money(amount, language) : formatPrice(amount);
            const templateKey = paymentType === 'cash_on_delivery' ? 'cod' : ((paymentType === 'full' || paymentType === 'full_payment') ? 'full' : 'advance');
            const configuredRaw = String(button.dataset[templateKey + 'Template'] || '').trim();
            const isLegacyFullTemplate = templateKey === 'full' && [
                '{amount} Pay and confirm order',
                '{amount} পেমেন্ট করে অর্ডার করুন'
            ].includes(configuredRaw);
            const configured = isLegacyFullTemplate
                ? (language === 'bn' ? '{amount} পেমেন্ট করুন' : 'Pay {amount}')
                : configuredRaw;
            const fallback = configured
                ? configured.replace(/\{amount\}/g, formatted)
                : (templateKey === 'cod'
                    ? (language === 'bn' ? 'অর্ডার কনফার্ম করুন' : 'Confirm order')
                    : (templateKey === 'full'
                        ? (language === 'bn' ? formatted + ' পেমেন্ট করুন' : 'Pay ' + formatted)
                        : (language === 'bn' ? formatted + ' পেমেন্ট করে অর্ডার করুন' : formatted + ' Pay and confirm order')));
            button.dataset.label = fallback;
            if (text && button.getAttribute('aria-busy') !== 'true') text.textContent = fallback;
            const extra = button.querySelector('[data-eilmo-order-submit-amount]');
            if (extra) extra.hidden = true;
        });
        checkout.querySelectorAll('[data-eilmo-order-submit][data-show-amount="yes"]').forEach(function (button) {
			const target = button.querySelector('[data-eilmo-order-submit-amount]');
			if (!target) return;
			if (checkout.dataset.emptySelection === 'yes') {
				target.textContent = '— Select a product';
				return;
			}
			const amount = chooseAmount(button.dataset.amountSource || 'auto', values || readValues(checkout));
			target.textContent = '— ' + (window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatPrice(amount),checkout.dataset.checkoutLanguage) : formatPrice(amount));
		});
	}

	function refreshCheckout(checkout, values) {
		if (!(window.eilmoCfDom.isElement(checkout))) return;
		update(checkout, values || readValues(checkout));
	}

	function initialize(root) {
		const scope = window.eilmoCfDom.isElement(root) || root instanceof Document ? root : document;
		if (window.eilmoCfDom.isElement(scope) && scope.matches('[data-eilmo-checkout]')) {
			refreshCheckout(scope);
		}
		scope.querySelectorAll('[data-eilmo-checkout]').forEach(function (checkout) {
			refreshCheckout(checkout);
		});
	}

	document.addEventListener('eilmo:summaryChange', function (event) {
		const detailCheckout = event.detail && window.eilmoCfDom.isElement(event.detail.checkout)
			? event.detail.checkout
			: null;
		const eventCheckout = window.eilmoCfDom.isElement(event.target)
			? event.target.closest('[data-eilmo-checkout]')
			: null;
		const checkout = detailCheckout || eventCheckout;
		if (!checkout) return;
		refreshCheckout(checkout, event.detail && event.detail.values ? event.detail.values : readValues(checkout));
	});

	/* Summary may initialize before this file when scripts execute after DOM
	 * ready. Reading the Summary dataset here fixes that race and also makes
	 * preselected/server-restored checkouts immediately correct. */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initialize(document); });
	} else {
		initialize(document);
	}

	/* Builder/AJAX fallback for dynamically inserted duplicate action UIs. */
	if (window.MutationObserver && document.documentElement) {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (window.eilmoCfDom.isElement(node)) initialize(node);
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
