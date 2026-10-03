/**
 * Eilmo Checkout Flow - WhatsApp Ordering.
 *
 * Quick WhatsApp ordering flow.
 *
 * Rules:
 *
 * - At least one product must be selected.
 * - Customer fields are optional.
 * - Address fields are optional.
 * - Delivery is optional.
 * - Payment is optional.
 * - Empty placeholder lines are removed.
 * - WooCommerce orders are never created here.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		button:
			'[data-eilmo-whatsapp-order]',

		error:
			'[data-eilmo-whatsapp-error]',

		productItem:
			'[data-eilmo-product-item], [data-eilmo-item]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		productTitle:
			[
				'[data-eilmo-product-title]',
				'.eilmo-cf-product__title',
				'.eilmo-cf-product-item__title',
				'.eilmo-cf-product-card__title',
			].join(','),

		comboOffer:
			'[data-eilmo-combo-offer]',

		comboInput:
			'[data-eilmo-combo-input]',

		comboTitle:
			'.eilmo-cf-combo-offer__title',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		orderBumpInput:
			'[data-eilmo-order-bump-input]',

		orderBumpTitle:
			'.eilmo-cf-special-discount-card__title',

		deliveryInput:
			'[data-eilmo-delivery-input]',

		deliveryMethod:
			'[data-eilmo-delivery-method]',

		deliveryLabel:
			'.eilmo-cf-delivery-method__label',

		paymentMethod:
			'[data-eilmo-payment-method]',

		paymentRadio:
			'[data-eilmo-payment-radio]',

		paymentTitle:
			'.eilmo-cf-payment-method__title',
	};

	/**
	 * Get configuration.
	 *
	 * @return {Object}
	 */
	function getConfig() {
		return (
			window.eilmoCfWhatsApp &&
			typeof window.eilmoCfWhatsApp ===
				'object'
		)
			? window.eilmoCfWhatsApp
			: {};
	}

	/**
	 * Get localized text.
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
			getConfig();

		const i18n =
			config.i18n &&
			typeof config.i18n ===
				'object'
				? config.i18n
				: {};

		const value =
			i18n[
				key
			];

		return (
			typeof value ===
				'string' &&
			value.trim() !==
				''
		)
			? value
			: fallback;
	}

	/**
	 * Normalize text.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizeText(
		value
	) {
		return String(
			value ?? ''
		)
			.replace(
				/\s+/g,
				' '
			)
			.trim();
	}

	/**
	 * Convert to integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(
		value
	) {
		const parsed =
			Number.parseInt(
				String(
					value ?? ''
				),
				10
			);

		return Number.isFinite(
			parsed
		)
			? Math.max(
				0,
				parsed
			)
			: 0;
	}

	/**
	 * Convert to number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(
		value
	) {
		const parsed =
			Number.parseFloat(
				String(
					value ?? ''
				)
			);

		return Number.isFinite(
			parsed
		)
			? Math.max(
				0,
				parsed
			)
			: 0;
	}

	/**
	 * Strip HTML.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function stripHTML(
		value
	) {
		const wrapper =
			document.createElement(
				'div'
			);

		wrapper.innerHTML =
			String(
				value ?? ''
			);

		return normalizeText(
			wrapper.textContent ||
			wrapper.innerText ||
			''
		);
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(
		element
	) {
		if (
			!(
				element instanceof
					Element
			)
		) {
			return null;
		}

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return checkout instanceof
			HTMLElement
				? checkout
				: null;
	}

	/**
	 * Clear error.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function clearError(
		checkout
	) {
		checkout.querySelectorAll( SELECTORS.error ).forEach( function ( element ) {
			if ( element instanceof HTMLElement ) {
				element.textContent = '';
				element.hidden = true;
			}
		} );
	}

	/**
	 * Show error.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      message  Message.
	 *
	 * @return {void}
	 */
	function showError(
		checkout,
		message
	) {
		checkout.querySelectorAll( SELECTORS.error ).forEach( function ( element ) {
			if ( element instanceof HTMLElement ) {
				element.textContent = String( message || '' );
				element.hidden = false;
			}
		} );
	}

	/**
	 * Read a form field.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      name     Field name.
	 *
	 * @return {string}
	 */
	function getFieldValue(
		checkout,
		name
	) {
		const field =
			checkout.querySelector(
				`[name="${name}"]`
			);

		if (
			field instanceof
				HTMLInputElement ||
			field instanceof
				HTMLSelectElement ||
			field instanceof
				HTMLTextAreaElement
		) {
			return normalizeText(
				field.value
			);
		}

		return '';
	}

	/**
	 * Get customer data.
	 *
	 * Existing checkout API is preferred.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getCustomerData(
		checkout
	) {
		const api =
			window.eilmoCfCheckout;

		let customer =
			{};

		if (
			api &&
			typeof api.getCustomerData ===
				'function'
		) {
			const result =
				api.getCustomerData(
					checkout
				);

			if (
				result &&
				typeof result ===
					'object' &&
				!Array.isArray(
					result
				)
			) {
				customer =
					result;
			}
		}

		const fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'billing_email',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		];

		fields.forEach(
			function (name) {
				if (
					normalizeText(
						customer[
							name
						]
					) !== ''
				) {
					return;
				}

				customer[
					name
				] =
					getFieldValue(
						checkout,
						name
					);
			}
		);

		return customer;
	}

	/**
	 * Build readable customer name.
	 *
	 * @param {Object} customer Customer data.
	 *
	 * @return {string}
	 */
	function getCustomerName(
		customer
	) {
		return [
			normalizeText(
				customer
					.billing_first_name
			),

			normalizeText(
				customer
					.billing_last_name
			),
		]
			.filter(
				Boolean
			)
			.join(
				' '
			);
	}

	/**
	 * Build readable address.
	 *
	 * Only populated values are included.
	 *
	 * @param {Object} customer Customer data.
	 *
	 * @return {string}
	 */
	function getAddress(
		customer
	) {
		const parts = [
			customer
				.billing_address_1,

			customer
				.billing_address_2,

			customer
				.billing_city,

			customer
				.billing_state,

			customer
				.billing_postcode,

			customer
				.billing_country,
		]
			.map(
				normalizeText
			)
			.filter(
				Boolean
			);

		return parts.join(
			', '
		);
	}

	/**
	 * Read integer from dataset.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {Array<string>}     keys    Dataset keys.
	 *
	 * @return {number}
	 */
	function getDatasetInteger(
		element,
		keys
	) {
		if (
			!(
				element instanceof
					HTMLElement
			)
		) {
			return 0;
		}

		for (
			const key of keys
		) {
			const value =
				toInteger(
					element.dataset[
						key
					]
				);

			if (
				value > 0
			) {
				return value;
			}
		}

		return 0;
	}

	/**
	 * Get product ID.
	 *
	 * @param {HTMLElement}       item  Item.
	 * @param {HTMLInputElement} input Quantity.
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

		if (
			itemId > 0
		) {
			return itemId;
		}

		return getDatasetInteger(
			input,
			[
				'productId',
				'parentProductId',
				'parentId',
			]
		);
	}

	/**
	 * Get variation ID.
	 *
	 * @param {HTMLElement}       item  Item.
	 * @param {HTMLInputElement} input Quantity.
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

		if (
			itemId > 0
		) {
			return itemId;
		}

		return getDatasetInteger(
			input,
			[
				'variationId',
			]
		);
	}

	/**
	 * Get product title.
	 *
	 * @param {HTMLElement} item        Item.
	 * @param {number}      productId   Product ID.
	 * @param {number}      variationId Variation ID.
	 *
	 * @return {string}
	 */
	function getProductTitle(
		item,
		productId,
		variationId
	) {
		const titleElement =
			item.querySelector(
				SELECTORS.productTitle
			);

		if (
			titleElement instanceof
				HTMLElement
		) {
			const title =
				normalizeText(
					titleElement
						.textContent
				);

			if (title) {
				return title;
			}
		}

		if (
			variationId > 0
		) {
			return (
				getText(
					'variation',
					'Variation'
				) +
				' #' +
				variationId
			);
		}

		if (
			productId > 0
		) {
			return (
				getText(
					'product',
					'Product'
				) +
				' #' +
				productId
			);
		}

		return getText(
			'product',
			'Product'
		);
	}

	/**
	 * Get product URL from rendered product title/link.
	 *
	 * If no product URL exists in the rendered checkout,
	 * an empty string is returned.
	 *
	 * @param {HTMLElement} item Item.
	 *
	 * @return {string}
	 */
	function getProductUrl(
		item
	) {
		const selectors = [
			'[data-eilmo-product-title] a[href]',
			'.eilmo-cf-product__title a[href]',
			'.eilmo-cf-product-item__title a[href]',
			'.eilmo-cf-product-card__title a[href]',
			'a[data-eilmo-product-link][href]',
		];

		for (
			const selector of selectors
		) {
			const link =
				item.querySelector(
					selector
				);

			if (
				link instanceof
					HTMLAnchorElement &&
				link.href
			) {
				return link.href;
			}
		}

		return '';
	}

	/**
	 * Build normal selected product lines.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getNormalProductLines(
		checkout
	) {
		const lines =
			[];

		checkout
			.querySelectorAll(
				SELECTORS.productItem
			)
			.forEach(
				function (item) {
					if (
						!(
							item instanceof
								HTMLElement
						)
					) {
						return;
					}

					const quantityInput =
						item.querySelector(
							SELECTORS
								.quantityInput
						);

					if (
						!(
							quantityInput instanceof
								HTMLInputElement
						)
					) {
						return;
					}

					const quantity =
						toInteger(
							quantityInput
								.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					const productId =
						getProductId(
							item,
							quantityInput
						);

					const variationId =
						getVariationId(
							item,
							quantityInput
						);

					const title =
						getProductTitle(
							item,
							productId,
							variationId
						);

					const url =
						getProductUrl(
							item
						);

					let line =
						'- ' +
						title +
						' × ' +
						quantity;

					if (url) {
						line +=
							'\n  ' +
							url;
					}

					lines.push(
						line
					);
				}
			);

		return lines;
	}

	/**
	 * Get selected Combo Offers.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getComboLines(
		checkout
	) {
		const lines =
			[];

		checkout
			.querySelectorAll(
				SELECTORS.comboInput +
					':checked'
			)
			.forEach(
				function (input) {
					if (
						!(
							input instanceof
								HTMLInputElement
						) ||
						input.disabled
					) {
						return;
					}

					const offer =
						input.closest(
							SELECTORS
								.comboOffer
						);

					let title =
						'';

					if (
						offer instanceof
							HTMLElement
					) {
						const titleElement =
							offer.querySelector(
								SELECTORS
									.comboTitle
							);

						if (
							titleElement instanceof
								HTMLElement
						) {
							title =
								normalizeText(
									titleElement
										.textContent
								);
						}
					}

					if (!title) {
						title =
							normalizeText(
								input.value
							);
					}

					if (!title) {
						return;
					}

					lines.push(
						'- ' +
						getText(
							'combo',
							'Combo'
						) +
						': ' +
						title
					);
				}
			);

		return lines;
	}

	/**
	 * Get selected Order Bumps.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getOrderBumpLines(
		checkout
	) {
		const lines =
			[];

		checkout
			.querySelectorAll(
				SELECTORS.orderBumpInput +
					':checked'
			)
			.forEach(
				function (input) {
					if (
						!(
							input instanceof
								HTMLInputElement
						) ||
						input.disabled
					) {
						return;
					}

					const offer =
						input.closest(
							SELECTORS
								.orderBumpOffer
						);

					let title =
						'';

					if (
						offer instanceof
							HTMLElement
					) {
						const titleElement =
							offer.querySelector(
								SELECTORS
									.orderBumpTitle
							);

						if (
							titleElement instanceof
								HTMLElement
						) {
							title =
								normalizeText(
									titleElement
										.textContent
								);
						}
					}

					if (!title) {
						title =
							normalizeText(
								input.value
							);
					}

					if (!title) {
						return;
					}

					lines.push(
						'- ' +
						getText(
							'specialOffer',
							'Special Offer'
						) +
						': ' +
						title
					);
				}
			);

		return lines;
	}

	/**
	 * Get all selected product-related lines.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getProductLines(
		checkout
	) {
		return [
			...getNormalProductLines(
				checkout
			),

			...getComboLines(
				checkout
			),

			...getOrderBumpLines(
				checkout
			),
		];
	}

	/**
	 * Get selected delivery label.
	 *
	 * No validation is performed.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getDeliveryLabel(
		checkout
	) {
		const api =
			window.eilmoCfDelivery;

		if (
			api &&
			typeof api.getSelected ===
				'function'
		) {
			const selected =
				api.getSelected(
					checkout
				);

			if (
				selected &&
				typeof selected ===
					'object'
			) {
				const label =
					normalizeText(
						selected.label ||
						selected.title ||
						''
					);

				if (label) {
					return label;
				}
			}
		}

		const input =
			checkout.querySelector(
				SELECTORS.deliveryInput +
					':checked'
			);

		if (
			!(
				input instanceof
					HTMLInputElement
			)
		) {
			return '';
		}

		const method =
			input.closest(
				SELECTORS.deliveryMethod
			);

		if (
			!(
				method instanceof
					HTMLElement
			)
		) {
			return '';
		}

		const label =
			method.querySelector(
				SELECTORS.deliveryLabel
			);

		return label instanceof
			HTMLElement
				? normalizeText(
					label.textContent
				)
				: '';
	}

	/**
	 * Get selected payment method label.
	 *
	 * No payment validation is performed.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentLabel(
		checkout
	) {
		const api =
			window.eilmoCfPayment;

		if (
			api &&
			typeof api.getSelectedMethod ===
				'function'
		) {
			const method =
				api.getSelectedMethod(
					checkout
				);

			if (
				method instanceof
					HTMLElement
			) {
				const title =
					method.querySelector(
						SELECTORS.paymentTitle
					);

				if (
					title instanceof
						HTMLElement
				) {
					const text =
						normalizeText(
							title.textContent
						);

					if (text) {
						return text;
					}
				}
			}
		}

		const radio =
			checkout.querySelector(
				SELECTORS.paymentRadio +
					':checked'
			);

		if (
			!(
				radio instanceof
					HTMLInputElement
			)
		) {
			return '';
		}

		const method =
			radio.closest(
				SELECTORS.paymentMethod
			);

		if (
			!(
				method instanceof
					HTMLElement
			)
		) {
			return '';
		}

		const title =
			method.querySelector(
				SELECTORS.paymentTitle
			);

		return title instanceof
			HTMLElement
				? normalizeText(
					title.textContent
				)
				: '';
	}

	/**
	 * Get current checkout total.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getTotal(
		checkout
	) {
		const summary =
			window.eilmoCfSummary;

		if (
			summary &&
			typeof summary.getValues ===
				'function'
		) {
			const values =
				summary.getValues(
					checkout
				);

			if (
				values &&
				typeof values ===
					'object'
			) {
				const grandTotal =
					toNumber(
						values.grandTotal ??
						values.grand_total ??
						0
					);

				if (
					grandTotal > 0
				) {
					return grandTotal;
				}

				const baseGrandTotal =
					toNumber(
						values.baseGrandTotal ??
						values.base_grand_total ??
						0
					);

				if (
					baseGrandTotal > 0
				) {
					return baseGrandTotal;
				}
			}
		}

		return toNumber(
			checkout.dataset
				.grandTotal ||
			checkout.dataset
				.baseGrandTotal ||
			0
		);
	}

	/**
	 * Format total.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getTotalLabel(
		checkout
	) {
		const total =
			getTotal(
				checkout
			);

		const summary =
			window.eilmoCfSummary;

		if (
			summary &&
			typeof summary.formatPrice ===
				'function'
		) {
			return stripHTML(
				summary.formatPrice(
					total
				)
			);
		}

		return String(
			total
		);
	}

	/**
	 * Remove lines whose placeholders have no values,
	 * and replace all populated placeholders.
	 *
	 * Example:
	 *
	 * Email: {email}
	 *
	 * If email is empty, the complete line disappears.
	 *
	 * @param {string} template     Template.
	 * @param {Object} replacements Values.
	 *
	 * @return {string}
	 */
	function processTemplate(
		template,
		replacements
	) {
		const placeholderPattern =
			/\{([a-z0-9_]+)\}/gi;

		const lines =
			String(
				template || ''
			)
				.replace(
					/\r\n?/g,
					'\n'
				)
				.split(
					'\n'
				);

		const output =
			[];

		lines.forEach(
			function (line) {
				const placeholders =
					Array.from(
						line.matchAll(
							placeholderPattern
						)
					);

				/*
				 * Normal static line.
				 */
				if (
					0 ===
						placeholders.length
				) {
					output.push(
						line
					);

					return;
				}

				/*
				 * If any placeholder used on this line
				 * has no value, remove the whole line.
				 */
				const hasEmptyValue =
					placeholders.some(
						function (match) {
							const key =
								String(
									match[1] ||
										''
								).toLowerCase();

							return (
								normalizeText(
									replacements[
										key
									]
								) ===
									''
							);
						}
					);

				if (hasEmptyValue) {
					return;
				}

				let processed =
					line;

				placeholders.forEach(
					function (match) {
						const key =
							String(
								match[1] ||
									''
							).toLowerCase();

						processed =
							processed
								.split(
									match[0]
								)
								.join(
									String(
										replacements[
											key
										] ??
											''
									)
								);
					}
				);

				output.push(
					processed
				);
			}
		);

		/*
		 * Remove excessive blank lines while retaining
		 * readable message sections.
		 */
		return output
			.join(
				'\n'
			)
			.replace(
				/[ \t]+\n/g,
				'\n'
			)
			.replace(
				/\n{3,}/g,
				'\n\n'
			)
			.trim();
	}

	/**
	 * Build message.
	 *
	 * @param {HTMLElement}   checkout     Checkout.
	 * @param {Array<string>} productLines Selected products.
	 *
	 * @return {string}
	 */
	function buildMessage(
		checkout,
		productLines = null
	) {
		const config =
			getConfig();

		const customer =
			getCustomerData(
				checkout
			);

		const products =
			Array.isArray(
				productLines
			)
				? productLines
				: getProductLines(
					checkout
				);

		const replacements = {
			customer_name:
				getCustomerName(
					customer
				),

			phone:
				normalizeText(
					customer
						.billing_phone
				),

			email:
				normalizeText(
					customer
						.billing_email
				),

			address:
				getAddress(
					customer
				),

			products:
				products.join(
					'\n'
				),

			delivery:
				getDeliveryLabel(
					checkout
				),

			payment:
				getPaymentLabel(
					checkout
				),

			total:
				getTotalLabel(
					checkout
				),

			checkout_url:
				window.location.href,
		};

		return processTemplate(
			String(
				config.messageTemplate ||
					''
			),
			replacements
		);
	}

	/**
	 * Open WhatsApp.
	 *
	 * @param {HTMLElement}   checkout     Checkout.
	 * @param {Array<string>} productLines Products.
	 *
	 * @return {boolean}
	 */
	function openWhatsApp(
		checkout,
		productLines = null
	) {
		const config =
			getConfig();

		const number =
			String(
				config.number ||
					''
			)
				.replace(
					/\D+/g,
					''
				);

		if (!number) {
			showError(
				checkout,
				getText(
					'unavailable',
					'WhatsApp ordering is currently unavailable.'
				)
			);

			return false;
		}

		const message =
			buildMessage(
				checkout,
				productLines
			);

		if (
			!message
		) {
			showError(
				checkout,
				getText(
					'unavailable',
					'WhatsApp ordering is currently unavailable.'
				)
			);

			return false;
		}

		const url =
			'https://wa.me/' +
			number +
			'?text=' +
			encodeURIComponent(
				message
			);

		window.open(
			url,
			'_blank',
			'noopener,noreferrer'
		);

		return true;
	}

	/**
	 * Handle button click.
	 *
	 * Only product selection is mandatory.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(
		event
	) {
		if (
			!(
				event.target instanceof
					Element
			)
		) {
			return;
		}

		const button =
			event.target.closest(
				SELECTORS.button
			);

		if (
			!(
				button instanceof
					HTMLButtonElement
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				button
			);

		if (!checkout) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		clearError(
			checkout
		);

		const productLines =
			getProductLines(
				checkout
			);

		/*
		 * This is the only required condition for
		 * WhatsApp Ordering.
		 */
		if (
			0 ===
				productLines.length
		) {
			showError(
				checkout,
				getText(
					'selectProduct',
					'Please select at least one product.'
				)
			);

			return;
		}

		openWhatsApp(
			checkout,
			productLines
		);
	}

	/**
	 * Public API.
	 */
	window.eilmoCfWhatsAppOrdering = {
		buildMessage:
			function (checkout) {

				if (
					!(
						checkout instanceof
							HTMLElement
					)
				) {
					return '';
				}

				return buildMessage(
					checkout
				);
			},

		open:
			function (checkout) {

				if (
					!(
						checkout instanceof
							HTMLElement
					)
				) {
					return false;
				}

				const products =
					getProductLines(
						checkout
					);

				if (
					0 ===
						products.length
				) {
					return false;
				}

				return openWhatsApp(
					checkout,
					products
				);
			},
	};

	document.addEventListener(
		'click',
		handleClick
	);
})();
