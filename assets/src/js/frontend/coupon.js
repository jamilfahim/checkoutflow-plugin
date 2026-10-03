/**
 * Eilmo Checkout Flow - Coupon.
 *
 * Handles custom coupon validation and live checkout synchronization.
 *
 * Normal Products, Combo Offers and Order Bumps remain independent
 * purchase contexts. Combo / Order Bump pricing and compatibility are
 * never trusted from the browser; only their selected IDs are submitted.
 *
 * Frontend values are previews only. Final coupon eligibility and totals
 * must always be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout: '[data-eilmo-checkout]',
		section: '[data-eilmo-coupon]',
		form: '[data-eilmo-coupon-form]',
		input: '[data-eilmo-coupon-input]',
		apply: '[data-eilmo-coupon-apply]',
		applied: '[data-eilmo-coupon-applied]',
		appliedCode: '[data-eilmo-coupon-applied-code]',
		appliedDiscount: '[data-eilmo-coupon-applied-discount]',
		remove: '[data-eilmo-coupon-remove]',
		message: '[data-eilmo-coupon-message]',
		item: '[data-eilmo-product-item], [data-eilmo-item]',
		quantityInput: '[data-eilmo-quantity-input]',

		comboSection: '[data-eilmo-combo-offers]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',

		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',

		customerEmail: [
			'[data-eilmo-customer-email]',
			'input[name="billing_email"]',
			'input[name*="[billing_email]"]',
			'input[type="email"]',
		].join(','),

		customerPhone: [
			'[data-eilmo-customer-phone]',
			'input[name="billing_phone"]',
			'input[name*="[billing_phone]"]',
			'input[type="tel"]',
		].join(','),

		billingCountry: [
			'[data-eilmo-billing-country]',
			'select[name="billing_country"]',
			'input[name="billing_country"]',
			'select[name*="[billing_country]"]',
			'input[name*="[billing_country]"]',
		].join(','),
	};

	const initialized = new WeakSet();

	const states = new WeakMap();

	const refreshTimers = new WeakMap();

	/**
	 * Convert value to non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number =
			Number.parseFloat(
				String(value)
			);

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(
			0,
			number
		);
	}

	/**
	 * Convert value to non-negative integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number =
			Number.parseInt(
				String(value),
				10
			);

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(
			0,
			number
		);
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(element) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return null;
		}

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
			? checkout
			: null;
	}

	/**
	 * Get Coupon section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSection(checkout) {
		const section =
			checkout.querySelector(
				SELECTORS.section
			);

		return window.eilmoCfDom.isElement(section)
			? section
			: null;
	}

	/**
	 * Get localized string.
	 *
	 * @param {string} key      Key.
	 * @param {string} fallback Fallback.
	 *
	 * @return {string}
	 */
	function getText(
		key,
		fallback
	) {
		const config =
			window.eilmoCf || {};

		const i18n =
			config.i18n || {};

		if (
			typeof i18n[key] === 'string' &&
			i18n[key] !== ''
		) {
			return i18n[key];
		}

		return fallback;
	}

	/**
	 * Format price.
	 *
	 * Prefer Summary API so all modules use
	 * identical WooCommerce currency formatting.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		if (
			window.eilmoCfSummary &&
			typeof window
				.eilmoCfSummary
				.formatPrice === 'function'
		) {
			return window
				.eilmoCfSummary
				.formatPrice(
					toNumber(amount)
				);
		}

		const config =
			window.eilmoCf || {};

		const currency =
			config.currency || {};

		const decimals =
			Number.isInteger(
				currency.decimals
			)
				? currency.decimals
				: 2;

		const symbol =
			typeof currency.symbol === 'string'
				? currency.symbol
				: '৳';

		const position =
			typeof currency.position === 'string'
				? currency.position
				: 'left';

		const formatted =
			toNumber(
				amount
			).toFixed(
				decimals
			);

		switch (position) {
			case 'left_space':
				return (
					symbol +
					'\u00A0' +
					formatted
				);

			case 'right':
				return (
					formatted +
					symbol
				);

			case 'right_space':
				return (
					formatted +
					'\u00A0' +
					symbol
				);

			case 'left':
			default:
				return (
					symbol +
					formatted
				);
		}
	}

	/**
	 * Get integer value from dataset.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {Array<string>}    keys    Dataset keys.
	 *
	 * @return {number}
	 */
	function getDatasetInteger(
		element,
		keys
	) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return 0;
		}

		for (
			let index = 0;
			index < keys.length;
			index += 1
		) {
			const key =
				keys[index];

			const value =
				element.dataset[key];

			const number =
				toInteger(
					value || 0
				);

			if (number > 0) {
				return number;
			}
		}

		return 0;
	}

	/**
	 * Read integer value from child field.
	 *
	 * @param {HTMLElement} item     Product item.
	 * @param {string}      selector Selector.
	 *
	 * @return {number}
	 */
	function getChildInteger(
		item,
		selector
	) {
		const field =
			item.querySelector(
				selector
			);

		if (
			!(
				window.eilmoCfDom.isElement(field)
			)
		) {
			return 0;
		}

		let rawValue = '';

		if (
			window.eilmoCfDom.isElement(field, 'INPUT') ||
			window.eilmoCfDom.isElement(field, 'SELECT')
		) {
			rawValue =
				field.value;
		} else {
			rawValue =
				field.getAttribute(
					'data-value'
				) || '';
		}

		return toInteger(
			rawValue
		);
	}

	/**
	 * Get variation ID.
	 *
	 * @param {HTMLElement}      item  Product item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getVariationId(
		item,
		input
	) {
		const itemId =
			getDatasetInteger(
				item,
				[
					'variationId',
				]
			);

		if (itemId > 0) {
			return itemId;
		}

		const inputId =
			getDatasetInteger(
				input,
				[
					'variationId',
				]
			);

		if (inputId > 0) {
			return inputId;
		}

		return getChildInteger(
			item,
			[
				'[data-eilmo-variation-id]',
				'input[name*="[variation_id]"]',
				'input[name="variation_id"]',
			].join(',')
		);
	}

	/**
	 * Get product ID.
	 *
	 * @param {HTMLElement}      item  Product item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getProductId(
		item,
		input
	) {
		const itemId =
			getDatasetInteger(
				item,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (itemId > 0) {
			return itemId;
		}

		const inputId =
			getDatasetInteger(
				input,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (inputId > 0) {
			return inputId;
		}

		const hiddenId =
			getChildInteger(
				item,
				[
					'[data-eilmo-product-id]',
					'input[name*="[product_id]"]',
					'input[name="product_id"]',
				].join(',')
			);

		if (hiddenId > 0) {
			return hiddenId;
		}

		return getVariationId(
			item,
			input
		);
	}

	/**
	 * Collect selected checkout items.
	 *
	 * Server recalculates prices from WooCommerce.
	 * Frontend never sends authoritative price values.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<Object>}
	 */
	function getItems(checkout) {
		const items =
			new Map();

		checkout
			.querySelectorAll(
				SELECTORS.item
			)
			.forEach(
				function (element) {
					if (
						!(
							window.eilmoCfDom.isElement(element)
						)
					) {
						return;
					}

					/*
					 * Normal products only.
					 *
					 * Combo Offers and Order Bumps are separate
					 * purchase contexts and are submitted below
					 * only by their authoritative offer IDs.
					 */
					if (
						element.closest(
							SELECTORS.comboOffer
						) ||
						element.closest(
							SELECTORS.orderBumpOffer
						)
					) {
						return;
					}

					const quantityInput =
						element.querySelector(
							SELECTORS.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(quantityInput, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toInteger(
							quantityInput.value
						);

					if (quantity <= 0) {
						return;
					}

					const variationId =
						getVariationId(
							element,
							quantityInput
						);

					const productId =
						getProductId(
							element,
							quantityInput
						);

					if (productId <= 0) {
						return;
					}

					/*
					 * Multiple Products can expose compatibility
					 * adapters for the same exact order item. Send
					 * one Coupon row per Simple product / variation;
					 * never add duplicate DOM quantities together.
					 */
					const key =
						variationId > 0
							? 'variation:' + variationId
							: 'product:' + productId;

					const existing =
						items.get(
							key
						);

					items.set(
						key,
						{
							product_id:
								productId,

							variation_id:
								variationId,

							quantity:
								existing
									? Math.max(
										existing.quantity,
										quantity
									)
									: quantity,
						}
					);
				}
			);

		return Array.from(
			items.values()
		);
	}

	/**
	 * Collect selected Combo Offer request data.
	 *
	 * Only selected Combo IDs and the signed checkout
	 * context are sent. Product IDs, prices, quantities,
	 * compatibility flags and discount values remain
	 * server-authoritative.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getComboRequestData(checkout) {
		const selectedIds = [];

		checkout
			.querySelectorAll(
				SELECTORS.comboOffer
			)
			.forEach(
				function (offer) {
					if (
						!(
							window.eilmoCfDom.isElement(offer)
						)
					) {
						return;
					}

					const input =
						offer.querySelector(
							SELECTORS.comboInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					const offerId =
						String(
							offer.dataset.comboId ||
							input.value ||
							''
						).trim();

					if (
						offerId &&
						!selectedIds.includes(
							offerId
						)
					) {
						selectedIds.push(
							offerId
						);
					}
				}
			);

		let context = {};

		const section =
			checkout.querySelector(
				SELECTORS.comboSection
			);

		if (
			window.eilmoCfDom.isElement(section)
		) {
			const rawContext =
				section.getAttribute(
					'data-eilmo-combo-context'
				) || '';

			if (rawContext) {
				try {
					const parsed =
						JSON.parse(
							rawContext
						);

					if (
						parsed &&
						'object' ===
							typeof parsed &&
						!Array.isArray(
							parsed
						)
					) {
						context = parsed;
					}
				} catch (error) {
					context = {};
				}
			}
		}

		return {
			selected_ids:
				selectedIds,

			context:
				context,
		};
	}

	/**
	 * Collect selected Order Bump request data.
	 *
	 * Only Order Bump IDs are submitted. The server
	 * reloads product, quantity, pricing and discount
	 * compatibility from saved settings.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getOrderBumpRequestData(checkout) {
		const selectedIds = [];
		let context = {};

		checkout
			.querySelectorAll(
				SELECTORS.orderBumpOffer
			)
			.forEach(
				function (offer) {
					if (
						!(
							window.eilmoCfDom.isElement(offer)
						)
					) {
						return;
					}

					const input =
						offer.querySelector(
							SELECTORS.orderBumpInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					const offerId =
						String(
							offer.dataset.orderBumpId ||
							input.value ||
							''
						).trim();

					if (
						offerId &&
						!selectedIds.includes(
							offerId
						)
					) {
						selectedIds.push(
							offerId
						);
					}
				}
			);

		const rawContext = checkout.getAttribute('data-eilmo-special-discount-context') || '';
		if (rawContext) {
			try {
				const parsed = JSON.parse(rawContext);
				if (parsed && 'object' === typeof parsed && !Array.isArray(parsed)) {
					context = parsed;
				}
			} catch (error) {
				context = {};
			}
		}

		return {
			selected_ids:
				selectedIds,

			context:
				context,
		};
	}

	/**
	 * Get customer email when available.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCustomerEmail(checkout) {
		const input =
			checkout.querySelector(
				SELECTORS.customerEmail
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return '';
		}

		return input.value.trim();
	}

	/**
	 * Get customer phone when available.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCustomerPhone(checkout) {
		const input =
			checkout.querySelector(
				SELECTORS.customerPhone
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return '';
		}

		return input.value.trim();
	}

	/**
	 * Get billing country when available.
	 *
	 * Used by the server to normalize phone numbers.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getBillingCountry(checkout) {
		const field =
			checkout.querySelector(
				SELECTORS.billingCountry
			);

		if (
			window.eilmoCfDom.isElement(field, 'INPUT') ||
			window.eilmoCfDom.isElement(field, 'SELECT')
		) {
			return field.value
				.trim()
				.toUpperCase();
		}

		return '';
	}

	/**
	 * Get coupon state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getState(checkout) {
		if (!states.has(checkout)) {
			states.set(
				checkout,
				{
					applied: false,
					pending: false,
					pendingReason: '',
					identificationMode: '',
					code: '',
					discount: 0,
					source: '',
					freeDelivery: false,
					requestId: 0,
					controller: null,
				}
			);
		}

		return states.get(
			checkout
		);
	}

	/**
	 * Set Coupon message.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {string}      message Message.
	 * @param {string}      type    success|error|info.
	 *
	 * @return {void}
	 */
	function setMessage(
		section,
		message,
		type
	) {
		const element =
			section.querySelector(
				SELECTORS.message
			);

		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return;
		}

		element.classList.remove(
			'eilmo-cf-coupon__message--success',
			'eilmo-cf-coupon__message--error',
			'eilmo-cf-coupon__message--info'
		);

		if (!message) {
			element.textContent =
				'';

			element.hidden =
				true;

			delete element.dataset.type;

			return;
		}

		const safeType =
			[
				'success',
				'error',
				'info',
			].includes(type)
				? type
				: 'info';

		element.textContent =
			message;

		element.hidden =
			false;

		element.dataset.type =
			safeType;

		element.classList.add(
			'eilmo-cf-coupon__message--' +
				safeType
		);
	}

	/**
	 * Toggle loading state.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {boolean}     loading Loading.
	 *
	 * @return {void}
	 */
	function setLoading(
		section,
		loading
	) {
		const input =
			section.querySelector(
				SELECTORS.input
			);

		const button =
			section.querySelector(
				SELECTORS.apply
			);

		section.classList.toggle(
			'eilmo-cf-coupon--loading',
			loading
		);

		section.dataset.loading =
			loading
				? 'yes'
				: 'no';

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.disabled =
				loading;
		}

		if (
			window.eilmoCfDom.isElement(button, 'BUTTON')
		) {
			if (
				!button.dataset.defaultText
			) {
				button.dataset.defaultText =
					button.textContent ||
					'Apply Coupon';
			}

			button.disabled =
				loading;

			button.textContent =
				loading
					? getText(
						'processing',
						'Processing...'
					)
					: button.dataset
						.defaultText;
		}
	}

	/**
	 * Format coupon benefit.
	 *
	 * @param {number}  discount     Discount.
	 * @param {boolean} freeDelivery Free delivery.
	 *
	 * @return {string}
	 */
	function formatBenefit(
		discount,
		freeDelivery
	) {
		if (
			freeDelivery &&
			toNumber(
				discount
			) <= 0
		) {
			return getText(
				'freeDelivery',
				'Free Delivery'
			);
		}

		if (
			toNumber(
				discount
			) <= 0
		) {
			return '';
		}

		return (
			'\u2212' +
			formatPrice(
				discount
			)
		);
	}

	/**
	 * Render applied Coupon state.
	 *
	 * Pending customer-identity checks keep the
	 * Coupon form visible and do not apply a discount.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {Object}      state   State.
	 *
	 * @return {void}
	 */
	function renderAppliedState(
		section,
		state
	) {
		const form =
			section.querySelector(
				SELECTORS.form
			);

		const applied =
			section.querySelector(
				SELECTORS.applied
			);

		const code =
			section.querySelector(
				SELECTORS.appliedCode
			);

		const discount =
			section.querySelector(
				SELECTORS.appliedDiscount
			);

		if (
			window.eilmoCfDom.isElement(form)
		) {
			form.hidden =
				state.applied === true;
		}

		if (
			window.eilmoCfDom.isElement(applied)
		) {
			applied.hidden =
				state.applied !== true;
		}

		if (
			window.eilmoCfDom.isElement(code)
		) {
			code.textContent =
				state.applied
					? state.code
					: '';
		}

		if (
			window.eilmoCfDom.isElement(discount)
		) {
			discount.textContent =
				state.applied
					? formatBenefit(
						state.discount,
						state.freeDelivery
					)
					: '';
		}

		section.dataset.applied =
			state.applied
				? 'yes'
				: 'no';

		section.dataset.pending =
			state.pending
				? 'yes'
				: 'no';

		section.dataset.pendingReason =
			state.pending
				? state.pendingReason
				: '';

		section.dataset.identificationMode =
			state.identificationMode ||
			'';

		section.dataset.couponCode =
			state.applied
				? state.code
				: '';

		section.dataset.pendingCouponCode =
			state.pending
				? state.code
				: '';

		section.dataset.couponDiscount =
			String(
				state.applied
					? toNumber(
						state.discount
					)
					: 0
			);

		section.dataset.freeDelivery =
			state.applied &&
			state.freeDelivery
				? 'yes'
				: 'no';
	}

	/**
	 * Synchronize Coupon with checkout state.
	 *
	 * Summary owns Grand Total calculation.
	 * Coupon only supplies coupon-discount.
	 *
	 * Pending Coupon state never affects totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      state    State.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout,
		state
	) {
		const discount =
			state.applied
				? toNumber(
					state.discount
				)
				: 0;

		checkout.dataset.couponApplied =
			state.applied
				? 'yes'
				: 'no';

		checkout.dataset.couponPending =
			state.pending
				? 'yes'
				: 'no';

		checkout.dataset.couponCode =
			state.applied
				? state.code
				: '';

		checkout.dataset.couponPendingCode =
			state.pending
				? state.code
				: '';

		checkout.dataset.couponSource =
			state.applied
				? state.source
				: '';

		checkout.dataset.couponDiscount =
			String(
				discount
			);

		checkout.dataset.couponFreeDelivery =
			state.applied &&
			state.freeDelivery
				? 'yes'
				: 'no';

		checkout.dataset.couponIdentificationMode =
			state.identificationMode ||
			'';

		if (
			window.eilmoCfSummary &&
			typeof window
				.eilmoCfSummary
				.setAmount === 'function'
		) {
			window
				.eilmoCfSummary
				.setAmount(
					checkout,
					'coupon-discount',
					discount
				);
		} else {
			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:summaryRefresh',
					{
						bubbles: true,

						detail: {
							checkout:
								checkout,
						},
					}
				)
			);
		}

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:couponChange',
				{
					bubbles: true,

					detail: {
						checkout:
							checkout,

						applied:
							state.applied ===
							true,

						pending:
							state.pending ===
							true,

						couponCode:
							state.applied
								? state.code
								: '',

						pendingCouponCode:
							state.pending
								? state.code
								: '',

						source:
							state.applied
								? state.source
								: '',

						couponDiscount:
							discount,

						freeDelivery:
							state.applied &&
							state.freeDelivery ===
								true,

						identificationMode:
							state.identificationMode ||
							'',
					},
				}
			)
		);
	}

	/**
	 * Clear pending revalidation timer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function clearRefreshTimer(checkout) {
		const timer =
			refreshTimers.get(
				checkout
			);

		if (!timer) {
			return;
		}

		window.clearTimeout(
			timer
		);

		refreshTimers.delete(
			checkout
		);
	}

	/**
	 * Reset Coupon state.
	 *
	 * @param {Object}  state        State.
	 * @param {boolean} preserveCode Preserve current code as pending.
	 *
	 * @return {void}
	 */
	function resetState(
		state,
		preserveCode
	) {
		state.applied =
			false;

		state.pending =
			preserveCode &&
			Boolean(state.code);

		state.pendingReason =
			state.pending
				? state.pendingReason
				: '';

		state.discount =
			0;

		state.source =
			'';

		state.freeDelivery =
			false;

		if (!preserveCode) {
			state.code =
				'';

			state.identificationMode =
				'';
		}
	}

	/**
	 * Clear Coupon.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {boolean}     focus    Focus input.
	 *
	 * @return {void}
	 */
	function clearCoupon(
		checkout,
		section,
		focus
	) {
		const state =
			getState(
				checkout
			);

		clearRefreshTimer(
			checkout
		);

		if (
			state.controller instanceof
				AbortController
		) {
			state.controller.abort();
		}

		state.requestId += 1;

		state.controller =
			null;

		state.pendingReason =
			'';

		resetState(
			state,
			false
		);

		const input =
			section.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.value =
				'';
		}

		setLoading(
			section,
			false
		);

		setMessage(
			section,
			'',
			'info'
		);

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);

		if (
			focus &&
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.focus();
		}
	}

	/**
	 * Extract AJAX response data.
	 *
	 * @param {*} payload Response payload.
	 *
	 * @return {Object}
	 */
	function getResponseData(payload) {
		if (
			payload &&
			payload.data &&
			typeof payload.data === 'object'
		) {
			return payload.data;
		}

		return {};
	}

	/**
	 * Extract AJAX response message.
	 *
	 * @param {*}      payload  Response.
	 * @param {string} fallback Fallback.
	 *
	 * @return {string}
	 */
	function getResponseMessage(
		payload,
		fallback
	) {
		const data =
			getResponseData(
				payload
			);

		if (
			typeof data.message ===
				'string' &&
			data.message !== ''
		) {
			return data.message;
		}

		if (
			payload &&
			typeof payload.message ===
				'string' &&
			payload.message !== ''
		) {
			return payload.message;
		}

		return fallback;
	}

	/**
	 * Determine whether response requests customer identity.
	 *
	 * @param {*} payload AJAX payload.
	 *
	 * @return {boolean}
	 */
	function isCustomerIdentityRequired(payload) {
		const data =
			getResponseData(
				payload
			);

		return (
			data.error_code ===
				'customer_identity_required' ||
			data.requires_customer_contact ===
				true
		);
	}

	/**
	 * Set Coupon to pending customer identity state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {Object}      state    State.
	 * @param {string}      code     Coupon code.
	 * @param {*}           payload  AJAX payload.
	 *
	 * @return {void}
	 */
	function setIdentityPending(
		checkout,
		section,
		state,
		code,
		payload
	) {
		const data =
			getResponseData(
				payload
			);

		state.applied =
			false;

		state.pending =
			true;

		state.pendingReason =
			'customer_identity_required';

		state.identificationMode =
			typeof data.identification_mode ===
				'string'
				? data.identification_mode
				: '';

		state.code =
			code;

		state.discount =
			0;

		state.source =
			'eilmo';

		state.freeDelivery =
			false;

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);

		setMessage(
			section,
			getResponseMessage(
				payload,
				'Enter your email or phone number to check eligibility for this coupon.'
			),
			'info'
		);
	}

	/**
	 * Apply or revalidate Coupon.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {string}      code     Coupon code.
	 * @param {boolean}     silent   Silent revalidation.
	 *
	 * @return {Promise<void>}
	 */
	async function applyCoupon(
		checkout,
		section,
		code,
		silent
	) {
		const couponCode =
			String(
				code || ''
			).trim();

		if (!couponCode) {
			setMessage(
				section,
				'Please enter a coupon code.',
				'error'
			);

			return;
		}

		const config =
			window.eilmoCf || {};

		const ajaxUrl =
			typeof config.ajaxUrl ===
				'string'
				? config.ajaxUrl
				: '';

		const nonce =
			typeof config.ajaxNonce ===
				'string'
				? config.ajaxNonce
				: '';

		if (
			!ajaxUrl ||
			!nonce
		) {
			setMessage(
				section,
				getText(
					'error',
					'Something went wrong. Please try again.'
				),
				'error'
			);

			return;
		}

		const state =
			getState(
				checkout
			);

		const items =
			getItems(
				checkout
			);

		/*
		 * Existing Checkout Flow currently requires at
		 * least one normal selected product.
		 *
		 * Combo / Order Bump are additional purchase
		 * contexts and do not replace that requirement.
		 */
		if (items.length === 0) {
			if (
				state.applied ||
				state.pending
			) {
				state.code =
					couponCode;

				state.pendingReason =
					'empty_order';

				resetState(
					state,
					true
				);

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);
			}

			setMessage(
				section,
				getText(
					'selectProduct',
					'Please select at least one product.'
				),
				silent
					? 'info'
					: 'error'
			);

			return;
		}

		if (
			state.controller instanceof
				AbortController
		) {
			state.controller.abort();
		}

		const controller =
			new AbortController();

		const requestId =
			state.requestId + 1;

		state.requestId =
			requestId;

		state.controller =
			controller;

		if (!silent) {
			setMessage(
				section,
				'',
				'info'
			);
		}

		setLoading(
			section,
			true
		);

		const body =
			new URLSearchParams();

		body.set(
			'action',
			'eilmo_cf_apply_coupon'
		);

		body.set(
			'nonce',
			nonce
		);

		body.set(
			'coupon_code',
			couponCode
		);

		body.set(
			'items',
			JSON.stringify(
				items
			)
		);

		/*
		 * Combo and Order Bump purchase contexts are sent
		 * only as identifiers.
		 *
		 * CouponAjax must resolve and recalculate:
		 *
		 * - Products.
		 * - Quantities.
		 * - Own promotional pricing.
		 * - apply_coupon compatibility.
		 *
		 * Browser values are never authoritative.
		 */
		body.set(
			'combo_offers',
			JSON.stringify(
				getComboRequestData(
					checkout
				)
			)
		);

		body.set(
			'order_bumps',
			JSON.stringify(
				getOrderBumpRequestData(
					checkout
				)
			)
		);

		const customerEmail =
			getCustomerEmail(
				checkout
			);

		const customerPhone =
			getCustomerPhone(
				checkout
			);

		const billingCountry =
			getBillingCountry(
				checkout
			);

		if (customerEmail) {
			body.set(
				'customer_email',
				customerEmail
			);
		}

		if (customerPhone) {
			body.set(
				'customer_phone',
				customerPhone
			);
		}

		if (billingCountry) {
			body.set(
				'billing_country',
				billingCountry
			);
		}

		try {
			const response =
				await fetch(
					ajaxUrl,
					{
						method:
							'POST',

						headers: {
							'Content-Type':
								'application/x-www-form-urlencoded; charset=UTF-8',
						},

						body:
							body.toString(),

						signal:
							controller.signal,

						credentials:
							'same-origin',
					}
				);

			const payload =
				await response.json();

			if (
				state.requestId !==
					requestId
			) {
				return;
			}

			if (
				!response.ok ||
				!payload ||
				payload.success !== true
			) {
				if (
					isCustomerIdentityRequired(
						payload
					)
				) {
					setIdentityPending(
						checkout,
						section,
						state,
						couponCode,
						payload
					);

					return;
				}

				throw new Error(
					getResponseMessage(
						payload,
						getText(
							'error',
							'Something went wrong. Please try again.'
						)
					)
				);
			}

			const data =
				getResponseData(
					payload
				);

			/*
			 * WooCommerce mode currently delegates
			 * authoritative application to native
			 * WooCommerce checkout integration.
			 *
			 * Final native WooCommerce Coupon allocation
			 * is still enforced by OrderCreator using
			 * exact source-aware line-item filtering.
			 */
			if (
				data.delegate_to_woocommerce ===
					true
			) {
				state.applied =
					false;

				state.pending =
					false;

				state.pendingReason =
					'';

				state.identificationMode =
					'';

				state.code =
					'';

				state.discount =
					0;

				state.source =
					'woocommerce';

				state.freeDelivery =
					false;

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);

				setMessage(
					section,
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'WooCommerce coupon recognized.',
					'info'
				);

				return;
			}

			if (
				data.valid !== true ||
				data.applied !== true
			) {
				throw new Error(
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'This coupon cannot be applied.'
				);
			}

			state.applied =
				true;

			state.pending =
				false;

			state.pendingReason =
				'';

			state.identificationMode =
				typeof data.identification_mode ===
					'string'
					? data.identification_mode
					: state.identificationMode;

			state.code =
				typeof data.coupon_code ===
					'string'
					? data.coupon_code
					: couponCode;

			state.discount =
				toNumber(
					data.coupon_discount ||
					0
				);

			state.source =
				typeof data.source ===
					'string'
					? data.source
					: 'eilmo';

			state.freeDelivery =
				data.free_delivery ===
					true;

			renderAppliedState(
				section,
				state
			);

			syncCheckout(
				checkout,
				state
			);

			if (!silent) {
				setMessage(
					section,
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'Coupon applied successfully.',
					'success'
				);
			} else {
				setMessage(
					section,
					'',
					'info'
				);
			}
		} catch (error) {
			if (
				error &&
				error.name ===
					'AbortError'
			) {
				return;
			}

			if (
				state.requestId !==
					requestId
			) {
				return;
			}

			const message =
				error instanceof Error &&
				error.message
					? error.message
					: getText(
						'error',
						'Something went wrong. Please try again.'
					);

			/*
			 * Applied/pending Coupon became invalid.
			 * Remove every checkout discount effect.
			 */
			if (
				state.applied ||
				state.pending
			) {
				state.pendingReason =
					'';

				resetState(
					state,
					false
				);

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);
			}

			setMessage(
				section,
				message,
				'error'
			);
		} finally {
			if (
				state.requestId ===
					requestId
			) {
				state.controller =
					null;

				setLoading(
					section,
					false
				);
			}
		}
	}

	/**
	 * Schedule Coupon revalidation.
	 *
	 * Used when:
	 * - Product quantity changes.
	 * - Combo Offer selection changes.
	 * - Order Bump selection changes.
	 * - Automatic Discount changes.
	 * - Customer identity changes.
	 *
	 * Applied and pending Coupons can both be
	 * revalidated automatically.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function scheduleRevalidation(
		checkout
	) {
		const state =
			getState(
				checkout
			);

		if (
			!(
				state.applied ||
				state.pending
			) ||
			!state.code
		) {
			return;
		}

		clearRefreshTimer(
			checkout
		);

		const timer =
			window.setTimeout(
				function () {
					refreshTimers.delete(
						checkout
					);

					const section =
						getSection(
							checkout
						);

					if (!section) {
						return;
					}

					applyCoupon(
						checkout,
						section,
						state.code,
						true
					);
				},
				350
			);

		refreshTimers.set(
			checkout,
			timer
		);
	}

	/**
	 * Determine whether element is a customer
	 * identity field relevant to Coupon eligibility.
	 *
	 * @param {Element} element Element.
	 *
	 * @return {boolean}
	 */
	function isCustomerIdentityField(element) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return false;
		}

		return (
			element.matches(
				SELECTORS.customerEmail
			) ||
			element.matches(
				SELECTORS.customerPhone
			) ||
			element.matches(
				SELECTORS.billingCountry
			)
		);
	}

	/**
	 * Handle Coupon form.
	 *
	 * @param {SubmitEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSubmit(event) {
		const form =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(form, 'FORM')
			)
		) {
			return;
		}

		if (
			!form.matches(
				SELECTORS.form
			)
		) {
			return;
		}

		event.preventDefault();

		const checkout =
			getCheckout(
				form
			);

		if (!checkout) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		const input =
			section.querySelector(
				SELECTORS.input
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return;
		}

		applyCoupon(
			checkout,
			section,
			input.value,
			false
		);
	}

	/**
	 * Handle Coupon remove.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(event) {
		const target =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(target)
			)
		) {
			return;
		}

		const remove =
			target.closest(
				SELECTORS.remove
			);

		if (
			!(
				window.eilmoCfDom.isElement(remove)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				remove
			);

		if (!checkout) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		event.preventDefault();

		clearCoupon(
			checkout,
			section,
			true
		);
	}

	/**
	 * Handle module events that can affect
	 * Coupon eligibility.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCheckoutChange(
		event
	) {
		let checkout =
			null;

		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			checkout =
				event.detail.checkout;
		} else if (
			window.eilmoCfDom.isElement(event.target)
		) {
			checkout =
				getCheckout(
					event.target
				);
		}

		if (!checkout) {
			return;
		}

		scheduleRevalidation(
			checkout
		);
	}

	/**
	 * Handle direct customer field input/change.
	 *
	 * This allows customer-limited Coupons to work
	 * before a dedicated customer-form module begins
	 * dispatching eilmo:customerChange.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleCustomerFieldChange(event) {
		const target =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(target)
			) ||
			!isCustomerIdentityField(
				target
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				target
			);

		if (!checkout) {
			return;
		}

		scheduleRevalidation(
			checkout
		);
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(
		checkout
	) {
		if (
			initialized.has(
				checkout
			)
		) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		initialized.add(
			checkout
		);

		const state =
			getState(
				checkout
			);

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);
	}

	/**
	 * Initialize all Coupon sections.
	 *
	 * @return {void}
	 */
	function initialize() {
		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if (
						window.eilmoCfDom.isElement(checkout)
					) {
						initializeCheckout(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Public Coupon API.
	 */
	window.eilmoCfCoupon = {

		/**
		 * Apply Coupon.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      code     Coupon code.
		 *
		 * @return {Promise<void>}
		 */
		apply: function (
			checkout,
			code
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return Promise.resolve();
			}

			const section =
				getSection(
					checkout
				);

			if (!section) {
				return Promise.resolve();
			}

			return applyCoupon(
				checkout,
				section,
				code,
				false
			);
		},

		/**
		 * Remove Coupon.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		remove: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return;
			}

			const section =
				getSection(
					checkout
				);

			if (section) {
				clearCoupon(
					checkout,
					section,
					false
				);
			}
		},

		/**
		 * Revalidate Coupon.
		 *
		 * @param {HTMLElement|null} checkout Checkout.
		 *
		 * @return {void}
		 */
		refresh: function (
			checkout
		) {
			if (
				window.eilmoCfDom.isElement(checkout)
			) {
				scheduleRevalidation(
					checkout
				);

				return;
			}

			document
				.querySelectorAll(
					SELECTORS.checkout
				)
				.forEach(
					function (item) {
						if (
							window.eilmoCfDom.isElement(item)
						) {
							scheduleRevalidation(
								item
							);
						}
					}
				);
		},

		/**
		 * Get Coupon result.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getResult: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return null;
			}

			const state =
				getState(
					checkout
				);

			return {
				applied:
					state.applied ===
					true,

				pending:
					state.pending ===
					true,

				pendingReason:
					state.pendingReason,

				identificationMode:
					state.identificationMode,

				code:
					state.code,

				discount:
					toNumber(
						state.discount
					),

				source:
					state.source,

				freeDelivery:
					state.freeDelivery ===
					true,
			};
		},

		/**
		 * Get Coupon discount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getDiscount: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return 0;
			}

			const state =
				getState(
					checkout
				);

			return state.applied
				? toNumber(
					state.discount
				)
				: 0;
		},
	};

	/*
	 * Coupon form.
	 */
	document.addEventListener(
		'submit',
		handleSubmit
	);

	/*
	 * Remove Coupon.
	 */
	document.addEventListener(
		'click',
		handleClick
	);

	/*
	 * Product changes may change:
	 * - Minimum spend.
	 * - Maximum spend.
	 * - Percentage amount.
	 * - Product restrictions.
	 */
	document.addEventListener(
		'eilmo:quantityChange',
		handleCheckoutChange
	);

	/*
	 * Multiple Products root selection sync can change Coupon
	 * minimum/maximum spend and product/variation restrictions.
	 * The revalidation scheduler coalesces this with quantity events.
	 */
	document.addEventListener(
		'eilmo:multipleProductsChange',
		handleCheckoutChange
	);

	/*
	 * Combo Offer selection can change the Coupon-
	 * eligible payable total when apply_coupon is ON.
	 */
	document.addEventListener(
		'eilmo:comboChange',
		handleCheckoutChange
	);

	/*
	 * Order Bump selection can change the Coupon-
	 * eligible payable total when apply_coupon is ON.
	 */
	document.addEventListener(
		'eilmo:orderBumpChange',
		handleCheckoutChange
	);

	/*
	 * Automatic Discount can change the
	 * Coupon calculation basis.
	 */
	document.addEventListener(
		'eilmo:discountChange',
		handleCheckoutChange
	);

	/*
	 * Dedicated customer-form module event.
	 */
	document.addEventListener(
		'eilmo:customerChange',
		handleCheckoutChange
	);

	/*
	 * Direct customer fields.
	 */
	document.addEventListener(
		'input',
		handleCustomerFieldChange
	);

	document.addEventListener(
		'change',
		handleCustomerFieldChange
	);

	if (
		document.readyState ===
			'loading'
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
