/**
 * Eilmo Checkout Flow - Delivery.
 *
 * Handles:
 * - Delivery method selection.
 * - Combo-priced checkout totals.
 * - Dynamic free-delivery thresholds.
 * - Automatic Discount free-delivery rules.
 * - Coupon free-delivery rules.
 * - Minimum amount 0 = immediately free.
 * - All delivery methods free.
 * - Specific delivery method free.
 * - Hide other methods.
 * - Restoring normal delivery charges.
 * - Delivery validation.
 * - Summary synchronization.
 * - Multiple checkout instances.
 *
 * Calculation order:
 *
 * Normal Products
 * + Combo Regular Total
 * - Combo Discount
 * - Automatic Discount
 * - Coupon Discount
 * = Delivery calculation basis.
 *
 * Important:
 * All frontend calculations are UI previews only.
 * The server-side DeliveryCalculator remains the
 * final authority for delivery availability and charge.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/**
	 * Selectors.
	 *
	 * @type {Object}
	 */
	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout], .eilmo-cf-checkout',

		item:
			'[data-eilmo-product-item], [data-eilmo-item]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		comboOffer:
			'[data-eilmo-combo-offer]',

		comboInput:
			'[data-eilmo-combo-input]',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		delivery:
			'[data-eilmo-delivery]',

		advancePayment:
			'[data-eilmo-advance-payment]',

		method:
			'[data-eilmo-delivery-method]',

		input:
			'[data-eilmo-delivery-input]',

		price:
			'[data-eilmo-delivery-price]',

		saving:
			'[data-eilmo-delivery-saving]',

		error:
			'[data-eilmo-delivery-error]',
	};

	/**
	 * CSS classes.
	 *
	 * @type {Object}
	 */
	const CLASSES = {
		selected:
			'eilmo-cf-delivery-method--selected',

		free:
			'eilmo-cf-delivery-method--free',

		error:
			'eilmo-cf-delivery--has-error',
	};

	/**
	 * Scheduled checkout refreshes.
	 *
	 * Prevents duplicate recalculation when one action
	 * causes multiple dependent feature events.
	 *
	 * @type {WeakSet}
	 */
	const scheduled =
		new WeakSet();

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

		return Number.isFinite(number)
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
			Number.parseFloat(
				String(value)
			);

		if (
			!Number.isFinite(
				quantity
			)
		) {
			return 0;
		}

		return Math.max(
			0,
			quantity
		);
	}

	/**
	 * Convert value to a non-negative integer identity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number =
			Number.parseInt(
				String(
					value || 0
				),
				10
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
	 * Get WooCommerce price decimals.
	 *
	 * @return {number}
	 */
	function getPriceDecimals() {
		const config =
			window.eilmoCf || {};

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
	 * Round monetary value.
	 *
	 * @param {*} value Value.
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
	 * Decode localized HTML entities.
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
					: 2,

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

		const safeAmount =
			roundMoney(
				amount
			);

		const fixed =
			safeAmount.toFixed(
				config.decimals
			);

		const parts =
			fixed.split(
				'.'
			);

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		if (
			config.decimals > 0 &&
			parts.length > 1
		) {
			return (
				parts[0] +
				config.decimalSeparator +
				parts[1]
			);
		}

		return parts[0];
	}

	/**
	 * Format WooCommerce-style price.
	 *
	 * Uses Summary formatter when available.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.formatPrice
		) {
			return window
				.eilmoCfSummary
				.formatPrice(
					amount
				);
		}

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
				typeof window
					.eilmoCf
					.i18n
					.free
		) {
			return window
				.eilmoCf
				.i18n
				.free;
		}

		return 'Free';
	}

	/**
	 * Get required-selection message.
	 *
	 * @return {string}
	 */
	function getRequiredMessage() {
		if (
			window.eilmoCf &&
			window.eilmoCf.i18n &&
			'string' ===
				typeof window
					.eilmoCf
					.i18n
					.deliveryRequired
		) {
			return window
				.eilmoCf
				.i18n
				.deliveryRequired;
		}

		return 'Please select a delivery method.';
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element} element Element.
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

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
				? checkout
				: null;
	}

	/**
	 * Get checkout from custom event.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckoutFromEvent(
		event
	) {
		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail.checkout;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			return getCheckout(
				event.target
			);
		}

		return null;
	}

	/**
	 * Get delivery section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getDelivery(checkout) {
		const delivery =
			checkout.querySelector(
				SELECTORS.delivery
			);

		return window.eilmoCfDom.isElement(delivery)
				? delivery
				: null;
	}

	/**
	 * Calculate regular selected product total.
	 *
	 * Combo component products are calculated separately.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateBaseProductTotal(
		checkout
	) {
		const itemTotals =
			new Map();

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
					 * Normal products only. Combo Offers and Order
					 * Bumps have independent payable totals and must
					 * never enter this base through nested adapters.
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

					if (price <= 0) {
						return;
					}

					const productId =
						toInteger(
							item.dataset
								.productId ||
							input.dataset
								.productId
						);

					const variationId =
						toInteger(
							item.dataset
								.variationId ||
							input.dataset
								.variationId
						);

					/*
					 * Multiple Products may expose more than one DOM
					 * adapter for the same exact order item. Keep one
					 * amount per Simple product / variation and never
					 * add duplicate representations together.
					 */
					const key =
						variationId > 0
							? 'variation:' + variationId
							: productId > 0
								? 'product:' + productId
								: item;

					const lineTotal =
						roundMoney(
							price *
								quantity
						);

					const existing =
						toNumber(
							itemTotals.get(
								key
							) || 0
						);

					itemTotals.set(
						key,
						Math.max(
							existing,
							lineTotal
						)
					);
				}
			);

		let total =
			0;

		itemTotals.forEach(
			function (lineTotal) {
				total +=
					toNumber(
						lineTotal
					);
			}
		);

		return roundMoney(
			total
		);
	}

	/**
	 * Get selected Combo Offer totals.
	 *
	 * Frontend Combo totals are display previews only.
	 *
	 * Final Combo items and pricing are recalculated
	 * server-side.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getComboTotals(
		checkout
	) {
		/*
		 * Prefer Summary's canonical Combo calculation.
		 */
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getComboTotals
		) {
			const values =
				window.eilmoCfSummary
					.getComboTotals(
						checkout
					);

			if (
				values &&
				'object' ===
					typeof values
			) {
				const regularTotal =
					roundMoney(
						values.regularTotal ||
							0
					);

				const discount =
					roundMoney(
						Math.min(
							toNumber(
								values.discount ||
									0
							),
							regularTotal
						)
					);

				const hasComboTotal =
					Object.prototype
						.hasOwnProperty.call(
							values,
							'comboTotal'
						);

				return {
					selectedCount:
						Math.max(
							0,
							Number.parseInt(
								String(
									values.selectedCount ||
										0
								),
								10
							) || 0
						),

					regularTotal:
						regularTotal,

					discount:
						discount,

					comboTotal:
						roundMoney(
							Math.min(
								regularTotal,
								hasComboTotal
									? toNumber(
										values.comboTotal
									)
									: Math.max(
										0,
										regularTotal -
											discount
									)
							)
						),
				};
			}
		}

		let regularTotal =
			0;

		let discount =
			0;

		let comboTotal =
			0;

		let selectedCount =
			0;

		/*
		 * Fallback to Combo public API.
		 */
		if (
			window.eilmoCfComboOffers &&
			'function' ===
				typeof window
					.eilmoCfComboOffers
					.getSelectedOffers
		) {
			const offers =
				window.eilmoCfComboOffers
					.getSelectedOffers(
						checkout
					);

			if (
				Array.isArray(
					offers
				)
			) {
				offers.forEach(
					function (offer) {
						if (
							!offer ||
							'object' !==
								typeof offer
						) {
							return;
						}

						const offerRegular =
							roundMoney(
								offer.regular_total ||
									0
							);

						const offerDiscount =
							roundMoney(
								Math.min(
									toNumber(
										offer.discount ||
											0
									),
									offerRegular
								)
							);

						const hasComboTotal =
							Object.prototype
								.hasOwnProperty.call(
									offer,
									'combo_total'
								);

						const offerCombo =
							roundMoney(
								Math.min(
									offerRegular,
									hasComboTotal
										? toNumber(
											offer.combo_total
										)
										: Math.max(
											0,
											offerRegular -
												offerDiscount
										)
								)
							);

						regularTotal =
							roundMoney(
								regularTotal +
									offerRegular
							);

						discount =
							roundMoney(
								discount +
									offerDiscount
							);

						comboTotal =
							roundMoney(
								comboTotal +
									offerCombo
							);

						selectedCount +=
							1;
					}
				);

				return {
					selectedCount:
						selectedCount,

					regularTotal:
						regularTotal,

					discount:
						roundMoney(
							Math.min(
								discount,
								regularTotal
							)
						),

					comboTotal:
						comboTotal,
				};
			}
		}

		/*
		 * DOM fallback.
		 */
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
						input.disabled ||
						'no' ===
							offer.dataset.available
					) {
						return;
					}

					const offerRegular =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const offerDiscount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset
										.comboDiscount ||
										0
								),
								offerRegular
							)
						);

					const hasComboTotal =
						'string' ===
							typeof offer
								.dataset
								.comboTotal;

					const offerCombo =
						roundMoney(
							Math.min(
								offerRegular,
								hasComboTotal
									? toNumber(
										offer.dataset
											.comboTotal
									)
									: Math.max(
										0,
										offerRegular -
											offerDiscount
									)
							)
						);

					regularTotal =
						roundMoney(
							regularTotal +
								offerRegular
						);

					discount =
						roundMoney(
							discount +
								offerDiscount
						);

					comboTotal =
						roundMoney(
							comboTotal +
								offerCombo
						);

					selectedCount +=
						1;
				}
			);

		return {
			selectedCount:
				selectedCount,

			regularTotal:
				regularTotal,

			discount:
				roundMoney(
					Math.min(
						discount,
						regularTotal
					)
				),

			comboTotal:
				comboTotal,
		};
	}

	/**
	 * Get current Automatic Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
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
				window.eilmoCfDiscount
					.getDiscount(
						checkout
					)
			);
		}

		return roundMoney(
			checkout.dataset
				.automaticDiscount ||
				0
		);
	}

	/**
	 * Get current Coupon Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
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
				window.eilmoCfSummary
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

	/**
	 * Build current delivery calculation totals.
	 *
	 * Server-side OrderCreator calculates delivery after:
	 *
	 * Combo pricing
	 * Automatic Discount
	 * Coupon Discount
	 *
	 * Full Payment Discount is intentionally NOT included
	 * because it is applied after Delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getDeliveryTotals(
		checkout
	) {
		const baseProductTotal =
			calculateBaseProductTotal(
				checkout
			);

		const combo =
			getComboTotals(
				checkout
			);

		const regularProductTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal
			);

		const comboDiscount =
			roundMoney(
				Math.min(
					combo.discount,
					regularProductTotal
				)
			);

		const afterComboDiscount =
			roundMoney(
				Math.max(
					0,
					regularProductTotal -
						comboDiscount
				)
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAutomaticDiscount(
						checkout
					),
					afterComboDiscount
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					afterComboDiscount -
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

		return {
			baseProductTotal:
				baseProductTotal,

			comboSelectedCount:
				combo.selectedCount,

			comboRegularTotal:
				combo.regularTotal,

			comboDiscount:
				comboDiscount,

			comboTotal:
				combo.comboTotal,

			regularProductTotal:
				regularProductTotal,

			automaticDiscount:
				automaticDiscount,

			couponDiscount:
				couponDiscount,

			discountedProductTotal:
				discountedProductTotal,

			deliveryBasisTotal:
				discountedProductTotal,
		};
	}

	/**
	 * Get current product total used by Delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getProductTotal(
		checkout
	) {
		return getDeliveryTotals(
			checkout
		).deliveryBasisTotal;
	}

	/**
	 * Get external free-delivery state.
	 *
	 * External sources:
	 *
	 * - Automatic Discount free-delivery rule.
	 * - Applied Coupon free-delivery rule.
	 *
	 * These sources override configured delivery
	 * threshold charge behavior and make all currently
	 * available delivery methods free.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getExternalFreeDeliveryState(
		checkout
	) {
		const automaticDiscount = 'yes' === checkout.dataset.automaticDiscountFreeDelivery;
		const coupon = 'yes' === checkout.dataset.couponFreeDelivery;

		const advanceSection = checkout.querySelector(SELECTORS.advancePayment);
		let fullPaymentFreeDelivery = false;
		let paymentType = String(checkout.dataset.paymentType || '');

		if (window.eilmoCfDom.isElement(advanceSection)) {
			if (!paymentType) {
				const selected = advanceSection.querySelector('[data-eilmo-payment-type]:checked');
				paymentType = window.eilmoCfDom.isElement(selected, 'INPUT')
					? String(selected.value || '')
					: '';
			}

			try {
				const config = JSON.parse(advanceSection.getAttribute('data-full-payment-discount') || '{}');
				fullPaymentFreeDelivery =
					'full' === paymentType &&
					config &&
					'object' === typeof config &&
					'yes' === String(config.free_delivery || 'no');
			} catch (error) {
				fullPaymentFreeDelivery = false;
			}
		}

		const methodId = (coupon || fullPaymentFreeDelivery)
			? ''
			: String(checkout.dataset.automaticDiscountFreeDeliveryMethod || '');
		const hideOtherMethods =
			!coupon &&
			!fullPaymentFreeDelivery &&
			'yes' === checkout.dataset.automaticDiscountFreeDeliveryHideOtherMethods;

		return {
			active: coupon || automaticDiscount || fullPaymentFreeDelivery,
			coupon: coupon,
			automaticDiscount: automaticDiscount,
			fullPayment: fullPaymentFreeDelivery,
			source: coupon
				? 'coupon'
				: (fullPaymentFreeDelivery ? 'full_payment' : (automaticDiscount ? 'special_discount' : '')),
			methodId: methodId,
			hideOtherMethods: hideOtherMethods,
		};
	}

	/**
	 * Get selected delivery input.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getSelectedInput(
		delivery
	) {
		const input =
			delivery.querySelector(
				SELECTORS.input +
					':checked'
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Get method wrapper.
	 *
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {HTMLElement|null}
	 */
	function getMethodFromInput(
		input
	) {
		const method =
			input.closest(
				SELECTORS.method
			);

		return window.eilmoCfDom.isElement(method)
				? method
				: null;
	}

	/**
	 * Determine whether configured method exists.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {string}      methodId Method ID.
	 *
	 * @return {boolean}
	 */
	function methodExists(
		delivery,
		methodId
	) {
		if (!methodId) {
			return false;
		}

		return Array.from(
			delivery.querySelectorAll(
				SELECTORS.method
			)
		).some(
			function (method) {
				return (
					window.eilmoCfDom.isElement(method) &&
					method.dataset
						.methodId ===
						methodId
				);
			}
		);
	}

	/**
	 * Update method price UI.
	 *
	 * @param {HTMLElement} method      Method.
	 * @param {number}      charge      Current charge.
	 * @param {boolean}     freeApplied Free applied.
	 * @param {string}      freeSource  Free source.
	 *
	 * @return {void}
	 */
	function updateMethodPrice(
		method,
		charge,
		freeApplied,
		freeSource = ''
	) {
		const price =
			method.querySelector(
				SELECTORS.price
			);

		if (
			window.eilmoCfDom.isElement(price)
		) {
			price.textContent =
				charge <= 0
					? getFreeLabel()
					: formatPrice(
						charge
					);
		}

		/*
		 * A naturally zero-price method is also visually
		 * represented as Free.
		 */
		method.classList.toggle(
			CLASSES.free,
			charge <= 0
		);

		method.dataset.freeDelivery =
			freeApplied
				? 'yes'
				: 'no';

		method.dataset.freeDeliverySource =
			freeApplied
				? String(
					freeSource ||
						'configured'
				)
				: '';

		const saving =
			method.querySelector(
				SELECTORS.saving
			);

		if (
			window.eilmoCfDom.isElement(saving)
		) {
			const baseCharge =
				toNumber(
					method.dataset
						.baseCharge ||
						0
				);

			/*
			 * Show the saving notice only when a normally
			 * paid delivery method becomes free.
			 */
			saving.hidden =
				!(
					freeApplied &&
					baseCharge > 0
				);
		}
	}

	/**
	 * Restore one method to normal charge.
	 *
	 * @param {HTMLElement} method Method.
	 *
	 * @return {void}
	 */
	function restoreMethod(
		method
	) {
		const baseCharge =
			toNumber(
				method.dataset
					.baseCharge ||
					0
			);

		method.dataset.charge =
			String(
				baseCharge
			);

		const input =
			method.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.dataset.charge =
				String(
					baseCharge
				);

			input.dataset.baseCharge =
				String(
					baseCharge
				);
		}

		updateMethodPrice(
			method,
			baseCharge,
			false
		);

		method.hidden =
			false;
	}

	/**
	 * Apply dynamic free-delivery settings.
	 *
	 * Configured free delivery:
	 *
	 * - Minimum 0 = immediately active.
	 * - Blank method = all methods free.
	 * - Specific method = only that method free.
	 * - Hide Other Methods applies to configured
	 *   specific-method rules.
	 *
	 * External free delivery:
	 *
	 * - Coupon free delivery.
	 * - Automatic Discount free delivery.
	 * - Makes all currently available methods free.
	 * - Does not hide delivery alternatives.
	 *
	 * @param {HTMLElement} delivery     Delivery.
	 * @param {number}      productTotal Delivery calculation basis.
	 *
	 * @return {void}
	 */
	function applyFreeDelivery(
		delivery,
		productTotal
	) {
		const checkout = getCheckout(delivery);
		const reward = checkout
			? getExternalFreeDeliveryState(checkout)
			: {active: false, coupon: false, automaticDiscount: false, source: '', methodId: '', hideOtherMethods: false};

		/*
		 * Delivery JS no longer evaluates its own threshold. The unified Special
		 * Discount preview publishes an already-resolved reward, while PHP remains
		 * authoritative. Coupon free delivery stays an independent coupon benefit.
		 */
		const freeDeliveryActive = reward.active;
		const freeMethodId = String(reward.methodId || '');
		const hideOtherMethods = Boolean(reward.hideOtherMethods);
		const allMethodsFree = reward.coupon || '' === freeMethodId;
		const specificMethodExists = allMethodsFree || methodExists(delivery, freeMethodId);

		delivery.dataset.orderTotal = String(productTotal);
		delivery.dataset.freeDeliveryActive = freeDeliveryActive ? 'yes' : 'no';
		delivery.dataset.configuredFreeDeliveryActive = 'no';
		delivery.dataset.externalFreeDeliveryActive = freeDeliveryActive ? 'yes' : 'no';
		delivery.dataset.couponFreeDelivery = reward.coupon ? 'yes' : 'no';
		delivery.dataset.automaticDiscountFreeDelivery = reward.automaticDiscount ? 'yes' : 'no';
		delivery.dataset.fullPaymentFreeDelivery = reward.fullPayment ? 'yes' : 'no';
		delivery.dataset.freeDeliverySource = reward.source;

		delivery.querySelectorAll(SELECTORS.method).forEach(function (method) {
			if (!(window.eilmoCfDom.isElement(method))) return;
			restoreMethod(method);
			if (!freeDeliveryActive || !specificMethodExists) return;

			const methodId = method.dataset.methodId || '';
			const shouldBeFree = allMethodsFree || methodId === freeMethodId;
			if (shouldBeFree) {
				method.dataset.charge = '0';
				const input = method.querySelector(SELECTORS.input);
				if (window.eilmoCfDom.isElement(input, 'INPUT')) input.dataset.charge = '0';
				updateMethodPrice(method, 0, true, reward.source);
			}

			if (reward.coupon || allMethodsFree || !hideOtherMethods) {
				method.hidden = false;
			} else {
				method.hidden = methodId !== freeMethodId;
			}
		});

		ensureValidSelection(delivery);
	}

	/**
	 * Ensure selected method remains available.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function ensureValidSelection(
		delivery
	) {
		const selected =
			getSelectedInput(
				delivery
			);

		if (selected) {
			const selectedMethod =
				getMethodFromInput(
					selected
				);

			if (
				selectedMethod &&
				!selectedMethod.hidden &&
				!selected.disabled
			) {
				return;
			}

			selected.checked =
				false;
		}

		const methods =
			Array.from(
				delivery.querySelectorAll(
					SELECTORS.method
				)
			);

		const available =
			methods.find(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						) ||
						method.hidden
					) {
						return false;
					}

					const input =
						method.querySelector(
							SELECTORS.input
						);

					return (
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						!input.disabled
					);
				}
			);

		if (!available) {
			return;
		}

		const input =
			available.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.checked =
				true;
		}
	}

	/**
	 * Update selected card state.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function updateSelectedClasses(
		delivery
	) {
		delivery
			.querySelectorAll(
				SELECTORS.method
			)
			.forEach(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						)
					) {
						return;
					}

					const input =
						method.querySelector(
							SELECTORS.input
						);

					const selected =
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						input.checked;

					method.classList.toggle(
						CLASSES.selected,
						selected
					);

					method.dataset.selected =
						selected
							? 'yes'
							: 'no';

					method.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);
				}
			);
	}

	/**
	 * Build selected method state.
	 *
	 * @param {HTMLInputElement|null} input Input.
	 *
	 * @return {Object}
	 */
	function getMethodState(
		input
	) {
		const empty = {
			methodId:
				'',

			label:
				'',

			charge:
				0,

			baseCharge:
				0,

			isFree:
				false,

			freeDeliveryApplied:
				false,

			freeDeliverySource:
				'',
		};

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return empty;
		}

		const method =
			getMethodFromInput(
				input
			);

		if (!method) {
			return empty;
		}

		const charge =
			toNumber(
				method.dataset.charge ||
					input.dataset.charge ||
					0
			);

		const baseCharge =
			toNumber(
				method.dataset.baseCharge ||
					input.dataset.baseCharge ||
					0
			);

		const labelElement =
			method.querySelector(
				'.eilmo-cf-delivery-method__label'
			);

		return {
			methodId:
				method.dataset.methodId ||
				input.dataset.methodId ||
				input.value ||
				'',

			label:
				labelElement
					? labelElement
						.textContent
						.trim()
					: '',

			charge:
				charge,

			baseCharge:
				baseCharge,

			/*
			 * Includes both:
			 *
			 * - rule-generated free delivery;
			 * - naturally zero-cost methods.
			 */
			isFree:
				charge <= 0,

			freeDeliveryApplied:
				'yes' ===
					method.dataset
						.freeDelivery,

			freeDeliverySource:
				method.dataset
					.freeDeliverySource ||
					'',
		};
	}

	/**
	 * Clear validation error.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function clearError(
		delivery
	) {
		delivery.classList.remove(
			CLASSES.error
		);

		const error =
			delivery.querySelector(
				SELECTORS.error
			);

		if (
			!(
				window.eilmoCfDom.isElement(error)
			)
		) {
			return;
		}

		error.textContent =
			'';

		error.hidden =
			true;
	}

	/**
	 * Show validation error.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {string}      message  Message.
	 *
	 * @return {void}
	 */
	function showError(
		delivery,
		message
	) {
		delivery.classList.add(
			CLASSES.error
		);

		const error =
			delivery.querySelector(
				SELECTORS.error
			);

		if (
			!(
				window.eilmoCfDom.isElement(error)
			)
		) {
			return;
		}

		error.textContent =
			message;

		error.hidden =
			false;
	}

	/**
	 * Validate delivery selection.
	 *
	 * Client validation is UX only.
	 * Server validation remains authoritative.
	 *
	 * @param {HTMLElement} delivery    Delivery.
	 * @param {boolean}     showMessage Show message.
	 *
	 * @return {boolean}
	 */
	function validate(
		delivery,
		showMessage = true
	) {
		const required =
			'yes' ===
				delivery.dataset
					.required;

		if (!required) {
			clearError(
				delivery
			);

			return true;
		}

		const selected =
			getSelectedInput(
				delivery
			);

		if (selected) {
			const method =
				getMethodFromInput(
					selected
				);

			if (
				method &&
				!method.hidden &&
				!selected.disabled
			) {
				clearError(
					delivery
				);

				return true;
			}
		}

		if (showMessage) {
			showError(
				delivery,
				getRequiredMessage()
			);
		}

		return false;
	}

	/**
	 * Dispatch delivery change.
	 *
	 * Summary / Advance Payment listen to this event.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {Object|null} totals   Delivery totals.
	 *
	 * @return {void}
	 */
	function dispatchDeliveryChange(
		delivery,
		totals = null
	) {
		const checkout =
			getCheckout(
				delivery
			);

		if (!checkout) {
			return;
		}

		const calculationTotals =
			totals &&
			'object' ===
				typeof totals
				? totals
				: getDeliveryTotals(
					checkout
				);

		const selected =
			getSelectedInput(
				delivery
			);

		const state =
			getMethodState(
				selected
			);

		delivery.dataset.selectedMethod =
			state.methodId;

		delivery.dataset.selectedCharge =
			String(
				state.charge
			);

		delivery.dataset.selectedBaseCharge =
			String(
				state.baseCharge
			);

		delivery.dataset.freeDeliveryApplied =
			state.freeDeliveryApplied
				? 'yes'
				: 'no';

		delivery.dataset.selectedFreeDeliverySource =
			state.freeDeliverySource ||
				'';

		/*
		 * Store calculation context.
		 */
		delivery.dataset.baseProductTotal =
			String(
				calculationTotals
					.baseProductTotal
			);

		delivery.dataset.comboRegularTotal =
			String(
				calculationTotals
					.comboRegularTotal
			);

		delivery.dataset.comboDiscount =
			String(
				calculationTotals
					.comboDiscount
			);

		delivery.dataset.regularProductTotal =
			String(
				calculationTotals
					.regularProductTotal
			);

		delivery.dataset.automaticDiscount =
			String(
				calculationTotals
					.automaticDiscount
			);

		delivery.dataset.couponDiscount =
			String(
				calculationTotals
					.couponDiscount
			);

		delivery.dataset.deliveryBasisTotal =
			String(
				calculationTotals
					.deliveryBasisTotal
			);

		/*
		 * Shared checkout delivery state.
		 */
		checkout.dataset.deliveryMethod =
			state.methodId;

		checkout.dataset.deliveryCharge =
			String(
				state.charge
			);

		checkout.dataset.deliveryBaseCharge =
			String(
				state.baseCharge
			);

		checkout.dataset.deliveryIsFree =
			state.isFree
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliveryApplied =
			state.freeDeliveryApplied
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliverySource =
			state.freeDeliverySource ||
				'';

		checkout.dataset.deliveryBasisTotal =
			String(
				calculationTotals
					.deliveryBasisTotal
			);

		delivery.dispatchEvent(
			new CustomEvent(
				'eilmo:deliveryChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						delivery:
							delivery,

						methodId:
							state.methodId,

						label:
							state.label,

						charge:
							state.charge,

						baseCharge:
							state.baseCharge,

						isFree:
							state.isFree,

						freeDeliveryApplied:
							state
								.freeDeliveryApplied,

						freeDeliverySource:
							state
								.freeDeliverySource,

						couponFreeDelivery:
							'yes' ===
								delivery.dataset
									.couponFreeDelivery,

						automaticDiscountFreeDelivery:
							'yes' ===
								delivery.dataset
									.automaticDiscountFreeDelivery,

						/*
						 * Calculation context.
						 */
						baseProductTotal:
							calculationTotals
								.baseProductTotal,

						comboSelectedCount:
							calculationTotals
								.comboSelectedCount,

						comboRegularTotal:
							calculationTotals
								.comboRegularTotal,

						comboDiscount:
							calculationTotals
								.comboDiscount,

						comboOfferDiscount:
							calculationTotals
								.comboDiscount,

						comboTotal:
							calculationTotals
								.comboTotal,

						regularProductTotal:
							calculationTotals
								.regularProductTotal,

						automaticDiscount:
							calculationTotals
								.automaticDiscount,

						couponDiscount:
							calculationTotals
								.couponDiscount,

						discountedProductTotal:
							calculationTotals
								.discountedProductTotal,

						productTotal:
							calculationTotals
								.deliveryBasisTotal,

						deliveryBasisTotal:
							calculationTotals
								.deliveryBasisTotal,

						freeDeliveryActive:
							'yes' ===
								delivery.dataset
									.freeDeliveryActive,

						valid:
							validate(
								delivery,
								false
							),
					},
				}
			)
		);
	}

	/**
	 * Refresh delivery state for one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refreshCheckout(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return;
		}

		const totals =
			getDeliveryTotals(
				checkout
			);

		applyFreeDelivery(
			delivery,
			totals.deliveryBasisTotal
		);

		updateSelectedClasses(
			delivery
		);

		clearError(
			delivery
		);

		dispatchDeliveryChange(
			delivery,
			totals
		);
	}

	/**
	 * Schedule delivery refresh.
	 *
	 * Combo change can cause:
	 *
	 * comboChange
	 * -> discountChange
	 * -> coupon/summary calculations
	 *
	 * Scheduling prevents unnecessary duplicate immediate
	 * refreshes while keeping state scoped to one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function scheduleRefresh(
		checkout
	) {
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

				refreshCheckout(
					checkout
				);
			}
		);
	}

	/**
	 * Handle radio selection.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(
		event
	) {
		const input =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			) ||
			!input.matches(
				SELECTORS.input
			) ||
			!input.checked
		) {
			return;
		}

		const delivery =
			input.closest(
				SELECTORS.delivery
			);

		if (
			!(
				window.eilmoCfDom.isElement(delivery)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				delivery
			);

		clearError(
			delivery
		);

		updateSelectedClasses(
			delivery
		);

		dispatchDeliveryChange(
			delivery,
			checkout
				? getDeliveryTotals(
					checkout
				)
				: null
		);
	}

	/**
	 * Handle checkout calculation dependency change.
	 *
	 * Applies to:
	 *
	 * - Product quantities.
	 * - Combo selection.
	 * - Coupon state.
	 * - Automatic Discount state.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCalculationChange(
		event
	) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		scheduleRefresh(
			checkout
		);
	}

	/**
	 * Validate checkout delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout
	) {
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return true;
		}

		return validate(
			delivery,
			true
		);
	}

	/**
	 * Get selected delivery method.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function getSelected(
		checkout
	) {
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return null;
		}

		return getMethodState(
			getSelectedInput(
				delivery
			)
		);
	}

	/**
	 * Get current delivery calculation totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function getTotals(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		return getDeliveryTotals(
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
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return;
		}

		if (
			'yes' ===
				delivery.dataset
					.eilmoInitialized
		) {
			return;
		}

		delivery.dataset.eilmoInitialized =
			'yes';

		/*
		 * Calculate immediately on page load.
		 *
		 * Important when:
		 *
		 * Free Delivery = ON
		 * Minimum Amount = 0
		 *
		 * It also restores / applies current Coupon,
		 * Automatic Discount and Combo dependent state.
		 */
		refreshCheckout(
			checkout
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
	 * Public Delivery API.
	 */
	window.eilmoCfDelivery = {

		/**
		 * Refresh delivery.
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
					refreshCheckout(
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
								refreshCheckout(
									item
								);
							}
						}
					);
			},

		/**
		 * Validate delivery.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {boolean}
		 */
		validateCheckout:
			validateCheckout,

		/**
		 * Get selected delivery method.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getSelected:
			getSelected,

		/**
		 * Get current delivery basis and related totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getTotals:
			getTotals,

		/**
		 * Get current threshold calculation amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getProductTotal:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				return getProductTotal(
					checkout
				);
			},
	};

	document.addEventListener(
		'change',
		handleChange
	);

	/*
	 * Product / Combo / Discount / Coupon changes all
	 * affect Delivery calculation.
	 */
	[
		'eilmo:quantityChange',
		'eilmo:multipleProductsChange',
		'eilmo:comboChange',
		'eilmo:orderBumpChange',
		'eilmo:couponChange',
		'eilmo:discountChange',
	].forEach(
		function (eventName) {
			document.addEventListener(
				eventName,
				handleCalculationChange
			);
		}
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
