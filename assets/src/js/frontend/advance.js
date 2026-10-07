/**
 * Eilmo Checkout Flow - Payment Options.
 *
 * Handles Cash on Delivery, Advance Payment and Full Payment Discount.
 * Frontend calculations are previews only. Final totals
 * must always be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const selectors = {
		checkout: '[data-eilmo-checkout]',
		section: '[data-eilmo-advance-payment]',
		option: '[data-eilmo-payment-option]',
		paymentType: '[data-eilmo-payment-type]',
		paymentMethods: '[data-eilmo-payment-methods]',
		advanceLabel: '[data-eilmo-advance-option-label]',
		advanceAmount: '[data-eilmo-advance-option-amount]',
		advanceSubtitle: '[data-eilmo-advance-option-subtitle]',
		advanceBadge: '[data-eilmo-advance-payment-badge]',
		fullLabel: '[data-eilmo-full-payment-label]',
		fullDescription: '[data-eilmo-full-payment-description]',
		fullDiscountBadge: '[data-eilmo-full-payment-discount-badge]',
		paymentContext: '[data-eilmo-payment-context]',
		paymentContextTitle: '[data-eilmo-payment-context-title]',
		paymentContextDescription: '[data-eilmo-payment-context-description]',
		paymentMethodsPayNote: '[data-eilmo-payment-methods-pay-note]',
		error: '[data-eilmo-advance-error]',
		quantityInput: '[data-eilmo-quantity-input]',
		productItem: '[data-eilmo-product-item], [data-eilmo-item]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',
		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',
		deliveryInput: '[data-eilmo-delivery-input]:checked',
	};

	const initialized = new WeakSet();
	const scheduled = new WeakSet();
	const results = new WeakMap();

	const paymentOptionIcons = Object.freeze({
		cash_on_delivery:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M6.5 9.5H8M16 14.5H17.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" stroke="currentColor" stroke-width="1.7"/></svg>',
		advance:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M4 7.5A3.5 3.5 0 0 1 7.5 4H18a2 2 0 0 1 2 2v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><rect x="4" y="7" width="17" height="13" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M15 11h7v5h-7a2.5 2.5 0 0 1 0-5Z" stroke="currentColor" stroke-width="1.7"/><circle cx="16" cy="13.5" r=".8" fill="currentColor"/></svg>',
		full:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="m15.5 15 1.4 1.4 2.7-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	});

	const paymentMethodFallbackIcon =
		'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M4 10h16M8 15h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

	function addPaymentOptionIcons(section) {
		section
			.querySelectorAll(selectors.option)
			.forEach(function (option) {
				if (
					!(window.eilmoCfDom.isElement(option)) ||
					option.querySelector('.eilmo-cf-advance-payment-option__type-icon')
				) {
					return;
				}

				const paymentType = normalizePaymentType(
					option.dataset.paymentType || ''
				);
				const iconMarkup = paymentOptionIcons[paymentType];

				if (!iconMarkup) {
					return;
				}

				const icon = document.createElement('span');
				icon.className = 'eilmo-cf-advance-payment-option__type-icon';
				icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = iconMarkup;

				const content = option.querySelector(
					'.eilmo-cf-advance-payment-option__content'
				);

				option.insertBefore(icon, content || null);
			});
	}

	function addPaymentMethodFallbackIcons(wrapper) {
		wrapper
			.querySelectorAll('[data-eilmo-payment-method]')
			.forEach(function (method) {
				if (!(window.eilmoCfDom.isElement(method))) {
					return;
				}

				const heading = method.querySelector(
					'.eilmo-cf-payment-method__heading'
				);

				if (
					!(window.eilmoCfDom.isElement(heading)) ||
					heading.querySelector('.eilmo-cf-payment-method__icon')
				) {
					return;
				}

				const icon = document.createElement('span');
				icon.className =
					'eilmo-cf-payment-method__icon eilmo-cf-payment-method__icon--fallback';
				icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = paymentMethodFallbackIcon;
				heading.appendChild(icon);
			});
	}

	function prepareUnifiedPaymentExperience(checkout, section) {
		section.classList.add(
			'eilmo-cf-advance-payment--unified'
		);

		addPaymentOptionIcons(section);

		const methodsWrapper = checkout.querySelector(
			'.eilmo-cf-checkout__payment-methods'
		);
		const paymentFooter = checkout.querySelector(
			'.eilmo-cf-checkout__payment-footer'
		);
		const error = section.querySelector(
			'[data-eilmo-advance-error]'
		);
		const oldContext = section.querySelector(
			selectors.paymentContext
		);
		let completion = section.querySelector(
			'.eilmo-cf-advance-payment__completion'
		);

		if (window.eilmoCfDom.isElement(oldContext)) {
			oldContext.remove();
		}

		if (!(window.eilmoCfDom.isElement(completion))) {
			completion = document.createElement('div');
			completion.className =
				'eilmo-cf-advance-payment__completion';
			completion.hidden = true;
			section.insertBefore(completion, error || null);
		}

		if (window.eilmoCfDom.isElement(methodsWrapper)) {
			methodsWrapper.classList.add(
				'eilmo-cf-advance-payment__methods'
			);
			completion.appendChild(methodsWrapper);
			addPaymentMethodFallbackIcons(methodsWrapper);
		}

		if (window.eilmoCfDom.isElement(paymentFooter)) {
			paymentFooter.classList.add(
				'eilmo-cf-advance-payment__footer'
			);
			section.insertBefore(paymentFooter, error || null);
		}
	}

	function toNumber(value) {
		const number = Number.parseFloat(String(value));

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(0, number);
	}

	function getPriceDecimals() {
		const config = window.eilmoCf || {};

		const currency =
			config.currency &&
			'object' === typeof config.currency
				? config.currency
				: {};

		let decimals = Number(currency.decimals);

		if (!Number.isFinite(decimals)) {
			decimals = Number(config.priceDecimals);
		}

		if (!Number.isFinite(decimals)) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(decimals)
		);
	}

	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(value) +
					Number.EPSILON
				) *
					multiplier
			) /
				multiplier
		);
	}

	function isEnabled(value) {
		return (
			true === value ||
			1 === value ||
			'1' === value ||
			'yes' === value ||
			'true' === value
		);
	}

	function parseJSON(
		value,
		fallback
	) {
		if (!value) {
			return fallback;
		}

		try {
			const parsed =
				JSON.parse(value);

			return null === parsed
				? fallback
				: parsed;
		} catch (error) {
			return fallback;
		}
	}

	function decodeHTML(value) {
		const element =
			document.createElement(
				'textarea'
			);

		element.innerHTML =
			String(value || '');

		return element.value;
	}

	function stripHTML(value) {
		const element =
			document.createElement(
				'div'
			);

		element.innerHTML =
			String(value || '');

		return decodeHTML(
			element.textContent ||
			element.innerText ||
			''
		);
	}

	function getCurrencyConfig() {
		const config = window.eilmoCf || {};

		const currency =
			config.currency &&
			'object' === typeof config.currency
				? config.currency
				: {};

		return {
			symbol:
				decodeHTML(
					'string' === typeof currency.symbol
						? currency.symbol
						: config.currencySymbol || '৳'
				),

			position:
				'string' === typeof currency.position
					? currency.position
					: config.currencyPosition || 'left',

			decimals:
				getPriceDecimals(),

			decimalSeparator:
				'string' === typeof currency.decimalSeparator
					? decodeHTML(
						currency.decimalSeparator
					)
					: config.decimalSeparator || '.',

			thousandSeparator:
				'string' === typeof currency.thousandSeparator
					? decodeHTML(
						currency.thousandSeparator
					)
					: config.thousandSeparator || ',',
		};
	}

	function formatPrice(amount) {
		const safeAmount =
			roundMoney(amount);

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window.eilmoCfSummary.formatPrice
		) {
			return stripHTML(
				window.eilmoCfSummary.formatPrice(
					safeAmount
				)
			);
		}

		const config =
			getCurrencyConfig();

		const parts =
			safeAmount
				.toFixed(config.decimals)
				.split('.');

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		const formattedNumber =
			config.decimals > 0
				? (
					parts[0] +
					config.decimalSeparator +
					parts[1]
				)
				: parts[0];

		switch (config.position) {
			case 'right':
				return (
					formattedNumber +
					config.symbol
				);

			case 'right_space':
				return (
					formattedNumber +
					' ' +
					config.symbol
				);

			case 'left_space':
				return (
					config.symbol +
					' ' +
					formattedNumber
				);

			case 'left':
			default:
				return (
					config.symbol +
					formattedNumber
				);
		}
	}

	function replaceTokens(
		text,
		values
	) {
		let output =
			String(text || '');

		Object.keys(
			values
		).forEach(
			function (key) {
				output =
					output
						.split(
							'{' +
								key +
								'}'
						)
						.join(
							String(
								values[key]
							)
						);
			}
		);

		return output;
	}

	function formatPercentage(value) {
		const number =
			Math.min(
				100,
				toNumber(value)
			);

		return (
			Number.parseFloat(
				number.toFixed(2)
			).toString() +
				'%'
		);
	}

	function getCheckoutFromEvent(event) {
		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail.checkout;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			const checkout =
				event.target.closest(
					selectors.checkout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	function calculateProductTotal(
		checkout
	) {
		let total = 0;
		let matchedItems = 0;

		checkout
			.querySelectorAll(
				selectors.productItem
			)
			.forEach(
				function (item) {
					if (
						!(
							window.eilmoCfDom.isElement(item)
						)
					) {
						return;
					}

					if (
						item.closest(
							selectors.comboOffer
						) ||
						item.closest(
							selectors.orderBumpOffer
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							selectors.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toNumber(input.value);

					if (quantity <= 0) {
						return;
					}

					let price =
						toNumber(
							item.dataset.price
						);

					if (price <= 0) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (price <= 0) {
						price =
							toNumber(
								input.dataset.price
							);
					}

					if (price <= 0) {
						return;
					}

					total +=
						price *
							quantity;

					matchedItems += 1;
				}
			);

		/*
		 * Native WooCommerce checkout already owns the cart. It does not
		 * render Eilmo's product-selector rows, so DOM-only calculation
		 * would incorrectly report an empty product total and replace the
		 * server-rendered Advance preview with “Select a product…”.
		 *
		 * The native bridge mirrors WooCommerce's authoritative cart total
		 * onto the checkout form. Use that value only when no Eilmo product
		 * rows exist, keeping Quick Checkout behavior unchanged.
		 */
		if (0 === matchedItems && window.eilmoCfDom.isElement(checkout)) {
			const nativeTotal = toNumber(
				checkout.dataset.productTotal || 0
			);

			if (nativeTotal > 0) {
				return roundMoney(nativeTotal);
			}

			const section = checkout.querySelector(
				selectors.section
			);

			if (window.eilmoCfDom.isElement(section)) {
				const sectionTotal = toNumber(
					section.dataset.productTotal || 0
				);

				if (sectionTotal > 0) {
					return roundMoney(sectionTotal);
				}
			}
		}

		return roundMoney(total);
	}

	function getPromotionalTotals(
		checkout,
		config
	) {
		let regularTotal = 0;
		let discount = 0;
		let payableTotal = 0;
		let selectedCount = 0;

		let automaticEligibleTotal = 0;
		let couponEligibleTotal = 0;

		let fullPaymentEligibleRegularTotal = 0;
		let fullPaymentEligibleTotal = 0;

		let automaticFullPaymentOverlapTotal = 0;
		let automaticCouponOverlapTotal = 0;
		let fullPaymentCouponOverlapTotal = 0;
		let automaticFullPaymentCouponOverlapTotal = 0;

		checkout
			.querySelectorAll(
				config.offerSelector
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
							config.inputSelector
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

					if (
						config.checkAvailability &&
						'no' ===
							offer.dataset.available
					) {
						return;
					}

					const offerRegularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const offerDiscount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset[
										config.discountKey
									] ||
										0
								),
								offerRegularTotal
							)
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								offerRegularTotal -
									offerDiscount
							)
						);

					const rawPayable =
						undefined !==
							offer.dataset[
								config.payableKey
							] &&
						'' !==
							String(
								offer.dataset[
									config.payableKey
								]
							).trim()
							? offer.dataset[
								config.payableKey
							]
							: fallbackTotal;

					const offerPayableTotal =
						roundMoney(
							Math.min(
								offerRegularTotal,
								toNumber(
									rawPayable
								)
							)
						);

					const automaticEligible =
						'yes' ===
							offer.dataset
								.applyAutomaticDiscount;

					const couponEligible =
						config.couponDefaultYes
							? (
								'no' !==
									offer.dataset
										.applyCoupon
							)
							: (
								'yes' ===
									offer.dataset
										.applyCoupon
							);

					const fullPaymentEligible =
						'no' !==
							offer.dataset
								.applyFullPaymentDiscount;

					regularTotal =
						roundMoney(
							regularTotal +
								offerRegularTotal
						);

					discount =
						roundMoney(
							discount +
								offerDiscount
						);

					payableTotal =
						roundMoney(
							payableTotal +
								offerPayableTotal
						);

					selectedCount += 1;

					if (automaticEligible) {
						automaticEligibleTotal =
							roundMoney(
								automaticEligibleTotal +
									offerPayableTotal
							);
					}

					if (couponEligible) {
						couponEligibleTotal =
							roundMoney(
								couponEligibleTotal +
									offerPayableTotal
							);
					}

					if (fullPaymentEligible) {
						fullPaymentEligibleRegularTotal =
							roundMoney(
								fullPaymentEligibleRegularTotal +
									offerRegularTotal
							);

						fullPaymentEligibleTotal =
							roundMoney(
								fullPaymentEligibleTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						fullPaymentEligible
					) {
						automaticFullPaymentOverlapTotal =
							roundMoney(
								automaticFullPaymentOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						couponEligible
					) {
						automaticCouponOverlapTotal =
							roundMoney(
								automaticCouponOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						fullPaymentEligible &&
						couponEligible
					) {
						fullPaymentCouponOverlapTotal =
							roundMoney(
								fullPaymentCouponOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						fullPaymentEligible &&
						couponEligible
					) {
						automaticFullPaymentCouponOverlapTotal =
							roundMoney(
								automaticFullPaymentCouponOverlapTotal +
									offerPayableTotal
							);
					}
				}
			);

		return {
			selectedCount:
				selectedCount,

			regularTotal:
				roundMoney(
					regularTotal
				),

			discount:
				roundMoney(
					Math.min(
						discount,
						regularTotal
					)
				),

			payableTotal:
				roundMoney(
					payableTotal
				),

			automaticEligibleTotal:
				roundMoney(
					automaticEligibleTotal
				),

			couponEligibleTotal:
				roundMoney(
					couponEligibleTotal
				),

			fullPaymentEligibleRegularTotal:
				roundMoney(
					fullPaymentEligibleRegularTotal
				),

			fullPaymentEligibleTotal:
				roundMoney(
					fullPaymentEligibleTotal
				),

			automaticFullPaymentOverlapTotal:
				roundMoney(
					automaticFullPaymentOverlapTotal
				),

			automaticCouponOverlapTotal:
				roundMoney(
					automaticCouponOverlapTotal
				),

			fullPaymentCouponOverlapTotal:
				roundMoney(
					fullPaymentCouponOverlapTotal
				),

			automaticFullPaymentCouponOverlapTotal:
				roundMoney(
					automaticFullPaymentCouponOverlapTotal
				),
		};
	}

	function getComboTotals(
		checkout
	) {
		const totals =
			getPromotionalTotals(
				checkout,
				{
					offerSelector:
						selectors.comboOffer,

					inputSelector:
						selectors.comboInput,

					discountKey:
						'comboDiscount',

					payableKey:
						'comboTotal',

					couponDefaultYes:
						true,

					checkAvailability:
						true,
				}
			);

		return {
			...totals,

			comboTotal:
				totals.payableTotal,
		};
	}

	function getOrderBumpTotals(
		checkout
	) {
		const totals =
			getPromotionalTotals(
				checkout,
				{
					offerSelector:
						selectors.orderBumpOffer,

					inputSelector:
						selectors.orderBumpInput,

					discountKey:
						'discount',

					payableKey:
						'bumpTotal',

					couponDefaultYes:
						false,

					checkAvailability:
						false,
				}
			);

		return {
			...totals,

			bumpTotal:
				totals.payableTotal,
		};
	}

	function getDeliveryCharge(
		checkout
	) {
		if (
			'string' ===
				typeof checkout.dataset
					.deliveryCharge
		) {
			return roundMoney(
				checkout.dataset
					.deliveryCharge
			);
		}

		const input =
			checkout.querySelector(
				selectors.deliveryInput
			);

		if (!input) {
			return 0;
		}

		return roundMoney(
			input.dataset.charge ||
				0
		);
	}

	function getAutomaticDiscount(
		checkout
	) {
		if (
			window.eilmoCfDiscount &&
			'function' ===
				typeof window
					.eilmoCfDiscount
					.getDiscount
		) {
			return roundMoney(
				window
					.eilmoCfDiscount
					.getDiscount(
						checkout
					)
			);
		}

		if (
			'string' ===
				typeof checkout.dataset
					.automaticDiscount
		) {
			return roundMoney(
				checkout.dataset
					.automaticDiscount
			);
		}

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getAmount
		) {
			return roundMoney(
				window
					.eilmoCfSummary
					.getAmount(
						checkout,
						'automatic-discount'
					)
			);
		}

		return 0;
	}

	function getCouponDiscount(
		checkout
	) {
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getAmount
		) {
			return roundMoney(
				window
					.eilmoCfSummary
					.getAmount(
						checkout,
						'coupon-discount'
					)
			);
		}

		return roundMoney(
			checkout.dataset
				.couponDiscount ||
				0
		);
	}

	function allocateDiscountShare(
		discount,
		sourceTotal,
		subsetTotal
	) {
		const safeDiscount =
			roundMoney(
				discount
			);

		const safeSourceTotal =
			roundMoney(
				sourceTotal
			);

		const safeSubsetTotal =
			roundMoney(
				Math.min(
					subsetTotal,
					safeSourceTotal
				)
			);

		if (
			safeDiscount <= 0 ||
			safeSourceTotal <= 0 ||
			safeSubsetTotal <= 0
		) {
			return 0;
		}

		return roundMoney(
			Math.min(
				safeSubsetTotal,
				safeDiscount *
					(
						safeSubsetTotal /
							safeSourceTotal
					)
			)
		);
	}

	function getTotals(
		checkout
	) {
		const baseProductTotal =
			calculateProductTotal(
				checkout
			);

		const combo =
			getComboTotals(
				checkout
			);

		const orderBump =
			getOrderBumpTotals(
				checkout
			);

		const productTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal +
					orderBump.regularTotal
			);

		const comboDiscount =
			roundMoney(
				Math.min(
					combo.discount,
					combo.regularTotal
				)
			);

		const orderBumpDiscount =
			roundMoney(
				Math.min(
					orderBump.discount,
					orderBump.regularTotal
				)
			);

		const payableProductTotalBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.comboTotal +
					orderBump.bumpTotal
			);

		const automaticDiscountBase =
			roundMoney(
				baseProductTotal +
					combo.automaticEligibleTotal +
					orderBump.automaticEligibleTotal
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAutomaticDiscount(
						checkout
					),
					automaticDiscountBase,
					payableProductTotalBeforeAutomatic
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					payableProductTotalBeforeAutomatic -
						automaticDiscount
				)
			);

		const couponDiscount =
			roundMoney(
				Math.min(
					getCouponDiscount(
						checkout
					),
					afterAutomaticDiscount
				)
			);

		const discountedProductTotal =
			roundMoney(
				Math.max(
					0,
					afterAutomaticDiscount -
						couponDiscount
				)
			);

		const deliveryCharge =
			getDeliveryCharge(
				checkout
			);

		const fullPaymentProductTotal =
			roundMoney(
				baseProductTotal +
					combo
						.fullPaymentEligibleRegularTotal +
					orderBump
						.fullPaymentEligibleRegularTotal
			);

		const fullPaymentPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.fullPaymentEligibleTotal +
					orderBump.fullPaymentEligibleTotal
			);

		const automaticFullPaymentOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticFullPaymentOverlapTotal +
					orderBump
						.automaticFullPaymentOverlapTotal
			);

		const fullPaymentAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticFullPaymentOverlapBase
			);

		const fullPaymentAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					fullPaymentPayableBeforeAutomatic -
						fullPaymentAutomaticDiscount
				)
			);

		const couponPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.couponEligibleTotal +
					orderBump.couponEligibleTotal
			);

		const automaticCouponOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticCouponOverlapTotal +
					orderBump
						.automaticCouponOverlapTotal
			);

		const couponAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticCouponOverlapBase
			);

		const couponPayableAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					couponPayableBeforeAutomatic -
						couponAutomaticDiscount
				)
			);

		const fullPaymentCouponPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo
						.fullPaymentCouponOverlapTotal +
					orderBump
						.fullPaymentCouponOverlapTotal
			);

		const automaticFullPaymentCouponOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticFullPaymentCouponOverlapTotal +
					orderBump
						.automaticFullPaymentCouponOverlapTotal
			);

		const fullPaymentCouponAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticFullPaymentCouponOverlapBase
			);

		const fullPaymentCouponPayableAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					fullPaymentCouponPayableBeforeAutomatic -
						fullPaymentCouponAutomaticDiscount
				)
			);

		const fullPaymentCouponDiscount =
			allocateDiscountShare(
				couponDiscount,
				couponPayableAfterAutomatic,
				fullPaymentCouponPayableAfterAutomatic
			);

		const fullPaymentDiscountedProductTotal =
			roundMoney(
				Math.max(
					0,
					fullPaymentAfterAutomatic -
						fullPaymentCouponDiscount
				)
			);

		const fullPaymentGrandTotal =
			roundMoney(
				fullPaymentDiscountedProductTotal +
					deliveryCharge
			);

		return {
			baseProductTotal:
				roundMoney(
					baseProductTotal
				),

			comboSelectedCount:
				combo.selectedCount,

			comboRegularTotal:
				roundMoney(
					combo.regularTotal
				),

			comboDiscount:
				comboDiscount,

			comboTotal:
				roundMoney(
					combo.comboTotal
				),

			orderBumpSelectedCount:
				orderBump.selectedCount,

			orderBumpRegularTotal:
				roundMoney(
					orderBump.regularTotal
				),

			orderBumpDiscount:
				orderBumpDiscount,

			orderBumpTotal:
				roundMoney(
					orderBump.bumpTotal
				),

			productTotal:
				productTotal,

			payableProductTotalBeforeAutomatic:
				payableProductTotalBeforeAutomatic,

			automaticDiscountBase:
				automaticDiscountBase,

			automaticDiscount:
				automaticDiscount,

			couponDiscount:
				couponDiscount,

			discountedProductTotal:
				discountedProductTotal,

			deliveryCharge:
				roundMoney(
					deliveryCharge
				),

			grandTotal:
				roundMoney(
					discountedProductTotal +
						deliveryCharge
				),

			fullPaymentProductTotal:
				fullPaymentProductTotal,

			fullPaymentPayableBeforeAutomatic:
				fullPaymentPayableBeforeAutomatic,

			fullPaymentAutomaticDiscount:
				fullPaymentAutomaticDiscount,

			fullPaymentCouponDiscount:
				fullPaymentCouponDiscount,

			fullPaymentDiscountedProductTotal:
				fullPaymentDiscountedProductTotal,

			fullPaymentGrandTotal:
				fullPaymentGrandTotal,
		};
	}

	function getBasisAmount(
		basis,
		totals
	) {
		switch (basis) {
			case 'product_total':
				return totals.productTotal;

			case 'discounted_product_total':
				return totals
					.discountedProductTotal;

			case 'grand_total':
			default:
				return totals.grandTotal;
		}
	}

	function getFullPaymentBasisAmount(
		basis,
		totals
	) {
		switch (basis) {
			case 'product_total':
				return totals
					.fullPaymentProductTotal;

			case 'grand_total':
				return totals
					.fullPaymentGrandTotal;

			case 'discounted_product_total':
			default:
				return totals
					.fullPaymentDiscountedProductTotal;
		}
	}

	function ruleMatches(
		rule,
		amount
	) {
		if (
			false === rule.enabled ||
			'no' === rule.enabled
		) {
			return false;
		}

		const minimum =
			toNumber(
				rule.minimum_amount
			);

		const maximum =
			toNumber(
				rule.maximum_amount
			);

		if (amount < minimum) {
			return false;
		}

		if (
			maximum > 0 &&
			amount >= maximum
		) {
			return false;
		}

		return true;
	}

	function findMatchingAdvanceRule(
		rules,
		amount
	) {
		if (
			!Array.isArray(rules) ||
			0 === rules.length
		) {
			return null;
		}

		let matchedRule = null;

		const enabledRules = [];

		for (
			let index = 0;
			index < rules.length;
			index += 1
		) {
			const rule =
				rules[index];

			if (
				!rule ||
				'object' !== typeof rule ||
				false === rule.enabled ||
				'no' === rule.enabled
			) {
				continue;
			}

			enabledRules.push(
				{
					rule:
						rule,

					index:
						index,

					minimum:
						toNumber(
							rule.minimum_amount
						),

					maximum:
						toNumber(
							rule.maximum_amount
						),
				}
			);

			if (
				!ruleMatches(
					rule,
					amount
				)
			) {
				continue;
			}

			matchedRule =
				rule;

			if (
				isEnabled(
					rule.stop_processing
				)
			) {
				break;
			}
		}

		if (matchedRule) {
			return matchedRule;
		}

		if (
			0 ===
				enabledRules.length
		) {
			return null;
		}

		enabledRules.sort(
			function (
				first,
				second
			) {
				if (
					first.minimum !==
						second.minimum
				) {
					return (
						second.minimum -
							first.minimum
					);
				}

				return (
					first.index -
						second.index
				);
			}
		);

		const finalTier =
			enabledRules[0];

		if (!finalTier) {
			return null;
		}

		if (
			finalTier.maximum <= 0 &&
			amount >=
				finalTier.minimum
		) {
			return finalTier.rule;
		}

		if (
			finalTier.maximum > 0 &&
			amount >=
				finalTier.maximum
		) {
			return finalTier.rule;
		}

		return null;
	}

	function calculateRule(
		rule,
		basisAmount,
		grandTotal
	) {
		const type =
			String(
				rule.type ||
					'fixed'
			);

		let advanceAmount = 0;
		let payNow = 0;

		switch (type) {
			case 'percentage':

				advanceAmount =
					basisAmount *
						(
							Math.min(
								100,
								toNumber(
									rule.value
								)
							) /
								100
						);

				payNow =
					advanceAmount;

				break;

			case 'full_payment':

				advanceAmount =
					grandTotal;

				payNow =
					grandTotal;

				break;

			case 'no_advance':

				advanceAmount =
					0;

				payNow =
					0;

				break;

			case 'fixed':
			default:

				advanceAmount =
					toNumber(
						rule.value
					);

				payNow =
					advanceAmount;

				break;
		}

		if (
			'fixed' === type ||
			'percentage' === type
		) {
			const minimumPay =
				toNumber(
					rule.minimum_pay_amount
				);

			const maximumPay =
				toNumber(
					rule.maximum_pay_amount
				);

			if (
				minimumPay > 0 &&
				payNow < minimumPay
			) {
				payNow =
					minimumPay;
			}

			if (
				maximumPay > 0 &&
				payNow > maximumPay
			) {
				payNow =
					maximumPay;
			}

			payNow =
				Math.min(
					payNow,
					grandTotal
				);

			advanceAmount =
				payNow;
		}

		payNow =
			roundMoney(
				Math.min(
					payNow,
					grandTotal
				)
			);

		advanceAmount =
			roundMoney(
				Math.min(
					advanceAmount,
					grandTotal
				)
			);

		return {
			ruleId:
				String(
					rule.id ||
						''
				),

			ruleType:
				type,

			advanceAmount:
				advanceAmount,

			payNow:
				payNow,

			remainingDue:
				roundMoney(
					Math.max(
						0,
						grandTotal -
							payNow
					)
				),
		};
	}

	function calculateAdvance(
		section,
		totals
	) {
		if (
			totals.productTotal <= 0
		) {
			return {
				ruleId:
					'',

				ruleType:
					'empty_order',

				advanceAmount:
					0,

				payNow:
					0,

				remainingDue:
					0,
			};
		}

		const calculationBasis =
			section.dataset
				.calculationBasis ||
					'grand_total';

		const basisAmount =
			getBasisAmount(
				calculationBasis,
				totals
			);

		const rules =
			parseJSON(
				section.getAttribute(
					'data-rules'
				),
				[]
			);

		const matchedRule =
			findMatchingAdvanceRule(
				rules,
				basisAmount
			);

		if (matchedRule) {
			return calculateRule(
				matchedRule,
				basisAmount,
				totals.grandTotal
			);
		}

		return {
			ruleId:
				'',

			ruleType:
				'fallback_full_payment',

			advanceAmount:
				totals.grandTotal,

			payNow:
				totals.grandTotal,

			remainingDue:
				0,
		};
	}

	function getConfiguredDiscountLabel(
		type,
		value
	) {
		if (
			'fixed' === type
		) {
			return formatPrice(
				value
			);
		}

		return formatPercentage(
			value
		);
	}

	function calculateFullPaymentDiscount(
		section,
		totals
	) {
		const settings =
			parseJSON(
				section.getAttribute(
					'data-full-payment-discount'
				),
				{}
			);

		const result = {
			enabled:
				false,

			offerAvailable:
				false,

			discountOfferAvailable:
				false,

			freeDelivery:
				false,

			freeDeliveryBadge:
				'',

			eligible:
				false,

			type:
				'percentage',

			value:
				0,

			basis:
				'discounted_product_total',

			basisAmount:
				0,

			minimumAmount:
				0,

			maximumDiscount:
				0,

			rawSaving:
				0,

			saving:
				0,

			discountedTotal:
				totals.grandTotal,

			discountLabel:
				'',

			eligibleProductTotal:
				roundMoney(
					totals
						.fullPaymentProductTotal ||
						0
				),

			eligibleDiscountedProductTotal:
				roundMoney(
					totals
						.fullPaymentDiscountedProductTotal ||
						0
				),

			eligibleGrandTotal:
				roundMoney(
					totals
						.fullPaymentGrandTotal ||
						0
				),
		};

		if (
			!settings ||
			'object' !==
				typeof settings
		) {
			return result;
		}

		const enabled =
			isEnabled(
				settings.enabled
			);

		const type =
			'fixed' ===
				settings.type
				? 'fixed'
				: 'percentage';

		let value =
			toNumber(
				settings.value
			);

		if (
			'percentage' === type
		) {
			value =
				Math.min(
					100,
					value
				);
		}

		const minimumAmount =
			toNumber(
				settings.minimum_amount
			);

		const maximumDiscount =
			toNumber(
				settings.maximum_discount
			);

		const freeDelivery =
			isEnabled(
				settings.free_delivery
			);

		const freeDeliveryBadge =
			String(
				settings.free_delivery_badge ||
					'Free Delivery'
			);

		const allowedBasis = [
			'product_total',
			'discounted_product_total',
			'grand_total',
		];

		const basis =
			-1 !==
				allowedBasis.indexOf(
					settings.basis
				)
				? settings.basis
				: 'discounted_product_total';

		const fullPaymentGrandTotal =
			roundMoney(
				Math.max(
					0,
					toNumber(totals.fullPaymentGrandTotal) -
						(freeDelivery ? toNumber(totals.deliveryCharge) : 0)
				)
			);

		const fullPaymentTotals = Object.assign({}, totals, {
			fullPaymentGrandTotal: fullPaymentGrandTotal,
		});

		const basisAmount =
			roundMoney(
				getFullPaymentBasisAmount(
					basis,
					fullPaymentTotals
				)
			);

		const discountOfferAvailable =
			enabled &&
			value > 0;

		const offerAvailable =
			discountOfferAvailable ||
			freeDelivery;

		result.enabled =
			enabled;

		result.offerAvailable =
			offerAvailable;

		result.discountOfferAvailable =
			discountOfferAvailable;

		result.freeDelivery =
			freeDelivery;

		result.freeDeliveryBadge =
			freeDeliveryBadge;

		result.type =
			type;

		result.value =
			value;

		result.basis =
			basis;

		result.basisAmount =
			basisAmount;

		result.minimumAmount =
			minimumAmount;

		result.maximumDiscount =
			maximumDiscount;

		result.discountedTotal =
			fullPaymentGrandTotal;

		if (discountOfferAvailable) {
			result.discountLabel =
				getConfiguredDiscountLabel(
					type,
					value
				);
		}

		if (!discountOfferAvailable) {
			return result;
		}

		if (
			totals.productTotal <= 0
		) {
			return result;
		}

		if (
			basisAmount <= 0 ||
			(
				minimumAmount > 0 &&
				basisAmount <
					minimumAmount
			)
		) {
			return result;
		}

		let rawSaving =
			'fixed' === type
				? value
				: (
					basisAmount *
						(
							value /
								100
						)
				);

		rawSaving =
			roundMoney(
				rawSaving
			);

		let saving =
			rawSaving;

		if (
			maximumDiscount > 0 &&
			saving > maximumDiscount
		) {
			saving =
				maximumDiscount;
		}

		saving =
			Math.min(
				saving,
				basisAmount,
				fullPaymentGrandTotal
			);

		saving =
			roundMoney(
				saving
			);

		if (saving <= 0) {
			return result;
		}

		const capped =
			rawSaving > 0 &&
			saving < rawSaving;

		if (
			capped ||
			'fixed' === type
		) {
			result.discountLabel =
				formatPrice(
					saving
				);
		} else {
			result.discountLabel =
				formatPercentage(
					value
				);
		}

		result.eligible =
			true;

		result.rawSaving =
			rawSaving;

		result.saving =
			saving;

		result.discountedTotal =
			roundMoney(
				Math.max(
					0,
					fullPaymentGrandTotal -
						saving
				)
			);

		return result;
	}

	function getTexts(section) {
		const texts =
			parseJSON(
				section.getAttribute(
					'data-texts'
				),
				{}
			);

		return (
			texts &&
			'object' === typeof texts
		)
			? texts
			: {};
	}

	function normalizePaymentType(value) {
		const paymentType =
			String(
				value || ''
			)
				.trim()
				.toLowerCase();

		return [
			'cash_on_delivery',
			'advance',
			'full',
		].includes(
			paymentType
		)
			? paymentType
			: '';
	}

	function getPaymentType(section) {
		const checked =
			section.querySelector(
				selectors.paymentType +
					':checked'
			);

		if (
			window.eilmoCfDom.isElement(checked, 'INPUT')
		) {
			const checkedType =
				normalizePaymentType(
					checked.value
				);

			if (checkedType) {
				return checkedType;
			}
		}

		const currentType =
			normalizePaymentType(
				section.dataset
					.paymentType ||
				section.dataset
					.defaultPaymentType ||
					''
			);

		if (currentType) {
			return currentType;
		}

		const firstAvailable =
			section.querySelector(
				selectors.paymentType +
					':not(:disabled)'
			);

		if (
			window.eilmoCfDom.isElement(firstAvailable, 'INPUT')
		) {
			const fallbackType =
				normalizePaymentType(
					firstAvailable.value
				);

			if (fallbackType) {
				return fallbackType;
			}
		}

		return 'advance';
	}

	function requiresPaymentMethod(
		paymentType
	) {
		return (
			'advance' ===
				paymentType ||
			'full' ===
				paymentType
		);
	}

	function updateOptionState(
		section,
		paymentType
	) {
		const normalizedType =
			normalizePaymentType(
				paymentType
			) ||
			'advance';

		section
			.querySelectorAll(
				selectors.option
			)
			.forEach(
				function (option) {
					if (
						!(
							window.eilmoCfDom.isElement(option)
						)
					) {
						return;
					}

					const optionType =
						normalizePaymentType(
							option.dataset
								.paymentType ||
								''
						);

					const selected =
						optionType ===
							normalizedType;

					const input =
						option.querySelector(
							selectors.paymentType
						);

					if (
						window.eilmoCfDom.isElement(input, 'INPUT')
					) {
						input.checked =
							selected;
					}

					option.classList.toggle(
						'eilmo-cf-advance-payment-option--selected',
						selected
					);

					option.dataset.selected =
						selected
							? 'yes'
							: 'no';

					option.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);
				}
			);

		section.dataset.paymentType =
			normalizedType;
	}

	function updatePaymentMethodsVisibility(
		checkout,
		paymentType
	) {
		const paymentMethods =
			checkout.querySelector(
				selectors.paymentMethods
			);

		const required =
			requiresPaymentMethod(
				paymentType
			);

		checkout.dataset
			.paymentMethodRequired =
				required
					? 'yes'
					: 'no';

		checkout.dataset
			.cashOnDeliverySelected =
				'cash_on_delivery' ===
					paymentType
						? 'yes'
						: 'no';

		if (
			!(
				window.eilmoCfDom.isElement(paymentMethods)
			)
		) {
			return;
		}

		const methodsWrapper = paymentMethods.closest(
			'.eilmo-cf-checkout__payment-methods'
		);

		paymentMethods.dataset
			.requiredByPaymentOption =
				required
					? 'yes'
					: 'no';

		if (!required) {
			paymentMethods.hidden =
				true;

			paymentMethods.setAttribute(
				'aria-hidden',
				'true'
			);

			paymentMethods.dataset
				.hiddenByPaymentOption =
					'yes';

			if (window.eilmoCfDom.isElement(methodsWrapper)) {
				methodsWrapper.hidden = true;
				methodsWrapper.setAttribute(
					'aria-hidden',
					'true'
				);
			}

			return;
		}

		if (window.eilmoCfDom.isElement(methodsWrapper)) {
			methodsWrapper.hidden = false;
			methodsWrapper.removeAttribute('aria-hidden');
		}

		if (
			'yes' ===
				paymentMethods.dataset
					.hiddenByPaymentOption
		) {
			paymentMethods.hidden =
				false;

			paymentMethods.removeAttribute(
				'aria-hidden'
			);

			delete paymentMethods.dataset
				.hiddenByPaymentOption;
		}

		if (
			window.eilmoCfPayment &&
			'function' ===
				typeof window
					.eilmoCfPayment
					.refreshNativeGateway
		) {
			window.eilmoCfPayment
				.refreshNativeGateway(
					checkout
				);
		}
	}

	function getEmptyAdvanceLabel(template) {
		return String(
			template || ''
		)
			.replace(
				/\s*\{pay_now\}\s*/g,
				' '
			)
			.replace(
				/\s+/g,
				' '
			)
			.trim();
	}

	function updateAdvanceLabel(
		label,
		text,
		result,
		totals
	) {
		if (!label) {
			return;
		}

		if (
			totals.productTotal <= 0
		) {
			label.textContent =
				getEmptyAdvanceLabel(
					text
				);

			return;
		}

		const placeholder =
			'__EILMO_PAY_NOW__';

		const rendered =
			replaceTokens(
				String(
					text || ''
				),
				{
					pay_now:
						placeholder,

					remaining_due:
						formatPrice(
							result
								.remainingDue
						),

					grand_total:
						formatPrice(
							totals
								.grandTotal
						),
				}
			);

		if (
			-1 ===
				rendered.indexOf(
					placeholder
				)
		) {
			label.textContent =
				rendered;

			return;
		}

		const parts =
			rendered.split(
				placeholder
			);

		label.textContent =
			'';

		label.appendChild(
			document.createTextNode(
				parts.shift() ||
					''
			)
		);

		const amount =
			document.createElement(
				'strong'
			);

		amount.className =
			'eilmo-cf-advance-payment-option__inline-amount';

		amount.setAttribute(
			'data-eilmo-advance-option-amount',
			''
		);

		amount.dataset.value =
			String(
				result.advanceAmount
			);

		amount.textContent =
			formatPrice(
				result.advanceAmount
			);

		label.appendChild(
			amount
		);

		label.appendChild(
			document.createTextNode(
				parts.join(
					placeholder
				)
			)
		);
	}

	function getAdvanceSubtitle(section) {
		let subtitle =
			section.querySelector(
				selectors.advanceSubtitle
			);

		if (
			window.eilmoCfDom.isElement(subtitle)
		) {
			return subtitle;
		}

		const option =
			section.querySelector(
				selectors.option +
					'[data-payment-type="advance"]'
			);

		if (
			!(
				window.eilmoCfDom.isElement(option)
			)
		) {
			return null;
		}

		const content =
			option.querySelector(
				'.eilmo-cf-advance-payment-option__content'
			);

		if (
			!(
				window.eilmoCfDom.isElement(content)
			)
		) {
			return null;
		}

		subtitle =
			document.createElement(
				'span'
			);

		subtitle.className =
			'eilmo-cf-advance-payment-option__description';

		subtitle.setAttribute(
			'data-eilmo-advance-option-subtitle',
			''
		);

		content.appendChild(
			subtitle
		);

		return subtitle;
	}

	function updateAdvanceBadge(section, totals, texts) {
		const badge = section.querySelector(
			selectors.advanceBadge
		);

		if (!window.eilmoCfDom.isElement(badge)) {
			return;
		}

		if (totals.productTotal <= 0) {
			badge.textContent = '';
			badge.hidden = true;
			return;
		}

		badge.textContent =
			texts.advance_badge ||
			'Less hassle';
		badge.hidden = '' === badge.textContent.trim();
	}

	function updateAdvanceOption(
		section,
		result,
		totals,
		texts
	) {
		const label =
			section.querySelector(
				selectors.advanceLabel
			);

		if (label) {
			updateAdvanceLabel(
				label,
				texts.advance_label ||
					'Advance Payment',
				result,
				totals
			);
		}

		updateAdvanceBadge(
			section,
			totals,
			texts
		);

		const subtitle =
			getAdvanceSubtitle(
				section
			);

		if (!subtitle) {
			return;
		}

		if (
			totals.productTotal <= 0
		) {
			subtitle.textContent =
				texts.empty_description ||
					'Select a product to see the advance amount.';

			subtitle.hidden =
				'' ===
					subtitle.textContent
						.trim();

			return;
		}

		subtitle.textContent =
			replaceTokens(
				texts.advance_card_subtitle ||
					'Pay {pay_now} in advance.',
				{
					pay_now:
						formatPrice(
							result.payNow
						),

					remaining_due:
						formatPrice(
							result.remainingDue
						),

					grand_total:
						formatPrice(
							totals.grandTotal
						),
				}
			);

		subtitle.hidden =
			'' ===
				subtitle.textContent
					.trim();
	}

	function updateFullPaymentOption(
		section,
		discount,
		totals,
		texts
	) {
		const option =
			section.querySelector(
				selectors.option +
					'[data-payment-type="full"]'
			);

		if (
			!(
				window.eilmoCfDom.isElement(option)
			)
		) {
			return;
		}

		const label =
			option.querySelector(
				selectors.fullLabel
			);

		const description =
			option.querySelector(
				selectors.fullDescription
			);

		const badge =
			option.querySelector(
				selectors.fullDiscountBadge
			);

		const discountedTotal =
			(
				discount.eligible ||
				discount.freeDelivery
			)
				? discount.discountedTotal
				: totals.grandTotal;

		const tokens = {
			discount:
				discount.discountLabel,

			grand_total:
				formatPrice(
					totals.grandTotal
				),

			discounted_total:
				formatPrice(
					discountedTotal
				),
		};

		option.dataset.discountOffer =
			discount.offerAvailable
				? 'yes'
				: 'no';

		option.dataset.discountEligible =
			discount.eligible
				? 'yes'
				: 'no';

		option.dataset.saving =
			String(
				discount.saving
			);

		option.dataset.discountedTotal =
			String(
				discountedTotal
			);

		option.dataset.discountBasisAmount =
			String(
				discount.basisAmount
			);

		option.classList.toggle(
			'eilmo-cf-advance-payment-option--discount',
			discount.offerAvailable
		);

		if (label) {
			label.textContent =
				replaceTokens(
					texts.full_payment_label ||
						'Full Payment',
					tokens
				);
		}

		if (description) {
			description.textContent =
				replaceTokens(
					texts.full_payment_description ||
						'',
					tokens
				);

			description.hidden =
				'' ===
					description.textContent
						.trim();
		}

		if (badge) {
			const badgeParts = [];

			if (
				discount.eligible &&
				discount.saving > 0 &&
				discount.discountLabel
			) {
				const discountBadge = replaceTokens(
					texts.discount_badge ||
						'Get {discount} OFF',
					tokens
				).trim();
				if (discountBadge) badgeParts.push(discountBadge);
			}

			if (discount.freeDelivery && toNumber(totals.deliveryCharge) > 0) {
				const freeBadge = String(
					texts.free_delivery_badge ||
						discount.freeDeliveryBadge ||
						'Free Delivery'
				).trim();
				if (freeBadge) badgeParts.push(freeBadge);
			}

			badge.textContent = badgeParts.filter(function (value, index, items) {
				return value && items.indexOf(value) === index;
			}).join(' + ');
			badge.hidden = badge.dataset.showBadge === 'no' || '' === badge.textContent.trim();
		}
	}

	function updatePaymentContext(
		section,
		paymentType,
		advanceResult,
		discount,
		totals,
		texts
	) {
		const completion = section.querySelector(
			'.eilmo-cf-advance-payment__completion'
		);
		const required = requiresPaymentMethod(paymentType);

		if (window.eilmoCfDom.isElement(completion)) {
			completion.hidden = !required;
		}

		const note = completion
			? completion.querySelector(selectors.paymentMethodsPayNote)
			: null;

		if (!window.eilmoCfDom.isElement(note)) {
			return;
		}

		if (!required || totals.productTotal <= 0) {
			note.textContent = '';
			note.hidden = true;
			return;
		}

		let payNow = advanceResult.payNow;

		if ('full' === paymentType) {
			payNow =
				discount.eligible ||
				discount.freeDelivery
					? discount.discountedTotal
					: totals.grandTotal;
		}

		note.textContent = replaceTokens(
			texts.payment_method_pay_now || 'Pay {amount} in advance.',
			{
				amount: formatPrice(payNow),
			}
		);
		note.hidden = '' === note.textContent.trim();
	}

	function clearError(section) {
		const error =
			section.querySelector(
				selectors.error
			);

		if (!error) {
			return;
		}

		error.textContent =
			'';

		error.hidden =
			true;
	}

	function storeResult(
		checkout,
		section,
		result
	) {
		checkout.dataset.advanceReady =
			'yes';

		checkout.dataset.paymentType =
			result.paymentType;

		checkout.dataset.cashOnDeliverySelected =
			result.isCashOnDelivery
				? 'yes'
				: 'no';

		checkout.dataset.paymentMethodRequired =
			result.requiresPaymentMethod
				? 'yes'
				: 'no';

		checkout.dataset.advanceRuleId =
			result.ruleId;

		checkout.dataset.advanceRuleType =
			result.ruleType;

		checkout.dataset.advanceAmount =
			String(
				result.advanceAmount
			);

		checkout.dataset.baseProductTotal =
			String(
				result.baseProductTotal
			);

		checkout.dataset.comboSelectedCount =
			String(
				result.comboSelectedCount
			);

		checkout.dataset.comboRegularTotal =
			String(
				result.comboRegularTotal
			);

		checkout.dataset.comboDiscount =
			String(
				result.comboDiscount
			);

		checkout.dataset.comboTotal =
			String(
				result.comboTotal
			);

		checkout.dataset.orderBumpSelectedCount =
			String(
				result.orderBumpSelectedCount
			);

		checkout.dataset.orderBumpRegularTotal =
			String(
				result.orderBumpRegularTotal
			);

		checkout.dataset.orderBumpDiscount =
			String(
				result.orderBumpDiscount
			);

		checkout.dataset.orderBumpTotal =
			String(
				result.orderBumpTotal
			);

		checkout.dataset.productTotal =
			String(
				result.productTotal
			);

		checkout.dataset.automaticDiscount =
			String(
				result.automaticDiscount
			);

		checkout.dataset.couponDiscount =
			String(
				result.couponDiscount
			);

		checkout.dataset.discountedProductTotal =
			String(
				result.discountedProductTotal
			);

		checkout.dataset.fullPaymentProductTotal =
			String(
				result.fullPaymentProductTotal
			);

		checkout.dataset.fullPaymentDiscountedProductTotal =
			String(
				result
					.fullPaymentDiscountedProductTotal
			);

		checkout.dataset.fullPaymentEligibleGrandTotal =
			String(
				result
					.fullPaymentEligibleGrandTotal
			);

		checkout.dataset.fullPaymentDiscount =
			String(
				result
					.appliedFullPaymentDiscount
			);

		checkout.dataset.fullPaymentDiscountPreview =
			String(
				result
					.fullPaymentDiscountPreview
			);

		checkout.dataset.fullPaymentDiscountOffer =
			result
				.fullPaymentDiscountOfferAvailable
				? 'yes'
				: 'no';

		checkout.dataset.baseGrandTotal =
			String(
				result.baseGrandTotal
			);

		checkout.dataset.payableGrandTotal =
			String(
				result.grandTotal
			);

		checkout.dataset.payNow =
			String(
				result.payNow
			);

		checkout.dataset.remainingDue =
			String(
				result.remainingDue
			);

		section.dataset.paymentType =
			result.paymentType;

		section.dataset.cashOnDeliverySelected =
			result.isCashOnDelivery
				? 'yes'
				: 'no';

		section.dataset.paymentMethodRequired =
			result.requiresPaymentMethod
				? 'yes'
				: 'no';

		section.dataset.advanceAmount =
			String(
				result.advanceAmount
			);

		section.dataset.comboDiscount =
			String(
				result.comboDiscount
			);

		section.dataset.productTotal =
			String(
				result.productTotal
			);

		/*
		 * Never overwrite:
		 *
		 * section.dataset.fullPaymentDiscount
		 *
		 * because it contains the JSON configuration.
		 */
		section.dataset.appliedFullPaymentDiscount =
			String(
				result
					.appliedFullPaymentDiscount
			);

		section.dataset.fullPaymentDiscountPreview =
			String(
				result
					.fullPaymentDiscountPreview
			);

		section.dataset.fullPaymentDiscountOffer =
			result
				.fullPaymentDiscountOfferAvailable
				? 'yes'
				: 'no';

		section.dataset.baseGrandTotal =
			String(
				result.baseGrandTotal
			);

		section.dataset.payableGrandTotal =
			String(
				result.grandTotal
			);

		section.dataset.payNow =
			String(
				result.payNow
			);

		section.dataset.remainingDue =
			String(
				result.remainingDue
			);

		section.dataset.hasSelectedProduct =
			result.productTotal > 0
				? 'yes'
				: 'no';

		results.set(
			checkout,
			result
		);
	}

	function dispatchChange(
		checkout,
		result
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:advancePaymentChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						paymentType:
							result.paymentType,

						isCashOnDelivery:
							result.isCashOnDelivery,

						requiresPaymentMethod:
							result.requiresPaymentMethod,

						ruleId:
							result.ruleId,

						ruleType:
							result.ruleType,

						baseProductTotal:
							result.baseProductTotal,

						comboSelectedCount:
							result.comboSelectedCount,

						comboRegularTotal:
							result.comboRegularTotal,

						comboDiscount:
							result.comboDiscount,

						comboOfferDiscount:
							result.comboDiscount,

						comboTotal:
							result.comboTotal,

						orderBumpSelectedCount:
							result
								.orderBumpSelectedCount,

						orderBumpRegularTotal:
							result
								.orderBumpRegularTotal,

						orderBumpDiscount:
							result.orderBumpDiscount,

						orderBumpTotal:
							result.orderBumpTotal,

						productTotal:
							result.productTotal,

						automaticDiscount:
							result.automaticDiscount,

						couponDiscount:
							result.couponDiscount,

						discountedProductTotal:
							result
								.discountedProductTotal,

						fullPaymentProductTotal:
							result
								.fullPaymentProductTotal,

						fullPaymentDiscountedProductTotal:
							result
								.fullPaymentDiscountedProductTotal,

						fullPaymentEligibleGrandTotal:
							result
								.fullPaymentEligibleGrandTotal,

						deliveryCharge:
							result.deliveryCharge,

						baseGrandTotal:
							result.baseGrandTotal,

						advanceAmount:
							result.advanceAmount,

						fullPaymentDiscountOfferAvailable:
							result
								.fullPaymentDiscountOfferAvailable,

						fullPaymentDiscountEligible:
							result
								.fullPaymentDiscountEligible,

						fullPaymentDiscountPreview:
							result
								.fullPaymentDiscountPreview,

						appliedFullPaymentDiscount:
							result
								.appliedFullPaymentDiscount,

						grandTotal:
							result.grandTotal,

						payNow:
							result.payNow,

						remainingDue:
							result.remainingDue,
					},
				}
			)
		);
	}

	function refresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return null;
		}

		clearError(
			section
		);

		const totals =
			getTotals(
				checkout
			);

		const texts =
			getTexts(
				section
			);

		/*
		 * IMPORTANT:
		 *
		 * This Advance calculation is always calculated
		 * independently from the selected Payment Option.
		 *
		 * It is used both for:
		 * - Actual Advance Payment flow.
		 * - Advance card preview while COD / Full is selected.
		 */
		const advance =
			calculateAdvance(
				section,
				totals
			);

		const discount =
			calculateFullPaymentDiscount(
				section,
				totals
			);

		const paymentType =
			getPaymentType(
				section
			);

		const hasProduct =
			totals.productTotal > 0;

		/*
		 * These values represent the CURRENTLY SELECTED
		 * checkout Payment Option.
		 */
		let advanceAmount =
			advance.advanceAmount;

		let payNow =
			advance.payNow;

		let remainingDue =
			advance.remainingDue;

		let payableGrandTotal =
			totals.grandTotal;

		let appliedDiscount =
			0;

		if (!hasProduct) {
			advanceAmount =
				0;

			payNow =
				0;

			remainingDue =
				0;

			payableGrandTotal =
				0;
		} else if (
			'cash_on_delivery' ===
				paymentType
		) {
			/*
			 * COD actual checkout values.
			 *
			 * Advance preview remains separate below.
			 */
			advanceAmount =
				0;

			payNow =
				0;

			remainingDue =
				totals.grandTotal;

			payableGrandTotal =
				totals.grandTotal;

			appliedDiscount =
				0;
		} else if (
			'full' === paymentType
		) {
			advanceAmount =
				0;

			if (
				discount.eligible
			) {
				appliedDiscount =
					discount.saving;
			}

			if (
				discount.eligible ||
				discount.freeDelivery
			) {
				payableGrandTotal =
					discount
						.discountedTotal;
			}

			payNow =
				payableGrandTotal;

			remainingDue =
				0;
		}

		advanceAmount =
			roundMoney(
				advanceAmount
			);

		payNow =
			roundMoney(
				payNow
			);

		remainingDue =
			roundMoney(
				remainingDue
			);

		payableGrandTotal =
			roundMoney(
				payableGrandTotal
			);

		appliedDiscount =
			roundMoney(
				appliedDiscount
			);

		updateOptionState(
			section,
			paymentType
		);

		updatePaymentMethodsVisibility(
			checkout,
			paymentType
		);

		/*
		 * --------------------------------------------------
		 * Advance Card Preview
		 * --------------------------------------------------
		 *
		 * Never use the selected-flow advanceAmount,
		 * payNow or remainingDue here.
		 *
		 * Example:
		 *
		 * COD selected:
		 *
		 * Actual checkout:
		 * Pay Now       = 0
		 * Remaining Due = Grand Total
		 *
		 * Advance card:
		 * Pay 200 Advance
		 * Remaining 1,275 later
		 *
		 * This makes the Advance offer visible immediately
		 * when the popup opens, without requiring the
		 * customer to click Advance first.
		 */
		const advancePreviewResult = {
			ruleId:
				advance.ruleId,

			ruleType:
				advance.ruleType,

			advanceAmount:
				roundMoney(
					advance.advanceAmount
				),

			payNow:
				roundMoney(
					advance.payNow
				),

			remainingDue:
				roundMoney(
					advance.remainingDue
				),
		};

		updateAdvanceOption(
			section,
			advancePreviewResult,
			totals,
			texts
		);

		updateFullPaymentOption(
			section,
			discount,
			totals,
			texts
		);

		updatePaymentContext(
			section,
			paymentType,
			advancePreviewResult,
			discount,
			totals,
			texts
		);

		const result = {
			paymentType:
				paymentType,

			isCashOnDelivery:
				'cash_on_delivery' ===
					paymentType,

			requiresPaymentMethod:
				requiresPaymentMethod(
					paymentType
				),

			ruleId:
				'cash_on_delivery' ===
					paymentType
						? ''
						: advance.ruleId,

			ruleType:
				'cash_on_delivery' ===
					paymentType
						? 'cash_on_delivery'
						: advance.ruleType,

			baseProductTotal:
				totals.baseProductTotal,

			comboSelectedCount:
				totals.comboSelectedCount,

			comboRegularTotal:
				totals.comboRegularTotal,

			comboDiscount:
				totals.comboDiscount,

			comboOfferDiscount:
				totals.comboDiscount,

			comboTotal:
				totals.comboTotal,

			orderBumpSelectedCount:
				totals
					.orderBumpSelectedCount,

			orderBumpRegularTotal:
				totals
					.orderBumpRegularTotal,

			orderBumpDiscount:
				totals.orderBumpDiscount,

			orderBumpTotal:
				totals.orderBumpTotal,

			productTotal:
				totals.productTotal,

			automaticDiscount:
				totals.automaticDiscount,

			couponDiscount:
				totals.couponDiscount,

			discountedProductTotal:
				totals
					.discountedProductTotal,

			fullPaymentProductTotal:
				totals
					.fullPaymentProductTotal,

			fullPaymentDiscountedProductTotal:
				totals
					.fullPaymentDiscountedProductTotal,

			fullPaymentEligibleGrandTotal:
				totals
					.fullPaymentGrandTotal,

			deliveryCharge:
				totals.deliveryCharge,

			baseGrandTotal:
				hasProduct
					? totals.grandTotal
					: 0,

			/*
			 * IMPORTANT:
			 *
			 * This is the ACTUAL selected-flow advance
			 * amount, not the Advance card preview.
			 *
			 * COD = 0
			 * Advance = calculated advance amount
			 * Full = 0
			 */
			advanceAmount:
				advanceAmount,

			fullPaymentDiscountOfferAvailable:
				discount.offerAvailable,

			fullPaymentDiscountEligible:
				discount.eligible,

			fullPaymentDiscountPreview:
				discount.saving,

			appliedFullPaymentDiscount:
				appliedDiscount,

			grandTotal:
				hasProduct
					? payableGrandTotal
					: 0,

			payNow:
				payNow,

			remainingDue:
				remainingDue,
		};

		storeResult(
			checkout,
			section,
			result
		);

		dispatchChange(
			checkout,
			result
		);

		return result;
	}

	function scheduleRefresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			scheduled.has(
				checkout
			)
		) {
			return;
		}

		scheduled.add(
			checkout
		);

		Promise.resolve().then(
			function () {
				scheduled.delete(
					checkout
				);

				refresh(
					checkout
				);
			}
		);
	}

	function validate(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return true;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (!section) {
			return true;
		}

		const selected =
			section.querySelector(
				selectors.paymentType +
					':checked'
			);

		if (
			window.eilmoCfDom.isElement(selected, 'INPUT') &&
			normalizePaymentType(
				selected.value
			)
		) {
			clearError(
				section
			);

			return true;
		}

		const error =
			section.querySelector(
				selectors.error
			);

		if (error) {
			const config =
				window.eilmoCf ||
					{};

			error.textContent =
				config.paymentOptionRequired ||
					'Please select a payment option.';

			error.hidden =
				false;
		}

		return false;
	}

	function initialize(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			initialized.has(
				checkout
			)
		) {
			return;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (!section) {
			return;
		}

		prepareUnifiedPaymentExperience(
			checkout,
			section
		);

		initialized.add(
			checkout
		);

		refresh(
			checkout
		);
	}

	function initializeAll() {
		document
			.querySelectorAll(
				selectors.checkout
			)
			.forEach(
				function (checkout) {
					if (
						window.eilmoCfDom.isElement(checkout)
					) {
						initialize(
							checkout
						);
					}
				}
			);
	}

	/*
	 * Cash on Delivery / Advance / Full
	 * Payment Option changed.
	 */
	document.addEventListener(
		'change',
		function (event) {
			if (
				!(
					window.eilmoCfDom.isElement(event.target, 'INPUT')
				) ||
				!event.target.matches(
					selectors.paymentType
				)
			) {
				return;
			}

			const checkout =
				event.target.closest(
					selectors.checkout
				);

			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return;
			}

			const section =
				event.target.closest(
					selectors.section
				);

			if (
				!(
					window.eilmoCfDom.isElement(section)
				)
			) {
				return;
			}

			const paymentType =
				normalizePaymentType(
					event.target.value
				);

			if (!paymentType) {
				return;
			}

			updateOptionState(
				section,
				paymentType
			);

			checkout.dataset.paymentType =
				paymentType;

			updatePaymentMethodsVisibility(
				checkout,
				paymentType
			);

			if (
				window.eilmoCfDelivery &&
				'function' === typeof window.eilmoCfDelivery.refresh
			) {
				window.eilmoCfDelivery.refresh(checkout);
			}

			refresh(
				checkout
			);
		}
	);

	[
		'eilmo:quantityChange',
		'eilmo:comboChange',
		'eilmo:orderBumpChange',
		'eilmo:deliveryChange',
		'eilmo:couponChange',
		'eilmo:discountChange',
	].forEach(
		function (eventName) {
			document.addEventListener(
				eventName,
				function (event) {
					const checkout =
						getCheckoutFromEvent(
							event
						);

					if (checkout) {
						scheduleRefresh(
							checkout
						);
					}
				}
			);
		}
	);

	window.eilmoCfAdvancePayment = {

		refresh:
			refresh,

		validate:
			validate,

		getResult:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return null;
				}

				return (
					results.get(
						checkout
					) ||
						null
				);
			},

		requiresPaymentMethod:
			requiresPaymentMethod,

		getPaymentType:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 'advance';
				}

				const section =
					checkout.querySelector(
						selectors.section
					);

				return window.eilmoCfDom.isElement(section)
						? getPaymentType(
							section
						)
						: 'advance';
			},

		getComboTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						selectedCount:
							0,

						regularTotal:
							0,

						discount:
							0,

						comboTotal:
							0,
					};
				}

				return getComboTotals(
					checkout
				);
			},

		getOrderBumpTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						selectedCount:
							0,

						regularTotal:
							0,

						discount:
							0,

						bumpTotal:
							0,
					};
				}

				return getOrderBumpTotals(
					checkout
				);
			},
	};

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initializeAll
		);
	} else {
		initializeAll();
	}
})();
