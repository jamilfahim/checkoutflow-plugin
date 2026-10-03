(function ($) {
	'use strict';

	var config = window.eilmoCFNativeCheckout || {};
	var updateTimer = 0;
	var suppressGatewayUpdate = false;

	function form() {
		return document.querySelector('form.checkout, form.woocommerce-checkout');
	}

	function controls(root) {
		return (root || document).querySelector('[data-eilmo-native-payment-controls]');
	}

	function selectedType(root) {
		var checked = (root || document).querySelector('[data-eilmo-native-payment-type]:checked');
		return checked instanceof HTMLInputElement ? String(checked.value || '') : '';
	}

	function gatewayRadios(checkout) {
		return checkout ? Array.prototype.slice.call(checkout.querySelectorAll('input[name="payment_method"]')) : [];
	}

	function checkedGateway(checkout) {
		return checkout ? checkout.querySelector('input[name="payment_method"]:checked') : null;
	}

	function gatewayById(checkout, id) {
		var radios = gatewayRadios(checkout);
		for (var i = 0; i < radios.length; i += 1) {
			if (String(radios[i].value || '') === String(id || '')) return radios[i];
		}
		return null;
	}

	function firstNonCodGateway(checkout) {
		var cod = String(config.codGatewayId || 'cod');
		var radios = gatewayRadios(checkout);
		for (var i = 0; i < radios.length; i += 1) {
			if (!radios[i].disabled && String(radios[i].value || '') !== cod) return radios[i];
		}
		return null;
	}

	function embeddedGatewayGrid(ui) {
		return ui ? ui.querySelector('[data-eilmo-native-gateway-grid]') : null;
	}

	function externalGatewayList(checkout, ui) {
		if (!checkout || embeddedGatewayGrid(ui)) return null;
		var lists = checkout.querySelectorAll('.woocommerce-checkout-payment .wc_payment_methods, #payment .wc_payment_methods');
		for (var i = 0; i < lists.length; i += 1) {
			if (!ui || !ui.contains(lists[i])) return lists[i];
		}
		return null;
	}

	function labelText(radio) {
		if (!(radio instanceof HTMLInputElement)) return '';
		var id = String(radio.id || '');
		var label = id ? document.querySelector('label[for="' + id.replace(/"/g, '') + '"]') : null;
		if (!label && radio.parentElement) label = radio.parentElement.querySelector('label');
		return label ? String(label.textContent || '').replace(/\s+/g, ' ').trim() : String(radio.value || '');
	}

	function methodKey(gatewayId) {
		gatewayId = String(gatewayId || '');
		if ('eilmo_bkash' === gatewayId) return 'bkash';
		if ('eilmo_nagad' === gatewayId) return 'nagad';
		return gatewayId;
	}

	function setHidden(root, selector, value) {
		var input = root ? root.querySelector(selector) : null;
		if (input instanceof HTMLInputElement) input.value = String(value || '');
	}

	function syncHidden(checkout) {
		var ui = controls(checkout || document);
		if (!ui) return;
		var gateway = checkedGateway(checkout);
		var gatewayId = gateway instanceof HTMLInputElement ? String(gateway.value || '') : '';
		setHidden(ui, '[data-eilmo-native-payment-gateway-id]', gatewayId);
		setHidden(ui, '[data-eilmo-native-payment-method]', labelText(gateway));
		setHidden(ui, '[data-eilmo-native-payment-method-key]', methodKey(gatewayId));
		setHidden(ui, '[data-eilmo-native-payment-source]', gatewayId === String(config.codGatewayId || 'cod') ? 'woocommerce_cod' : 'woocommerce_gateway');
	}

	function decorateGateways(checkout) {
		var ui = controls(checkout || document);
		if (!ui) return;
		var list = embeddedGatewayGrid(ui) || externalGatewayList(checkout, ui);
		if (!list) return;
		if (!embeddedGatewayGrid(ui)) { list.setAttribute('data-eilmo-native-external-gateway-list', ''); list.classList.add('eilmo-cf-native-gateway-grid'); }
		var visibleGatewayCount = 0;
		list.querySelectorAll('li.wc_payment_method').forEach(function (item) {
			item.classList.add('eilmo-cf-native-gateway-item');
			var radio = item.querySelector('input[name="payment_method"]');
			var label = radio && radio.id ? item.querySelector('label[for="' + radio.id.replace(/"/g, '') + '"]') : item.querySelector('label');
			var box = item.querySelector('.payment_box');
			if (radio) {
				radio.classList.add('eilmo-cf-native-gateway-radio');
				if (String(radio.value || '') === String(config.codGatewayId || 'cod')) {
					item.hidden = true;
					item.setAttribute('aria-hidden', 'true');
					item.setAttribute('data-eilmo-native-cod-engine', '');
				} else if (!radio.disabled) {
					visibleGatewayCount += 1;
				}
			}
			if (label) label.classList.add('eilmo-cf-native-gateway-card');
			if (box) box.classList.add('eilmo-cf-native-gateway-fields');
		});
		list.setAttribute('data-eilmo-native-gateway-count', String(visibleGatewayCount));
	}

	function ensureGatewayForType(checkout, type, triggerChange) {
		if (!checkout) return null;
		var cod = String(config.codGatewayId || 'cod');
		var target = null;
		if ('cash_on_delivery' === type) {
			target = gatewayById(checkout, cod);
		} else {
			var current = checkedGateway(checkout);
			if (current instanceof HTMLInputElement && !current.disabled && String(current.value || '') !== cod) {
				target = current;
			} else {
				target = firstNonCodGateway(checkout);
			}
		}
		if (!(target instanceof HTMLInputElement)) return null;
		if (!target.checked) {
			suppressGatewayUpdate = true;
			target.checked = true;
			if (triggerChange) $(target).trigger('change');
			window.setTimeout(function () { suppressGatewayUpdate = false; }, 0);
		}
		return target;
	}

	function syncVisual(checkout) {
		var ui = controls(checkout || document);
		if (!ui) return;
		var type = selectedType(ui);
		ui.dataset.paymentType = type;
		ui.querySelectorAll('[data-eilmo-native-payment-option]').forEach(function (card) {
			card.classList.toggle('is-selected', String(card.getAttribute('data-eilmo-native-payment-option') || '') === type);
		});

		var methods = ui.querySelector('[data-eilmo-native-payment-methods]');
		if (methods) {
			var hideMethods = 'cash_on_delivery' === type;
			methods.hidden = hideMethods;
			methods.setAttribute('aria-hidden', hideMethods ? 'true' : 'false');
			var note = methods.querySelector('[data-eilmo-native-payment-method-pay-note]');
			if (note) {
				var text = '';
				if ('advance' === type) text = String(methods.getAttribute('data-advance-pay-note') || '');
				if ('full' === type) text = String(methods.getAttribute('data-full-pay-note') || '');
				note.textContent = text;
				note.hidden = hideMethods || !text;
			}
		}

		var externalList = externalGatewayList(checkout, ui);
		if (externalList) {
			var hideExternalMethods = 'cash_on_delivery' === type;
			externalList.hidden = hideExternalMethods;
			externalList.setAttribute('aria-hidden', hideExternalMethods ? 'true' : 'false');
		}

		var selected = checkedGateway(checkout);
		checkout.querySelectorAll('li.wc_payment_method').forEach(function (item) {
			var radio = item.querySelector('input[name="payment_method"]');
			item.classList.toggle('is-selected', Boolean(radio && selected === radio));
		});
		syncHidden(checkout);
	}

	function updateOptionCopyFromMarker(checkout) {
		if (!checkout) return;
		var marker = checkout.querySelector('[data-eilmo-native-cart-totals]');
		var ui = controls(checkout);
		if (!marker || !ui) return;
		var advanceNote = String(marker.getAttribute('data-advance-pay-note') || '');
		var fullNote = String(marker.getAttribute('data-full-pay-note') || '');
		var advanceSmall = ui.querySelector('[data-eilmo-native-payment-option="advance"] .eilmo-cf-native-option-copy small');
		var fullSmall = ui.querySelector('[data-eilmo-native-payment-option="full"] .eilmo-cf-native-option-copy small');
		if (advanceSmall && advanceNote) advanceSmall.textContent = advanceNote;
		if (fullSmall && fullNote) fullSmall.textContent = fullNote;
		var methods = ui.querySelector('[data-eilmo-native-payment-methods]');
		if (methods) {
			if (advanceNote) methods.setAttribute('data-advance-pay-note', advanceNote);
			if (fullNote) methods.setAttribute('data-full-pay-note', fullNote);
		}
	}

	function requestCheckoutUpdate(checkout) {
		if (!checkout) return;
		window.clearTimeout(updateTimer);
		updateTimer = window.setTimeout(function () {
			syncHidden(checkout);
			$(document.body).trigger('update_checkout');
		}, 80);
	}

	function initialize() {
		var checkout = form();
		if (!checkout) return;
		checkout.classList.add('eilmo-cf-native-checkout');
		checkout.setAttribute('data-eilmo-native-checkout', '');
		decorateGateways(checkout);
		var type = selectedType(checkout);
		if (type) ensureGatewayForType(checkout, type, false);
		updateOptionCopyFromMarker(checkout);
		syncVisual(checkout);
	}

	$(document.body).on('change', '[data-eilmo-native-delivery-input]', function () {
		var checkout = form();
		if (!checkout) return;
		requestCheckoutUpdate(checkout);
	});

	$(document.body).on('change', '[data-eilmo-native-payment-type]', function () {
		var checkout = form();
		if (!checkout) return;
		var type = String(this.value || '');
		ensureGatewayForType(checkout, type, true);
		syncVisual(checkout);
		requestCheckoutUpdate(checkout);
	});

	$(document.body).on('change', 'form.checkout input[name="payment_method"], form.woocommerce-checkout input[name="payment_method"]', function () {
		var checkout = form();
		if (!checkout) return;
		var ui = controls(checkout);
		var type = selectedType(ui || checkout);
		if ('cash_on_delivery' === type && String(this.value || '') !== String(config.codGatewayId || 'cod')) {
			var preferred = ui ? ui.querySelector('[data-eilmo-native-payment-type][value="advance"]:not(:disabled), [data-eilmo-native-payment-type][value="full"]:not(:disabled)') : null;
			if (preferred instanceof HTMLInputElement) preferred.checked = true;
		}
		syncVisual(checkout);
		if (!suppressGatewayUpdate) {
			/* WooCommerce owns payment_method changes. Its checkout controller will
			 * initialize gateway fields and any gateway-specific validation. */
		}
	});

	$(document.body).on('updated_checkout checkout_error', function () {
		window.setTimeout(initialize, 0);
	});

	$('form.checkout, form.woocommerce-checkout').on('checkout_place_order', function () {
		var checkout = this;
		syncHidden(checkout);
		/* Do not intercept, disable or replace WooCommerce submit. Server-side
		 * validation remains authoritative for Eilmo payment/fraud rules. */
		return true;
	});

	$(initialize);
})(jQuery);
