/**
 * Eilmo Checkout Flow - Automatic Discounts.
 *
 * Handles:
 * - Automatic discount range matching.
 * - Percentage discounts.
 * - Fixed discounts.
 * - Maximum discount caps.
 * - Free delivery rules.
 * - Spend More, Save More notice status.
 * - Active and upcoming discount tiers.
 * - Normal Product / Combo / Order Bump isolation.
 * - Summary synchronization through events.
 *
 * Important:
 *
 * Automatic Discount basis:
 *
 * Normal Products
 * +
 * Combo Offers explicitly allowing Automatic Discount
 * +
 * Order Bumps explicitly allowing Automatic Discount
 *
 * Combo Offers and Order Bumps use their payable
 * promotional totals after their own offer discounts,
 * not their regular subtotals.
 *
 * Frontend calculations are UI previews only.
 * Final checkout totals must always be
 * recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		notice:
			'[data-eilmo-discount-notice]',

		status:
			'[data-eilmo-discount-status]',

		tier:
			'[data-eilmo-discount-tier]',

		productItem:
			'[data-eilmo-product-item], [data-eilmo-item]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		comboOffer:
			'[data-eilmo-combo-offer]',

		comboInput:
			'[data-eilmo-combo-input]',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		orderBumpInput:
			'[data-eilmo-order-bump-input]',
	};

	const initialized =
		new WeakSet();

	const scheduled =
		new WeakSet();

	const results =
		new WeakMap();

	/**
	 * Convert value to a non-negative number.
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

		if (
			!Number.isFinite(
				number
			)
		) {
			return 0;
		}

		return Math.max(
			0,
			number
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
	 * Round monetary value.
	 *
	 * @param {number} value Value.
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
	 * Parse JSON safely.
	 *
	 * @param {string} value    JSON.
	 * @param {*}      fallback Fallback.
	 *
	 * @return {*}
	 */
	function parseJSON(
		value,
		fallback
	) {
		if (!value) {
			return fallback;
		}

		try {

			const parsed =
				JSON.parse(
					value
				);

			return null ===
				parsed
					? fallback
					: parsed;

		} catch (error) {

			return fallback;
		}
	}

	/**
	 * Decode HTML entities.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHTML(value) {
		const textarea =
			document.createElement(
				'textarea'
			);

		textarea.innerHTML =
			String(
				value ||
					''
			);

		return textarea.value;
	}

	/**
	 * Strip HTML.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function stripHTML(value) {
		const element =
			document.createElement(
				'div'
			);

		element.innerHTML =
			String(
				value ||
					''
			);

		return decodeHTML(
			element.textContent ||
				element.innerText ||
				''
		);
	}

	/**
	 * Get currency configuration.
	 *
	 * @return {Object}
	 */
	function getCurrencyConfig() {
		const config =
			window.eilmoCf ||
			{};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		return {
			symbol:
				decodeHTML(
					'string' ===
						typeof currency.symbol
						? currency.symbol
						: (
							config.currencySymbol ||
								'৳'
						)
				),

			position:
				'string' ===
					typeof currency.position
					? currency.position
					: (
						config.currencyPosition ||
							'left'
					),

			decimals:
				getPriceDecimals(),

			decimalSeparator:
				'string' ===
					typeof currency.decimalSeparator
					? decodeHTML(
						currency.decimalSeparator
					)
					: (
						config.decimalSeparator ||
							'.'
					),

			thousandSeparator:
				'string' ===
					typeof currency.thousandSeparator
					? decodeHTML(
						currency.thousandSeparator
					)
					: (
						config.thousandSeparator ||
							','
					),
		};
	}

	/**
	 * Format price.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		const safeAmount =
			roundMoney(
				amount
			);

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.formatPrice
		) {
			return stripHTML(
				window
					.eilmoCfSummary
					.formatPrice(
						safeAmount
					)
			);
		}

		const config =
			getCurrencyConfig();

		const parts =
			safeAmount
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

		const formattedNumber =
			config.decimals > 0
				? (
					parts[0] +
					config.decimalSeparator +
					parts[1]
				)
				: parts[0];

		switch (
			config.position
		) {
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

	/**
	 * Format percentage.
	 *
	 * @param {number} value Percentage.
	 *
	 * @return {string}
	 */
	function formatPercentage(value) {
		const percentage =
			Math.min(
				100,
				toNumber(
					value
				)
			);

		return (
			Number.parseFloat(
				percentage.toFixed(
					2
				)
			).toString() +
				'%'
		);
	}

	/**
	 * Replace placeholders.
	 *
	 * @param {string} text   Text.
	 * @param {Object} values Values.
	 *
	 * @return {string}
	 */
	function replaceTokens(
		text,
		values
	) {
		let output =
			String(
				text ||
					''
			);

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
								values[
									key
								]
							)
						);
			}
		);

		return output;
	}

	/**
	 * Calculate selected normal-product total.
	 *
	 * IMPORTANT:
	 *
	 * This function represents NORMAL checkout
	 * products only.
	 *
	 * Combo Offer products are never added here.
	 * Order Bumps are handled separately.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateProductTotal(
		checkout
	) {
		const itemTotals =
			new Map();

		checkout
			.querySelectorAll(
				SELECTORS.productItem
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
					 * Extra isolation safety.
					 *
					 * If another component happens to
					 * reuse data-eilmo-item, never allow
					 * Combo or Order Bump markup to enter
					 * the normal-product calculation.
					 */
					if (
						item.closest(
							'[data-eilmo-combo-offer]'
						) ||
						item.closest(
							'[data-eilmo-order-bump]'
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS
								.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toNumber(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					let price =
						toNumber(
							item.dataset
								.price
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
								input.dataset
									.price
							);
					}

					if (
						price <= 0
					) {
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
					 * Multiple Products may expose compatibility
					 * adapters for one exact order item. Keep one
					 * preview amount per Simple product / variation;
					 * never sum duplicate DOM representations.
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
	 * Build product/quantity condition context for the unified rule engine.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @return {Object}
	 */
	function getConditionContext(checkout) {
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		const productTotals = {};
		const discountProductTotals = {};
		const productIds = [];
		const seen = new Set();

		function addLine(productId, variationId, quantity, lineTotal, discountEligible) {
			if (quantity <= 0 || productId <= 0) return;
			cartQuantity += quantity;
			productIds.push(productId);
			productQuantities[productId] = toNumber(productQuantities[productId]) + quantity;
			productTotals[productId] = roundMoney(toNumber(productTotals[productId]) + lineTotal);
			if (discountEligible) {
				discountProductTotals[productId] = roundMoney(toNumber(discountProductTotals[productId]) + lineTotal);
			}
			if (variationId > 0) {
				productIds.push(variationId);
				productQuantities[variationId] = toNumber(productQuantities[variationId]) + quantity;
				productTotals[variationId] = roundMoney(toNumber(productTotals[variationId]) + lineTotal);
				if (discountEligible) {
					discountProductTotals[variationId] = roundMoney(toNumber(discountProductTotals[variationId]) + lineTotal);
				}
			}
		}

		checkout.querySelectorAll(SELECTORS.productItem).forEach(function (item) {
			if (!(window.eilmoCfDom.isElement(item)) || item.closest('[data-eilmo-combo-offer], [data-eilmo-order-bump]')) return;
			const input = item.querySelector(SELECTORS.quantityInput);
			if (!(window.eilmoCfDom.isElement(input, 'INPUT'))) return;
			const quantity = toNumber(input.value);
			const productId = toInteger(item.dataset.productId || input.dataset.productId);
			const variationId = toInteger(item.dataset.variationId || input.dataset.variationId);
			const key = variationId > 0 ? `v:${variationId}` : `p:${productId}`;
			if (quantity <= 0 || seen.has(key)) return;
			seen.add(key);
			const price = toNumber(item.dataset.price || item.dataset.productPrice || input.dataset.price || 0);
			const lineTotal = roundMoney(price * quantity);
			productTotal += lineTotal;
			addLine(productId, variationId, quantity, lineTotal, true);
		});

		checkout.querySelectorAll(SELECTORS.comboOffer).forEach(function (combo) {
			if (!(window.eilmoCfDom.isElement(combo))) return;
			const input = combo.querySelector(SELECTORS.comboInput);
			if (!(window.eilmoCfDom.isElement(input, 'INPUT')) || !input.checked || input.disabled) return;
			const components = Array.from(combo.querySelectorAll('.eilmo-cf-combo-offer__item[data-product-id]'));
			const regularTotal = components.reduce(function (sum, item) {
				return sum + toNumber(item.dataset.lineTotal || 0);
			}, 0);
			const comboTotal = roundMoney(combo.dataset.comboTotal || Math.max(0, toNumber(combo.dataset.regularTotal) - toNumber(combo.dataset.comboDiscount)));
			const scale = regularTotal > 0 ? Math.min(1, comboTotal / regularTotal) : 0;
			const discountEligible = 'yes' === String(combo.dataset.applyAutomaticDiscount || 'no');
			productTotal += comboTotal;
			components.forEach(function (item) {
				if (!(window.eilmoCfDom.isElement(item))) return;
				const quantity = toNumber(item.dataset.quantity || 1);
				const productId = toInteger(item.dataset.productId);
				const variationId = toInteger(item.dataset.variationId);
				const componentTotal = roundMoney(toNumber(item.dataset.lineTotal || 0) * scale);
				addLine(productId, variationId, quantity, componentTotal, discountEligible);
			});
		});

		let currentProductIds = parseJSON(checkout.getAttribute('data-eilmo-current-product-ids'), []);
		if (!Array.isArray(currentProductIds)) currentProductIds = [];
		currentProductIds = currentProductIds.map(toInteger).filter(function (id, index, all) {
			return id > 0 && all.indexOf(id) === index;
		});

		return {
			productTotal: roundMoney(productTotal),
			cartQuantity: cartQuantity,
			productQuantities: productQuantities,
			productTotals: productTotals,
			discountProductTotals: discountProductTotals,
			productIds: productIds.filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; }),
			currentProductIds: currentProductIds,
		};
	}

	function getRuleScopeIds(rule, context) {
		if ('current_product' === rule.ruleScope) {
			return Array.isArray(context.currentProductIds) ? context.currentProductIds : [];
		}
		if ('selected_products' === rule.ruleScope) {
			const ids = Array.isArray(rule.scopeProductIds) ? rule.scopeProductIds.slice() : [];
			if (0 === ids.length && rule.conditionProductId > 0) ids.push(rule.conditionProductId);
			return ids;
		}
		return [];
	}

	function getScopedConditionContext(rule, context) {
		if (!rule || 'whole_cart' === rule.ruleScope) {
			return {
				productTotal: roundMoney(context.productTotal || 0),
				cartQuantity: toNumber(context.cartQuantity),
				productQuantities: context.productQuantities || {},
				scopeProductIds: context.productIds || [],
			};
		}
		const ids = getRuleScopeIds(rule, context);
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		ids.forEach(function (id) {
			const quantity = toNumber((context.productQuantities || {})[id]);
			const total = toNumber((context.productTotals || {})[id]);
			if (quantity > 0) {
				productQuantities[id] = quantity;
				cartQuantity += quantity;
			}
			productTotal += total;
		});
		return {
			productTotal: roundMoney(productTotal),
			cartQuantity: cartQuantity,
			productQuantities: productQuantities,
			scopeProductIds: ids,
		};
	}

	function getRuleDiscountBase(rule, context, automaticDiscountBase) {
		if (!rule || 'whole_cart' === rule.ruleScope) return roundMoney(automaticDiscountBase);
		const ids = getRuleScopeIds(rule, context);
		let total = 0;
		ids.forEach(function (id) {
			total += toNumber((context.discountProductTotals || {})[id]);
		});
		return roundMoney(Math.min(toNumber(automaticDiscountBase), total));
	}

	/**
	 * Calculate Combo Offer amount eligible for
	 * Automatic Discount.
	 *
	 * Default compatibility is NO.
	 *
	 * Only:
	 *
	 * data-apply-automatic-discount="yes"
	 *
	 * makes a selected Combo Offer eligible.
	 *
	 * The payable combo total is used after the
	 * Combo Offer's own discount has already been
	 * applied.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateComboAutomaticTotal(
		checkout
	) {
		let total =
			0;

		checkout
			.querySelectorAll(
				SELECTORS
					.comboOffer
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
							SELECTORS
								.comboInput
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

					/*
					 * Explicit opt-in only.
					 *
					 * Missing attribute defaults to NO.
					 */
					if (
						'yes' !==
							offer.dataset
								.applyAutomaticDiscount
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
						Math.min(
							roundMoney(
								offer.dataset
									.comboDiscount ||
									0
							),
							regularTotal
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
							)
						);

					const comboTotal =
						roundMoney(
							undefined !==
								offer.dataset
									.comboTotal &&
							'' !==
								String(
									offer.dataset
										.comboTotal
								)
									.trim()
								? offer.dataset
									.comboTotal
								: fallbackTotal
						);

					total +=
						Math.min(
							regularTotal,
							comboTotal
						);
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Calculate Order Bump amount eligible for
	 * Automatic Discount.
	 *
	 * Default compatibility is NO.
	 *
	 * Only:
	 *
	 * data-apply-automatic-discount="yes"
	 *
	 * makes a selected Order Bump eligible.
	 *
	 * The payable bump total is used after the
	 * Order Bump's own discount has already been
	 * applied.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateOrderBumpAutomaticTotal(
		checkout
	) {
		let total =
			0;

		checkout
			.querySelectorAll(
				SELECTORS
					.orderBumpOffer
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
							SELECTORS
								.orderBumpInput
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

					/*
					 * Explicit opt-in only.
					 *
					 * Missing attribute defaults to NO.
					 */
					if (
						'yes' !==
							offer.dataset
								.applyAutomaticDiscount
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
						Math.min(
							roundMoney(
								offer.dataset
									.discount ||
									0
							),
							regularTotal
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
							)
						);

					const bumpTotal =
						roundMoney(
							undefined !==
								offer.dataset
									.bumpTotal &&
							'' !==
								String(
									offer.dataset
										.bumpTotal
								)
									.trim()
								? offer.dataset
									.bumpTotal
								: fallbackTotal
						);

					total +=
						Math.min(
							regularTotal,
							bumpTotal
						);
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Get authoritative frontend Automatic Discount base.
	 *
	 * Calculation:
	 *
	 * Normal Products
	 * +
	 * Eligible Combo Offer payable totals
	 * +
	 * Eligible Order Bump payable totals
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getAutomaticDiscountBase(
		checkout
	) {
		const normalProductTotal =
			calculateProductTotal(
				checkout
			);

		const comboTotal =
			calculateComboAutomaticTotal(
				checkout
			);

		const orderBumpTotal =
			calculateOrderBumpAutomaticTotal(
				checkout
			);

		const automaticDiscountBase =
			roundMoney(
				normalProductTotal +
					comboTotal +
					orderBumpTotal
			);

		return {
			normalProductTotal:
				normalProductTotal,

			comboTotal:
				comboTotal,

			orderBumpTotal:
				orderBumpTotal,

			automaticDiscountBase:
				automaticDiscountBase,
		};
	}

	/**
	 * Get frontend rules.
	 *
	 * @param {HTMLElement} notice Notice.
	 *
	 * @return {Array}
	 */
	function getRules(notice) {
		const parsed =
			parseJSON(
				notice.getAttribute(
					'data-rules'
				),
				[]
			);

		if (
			!Array.isArray(
				parsed
			)
		) {
			return [];
		}

		return parsed
			.filter(
				function (rule) {
					return (
						rule &&
						'object' ===
							typeof rule
					);
				}
			)
			.map(
				function (rule) {
					return {
						id:
							String(
								rule.id ||
									''
							),

						name:
							String(
								rule.name ||
									''
							),

						minimum:
							roundMoney(
								rule.minimum
							),

						maximum:
							roundMoney(
								rule.maximum
							),

						type:
							String(
								rule.type ||
									'percentage'
							),

						value:
							toNumber(
								rule.value
							),

						maximumDiscount:
							roundMoney(
								rule
									.maximum_discount
							),

						priority:
							Number.parseInt(
								String(
									rule.priority ||
										10
								),
								10
							) ||
								10,

						stopProcessing:
							true ===
								rule
									.stop_processing ||
							'yes' ===
								rule
									.stop_processing,

						conditionType:
							String(rule.condition_type || 'order_amount'),

						conditionProductId:
							toInteger(rule.condition_product_id),

						conditionProductQuantity:
							Math.max(1, toNumber(rule.condition_product_quantity || 1)),

						conditionCartQuantity:
							Math.max(1, toNumber(rule.condition_cart_quantity || 1)),

						ruleScope:
							['current_product', 'selected_products', 'whole_cart'].includes(String(rule.rule_scope || ''))
								? String(rule.rule_scope)
								: 'whole_cart',

						scopeProductIds:
							Array.isArray(rule.scope_product_ids)
								? rule.scope_product_ids.map(toInteger).filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; })
								: [],

						freeDeliveryMethodId:
							String(rule.free_delivery_method_id || ''),

						freeDeliveryHideOtherMethods:
							true === rule.free_delivery_hide_other_methods || 'yes' === rule.free_delivery_hide_other_methods,

						compatibilityRule:
							Boolean(rule.compatibility_rule),

						ineligibleAction:
							String(rule.ineligible_action || 'show_locked'),

						lockedText:
							String(rule.locked_text || ''),

						appliedText:
							String(rule.applied_text || 'Applied'),

						endTimestamp:
							toInteger(rule.end_timestamp),
					};
				}
			);
	}

	/**
	 * Get notification texts.
	 *
	 * @param {HTMLElement} notice Notice.
	 *
	 * @return {Object}
	 */
	function getTexts(notice) {
		const texts =
			parseJSON(
				notice.getAttribute(
					'data-texts'
				),
				{}
			);

		return (
			texts &&
			'object' ===
				typeof texts
		)
			? texts
			: {};
	}

	/**
	 * Check whether rule matches.
	 *
	 * Range:
	 *
	 * minimum <= amount < maximum
	 *
	 * maximum = 0 means no maximum.
	 *
	 * @param {Object} rule   Rule.
	 * @param {number} amount Amount.
	 *
	 * @return {boolean}
	 */
	function ruleMatches(
		rule,
		amount,
		context = {}
	) {
		if (rule.endTimestamp > 0 && Math.floor(Date.now() / 1000) >= rule.endTimestamp) return false;
		const scoped = getScopedConditionContext(rule, context);
		const type = String(rule.conditionType || 'order_amount');
		if ('always' === type) {
			return 'whole_cart' === rule.ruleScope || ((scoped.scopeProductIds || []).length > 0 && toNumber(scoped.cartQuantity) > 0);
		}
		if (['specific_product', 'product_exists'].includes(type)) {
			const quantities = scoped.productQuantities || {};
			return rule.conditionProductId > 0 && toNumber(quantities[rule.conditionProductId]) >= rule.conditionProductQuantity;
		}
		if (['cart_quantity', 'minimum_quantity', 'buy_x_quantity'].includes(type)) {
			return toNumber(scoped.cartQuantity) >= rule.conditionCartQuantity;
		}
		if ('current_product' === type) {
			return (scoped.scopeProductIds || []).length > 0 && toNumber(scoped.cartQuantity) > 0;
		}
		const scopedAmount = toNumber(scoped.productTotal);
		if (scopedAmount < rule.minimum) return false;
		if (rule.maximum > 0 && scopedAmount > rule.maximum) return false;
		return true;
	}

	/**
	 * Find matching automatic discount rule.
	 *
	 * Normal ranges use:
	 *
	 * minimum <= amount < maximum
	 *
	 * Special rule:
	 * The highest enabled tier becomes the final tier.
	 * Once the customer reaches/passes that tier's
	 * configured maximum, the same final tier continues.
	 *
	 * Example:
	 *
	 * 500 - 2000  = 7%
	 * 3000 - 5000 = 9%
	 *
	 * 5400+ still receives 9%.
	 *
	 * @param {Array}  rules  Rules.
	 * @param {number} amount Automatic Discount base.
	 *
	 * @return {Object|null}
	 */
	function findMatchingRule(
		rules,
		amount,
		context = {}
	) {
		if (
			!Array.isArray(
				rules
			) ||
			0 ===
				rules.length
		) {
			return null;
		}

		/*
		 * First try normal half-open
		 * range matching.
		 */
		const matches =
			rules.filter(
				function (rule) {
					return ruleMatches(
						rule,
						amount,
						context
					);
				}
			);

		if (
			matches.length > 0
		) {
			matches.sort(
				function (
					first,
					second
				) {
					if (
						first.priority !==
							second.priority
					) {
						return (
							first.priority -
								second.priority
						);
					}

					return (
						second.minimum -
							first.minimum
					);
				}
			);

			return matches[0];
		}

		/*
		 * No normal range matched.
		 *
		 * Find the highest configured tier.
		 */
		const orderedRules =
			rules
				.filter(function (rule) { return !rule.conditionType || ['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType); })
				.slice()
				.sort(
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
							first.priority -
								second.priority
						);
					}
				);

		const finalRule =
			orderedRules[0];

		if (!finalRule) {
			return null;
		}

		/*
		 * Final tier with no maximum
		 * is already open-ended.
		 */
		if (
			finalRule.maximum <= 0 &&
			amount >=
				finalRule.minimum
		) {
			return finalRule;
		}

		/*
		 * If customer exceeds the maximum
		 * of the final configured tier,
		 * continue applying that tier.
		 */
		if (
			finalRule.maximum > 0 &&
			amount >=
				finalRule.maximum
		) {
			return finalRule;
		}

		return null;
	}

	/**
	 * Find every matching Special Discount rule.
	 *
	 * The old tier engine returned only one winner. Unified Special Discounts
	 * are independent rewards, so a monetary reward and Free Delivery (or two
	 * separate monetary rewards) may be active at the same time. The historical
	 * final-tier continuation is retained only when no normal rule matches.
	 *
	 * @param {Array}  rules   Rules.
	 * @param {number} amount  Automatic Discount base.
	 * @param {Object} context Product and quantity context.
	 *
	 * @return {Array}
	 */
	function findMatchingRules(
		rules,
		amount,
		context = {}
	) {
		if (!Array.isArray(rules) || 0 === rules.length) {
			return [];
		}

		const matches = rules
			.filter(function (rule) {
				return ruleMatches(rule, amount, context);
			})
			.sort(function (first, second) {
				if (first.priority !== second.priority) {
					return first.priority - second.priority;
				}

				return second.minimum - first.minimum;
			});

		if (matches.length > 0) {
			return matches;
		}

		const continuedRule = findMatchingRule(rules, amount, context);

		return continuedRule ? [continuedRule] : [];
	}

	/**
	 * Find nearest upcoming rule.
	 *
	 * @param {Array}  rules  Rules.
	 * @param {number} amount Automatic Discount base.
	 *
	 * @return {Object|null}
	 */
	function findUpcomingRule(
		rules,
		amount
	) {
		const upcoming =
			rules
				.filter(
					function (rule) {
						return (
							(!rule.conditionType || ['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType)) &&
							rule.minimum >
								amount
						);
					}
				)
				.sort(
					function (
						first,
						second
					) {
						if (
							first.minimum !==
								second.minimum
						) {
							return (
								first.minimum -
									second.minimum
							);
						}

						return (
							first.priority -
								second.priority
						);
					}
				);

		return upcoming.length > 0
			? upcoming[0]
			: null;
	}

	/**
	 * Calculate rule discount.
	 *
	 * @param {Object} rule         Rule.
	 * @param {number} productTotal Automatic Discount base.
	 *
	 * @return {Object}
	 */
	function calculateRuleDiscount(
		rule,
		productTotal
	) {
		const result = {
			type:
				rule.type,

			rawDiscount:
				0,

			discount:
				0,

			freeDelivery:
				false,

			capped:
				false,

			clamped:
				false,
		};

		if (
			'free_delivery' ===
				rule.type
		) {
			result.freeDelivery =
				true;

			return result;
		}

		let rawDiscount =
			0;

		if (
			'fixed' ===
				rule.type
		) {
			rawDiscount =
				rule.value;
		} else {
			rawDiscount =
				productTotal *
					(
						Math.min(
							100,
							rule.value
						) /
							100
					);
		}

		rawDiscount =
			roundMoney(
				rawDiscount
			);

		let discount =
			rawDiscount;

		if (
			rule.maximumDiscount > 0 &&
			discount >
				rule.maximumDiscount
		) {
			discount =
				rule.maximumDiscount;

			result.capped =
				true;
		}

		if (
			discount >
				productTotal
		) {
			discount =
				productTotal;

			result.clamped =
				true;
		}

		result.rawDiscount =
			rawDiscount;

		result.discount =
			roundMoney(
				discount
			);

		return result;
	}

	/**
	 * Get discount label.
	 *
	 * Tier list always represents configured offer.
	 * Active status can show actual capped/clamped saving.
	 *
	 * @param {Object}  rule        Rule.
	 * @param {Object}  calculation Calculation.
	 * @param {boolean} preview     Preview.
	 *
	 * @return {string}
	 */
	function getDiscountLabel(
		rule,
		calculation,
		preview
	) {
		if (
			'free_delivery' ===
				rule.type
		) {
			return 'Free Delivery';
		}

		if (
			'fixed' ===
				rule.type
		) {
			if (
				!preview &&
				calculation &&
				(
					calculation.capped ||
					calculation.clamped
				)
			) {
				return formatPrice(
					calculation.discount
				);
			}

			return formatPrice(
				rule.value
			);
		}

		if (
			!preview &&
			calculation &&
			(
				calculation.capped ||
					calculation.clamped
			)
		) {
			return formatPrice(
				calculation.discount
			);
		}

		return formatPercentage(
			rule.value
		);
	}

	/**
	 * Get active status.
	 *
	 * @param {Object} rule         Rule.
	 * @param {Object} calculation Calculation.
	 * @param {number} productTotal Automatic Discount base.
	 * @param {Object} texts        Texts.
	 *
	 * @return {string}
	 */
	function getActiveStatus(
		rule,
		calculation,
		productTotal,
		texts
	) {
		if (
			'free_delivery' ===
				rule.type
		) {
			return 'Free Delivery unlocked.';
		}

		const discountLabel =
			getDiscountLabel(
				rule,
				calculation,
				false
			);

		return replaceTokens(
			texts.active_status ||
				'You are getting {discount} OFF.',
			{
				discount:
					discountLabel,

				current_total:
					formatPrice(
						productTotal
					),

				minimum_amount:
					formatPrice(
						rule.minimum
					),

				maximum_amount:
					rule.maximum > 0
						? formatPrice(
							rule.maximum
						)
						: '',
			}
		);
	}

	/**
	 * Get upcoming status.
	 *
	 * @param {Object} rule         Rule.
	 * @param {number} productTotal Automatic Discount base.
	 * @param {Object} texts        Texts.
	 *
	 * @return {string}
	 */
	function getUpcomingStatus(
		rule,
		productTotal,
		texts
	) {
		const remainingAmount =
			roundMoney(
				Math.max(
					0,
					rule.minimum -
						productTotal
				)
			);

		if (
			'free_delivery' ===
				rule.type
		) {
			return (
				'Add ' +
					formatPrice(
						remainingAmount
					) +
					' more to unlock Free Delivery.'
			);
		}

		const calculation =
			calculateRuleDiscount(
				rule,
				Math.max(
					rule.minimum,
					productTotal
				)
			);

		const discountLabel =
			getDiscountLabel(
				rule,
				calculation,
				true
			);

		return replaceTokens(
			texts.upcoming_status ||
				'Add {remaining_amount} more to unlock {discount} OFF.',
			{
				discount:
					discountLabel,

				remaining_amount:
					formatPrice(
						remainingAmount
					),

				current_total:
					formatPrice(
						productTotal
					),

				minimum_amount:
					formatPrice(
						rule.minimum
					),

				maximum_amount:
					rule.maximum > 0
						? formatPrice(
							rule.maximum
						)
						: '',
			}
		);
	}

	/**
	 * Update status text.
	 *
	 * @param {HTMLElement} notice Notice.
	 * @param {string}      text   Text.
	 *
	 * @return {void}
	 */
	function updateStatus(
		notice,
		text
	) {
		const status =
			notice.querySelector(
				SELECTORS.status
			);

		if (
			!(
				window.eilmoCfDom.isElement(status)
			)
		) {
			return;
		}

		status.textContent =
			String(
				text ||
					''
			);

		status.hidden =
			'' ===
				status.textContent
					.trim();
	}

	/**
	 * Update tier state.
	 *
	 * Tier list always remains visible.
	 *
	 * @param {HTMLElement} notice       Notice.
	 * @param {Array}       rules        Rules.
	 * @param {Array}       activeRules  Active rules.
	 * @param {Object|null} upcomingRule Upcoming rule.
	 * @param {number}      productTotal Automatic Discount base.
	 * @param {Object}      context      Product and quantity context.
	 *
	 * @return {void}
	 */
	function updateTierStates(
		notice,
		rules,
		activeRules,
		upcomingRule,
		productTotal,
		context
	) {
		const rulesById =
			new Map();
		const activeRuleIds = new Set(
			(Array.isArray(activeRules) ? activeRules : [])
				.map(function (rule) {
					return String((rule && rule.id) || '');
				})
				.filter(Boolean)
		);

		rules.forEach(
			function (rule) {
				rulesById.set(
					rule.id,
					rule
				);
			}
		);

		notice
			.querySelectorAll(
				SELECTORS.tier
			)
			.forEach(
				function (tier) {
					if (
						!(
							window.eilmoCfDom.isElement(tier)
						)
					) {
						return;
					}

					const ruleId =
						String(
							tier.dataset
								.ruleId ||
								''
						);

					const rule =
						rulesById.get(
							ruleId
						);

					const active = activeRuleIds.has(ruleId);

					const upcoming =
						Boolean(
							upcomingRule &&
							upcomingRule.id ===
								ruleId
						);

					const expired = Boolean(rule && rule.endTimestamp > 0 && Math.floor(Date.now() / 1000) >= rule.endTimestamp);
					const eligible = Boolean(rule && !expired && ruleMatches(rule, productTotal, context));
					const compactCard = tier.classList.contains('eilmo-cf-special-discount-card--compact');

					const completed = eligible;

					let progressText = '';
					if (rule) {
						const genericAmountText = 'Add {remaining_amount} more to unlock this reward.';
						if (active) {
							progressText = '';
						} else if ('specific_product' === rule.conditionType) {
							const current = toNumber((context.productQuantities || {})[rule.conditionProductId]);
							const remaining = Math.max(0, rule.conditionProductQuantity - current);
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${remaining} more` : (!rule.lockedText || genericAmountText === rule.lockedText ? `Add ${remaining} more required product` : rule.lockedText));
						} else if ('cart_quantity' === rule.conditionType) {
							const remaining = Math.max(0, rule.conditionCartQuantity - toNumber(context.cartQuantity));
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${remaining} more` : (!rule.lockedText || genericAmountText === rule.lockedText ? `Add ${remaining} more item${1 === remaining ? '' : 's'}` : rule.lockedText));
						} else if (['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType) || !rule.conditionType) {
							const remaining = Math.max(0, rule.minimum - productTotal);
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${formatPrice(remaining)} more` : (rule.lockedText || `Spend ${formatPrice(remaining)} more to unlock`));
						} else {
							progressText = eligible ? 'Eligible' : (rule.lockedText || 'Offer locked');
						}

						progressText = String(progressText || '')
							.split('{remaining_amount}').join(formatPrice(Math.max(0, rule.minimum - productTotal)))
							.split('{remaining_quantity}').join(String(
								'specific_product' === rule.conditionType
									? Math.max(0, rule.conditionProductQuantity - toNumber((context.productQuantities || {})[rule.conditionProductId]))
									: Math.max(0, rule.conditionCartQuantity - toNumber(context.cartQuantity))
							))
							.split('{required_product}').join(rule.conditionProductId > 0 ? `#${rule.conditionProductId}` : '');
					}

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--active',
						active
					);

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--next',
						upcoming
					);

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--completed',
						completed
					);

					tier.classList.toggle('is-selected', active);
					tier.classList.toggle('is-eligible', eligible);
					tier.classList.toggle('is-ineligible', !eligible);
					tier.dataset.eligible = eligible ? 'yes' : 'no';
					tier.hidden = Boolean(expired || (rule && !eligible && 'hide' === rule.ineligibleAction));

					const progress = tier.querySelector('[data-eilmo-special-discount-auto-status]');
					if (window.eilmoCfDom.isElement(progress)) {
						progress.textContent = progressText;
						progress.hidden = active || !progressText;
					}

					const action = tier.querySelector('[data-eilmo-special-discount-auto-action]');
					if (window.eilmoCfDom.isElement(action)) {
						action.hidden = !active;
					}

					tier.dataset.active =
						active
							? 'yes'
							: 'no';

					tier.dataset.next =
						upcoming
							? 'yes'
							: 'no';

					tier.dataset.completed =
						completed
							? 'yes'
							: 'no';
				}
			);
	}

	/**
	 * Store automatic discount state.
	 *
	 * automaticDiscount is the canonical
	 * discount dataset key.
	 *
	 * automaticDiscountBase is the canonical
	 * eligible basis dataset key.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} notice   Notice.
	 * @param {Object}      result   Result.
	 *
	 * @return {void}
	 */
	function storeResult(
		checkout,
		notice,
		result
	) {
		checkout.dataset
			.automaticDiscountReady =
				'yes';

		checkout.dataset
			.automaticDiscount =
				String(
					result.discount
				);

		checkout.dataset
			.automaticDiscountBase =
				String(
					result
						.automaticDiscountBase
				);

		checkout.dataset
			.automaticDiscountNormalProductTotal =
				String(
					result
						.normalProductTotal
				);

		checkout.dataset
			.automaticDiscountComboTotal =
				String(
					result
						.comboAutomaticTotal
				);

		checkout.dataset
			.automaticDiscountOrderBumpTotal =
				String(
					result
						.orderBumpAutomaticTotal
				);

		checkout.dataset
			.automaticDiscountRuleId =
				result.ruleId;

		checkout.dataset
			.automaticDiscountRuleIds =
				Array.isArray(result.ruleIds)
					? result.ruleIds.join(',')
					: result.ruleId;

		checkout.dataset
			.automaticDiscountRuleType =
				result.ruleType;

		checkout.dataset
			.automaticDiscountLabel =
				result.discountLabel;

		checkout.dataset
			.automaticDiscountFreeDelivery =
				result.freeDelivery
					? 'yes'
					: 'no';

		checkout.dataset.automaticDiscountFreeDeliveryMethod =
			String(result.freeDeliveryMethodId || '');

		checkout.dataset.automaticDiscountFreeDeliveryHideOtherMethods =
			result.freeDeliveryHideOtherMethods ? 'yes' : 'no';

		/*
		 * Keep productTotal for compatibility with
		 * existing markup.
		 *
		 * It now means Automatic Discount eligible
		 * product total, NOT global Summary Product Total.
		 */
		notice.dataset
			.productTotal =
				String(
					result
						.automaticDiscountBase
				);

		notice.dataset
			.automaticDiscountBase =
				String(
					result
						.automaticDiscountBase
				);

		notice.dataset
			.normalProductTotal =
				String(
					result
						.normalProductTotal
				);

		notice.dataset
			.comboAutomaticTotal =
				String(
					result
						.comboAutomaticTotal
				);

		notice.dataset
			.orderBumpAutomaticTotal =
				String(
					result
						.orderBumpAutomaticTotal
				);

		notice.dataset
			.discount =
				String(
					result.discount
				);

		notice.dataset
			.activeRuleId =
				result.ruleId;

		notice.dataset
			.activeRuleIds =
				Array.isArray(result.ruleIds)
					? result.ruleIds.join(',')
					: result.ruleId;

		notice.dataset
			.activeRuleType =
				result.ruleType;

		results.set(
			checkout,
			result
		);
	}

	/**
	 * Dispatch Automatic Discount change.
	 *
	 * Summary and other modules can consume
	 * this event.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      result   Result.
	 *
	 * @return {void}
	 */
	function dispatchChange(
		checkout,
		result
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:discountChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						source:
							'automatic',

						/*
						 * Backward compatibility.
						 *
						 * productTotal now means the
						 * Automatic Discount eligible
						 * base, not Summary Product Total.
						 */
						productTotal:
							result
								.automaticDiscountBase,

						normalProductTotal:
							result
								.normalProductTotal,

						comboAutomaticTotal:
							result
								.comboAutomaticTotal,

						orderBumpAutomaticTotal:
							result
								.orderBumpAutomaticTotal,

						automaticDiscountBase:
							result
								.automaticDiscountBase,

						ruleId:
							result.ruleId,

						ruleIds:
							result.ruleIds,

						ruleType:
							result.ruleType,

						discount:
							result.discount,

						automaticDiscount:
							result.discount,

						discountLabel:
							result.discountLabel,

						freeDelivery:
							result.freeDelivery,

						hasActiveRule:
							result.hasActiveRule,

						hasUpcomingRule:
							result.hasUpcomingRule,
					},
				}
			)
		);
	}

	/**
	 * Refresh Automatic Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function refresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		const notice =
			checkout.querySelector(
				SELECTORS.notice
			);

		/*
		 * No notice means Automatic Discounts are
		 * not active for this checkout.
		 *
		 * This happens when:
		 *
		 * - Discounts are disabled.
		 * - Notice is disabled.
		 * - No automatic rules exist.
		 * - Full Payment Discount has precedence.
		 */
		if (
			!(
				window.eilmoCfDom.isElement(notice)
			)
		) {
			return null;
		}

		/*
		 * --------------------------------------
		 * Automatic Discount basis
		 * --------------------------------------
		 *
		 * NORMAL PRODUCT
		 * +
		 * explicitly eligible Combo Offer
		 * +
		 * explicitly eligible Order Bump
		 */
		const base =
			getAutomaticDiscountBase(
				checkout
			);

		const normalProductTotal =
			base.normalProductTotal;

		const comboAutomaticTotal =
			base.comboTotal;

		const orderBumpAutomaticTotal =
			base.orderBumpTotal;

		const productTotal =
			base.automaticDiscountBase;

		const conditionContext =
			getConditionContext(checkout);

		const rules =
			getRules(
				notice
			);

		const texts =
			getTexts(
				notice
			);

		let activeRule = null;
		let activeRules = [];

		let upcomingRule =
			null;

		let discount =
			0;

		let discountLabel =
			'';

		let ruleId =
			'';

		let ruleType =
			'';

		let freeDelivery =
			false;

		if (productTotal > 0) {
			activeRules = findMatchingRules(
				rules,
				productTotal,
				conditionContext
			);
			activeRule = activeRules[0] || null;
		}

		let freeDeliveryMethodId = '';
		let freeDeliveryHideOtherMethods = false;

		if (activeRule) {
			const calculations = activeRules.map(function (rule) {
				return {
					rule: rule,
					discountBase: getRuleDiscountBase(rule, conditionContext, productTotal),
					calculation: calculateRuleDiscount(rule, getRuleDiscountBase(rule, conditionContext, productTotal)),
				};
			});
			const calculation = calculations[0].calculation;

			discount = roundMoney(
				calculations.reduce(function (total, entry) {
					return total + entry.calculation.discount;
				}, 0)
			);

			freeDelivery = calculations.some(function (entry) {
				return entry.calculation.freeDelivery;
			});

			const freeDeliveryRule = activeRules.find(function (rule) {
				return 'free_delivery' === rule.type;
			});
			if (freeDeliveryRule) {
				freeDeliveryMethodId = String(freeDeliveryRule.freeDeliveryMethodId || '');
				freeDeliveryHideOtherMethods = Boolean(freeDeliveryRule.freeDeliveryHideOtherMethods);
			}

			discountLabel =
				getDiscountLabel(
					activeRule,
					calculation,
					false
				);

			ruleId =
				activeRule.id;

			ruleType =
				activeRule.type;

			updateStatus(
				notice,
				getActiveStatus(
					activeRule,
					calculation,
					productTotal,
					texts
				)
			);

		} else {

			upcomingRule =
				findUpcomingRule(
					rules,
					productTotal
				);

			if (
				productTotal <= 0
			) {
				updateStatus(
					notice,
					texts.empty_status ||
						'Select products to unlock your discount.'
				);

			} else if (
				upcomingRule
			) {
				updateStatus(
					notice,
					getUpcomingStatus(
						upcomingRule,
						productTotal,
						texts
					)
				);

			} else {
				updateStatus(
					notice,
					''
				);
			}
		}

		/*
		 * Before selecting an eligible product,
		 * highlight the first available discount
		 * tier as next.
		 */
		if (
			productTotal <= 0 &&
			!upcomingRule
		) {
			upcomingRule =
				findUpcomingRule(
					rules,
					0
				);
		}

		updateTierStates(
			notice,
			rules,
			activeRules,
			upcomingRule,
			productTotal,
			conditionContext
		);

		discount =
			roundMoney(
				discount
			);

		/*
		 * Final safety:
		 *
		 * Automatic Discount can never exceed the
		 * eligible Automatic Discount basis.
		 */
		discount =
			Math.min(
				discount,
				productTotal
			);

		discount =
			roundMoney(
				discount
			);

		const result = {
			/*
			 * Backward-compatible productTotal.
			 *
			 * This is intentionally the Automatic
			 * Discount base.
			 */
			productTotal:
				productTotal,

			normalProductTotal:
				normalProductTotal,

			comboAutomaticTotal:
				comboAutomaticTotal,

			orderBumpAutomaticTotal:
				orderBumpAutomaticTotal,

			automaticDiscountBase:
				productTotal,

			ruleId:
				ruleId,

			ruleIds:
				activeRules.map(function (rule) {
					return rule.id;
				}),

			ruleType:
				ruleType,

			discount:
				discount,

			automaticDiscount:
				discount,

			discountLabel:
				discountLabel,

			freeDelivery:
				freeDelivery,

			freeDeliveryMethodId:
				freeDeliveryMethodId,

			freeDeliveryHideOtherMethods:
				freeDeliveryHideOtherMethods,

			hasActiveRule:
				activeRules.length > 0,

			hasUpcomingRule:
				null !==
					upcomingRule,
		};

		storeResult(
			checkout,
			notice,
			result
		);

		/*
		 * Summary listens to this event and updates
		 * the automatic-discount row.
		 *
		 * We intentionally do not call
		 * Summary.setAmount() here to prevent
		 * duplicate recalculations.
		 */
		dispatchChange(
			checkout,
			result
		);

		return result;
	}

	/**
	 * Schedule refresh.
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

				refresh(
					checkout
				);
			}
		);
	}

	/**
	 * Get checkout from event.
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
			return event.detail
				.checkout;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			const checkout =
				event.target.closest(
					SELECTORS.checkout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initialize(
		checkout
	) {
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

		const notice =
			checkout.querySelector(
				SELECTORS.notice
			);

		if (
			!(
				window.eilmoCfDom.isElement(notice)
			)
		) {
			return;
		}

		initialized.add(
			checkout
		);

		refresh(
			checkout
		);
	}

	/**
	 * Initialize all checkouts.
	 *
	 * @return {void}
	 */
	function initializeAll() {
		document
			.querySelectorAll(
				SELECTORS.checkout
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

	/**
	 * Normal product quantity changed.
	 *
	 * Automatic Discount must recalculate.
	 */
	document.addEventListener(
		'eilmo:quantityChange',
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

	/**
	 * Multiple Products selection changed.
	 *
	 * Quantity events normally cover the same interaction, while
	 * this root event also protects controller-level selection sync.
	 * scheduleRefresh() coalesces both into one calculation.
	 */
	document.addEventListener(
		'eilmo:multipleProductsChange',
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

	/**
	 * Order Bump selection changed.
	 *
	 * Recalculate only because a selected Order Bump
	 * MAY explicitly allow Automatic Discount.
	 *
	 * Order Bumps with:
	 *
	 * apply_automatic_discount = no
	 *
	 * still produce the same Automatic Discount base.
	 */
	document.addEventListener(
		'eilmo:orderBumpChange',
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

	/**
	 * Combo Offer selection changed.
	 *
	 * Recalculate because a selected Combo Offer
	 * MAY explicitly allow Automatic Discount.
	 *
	 * Combo Offers with:
	 *
	 * apply_automatic_discount = no
	 *
	 * do not change the Automatic Discount base.
	 */
	document.addEventListener(
		'eilmo:comboChange',
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

	/**
	 * Public Automatic Discount API.
	 */
	window.eilmoCfDiscount = {

		/**
		 * Refresh one checkout.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		refresh:
			refresh,

		/**
		 * Get current result.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
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

		/**
		 * Get current Automatic Discount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getDiscount:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				const result =
					results.get(
						checkout
					);

				if (result) {
					return toNumber(
						result.discount
					);
				}

				return toNumber(
					checkout.dataset
						.automaticDiscount ||
						0
				);
			},

		/**
		 * Get current Automatic Discount base.
		 *
		 * Eligible Combo Offers and eligible Order Bumps
		 * are included with their payable promotional totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getBase:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				const result =
					results.get(
						checkout
					);

				if (
					result &&
					Number.isFinite(
						Number(
							result
								.automaticDiscountBase
						)
					)
				) {
					return toNumber(
						result
							.automaticDiscountBase
					);
				}

				return getAutomaticDiscountBase(
					checkout
				)
					.automaticDiscountBase;
			},

		/**
		 * Get current base breakdown.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		getBaseBreakdown:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						normalProductTotal:
							0,

						comboTotal:
							0,

						orderBumpTotal:
							0,

						automaticDiscountBase:
							0,
					};
				}

				return getAutomaticDiscountBase(
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
