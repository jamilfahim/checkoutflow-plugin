/**
 * Eilmo Checkout Flow - Live Summary.
 *
 * Frontend calculations are UI previews only.
 * Normal Products, Combo Offers and Order Bumps remain
 * independent purchase contexts. Final totals must always
 * be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout: '[data-eilmo-checkout]',
		item: '[data-eilmo-product-item], [data-eilmo-item]',
		quantityInput: '[data-eilmo-quantity-input]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',
		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',
		summaryShell: '[data-eilmo-summary-shell]',
		summaryPanel: '[data-eilmo-summary-panel]',
		toggle: '[data-eilmo-summary-toggle]',
		close: '[data-eilmo-summary-close]',
		backdrop: '[data-eilmo-summary-backdrop]',
		mobileCheckoutDetailsTarget: '[data-eilmo-mobile-checkout-details-target]',
		summaryCheckoutDetails: '[data-eilmo-summary-checkout-details]',
		summaryOrderAction: '[data-eilmo-summary-order-action]',
		summaryWhatsAppAction: '[data-eilmo-summary-whatsapp-action]',
		summaryRelocationAnchor: '[data-eilmo-summary-relocation-anchor]',
		advancePayment: '[data-eilmo-advance-payment]',
		paymentType: '[data-eilmo-payment-type]',
	};

	const DATASET_KEYS = {
		'product-total': 'productTotal',
		'combo-discount': 'comboDiscount',
		'order-bump-regular-total': 'orderBumpRegularTotal',
		'order-bump-discount': 'orderBumpDiscount',
		'order-bump-total': 'orderBumpTotal',
		'automatic-discount': 'automaticDiscount',
		'coupon-discount': 'couponDiscount',
		'delivery-charge': 'deliveryCharge',
		'advance-payment': 'advanceAmount',
		'full-payment-discount': 'fullPaymentDiscount',
		'grand-total': 'grandTotal',
		'pay-now': 'payNow',
		'remaining-due': 'remainingDue',
	};

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
				String(
					value
				)
			);

		return Number.isFinite(
			number
		)
			? Math.max(
				0,
				number
			)
			: 0;
	}

	/**
	 * Convert value to quantity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toQuantity(value) {
		const quantity =
			Number.parseInt(
				String(
					value
				),
				10
			);

		return Number.isFinite(
			quantity
		)
			? Math.max(
				0,
				quantity
			)
			: 0;
	}

	/**
	 * Get WooCommerce price decimals.
	 *
	 * @return {number}
	 */
	function getPriceDecimals() {
		const config =
			window.eilmoCf ||
			{};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		let decimals =
			Number(
				currency.decimals
			);

		if (
			!Number.isFinite(
				decimals
			)
		) {
			decimals =
				Number(
					config.priceDecimals
				);
		}

		if (
			!Number.isFinite(
				decimals
			)
		) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(
				decimals
			)
		);
	}

	/**
	 * Round monetary amount.
	 *
	 * @param {*} value Amount.
	 *
	 * @return {number}
	 */
	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(
						value
					) +
					Number.EPSILON
				) *
					multiplier
			) /
			multiplier
		);
	}

	/**
	 * Decode HTML entities.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHtmlEntities(value) {
		if (
			'string' !==
				typeof value
		) {
			return '';
		}

		const textarea =
			document.createElement(
				'textarea'
			);

		textarea.innerHTML =
			value;

		return textarea.value
			.replace(
				/\u00a0/g,
				' '
			)
			.trim();
	}

	/**
	 * Get currency configuration.
	 *
	 * @return {Object}
	 */
	function getCurrencyConfig() {
		const config =
			window.eilmoCf &&
			window.eilmoCf.currency
				? window.eilmoCf.currency
				: {};

		return {
			symbol:
				decodeHtmlEntities(
					'string' ===
						typeof config.symbol
						? config.symbol
						: '৳'
				),

			position:
				'string' ===
					typeof config.position
					? config.position
					: 'left',

			decimals:
				Number.isInteger(
					config.decimals
				)
					? config.decimals
					: getPriceDecimals(),

			decimalSeparator:
				'string' ===
					typeof config.decimalSeparator
					? decodeHtmlEntities(
						config.decimalSeparator
					)
					: '.',

			thousandSeparator:
				'string' ===
					typeof config.thousandSeparator
					? decodeHtmlEntities(
						config.thousandSeparator
					)
					: ',',
		};
	}

	/**
	 * Format number.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatNumber(amount) {
		const config =
			getCurrencyConfig();

		const parts =
			roundMoney(
				amount
			)
				.toFixed(
					config.decimals
				)
				.split(
					'.'
				);

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		if (config.decimals > 0 && parts.length > 1) {
			const decimals = parts[1].replace(/0+$/, '');
			return parts[0] + (decimals ? config.decimalSeparator + decimals : '');
		}

		return parts[0];
	}

	/**
	 * Format WooCommerce-style currency.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		const config =
			getCurrencyConfig();

		const formatted =
			formatNumber(
				amount
			);

		switch (
			config.position
		) {
			case 'left':

				return (
					config.symbol +
					formatted
				);

			case 'left_space':

				return (
					config.symbol +
					'\u00A0' +
					formatted
				);

			case 'right_space':

				return (
					formatted +
					'\u00A0' +
					config.symbol
				);

			case 'right':
			default:

				return (
					formatted +
					config.symbol
				);
		}
	}

	/**
	 * Get localized Free label.
	 *
	 * @return {string}
	 */
	function getFreeLabel() {
		if (
			window.eilmoCf &&
			window.eilmoCf.i18n &&
			'string' ===
				typeof window.eilmoCf
					.i18n.free
		) {
			return window.eilmoCf
				.i18n.free;
		}

		return 'Free';
	}

	/**
	 * Normalize amount key.
	 *
	 * @param {string} key Key.
	 *
	 * @return {string}
	 */
	function normalizeAmountKey(key) {
		return String(
			key ||
				''
		);
	}

	/**
	 * Determine whether amount is a discount.
	 *
	 * @param {string} key Key.
	 *
	 * @return {boolean}
	 */
	function isDiscountAmount(key) {
		return [
			'combo-discount',
			'order-bump-discount',
			'automatic-discount',
			'coupon-discount',
			'full-payment-discount',
		].includes(
			normalizeAmountKey(
				key
			)
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
		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return null;
		}

		if (
			element.matches(
				SELECTORS.checkout
			)
		) {
			return window.eilmoCfDom.isElement(element)
					? element
					: null;
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
	 * Get checkout from event.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckoutFromEvent(event) {
		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail
				.checkout;
		}

		return window.eilmoCfDom.isElement(event.target)
				? getCheckout(
					event.target
				)
				: null;
	}

	/**
	 * Get Summary shell.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSummaryShell(checkout) {
		const shell =
			checkout.querySelector(
				SELECTORS.summaryShell
			);

		return window.eilmoCfDom.isElement(shell)
				? shell
				: null;
	}

	/**
	 * Get amount element.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {HTMLElement|null}
	 */
	function getAmountElement(
		checkout,
		key
	) {
		const element =
			checkout.querySelector(
				'[data-eilmo-summary-amount="' +
					normalizeAmountKey(
						key
					) +
					'"]'
			);

		return window.eilmoCfDom.isElement(element)
				? element
				: null;
	}

	/**
	 * Get amount from checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {number}
	 */
	function getDatasetAmount(
		checkout,
		key
	) {
		const datasetKey =
			DATASET_KEYS[
				normalizeAmountKey(
					key
				)
			];

		if (!datasetKey) {
			return 0;
		}

		return toNumber(
			checkout.dataset[
				datasetKey
			] ||
				0
		);
	}

	/**
	 * Store amount in checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {void}
	 */
	function setDatasetAmount(
		checkout,
		key,
		amount
	) {
		const datasetKey =
			DATASET_KEYS[
				normalizeAmountKey(
					key
				)
			];

		if (!datasetKey) {
			return;
		}

		checkout.dataset[
			datasetKey
		] =
			String(
				roundMoney(
					amount
				)
			);
	}

	/**
	 * Get stored amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {number}
	 */
	function getAmount(
		checkout,
		key
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const element =
			getAmountElement(
				checkout,
				normalizedKey
			);

		if (element) {
			return toNumber(
				element.dataset.value ||
					0
			);
		}

		return getDatasetAmount(
			checkout,
			normalizedKey
		);
	}

	/**
	 * Determine whether selected delivery is free.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function deliveryIsFree(checkout) {
		return (
			'string' ===
				typeof checkout.dataset
					.deliveryMethod &&
			'' !==
				checkout.dataset
					.deliveryMethod &&
			'yes' ===
				checkout.dataset
					.deliveryIsFree
		);
	}

	/**
	 * Format Summary amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {string}
	 */
	function formatSummaryAmount(
		checkout,
		key,
		amount
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const safeAmount =
			roundMoney(
				amount
			);

		if (
			'delivery-charge' ===
				normalizedKey &&
			deliveryIsFree(
				checkout
			)
		) {
			return getFreeLabel();
		}

		const formatted =
			formatPrice(
				safeAmount
			);

		if (
			isDiscountAmount(
				normalizedKey
			) &&
			safeAmount > 0
		) {
			return (
				'\u2212' +
				formatted
			);
		}

		return formatted;
	}

	/**
	 * Set Summary amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {void}
	 */
	function setAmount(
		checkout,
		key,
		amount
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const safeAmount =
			roundMoney(
				amount
			);

		const formatted =
			formatSummaryAmount(
				checkout,
				normalizedKey,
				safeAmount
			);

		setDatasetAmount(
			checkout,
			normalizedKey,
			safeAmount
		);

		if (isDiscountAmount(normalizedKey) || ['advance-payment', 'pay-now', 'remaining-due'].includes(normalizedKey)) {
			const keepZeroBreakdownVisible =
				checkout.dataset.emptySelection === 'yes' &&
				['pay-now', 'remaining-due'].includes(normalizedKey);
			checkout
				.querySelectorAll(
					'[data-eilmo-summary-row="' +
						normalizedKey +
						'"]'
				)
				.forEach(function (row) {
					if (window.eilmoCfDom.isElement(row)) {
						row.hidden = !keepZeroBreakdownVisible && safeAmount <= 0;
					}
				});
		}

		[
			'data-eilmo-summary-amount',
			'data-eilmo-mobile-summary-amount',
		].forEach(
			function (attribute) {
				checkout
					.querySelectorAll(
						'[' +
							attribute +
							'="' +
							normalizedKey +
							'"]'
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

							element.dataset.value =
								String(
									safeAmount
								);

							element.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatted, checkout.dataset.checkoutLanguage) : formatted;
						}
					);
			}
		);
	}

	/**
	 * Calculate normal selected product total.
	 *
	 * Combo and Order Bump contexts are excluded here.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateProductTotal(checkout) {
		let total =
			0;

		checkout
			.querySelectorAll(
				SELECTORS.item
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

					/*
					 * Normal products only.
					 *
					 * Same WooCommerce product can exist
					 * again as Combo or Order Bump.
					 */
					if (
						item.closest(
							SELECTORS.comboOffer
						) ||
						item.closest(
							SELECTORS.orderBumpOffer
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toQuantity(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					let price =
						toNumber(
							item.dataset.price
						);

					if (
						price <= 0
					) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (
						price <= 0
					) {
						price =
							toNumber(
								input.dataset.price
							);
					}

					if (
						price <= 0
					) {
						return;
					}

					total +=
						price *
							quantity;
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Calculate promotional source totals.
	 *
	 * Used for Combo Offers and Order Bumps.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      config   Source configuration.
	 *
	 * @return {Object}
	 */
	function calculatePromotionalTotals(
		checkout,
		config
	) {
		const totals = {
			selectedCount:
				0,

			regularTotal:
				0,

			discount:
				0,

			payableTotal:
				0,

			automaticEligibleTotal:
				0,

			couponEligibleTotal:
				0,

			fullPaymentEligibleRegularTotal:
				0,

			fullPaymentEligibleTotal:
				0,

			automaticCouponOverlapTotal:
				0,

			automaticFullPaymentOverlapTotal:
				0,

			fullPaymentCouponOverlapTotal:
				0,

			automaticFullPaymentCouponOverlapTotal:
				0,
		};

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

					const regularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const discount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset[
										config.discountKey
									] ||
										0
								),
								regularTotal
							)
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
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

					const payableTotal =
						roundMoney(
							Math.min(
								regularTotal,
								toNumber(
									rawPayable
								)
							)
						);

					/*
					 * Automatic Discount:
					 *
					 * Combo default      = no.
					 * Order Bump default = no.
					 */
					const automaticEligible =
						'yes' ===
							offer.dataset
								.applyAutomaticDiscount;

					/*
					 * Coupon:
					 *
					 * Combo default      = yes.
					 * Order Bump default = no.
					 */
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

					/*
					 * Full Payment Discount:
					 *
					 * Combo default      = yes.
					 * Order Bump default = yes.
					 *
					 * Only explicit "no" excludes.
					 */
					const fullPaymentEligible =
						'no' !==
							offer.dataset
								.applyFullPaymentDiscount;

					totals.selectedCount +=
						1;

					totals.regularTotal +=
						regularTotal;

					totals.discount +=
						discount;

					totals.payableTotal +=
						payableTotal;

					if (automaticEligible) {
						totals
							.automaticEligibleTotal +=
								payableTotal;
					}

					if (couponEligible) {
						totals
							.couponEligibleTotal +=
								payableTotal;
					}

					if (fullPaymentEligible) {
						totals
							.fullPaymentEligibleRegularTotal +=
								regularTotal;

						totals
							.fullPaymentEligibleTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						couponEligible
					) {
						totals
							.automaticCouponOverlapTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						fullPaymentEligible
					) {
						totals
							.automaticFullPaymentOverlapTotal +=
								payableTotal;
					}

					if (
						fullPaymentEligible &&
						couponEligible
					) {
						totals
							.fullPaymentCouponOverlapTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						fullPaymentEligible &&
						couponEligible
					) {
						totals
							.automaticFullPaymentCouponOverlapTotal +=
								payableTotal;
					}
				}
			);

		Object.keys(
			totals
		).forEach(
			function (key) {
				if (
					'selectedCount' ===
						key
				) {
					return;
				}

				totals[key] =
					roundMoney(
						totals[key]
					);
			}
		);

		totals.discount =
			Math.min(
				totals.discount,
				totals.regularTotal
			);

		totals.payableTotal =
			Math.min(
				totals.payableTotal,
				totals.regularTotal
			);

		return totals;
	}

	/**
	 * Calculate Combo totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateComboTotals(checkout) {
		const totals =
			calculatePromotionalTotals(
				checkout,
				{
					offerSelector:
						SELECTORS.comboOffer,

					inputSelector:
						SELECTORS.comboInput,

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

	/**
	 * Calculate Order Bump totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateOrderBumpTotals(checkout) {
		const totals =
			calculatePromotionalTotals(
				checkout,
				{
					offerSelector:
						SELECTORS.orderBumpOffer,

					inputSelector:
						SELECTORS.orderBumpInput,

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

	/**
	 * Determine whether Payment Options section exists.
	 *
	 * Historical markup/API names still use
	 * "advance payment" for backward compatibility.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function hasAdvancePayment(checkout) {
		return Boolean(
			checkout.querySelector(
				SELECTORS.advancePayment
			)
		);
	}

	/**
	 * Normalize Payment Option type.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizePaymentType(value) {
		const type =
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
			type
		)
			? type
			: '';
	}

	/**
	 * Get selected Cash on Delivery / Advance / Full
	 * Payment Option.
	 *
	 * Priority:
	 *
	 * 1. Checked Payment Option radio.
	 * 2. Shared checkout dataset.
	 * 3. Advance Payment public API result.
	 * 4. Full Payment compatibility fallback.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentType(checkout) {
		const section =
			checkout.querySelector(
				SELECTORS.advancePayment
			);

		if (
			window.eilmoCfDom.isElement(section)
		) {
			const selected =
				section.querySelector(
					SELECTORS.paymentType +
						':checked'
				);

			if (
				window.eilmoCfDom.isElement(selected, 'INPUT')
			) {
				const selectedType =
					normalizePaymentType(
						selected.value
					);

				if (selectedType) {
					return selectedType;
				}
			}
		}

		const datasetType =
			normalizePaymentType(
				checkout.dataset
					.paymentType ||
					''
			);

		if (datasetType) {
			return datasetType;
		}

		const api =
			window
				.eilmoCfAdvancePayment;

		if (
			api &&
			typeof api.getResult ===
				'function'
		) {
			const result =
				api.getResult(
					checkout
				);

			if (
				result &&
				'object' ===
					typeof result
			) {
				const runtimeType =
					normalizePaymentType(
						result.paymentType ||
						result.payment_type ||
						''
					);

				if (runtimeType) {
					return runtimeType;
				}
			}
		}

		return 'full';
	}

	/**
	 * Determine whether Cash on Delivery is selected.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isCashOnDelivery(checkout) {
		return (
			'cash_on_delivery' ===
				getPaymentType(
					checkout
				)
		);
	}

	/**
	 * Allocate discount proportionally to an eligible
	 * overlapping source.
	 *
	 * @param {number} discount    Discount.
	 * @param {number} sourceTotal Source total.
	 * @param {number} subsetTotal Overlap total.
	 *
	 * @return {number}
	 */
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
					toNumber(
						subsetTotal
					),
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

	/**
	 * Calculate current Summary.
	 *
	 * Calculation order:
	 *
	 * Normal Product
	 * +
	 * Combo
	 * - Combo Own Discount
	 * +
	 * Order Bump
	 * - Order Bump Own Discount
	 * ↓
	 * Automatic Discount
	 * ↓
	 * Coupon
	 * ↓
	 * Delivery
	 * ↓
	 * Full Payment Discount
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateSummary(checkout) {
		const hasProducts = hasSelectedProducts(checkout);
		const baseProductTotal =
			calculateProductTotal(
				checkout
			);

		const combo =
			calculateComboTotals(
				checkout
			);

		const orderBump =
			calculateOrderBumpTotals(
				checkout
			);

		/*
		 * Regular Product Total before promotional
		 * purchase-context discounts.
		 */
		const productTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal +
					orderBump.regularTotal
			);

		const comboOfferDiscount =
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

		/*
		 * Actual payable product amount after Combo and
		 * Order Bump own offer pricing.
		 */
		const afterOfferDiscounts =
			roundMoney(
				Math.max(
					0,
					baseProductTotal +
						combo.comboTotal +
						orderBump.bumpTotal
				)
			);

		/*
		 * --------------------------------------
		 * Automatic Discount
		 * --------------------------------------
		 *
		 * Normal:
		 * yes.
		 *
		 * Combo:
		 * apply_automatic_discount.
		 *
		 * Order Bump:
		 * apply_automatic_discount.
		 */
		const automaticDiscountBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticEligibleTotal +
					orderBump
						.automaticEligibleTotal
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAmount(
						checkout,
						'automatic-discount'
					),
					automaticDiscountBase,
					afterOfferDiscounts
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					afterOfferDiscounts -
						automaticDiscount
				)
			);

		/*
		 * --------------------------------------
		 * Coupon Discount
		 * --------------------------------------
		 *
		 * Normal:
		 * yes.
		 *
		 * Combo:
		 * apply_coupon, default yes.
		 *
		 * Order Bump:
		 * apply_coupon, default no.
		 */
		const couponBaseBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo
						.couponEligibleTotal +
					orderBump
						.couponEligibleTotal
			);

		/*
		 * Only Automatic Discount belonging to
		 * Coupon-eligible contexts may reduce the
		 * Coupon preview basis.
		 */
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

		const couponDiscountBase =
			roundMoney(
				Math.max(
					0,
					couponBaseBeforeAutomatic -
						couponAutomaticDiscount
				)
			);

		const couponDiscount =
			roundMoney(
				Math.min(
					getAmount(
						checkout,
						'coupon-discount'
					),
					couponDiscountBase,
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

		const totalDiscount =
			roundMoney(
				Math.min(
					productTotal,
					comboOfferDiscount +
						orderBumpDiscount +
						automaticDiscount +
						couponDiscount
				)
			);

		/*
		 * Delivery.
		 */
		const deliveryCharge =
			hasProducts
				? roundMoney(
					getAmount(
						checkout,
						'delivery-charge'
					)
				)
				: 0;

		const paymentType =
			getPaymentType(
				checkout
			);

		/*
		 * Advance amount.
		 *
		 * Cash on Delivery never has an advance amount.
		 * A stale Advance preview may still exist in the
		 * DOM/dataset after switching options, therefore
		 * the effective Summary amount is forced to zero.
		 */
		const storedAdvancePayment =
			roundMoney(
				getAmount(
					checkout,
					'advance-payment'
				)
			);

		const advancePayment =
			!hasProducts || 'cash_on_delivery' ===
				paymentType
					? 0
					: storedAdvancePayment;

		/*
		 * Base Grand Total excludes Full Payment
		 * Discount so the discount never discounts itself.
		 */
		const baseGrandTotal =
			roundMoney(
				discountedProductTotal +
					deliveryCharge
			);

		let fullPaymentDiscount =
			roundMoney(
				getAmount(
					checkout,
					'full-payment-discount'
				)
			);

		if (
			'full' !==
				paymentType
		) {
			fullPaymentDiscount =
				0;
		}

		/*
		 * advance.js calculates the source-aware
		 * Full Payment Discount.
		 *
		 * If its eligible grand-total snapshot exists,
		 * use it as an additional stale-preview guard.
		 */
		const hasFullPaymentEligibleGrandTotal =
			'string' ===
				typeof checkout.dataset
					.fullPaymentEligibleGrandTotal;

		const fullPaymentEligibleGrandTotal =
			hasFullPaymentEligibleGrandTotal
				? toNumber(
					checkout.dataset
						.fullPaymentEligibleGrandTotal
				)
				: baseGrandTotal;

		fullPaymentDiscount =
			roundMoney(
				Math.min(
					fullPaymentDiscount,
					baseGrandTotal,
					fullPaymentEligibleGrandTotal
				)
			);

		const grandTotal =
			roundMoney(
				Math.max(
					0,
					baseGrandTotal -
						fullPaymentDiscount
				)
			);

		const advanceRuleType =
			'string' ===
				typeof checkout.dataset
					.advanceRuleType
				? checkout.dataset
					.advanceRuleType
				: '';

		let payNow =
			grandTotal;

		/*
		 * Cash on Delivery:
		 *
		 * - Nothing is payable during checkout.
		 * - The full authoritative Grand Total remains due.
		 * - Full Payment Discount is already forced to zero
		 *   above because paymentType is not "full".
		 */
		if (
			'cash_on_delivery' ===
				paymentType
		) {
			payNow =
				0;

		} else if (
			hasAdvancePayment(
				checkout
			)
		) {
			if (
				'full' ===
					paymentType
			) {
				payNow =
					grandTotal;

			} else if (
				'no_advance' ===
					advanceRuleType
			) {
				payNow =
					0;

			} else if (
				advancePayment > 0
			) {
				payNow =
					Math.min(
						advancePayment,
						grandTotal
					);

			} else if (
				'yes' ===
					checkout.dataset
						.advanceReady
			) {
				payNow =
					Math.min(
						toNumber(
							checkout.dataset
								.payNow ||
								0
						),
						grandTotal
					);
			}
		}

		payNow =
			roundMoney(
				payNow
			);

		const remainingDue =
			roundMoney(
				Math.max(
					0,
					grandTotal -
						payNow
				)
			);

		return {
			baseProductTotal:
				baseProductTotal,

			base_product_total:
				baseProductTotal,

			/*
			 * Combo.
			 */
			comboSelectedCount:
				combo.selectedCount,

			combo_selected_count:
				combo.selectedCount,

			comboRegularTotal:
				combo.regularTotal,

			combo_regular_total:
				combo.regularTotal,

			comboTotal:
				combo.comboTotal,

			combo_total:
				combo.comboTotal,

			comboOfferDiscount:
				comboOfferDiscount,

			combo_offer_discount:
				comboOfferDiscount,

			/*
			 * Canonical Combo own discount aliases.
			 *
			 * This is no longer an alias for
			 * Automatic Discount.
			 */
			comboDiscount:
				comboOfferDiscount,

			combo_discount:
				comboOfferDiscount,

			comboAutomaticEligibleTotal:
				combo
					.automaticEligibleTotal,

			combo_automatic_eligible_total:
				combo
					.automaticEligibleTotal,

			comboCouponEligibleTotal:
				combo
					.couponEligibleTotal,

			combo_coupon_eligible_total:
				combo
					.couponEligibleTotal,

			comboFullPaymentEligibleRegularTotal:
				combo
					.fullPaymentEligibleRegularTotal,

			combo_full_payment_eligible_regular_total:
				combo
					.fullPaymentEligibleRegularTotal,

			comboFullPaymentEligibleTotal:
				combo
					.fullPaymentEligibleTotal,

			combo_full_payment_eligible_total:
				combo
					.fullPaymentEligibleTotal,

			/*
			 * Order Bump.
			 */
			orderBumpSelectedCount:
				orderBump
					.selectedCount,

			order_bump_selected_count:
				orderBump
					.selectedCount,

			orderBumpRegularTotal:
				orderBump
					.regularTotal,

			order_bump_regular_total:
				orderBump
					.regularTotal,

			orderBumpDiscount:
				orderBumpDiscount,

			order_bump_discount:
				orderBumpDiscount,

			orderBumpTotal:
				orderBump
					.bumpTotal,

			order_bump_total:
				orderBump
					.bumpTotal,

			orderBumpAutomaticEligibleTotal:
				orderBump
					.automaticEligibleTotal,

			order_bump_automatic_eligible_total:
				orderBump
					.automaticEligibleTotal,

			orderBumpCouponEligibleTotal:
				orderBump
					.couponEligibleTotal,

			order_bump_coupon_eligible_total:
				orderBump
					.couponEligibleTotal,

			orderBumpFullPaymentEligibleRegularTotal:
				orderBump
					.fullPaymentEligibleRegularTotal,

			order_bump_full_payment_eligible_regular_total:
				orderBump
					.fullPaymentEligibleRegularTotal,

			orderBumpFullPaymentEligibleTotal:
				orderBump
					.fullPaymentEligibleTotal,

			order_bump_full_payment_eligible_total:
				orderBump
					.fullPaymentEligibleTotal,

			/*
			 * Product totals.
			 */
			productTotal:
				productTotal,

			product_total:
				productTotal,

			afterOfferDiscounts:
				afterOfferDiscounts,

			after_offer_discounts:
				afterOfferDiscounts,

			/*
			 * Automatic Discount.
			 */
			automaticDiscountBase:
				automaticDiscountBase,

			automatic_discount_base:
				automaticDiscountBase,

			automaticDiscount:
				automaticDiscount,

			automatic_discount:
				automaticDiscount,

			/*
			 * Coupon.
			 */
			couponBaseBeforeAutomatic:
				couponBaseBeforeAutomatic,

			coupon_base_before_automatic:
				couponBaseBeforeAutomatic,

			couponAutomaticDiscount:
				couponAutomaticDiscount,

			coupon_automatic_discount:
				couponAutomaticDiscount,

			couponDiscountBase:
				couponDiscountBase,

			coupon_discount_base:
				couponDiscountBase,

			couponDiscount:
				couponDiscount,

			coupon_discount:
				couponDiscount,

			totalDiscount:
				totalDiscount,

			total_discount:
				totalDiscount,

			discountedProductTotal:
				discountedProductTotal,

			discounted_product_total:
				discountedProductTotal,

			/*
			 * Delivery / payment.
			 */
			deliveryCharge:
				deliveryCharge,

			delivery_charge:
				deliveryCharge,

			paymentType:
				paymentType,

			payment_type:
				paymentType,

			isCashOnDelivery:
				'cash_on_delivery' ===
					paymentType,

			is_cash_on_delivery:
				'cash_on_delivery' ===
					paymentType,

			isAdvancePayment:
				'advance' ===
					paymentType,

			is_advance_payment:
				'advance' ===
					paymentType,

			isFullPayment:
				'full' ===
					paymentType,

			is_full_payment:
				'full' ===
					paymentType,

			advancePayment:
				advancePayment,

			advance_payment:
				advancePayment,

			advanceRuleType:
				advanceRuleType,

			advance_rule_type:
				advanceRuleType,

			fullPaymentDiscount:
				fullPaymentDiscount,

			full_payment_discount:
				fullPaymentDiscount,

			fullPaymentEligibleGrandTotal:
				fullPaymentEligibleGrandTotal,

			full_payment_eligible_grand_total:
				fullPaymentEligibleGrandTotal,

			baseGrandTotal:
				baseGrandTotal,

			base_grand_total:
				baseGrandTotal,

			grandTotal:
				grandTotal,

			grand_total:
				grandTotal,

			payNow:
				payNow,

			pay_now:
				payNow,

			remainingDue:
				remainingDue,

			remaining_due:
				remainingDue,
		};
	}

	/**
	 * Render currently selected normal product/variation items above totals.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 */
	function renderSelectedItems(checkout) {
		const container = checkout.querySelector('[data-eilmo-summary-items]');
		if (!container) return;
		const list = container.querySelector('[data-eilmo-summary-items-list]');
		if (!list) return;

		const showThumbnail = container.dataset.showThumbnail !== 'no';
		const showVariation = container.dataset.showVariation !== 'no';
		const showQuantity = false;
		const showPrice = container.dataset.showPrice !== 'no';
		const selected = [];

		checkout.querySelectorAll(SELECTORS.item).forEach(function (item) {
			/*
			 * Normal checkout products only. Combo Offers and Order Bumps use
			 * their own summary rows even if nested markup exposes an item adapter.
			 */
			if (
				item.closest(SELECTORS.comboOffer) ||
				item.closest(SELECTORS.orderBumpOffer)
			) {
				return;
			}

			const input = item.querySelector(SELECTORS.quantityInput);
			if (!input) return;
			const quantity = toQuantity(input.value);
			if (quantity <= 0) return;
			selected.push({
				productId: parseInt(item.dataset.productId || '0', 10),
				variationId: parseInt(item.dataset.variationId || '0', 10),
				name: String(item.dataset.itemName || '').trim(),
				variation: String(item.dataset.itemVariation || '').trim(),
				image: String(item.dataset.itemImage || '').trim(),
				price: toNumber(item.dataset.price || 0),
				quantity: quantity,
				locked: item.dataset.summaryQuantityLocked === 'yes',
			});
		});

		// Itemize selected offers separately; their quantity belongs to the
		// offer, so ordinary product quantity buttons must not edit them.
		checkout.querySelectorAll(SELECTORS.comboOffer + ', ' + SELECTORS.orderBumpOffer).forEach(function (offer) {
			const input = offer.querySelector(SELECTORS.comboInput + ', ' + SELECTORS.orderBumpInput);
			if (!input || !input.checked) return;
			const isCombo = offer.matches(SELECTORS.comboOffer);
			const title = offer.querySelector(isCombo ? '.eilmo-cf-combo-offer__title' : '.eilmo-cf-special-discount-card__title');
			const components = Array.from(offer.querySelectorAll('.eilmo-cf-combo-offer__item')).map(function (item) {
				const name = item.querySelector('.eilmo-cf-combo-offer__item-title');
				return (name ? name.textContent.trim() : '') + ' × ' + (item.dataset.quantity || '1');
			}).join(' + ');
			if (!isCombo && 'free_gift' === offer.dataset.offerType) return;
			if (!isCombo && 'product' !== offer.dataset.offerType) return;
			selected.push({
				name: title ? title.textContent.trim() : 'Offer',
				variation: components,
				quantity: 1,
				price: toNumber(isCombo ? offer.dataset.comboTotal : offer.dataset.bumpTotal),
				locked: true,
				isCombo: isCombo,
				comboId: isCombo ? String(offer.dataset.comboId || '').trim() : '',
			});
		});

		list.innerHTML = '';
		container.hidden = selected.length === 0;
		selected.forEach(function (entry) {
			const row = document.createElement('div');
			row.className = 'eilmo-cf-summary-item';
			row.dataset.productId = String(entry.productId);
			row.dataset.variationId = String(entry.variationId);

			if (showThumbnail && entry.image) {
				const img = document.createElement('img');
				img.className = 'eilmo-cf-summary-item__image';
				img.src = entry.image;
				img.alt = '';
				img.loading = 'lazy';
				row.appendChild(img);
			}

			const info = document.createElement('div');
			info.className = 'eilmo-cf-summary-item__info';
			const name = document.createElement('div');
			name.className = 'eilmo-cf-summary-item__name';
			name.textContent = (entry.name || 'Product') + ((entry.locked || !showQuantity) ? ' × ' + entry.quantity : '');
			info.appendChild(name);
			if (showVariation && entry.variation) {
				const variation = document.createElement('div');
				variation.className = 'eilmo-cf-summary-item__variation';
				variation.textContent = entry.variation;
				info.appendChild(variation);
			}
			row.appendChild(info);

			if (showQuantity && !entry.locked) {
				const qty = document.createElement('div');
				qty.className = 'eilmo-cf-summary-item__quantity';
				qty.innerHTML = '<button type="button" data-eilmo-summary-qty-minus aria-label="Decrease quantity"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button><span>' + entry.quantity + '</span><button type="button" data-eilmo-summary-qty-plus aria-label="Increase quantity"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M5 12h14"/></svg></button>';
				if (window.eilmoCfLanguage) qty.querySelectorAll('button').forEach(button => button.setAttribute('aria-label', window.eilmoCfLanguage.copy(checkout,button.getAttribute('aria-label'))));
				row.appendChild(qty);
			}

			if (showPrice) {
				const price = document.createElement('strong');
				price.className = 'eilmo-cf-summary-item__price';
				price.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatPrice(entry.price * entry.quantity),checkout.dataset.checkoutLanguage) : formatPrice(entry.price * entry.quantity);
				row.appendChild(price);
			}

			if (entry.isCombo && entry.comboId) {
				const remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'eilmo-cf-summary-item__remove';
				remove.setAttribute('data-eilmo-summary-combo-remove', '');
				remove.dataset.comboId = entry.comboId;
				remove.setAttribute('aria-label', 'Remove combo');
				remove.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';
				if (window.eilmoCfLanguage) {
					remove.setAttribute('aria-label', window.eilmoCfLanguage.copy(checkout, 'Remove combo'));
				}
				row.appendChild(remove);
			}

			list.appendChild(row);
		});
	}

	/**
	 * Handle quantity buttons rendered inside Order Summary.
	 *
	 * @param {MouseEvent} event Click event.
	 */
	function handleSummaryItemQuantity(event) {
		if (!(window.eilmoCfDom.isElement(event.target))) return;

		const comboRemove = event.target.closest('[data-eilmo-summary-combo-remove]');
		if (comboRemove) {
			const checkout = comboRemove.closest(SELECTORS.checkout);
			const comboId = String(comboRemove.dataset.comboId || '').trim();
			if (checkout && comboId && window.eilmoCfComboOffers && typeof window.eilmoCfComboOffers.deselect === 'function') {
				event.preventDefault();
				window.eilmoCfComboOffers.deselect(checkout, comboId, true);
			}
			return;
		}

	}

	/**
	 * Update Summary UI and shared checkout state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function hasSelectedProducts(checkout) {
		return Array.from(checkout.querySelectorAll(SELECTORS.quantityInput)).some(input => !input.disabled && toQuantity(input.value) > 0)
			|| Array.from(checkout.querySelectorAll(SELECTORS.comboInput)).some(input => input.checked && !input.disabled);
	}

	function updateSpecialRewardPresentation(checkout, values) {
		const gifts = [];
		checkout.querySelectorAll('[data-eilmo-order-bump][data-offer-type="free_gift"]').forEach(function (offer) {
			const input = offer.querySelector(SELECTORS.orderBumpInput);
			if (!input || !input.checked || input.disabled) return;
			const title = String(offer.dataset.rewardTitle || '').trim();
			if (title && !gifts.includes(title)) gifts.push(title);
		});

		const giftRow = checkout.querySelector('[data-eilmo-summary-row="free-gift"]');
		const giftText = checkout.querySelector('[data-eilmo-summary-text="free-gift"]');
		if (giftRow instanceof HTMLElement) { giftRow.hidden = gifts.length === 0; }
		if (giftText instanceof HTMLElement) { giftText.textContent = gifts.length ? gifts.join(', ') + ' — FREE' : ''; }

	}

	function updateSummary(checkout) {
		renderSelectedItems(checkout);
		const hasProducts = hasSelectedProducts(checkout);
		checkout.dataset.emptySelection = hasProducts ? 'no' : 'yes';
		checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
			button.disabled = !hasProducts || checkout.dataset.fraudBlocked === 'yes' || button.getAttribute('aria-busy') === 'true';
		});

		const values = calculateSummary(checkout);
		updateSpecialRewardPresentation(checkout, values);

		/*
		 * Summary rows.
		 */
		setAmount(
			checkout,
			'product-total',
			values.productTotal
		);

		setAmount(
			checkout,
			'combo-discount',
			values.comboOfferDiscount
		);

		setAmount(
			checkout,
			'order-bump-regular-total',
			values.orderBumpRegularTotal
		);

		setAmount(
			checkout,
			'order-bump-discount',
			values.orderBumpDiscount
		);

		setAmount(
			checkout,
			'order-bump-total',
			values.orderBumpTotal
		);

		setAmount(
			checkout,
			'automatic-discount',
			values.automaticDiscount
		);

		setAmount(
			checkout,
			'coupon-discount',
			values.couponDiscount
		);

		setAmount(
			checkout,
			'delivery-charge',
			values.deliveryCharge
		);

		setAmount(
			checkout,
			'advance-payment',
			values.advancePayment
		);

		setAmount(
			checkout,
			'full-payment-discount',
			values.fullPaymentDiscount
		);

		setAmount(
			checkout,
			'grand-total',
			values.grandTotal
		);

		setAmount(
			checkout,
			'pay-now',
			values.payNow
		);

		setAmount(
			checkout,
			'remaining-due',
			values.remainingDue
		);

		/*
		 * Shared state.
		 */
		Object.assign(
			checkout.dataset,
			{
				baseProductTotal:
					String(
						values
							.baseProductTotal
					),

				productTotal:
					String(
						values
							.productTotal
					),

				comboSelectedCount:
					String(
						values
							.comboSelectedCount
					),

				comboRegularTotal:
					String(
						values
							.comboRegularTotal
					),

				comboTotal:
					String(
						values
							.comboTotal
					),

				comboDiscount:
					String(
						values
							.comboOfferDiscount
					),

				comboAutomaticEligibleTotal:
					String(
						values
							.comboAutomaticEligibleTotal
					),

				comboCouponEligibleTotal:
					String(
						values
							.comboCouponEligibleTotal
					),

				comboFullPaymentEligibleRegularTotal:
					String(
						values
							.comboFullPaymentEligibleRegularTotal
					),

				comboFullPaymentEligibleTotal:
					String(
						values
							.comboFullPaymentEligibleTotal
					),

				orderBumpSelectedCount:
					String(
						values
							.orderBumpSelectedCount
					),

				orderBumpRegularTotal:
					String(
						values
							.orderBumpRegularTotal
					),

				orderBumpDiscount:
					String(
						values
							.orderBumpDiscount
					),

				orderBumpTotal:
					String(
						values
							.orderBumpTotal
					),

				orderBumpAutomaticEligibleTotal:
					String(
						values
							.orderBumpAutomaticEligibleTotal
					),

				orderBumpCouponEligibleTotal:
					String(
						values
							.orderBumpCouponEligibleTotal
					),

				orderBumpFullPaymentEligibleRegularTotal:
					String(
						values
							.orderBumpFullPaymentEligibleRegularTotal
					),

				orderBumpFullPaymentEligibleTotal:
					String(
						values
							.orderBumpFullPaymentEligibleTotal
					),

				automaticDiscountBase:
					String(
						values
							.automaticDiscountBase
					),

				automaticDiscount:
					String(
						values
							.automaticDiscount
					),

				couponBaseBeforeAutomatic:
					String(
						values
							.couponBaseBeforeAutomatic
					),

				couponAutomaticDiscount:
					String(
						values
							.couponAutomaticDiscount
					),

				couponDiscountBase:
					String(
						values
							.couponDiscountBase
					),

				couponDiscount:
					String(
						values
							.couponDiscount
					),

				discountedProductTotal:
					String(
						values
							.discountedProductTotal
					),

				baseGrandTotal:
					String(
						values
							.baseGrandTotal
					),

				fullPaymentDiscount:
					String(
						values
							.fullPaymentDiscount
					),

				fullPaymentEligibleGrandTotal:
					String(
						values
							.fullPaymentEligibleGrandTotal
					),

				grandTotal:
					String(
						values
							.grandTotal
					),

				payableGrandTotal:
					String(
						values
							.grandTotal
					),

				paymentType:
					values.paymentType,

				cashOnDeliverySelected:
					values.isCashOnDelivery
						? 'yes'
						: 'no',

				paymentMethodRequired:
					values.isCashOnDelivery
						? 'no'
						: 'yes',

				advanceAmount:
					String(
						values
							.advancePayment
					),

				payNow:
					String(
						values
							.payNow
					),

				remainingDue:
					String(
						values
							.remainingDue
					),
			}
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						values:
							values,
					},
				}
			)
		);

		return values;
	}

	/**
	 * Get responsive Summary mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getActiveSummaryMode(checkout) {
		if (
			window.innerWidth <=
				767
		) {
			return (
				checkout.dataset
					.summaryMobile ||
					'inline'
			);
		}

		if (
			window.innerWidth <=
				1024
		) {
			return (
				checkout.dataset
					.summaryTablet ||
					'below'
			);
		}

		return (
			checkout.dataset
				.summaryDesktop ||
				'right_sticky'
		);
	}

	/**
	 * Determine drawer mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isDrawerMode(checkout) {
		return (
			'bottom_drawer' ===
				getActiveSummaryMode(
					checkout
				)
		);
	}

	/**
	 * Update drawer accessibility.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     open     Open.
	 *
	 * @return {void}
	 */
	function updateDrawerAccessibility(
		checkout,
		open
	) {
		const panel =
			checkout.querySelector(
				SELECTORS.summaryPanel
			);

		checkout
			.querySelectorAll(
				SELECTORS.toggle
			)
			.forEach(
				function (toggle) {
					toggle.setAttribute(
						'aria-expanded',
						open
							? 'true'
							: 'false'
					);
				}
			);

		if (panel) {
            open = open || !isDrawerMode(checkout);
			panel.setAttribute(
				'aria-hidden',
				open
					? 'false'
					: 'true'
			);
		}
	}

	/**
	 * Open drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function openDrawer(checkout) {
		if (
			!isDrawerMode(
				checkout
			)
		) {
			return;
		}

		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		shell.dataset.summaryOpen =
			'yes';

		checkout.classList.add(
			'eilmo-cf-summary-drawer-open'
		);

		updateDrawerAccessibility(
			checkout,
			true
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryOpen',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,
					},
				}
			)
		);
	}

	/**
	 * Close drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function closeDrawer(checkout) {
		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		shell.dataset.summaryOpen =
			'no';

		checkout.classList.remove(
			'eilmo-cf-summary-drawer-open'
		);

		updateDrawerAccessibility(
			checkout,
			false
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryClose',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,
					},
				}
			)
		);
	}

	/**
	 * Toggle drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function toggleDrawer(checkout) {
		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		if (
			'yes' ===
				shell.dataset
					.summaryOpen
		) {
			closeDrawer(
				checkout
			);

			return;
		}

		openDrawer(
			checkout
		);
	}

	/**
	 * Handle click.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const toggle =
			event.target.closest(
				SELECTORS.toggle
			);

		if (toggle) {
			const checkout =
				getCheckout(
					toggle
				);

			if (!checkout) {
				return;
			}

			event.preventDefault();

			toggleDrawer(
				checkout
			);

			return;
		}

		const close =
			event.target.closest(
				SELECTORS.close
			);

		if (close) {
			const checkout =
				getCheckout(
					close
				);

			if (!checkout) {
				return;
			}

			event.preventDefault();

			closeDrawer(
				checkout
			);

			return;
		}

		const backdrop =
			event.target.closest(
				SELECTORS.backdrop
			);

		if (!backdrop) {
			return;
		}

		const checkout =
			getCheckout(
				backdrop
			);

		if (checkout) {
			closeDrawer(
				checkout
			);
		}
	}

	/**
	 * Handle Escape.
	 *
	 * @param {KeyboardEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleKeydown(event) {
		if (
			'Escape' !==
				event.key
		) {
			return;
		}

		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if (
						!(
							window.eilmoCfDom.isElement(checkout)
						)
					) {
						return;
					}

					const shell =
						getSummaryShell(
							checkout
						);

					if (
						shell &&
						'yes' ===
							shell.dataset
								.summaryOpen
					) {
						closeDrawer(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Simple checkout refresh handler.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSimpleRefresh(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (checkout) {
			updateSummary(
				checkout
			);
		}
	}

	/**
	 * Handle delivery change.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleDeliveryChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		const methodId =
			'string' ===
				typeof detail.methodId
				? detail.methodId
				: '';

		const charge =
			roundMoney(
				detail.charge ||
					0
			);

		const baseCharge =
			roundMoney(
				detail.baseCharge ||
					0
			);

		checkout.dataset.deliveryMethod =
			methodId;

		checkout.dataset.deliveryCharge =
			String(
				charge
			);

		checkout.dataset.deliveryBaseCharge =
			String(
				baseCharge
			);

		checkout.dataset.deliveryIsFree =
			true ===
				detail.isFree
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliveryApplied =
			true ===
				detail.freeDeliveryApplied
				? 'yes'
				: 'no';

		setAmount(
			checkout,
			'delivery-charge',
			charge
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Automatic Discount.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleDiscountChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		setAmount(
			checkout,
			'automatic-discount',
			toNumber(
				undefined !==
					detail.automaticDiscount
					? detail
						.automaticDiscount
					: (
						detail.discount ||
							0
					)
			)
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Coupon Discount.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCouponChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		setAmount(
			checkout,
			'coupon-discount',
			toNumber(
				detail.couponDiscount ||
					0
			)
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Cash on Delivery / Advance / Full Payment calculation.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleAdvancePaymentChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		const paymentType =
			normalizePaymentType(
				detail.paymentType ||
				detail.payment_type ||
					''
			) ||
			getPaymentType(
				checkout
			);

		const advanceAmount =
			'cash_on_delivery' ===
				paymentType
					? 0
					: roundMoney(
						detail.advanceAmount ||
						detail.advance_amount ||
							0
					);

		const appliedFullPaymentDiscount =
			'full' ===
				paymentType
					? roundMoney(
						detail
							.appliedFullPaymentDiscount ||
						detail
							.fullPaymentDiscount ||
							0
					)
					: 0;

		checkout.dataset.advanceReady =
			'yes';

		checkout.dataset.paymentType =
			paymentType;

		checkout.dataset.advanceAmount =
			String(
				advanceAmount
			);

		checkout.dataset.advanceRuleId =
			'string' ===
				typeof detail.ruleId
				? detail.ruleId
				: '';

		checkout.dataset.advanceRuleType =
			'cash_on_delivery' ===
				paymentType
					? 'cash_on_delivery'
					: (
						'string' ===
							typeof detail.ruleType
								? detail.ruleType
								: ''
					);

		checkout.dataset.fullPaymentDiscount =
			String(
				appliedFullPaymentDiscount
			);

		checkout.dataset.fullPaymentDiscountPreview =
			String(
				roundMoney(
					detail
						.fullPaymentDiscountPreview ||
						0
				)
			);

		if (
			undefined !==
				detail.fullPaymentProductTotal
		) {
			checkout.dataset
				.fullPaymentProductTotal =
					String(
						roundMoney(
							detail
								.fullPaymentProductTotal
						)
					);
		}

		if (
			undefined !==
				detail
					.fullPaymentDiscountedProductTotal
		) {
			checkout.dataset
				.fullPaymentDiscountedProductTotal =
					String(
						roundMoney(
							detail
								.fullPaymentDiscountedProductTotal
						)
					);
		}

		if (
			undefined !==
				detail
					.fullPaymentEligibleGrandTotal
		) {
			checkout.dataset
				.fullPaymentEligibleGrandTotal =
					String(
						roundMoney(
							detail
								.fullPaymentEligibleGrandTotal
						)
					);
		}

		checkout.dataset.baseGrandTotal =
			String(
				roundMoney(
					detail.baseGrandTotal ||
						0
				)
			);

		checkout.dataset.payableGrandTotal =
			String(
				roundMoney(
					detail.grandTotal ||
						0
				)
			);

		const eventGrandTotal =
			roundMoney(
				detail.grandTotal ||
				detail.grand_total ||
				checkout.dataset
					.grandTotal ||
					0
			);

		const eventPayNow =
			'cash_on_delivery' ===
				paymentType
					? 0
					: roundMoney(
						detail.payNow ||
						detail.pay_now ||
							0
					);

		const eventRemainingDue =
			'cash_on_delivery' ===
				paymentType
					? eventGrandTotal
					: roundMoney(
						detail.remainingDue ||
						detail.remaining_due ||
							0
					);

		checkout.dataset.payNow =
			String(
				eventPayNow
			);

		checkout.dataset.remainingDue =
			String(
				eventRemainingDue
			);

		checkout.dataset.cashOnDeliverySelected =
			'cash_on_delivery' ===
				paymentType
					? 'yes'
					: 'no';

		checkout.dataset.paymentMethodRequired =
			'cash_on_delivery' ===
				paymentType
					? 'no'
					: 'yes';

		setAmount(
			checkout,
			'advance-payment',
			advanceAmount
		);

		setAmount(
			checkout,
			'full-payment-discount',
			appliedFullPaymentDiscount
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle generic Summary refresh.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSummaryRefresh(event) {
		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		/*
		 * Order Bump already has a dedicated change event.
		 */
		if (
			'order-bump' ===
				detail.source
		) {
			return;
		}

		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (checkout) {
			updateSummary(
				checkout
			);
		}
	}

	/**
	 * Keep mobile Summary drawer focused on order summary content only.
	 *
	 * Checkout details/order actions are rendered once by PHP. On mobile,
	 * move those existing DOM nodes into the normal checkout flow rather
	 * than cloning them. This preserves all payment/customer state and
	 * event listeners while preventing duplicate form controls.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 *
	 * @return {void}
	 */
	function syncMobileSummaryContent(checkout) {
		const target = checkout.querySelector(SELECTORS.mobileCheckoutDetailsTarget);
		const panel = checkout.querySelector(SELECTORS.summaryPanel);

		if (!target || !panel) {
			return;
		}

		const details = checkout.querySelector(SELECTORS.summaryCheckoutDetails);
		const orderAction = checkout.querySelector(SELECTORS.summaryOrderAction);
		const whatsappAction = checkout.querySelector(SELECTORS.summaryWhatsAppAction);
		const anchor = panel.querySelector(SELECTORS.summaryRelocationAnchor);
		const mobile = window.matchMedia('(max-width: 767px)').matches;

		if (mobile) {
			if (orderAction && orderAction.parentElement !== target) {
				target.appendChild(orderAction);
			}

			if (whatsappAction && whatsappAction.parentElement !== target) {
				target.appendChild(whatsappAction);
			}

			if (details && details.parentElement !== target) {
				target.appendChild(details);
			}

			return;
		}

		if (orderAction && orderAction.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(orderAction, anchor);
			} else {
				panel.appendChild(orderAction);
			}
		}

		if (whatsappAction && whatsappAction.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(whatsappAction, anchor);
			} else {
				panel.appendChild(whatsappAction);
			}
		}

		if (details && details.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(details, anchor);
			} else {
				panel.appendChild(details);
			}
		}
	}

	/**
	 * Handle responsive resize.
	 *
	 * @return {void}
	 */
	function handleResize() {
		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if ( window.eilmoCfDom.isElement(checkout) ) {
						syncMobileSummaryContent( checkout );
					}

					if (
						window.eilmoCfDom.isElement(checkout) &&
						!isDrawerMode(
							checkout
						)
					) {
						closeDrawer(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(checkout) {
		syncMobileSummaryContent( checkout );

		updateSummary(
			checkout
		);

		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		let open =
			'yes' ===
				shell.dataset
					.summaryOpen;

		if (
			isDrawerMode(
				checkout
			) &&
			'yes' ===
				checkout.dataset
					.mobileSummaryCollapsed
		) {
			open =
				false;

			shell.dataset.summaryOpen =
				'no';
		}

		checkout.classList.toggle(
			'eilmo-cf-summary-drawer-open',
			isDrawerMode( checkout ) && open
		);

		updateDrawerAccessibility(
			checkout,
			open
		);
	}

	/**
	 * Initialize all checkout instances.
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
	 * Public Summary API.
	 */
	window.eilmoCfSummary = {

		/**
		 * Format price.
		 *
		 * @param {number} amount Amount.
		 *
		 * @return {string}
		 */
		formatPrice:
			function (amount) {
				return formatPrice(
					amount
				);
			},

		/**
		 * Refresh one or all checkout instances.
		 *
		 * @param {HTMLElement|null} checkout Checkout.
		 *
		 * @return {void}
		 */
		refresh:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					updateSummary(
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
								updateSummary(
									item
								);
							}
						}
					);
			},

		/**
		 * Set external amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      key      Key.
		 * @param {number}      amount   Amount.
		 *
		 * @return {void}
		 */
		setAmount:
			function (
				checkout,
				key,
				amount
			) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return;
				}

				setAmount(
					checkout,
					key,
					amount
				);

				updateSummary(
					checkout
				);
			},

		/**
		 * Get amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      key      Key.
		 *
		 * @return {number}
		 */
		getAmount:
			function (
				checkout,
				key
			) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				return getAmount(
					checkout,
					key
				);
			},

		/**
		 * Get complete Summary values.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getValues:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return null;
				}

				return calculateSummary(
					checkout
				);
			},

		/**
		 * Get selected Payment Option.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {string}
		 */
		getPaymentType:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 'full';
				}

				return getPaymentType(
					checkout
				);
			},

		/**
		 * Determine whether COD is selected.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {boolean}
		 */
		isCashOnDelivery:
			function (checkout) {
				return (
					window.eilmoCfDom.isElement(checkout) &&
					isCashOnDelivery(
						checkout
					)
				);
			},

		/**
		 * Get Combo totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
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

						payableTotal:
							0,

						comboTotal:
							0,

						automaticEligibleTotal:
							0,

						couponEligibleTotal:
							0,

						fullPaymentEligibleRegularTotal:
							0,

						fullPaymentEligibleTotal:
							0,

						automaticCouponOverlapTotal:
							0,

						automaticFullPaymentOverlapTotal:
							0,

						fullPaymentCouponOverlapTotal:
							0,

						automaticFullPaymentCouponOverlapTotal:
							0,
					};
				}

				return calculateComboTotals(
					checkout
				);
			},

		/**
		 * Get Order Bump totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
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

						payableTotal:
							0,

						bumpTotal:
							0,

						automaticEligibleTotal:
							0,

						couponEligibleTotal:
							0,

						fullPaymentEligibleRegularTotal:
							0,

						fullPaymentEligibleTotal:
							0,

						automaticCouponOverlapTotal:
							0,

						automaticFullPaymentOverlapTotal:
							0,

						fullPaymentCouponOverlapTotal:
							0,

						automaticFullPaymentCouponOverlapTotal:
							0,
					};
				}

				return calculateOrderBumpTotals(
					checkout
				);
			},

		/**
		 * Open mobile Summary.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		open:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					openDrawer(
						checkout
					);
				}
			},

		/**
		 * Close mobile Summary.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		close:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					closeDrawer(
						checkout
					);
				}
			},
	};

	document.addEventListener(
		'click',
		handleSummaryItemQuantity
	);

	document.addEventListener(
		'eilmo:quantityChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:comboChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:orderBumpChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:deliveryChange',
		handleDeliveryChange
	);

	document.addEventListener(
		'eilmo:discountChange',
		handleDiscountChange
	);

	document.addEventListener(
		'eilmo:couponChange',
		handleCouponChange
	);

	document.addEventListener(
		'eilmo:advancePaymentChange',
		handleAdvancePaymentChange
	);

	document.addEventListener(
		'eilmo:summaryRefresh',
		handleSummaryRefresh
	);

	document.addEventListener(
		'click',
		handleClick
	);

	document.addEventListener(
		'keydown',
		handleKeydown
	);

	window.addEventListener(
		'resize',
		handleResize
	);

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
