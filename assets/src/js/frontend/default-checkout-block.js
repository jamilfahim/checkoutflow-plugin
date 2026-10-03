(function () {
	'use strict';

	const config = window.eilmoCFDefaultCheckoutBlock || {};
	const namespace = config.namespace || 'eilmo-checkout-flow';
	const fields = config.fields || {};
	let applying = false;
	let applyTimer = null;

	function registerCheckoutFilters() {
		const blocksCheckout = window.wc && window.wc.blocksCheckout;

		if (
			!blocksCheckout ||
			typeof blocksCheckout.registerCheckoutFilters !== 'function'
		) {
			return;
		}

		blocksCheckout.registerCheckoutFilters(namespace, {
			placeOrderButtonLabel(defaultValue) {
				return config.orderButtonLabel || defaultValue;
			},
		});
	}

	function fieldParts(fieldKey) {
		if (fieldKey === 'order_comments') {
			return { section: 'order', name: 'comments' };
		}

		const separator = fieldKey.indexOf('_');

		return separator > 0
			? {
				section: fieldKey.slice(0, separator),
				name: fieldKey.slice(separator + 1),
			}
			: { section: '', name: fieldKey };
	}

	function findInput(fieldKey) {
		const parts = fieldParts(fieldKey);

		if (fieldKey === 'billing_email') {
			return document.querySelector(
				'#email, #billing-email, #billing_email, input[name="email"], input[name="billing_email"]'
			);
		}

		if (fieldKey === 'order_comments') {
			return document.querySelector(
				'#order-notes, #order_comments, textarea[name="order_notes"], textarea[name="order_comments"]'
			);
		}

		const dashedId = parts.section + '-' + parts.name;

		return document.querySelector(
			'#' + dashedId +
			', #' + fieldKey +
			', [name="' + fieldKey.replace(/"/g, '') + '"]'
		);
	}

	function fieldWrapper(input, fieldKey) {
		if (!input) {
			return null;
		}

		const parts = fieldParts(fieldKey);

		if (fieldKey === 'order_comments') {
			return input.closest(
				'.wc-block-checkout__add-note, .wc-block-components-textarea'
			) || input.parentElement;
		}

		return input.closest(
			'.wc-block-components-address-form__' + parts.name +
			', .wc-block-components-text-input' +
			', .wc-block-components-select-input' +
			', .wc-block-components-combobox'
		) || input.parentElement;
	}

	function updateLabel(wrapper, input, labelText) {
		if (!labelText || !wrapper || !input) {
			return;
		}

		const inputId = input.getAttribute('id');
		const label = inputId
			? wrapper.querySelector('label[for="' + inputId + '"]')
			: wrapper.querySelector('label');

		if (label && label.textContent.trim() !== labelText) {
			label.textContent = labelText;
		}

		input.setAttribute('aria-label', labelText);
	}

	function applyField(fieldKey, setting) {
		const input = findInput(fieldKey);
		const wrapper = fieldKey === 'order_comments' && !input
			? document.querySelector('.wc-block-checkout__add-note')
			: fieldWrapper(input, fieldKey);

		if (!wrapper) {
			return;
		}

		let shouldHide = setting.enabled === 'no';

		/* Checkout Block requires contact email for guest checkout. */
		if (
			fieldKey === 'billing_email' &&
			input &&
			!String(input.value || '').trim()
		) {
			shouldHide = false;
		}

		wrapper.hidden = shouldHide;
		wrapper.toggleAttribute('data-eilmo-field-hidden', shouldHide);

		if (shouldHide && input) {
			input.required = false;
			input.removeAttribute('aria-required');
		}

		if (!input) {
			return;
		}

		updateLabel(wrapper, input, setting.label || '');

		if (setting.placeholder) {
			input.setAttribute('placeholder', setting.placeholder);
		}

		if (!shouldHide && setting.required === 'yes') {
			input.required = true;
			input.setAttribute('aria-required', 'true');
		} else if (setting.required === 'no' || shouldHide) {
			input.required = false;
			input.removeAttribute('aria-required');
		}

		wrapper.classList.toggle(
			'eilmo-cf-block-field--full',
			setting.width === 'full'
		);
		wrapper.classList.toggle(
			'eilmo-cf-block-field--half',
			setting.width === 'half'
		);

		const priority = Number.parseInt(setting.priority, 10);

		if (Number.isFinite(priority) && priority > 0) {
			wrapper.style.order = String(priority);
			wrapper.setAttribute('data-eilmo-field-priority', String(priority));
		} else if (wrapper.hasAttribute('data-eilmo-field-priority')) {
			wrapper.style.removeProperty('order');
			wrapper.removeAttribute('data-eilmo-field-priority');
		}
	}

	function applyFields() {
		if (applying) {
			return;
		}

		applying = true;

		Object.keys(fields).forEach((fieldKey) => {
			const setting = fields[fieldKey];

			if (setting && typeof setting === 'object') {
				applyField(fieldKey, setting);
			}
		});

		applying = false;
	}

	function scheduleApply() {
		window.clearTimeout(applyTimer);
		applyTimer = window.setTimeout(applyFields, 40);
	}

	function initialize() {
		applyFields();

		const observer = new MutationObserver(() => {
			if (!applying) {
				scheduleApply();
			}
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});
	}

	registerCheckoutFilters();

	if (window.wp && typeof window.wp.domReady === 'function') {
		window.wp.domReady(initialize);
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
