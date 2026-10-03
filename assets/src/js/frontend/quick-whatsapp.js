/**
 * Eilmo Checkout Flow - Single Product Quick WhatsApp.
 *
 * Direct WhatsApp ordering for WooCommerce
 * single-product pages.
 *
 * This script is intentionally independent from
 * the Quick Checkout drawer.
 *
 * Rules:
 *
 * - Never opens the Quick Checkout drawer.
 * - Never creates a WooCommerce order.
 * - Variable products require a selected variation.
 * - Sends only:
 *   - Product name.
 *   - Selected variation.
 *   - Current price.
 *   - Product URL.
 * - Does not send:
 *   - Customer information.
 *   - Phone.
 *   - Email.
 *   - Address.
 *   - Delivery.
 *   - Payment.
 *   - Coupon.
 *   - Combo Offers.
 *   - Order Bumps.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		/*
		 * Current QuickCheckoutRenderer markup.
		 *
		 * This is intentionally different from the
		 * Order Now selector used by quick-checkout.js:
		 *
		 * data-eilmo-quick-checkout-trigger="order"
		 */
		button:
			'[data-eilmo-quick-checkout-trigger="whatsapp"], [data-eilmo-quick-whatsapp]',

		actions:
			'[data-eilmo-quick-checkout-actions]',

		productMessage:
			'[data-eilmo-quick-checkout-product-message]',

		root:
			'[data-eilmo-quick-checkout]',

		productForm:
			'form.cart',

		variationForm:
			'form.variations_form',

		variationId:
			'input.variation_id, input[name="variation_id"]',

		variationSelect:
			'.variations select',

		productTitle:
			'.product_title',

		variationPrice:
			'.woocommerce-variation-price .price',

		productPrice:
			'.summary .price',
	};

	/**
	 * Get localized configuration.
	 *
	 * @return {Object}
	 */
	function getConfig() {

		return (
			window.eilmoCfQuickWhatsApp &&
			typeof window.eilmoCfQuickWhatsApp ===
				'object'
		)
			? window.eilmoCfQuickWhatsApp
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
	 * Normalize readable text.
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
	 * Convert value to integer.
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
	 * Strip HTML.
	 *
	 * @param {*} value HTML.
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
	 * Get Quick Checkout root associated with the
	 * product-page action buttons.
	 *
	 * @param {HTMLButtonElement} button Button.
	 *
	 * @return {HTMLElement|null}
	 */
	function getRoot(
		button
	) {

		/*
		 * Older/current markup may still expose
		 * aria-controls.
		 */
		const controls =
			String(
				button.getAttribute(
					'aria-controls'
				) ||
				''
			).trim();

		if (controls) {

			const controlled =
				document.getElementById(
					controls
				);

			if (
				controlled instanceof
					HTMLElement &&
				controlled.matches(
					SELECTORS.root
				)
			) {
				return controlled;
			}
		}

		const actions =
			button.closest(
				SELECTORS.actions
			);

		if (
			!(
				actions instanceof
					HTMLElement
			)
		) {
			return null;
		}

		/*
		 * Support message element either inside
		 * actions or between actions and root.
		 */
		let sibling =
			actions.nextElementSibling;

		while (sibling) {

			if (
				sibling instanceof
					HTMLElement &&
				sibling.matches(
					SELECTORS.root
				)
			) {
				return sibling;
			}

			sibling =
				sibling.nextElementSibling;
		}

		return null;
	}

	/**
	 * Get validation message element.
	 *
	 * @param {HTMLButtonElement} button Button.
	 *
	 * @return {HTMLElement|null}
	 */
	function getMessageElement(
		button
	) {

		const actions =
			button.closest(
				SELECTORS.actions
			);

		if (
			!(
				actions instanceof
					HTMLElement
			)
		) {
			return null;
		}

		const inside =
			actions.querySelector(
				SELECTORS.productMessage
			);

		if (
			inside instanceof
				HTMLElement
		) {
			return inside;
		}

		const next =
			actions.nextElementSibling;

		if (
			next instanceof
				HTMLElement &&
			next.matches(
				SELECTORS.productMessage
			)
		) {
			return next;
		}

		return null;
	}

	/**
	 * Show/clear product-page message.
	 *
	 * @param {HTMLButtonElement} button  Button.
	 * @param {string}            message Message.
	 *
	 * @return {void}
	 */
	function setMessage(
		button,
		message
	) {

		const element =
			getMessageElement(
				button
			);

		if (
			!(
				element instanceof
					HTMLElement
			)
		) {
			return;
		}

		element.textContent =
			String(
				message || ''
			);

		element.hidden =
			!message;
	}

	/**
	 * Get WooCommerce product form.
	 *
	 * @param {HTMLElement} root Quick root.
	 *
	 * @return {HTMLFormElement|null}
	 */
	function getProductForm(
		root
	) {

		const product =
			root.closest(
				'.product'
			);

		if (
			product instanceof
				Element
		) {

			const variationForm =
				product.querySelector(
					SELECTORS.variationForm
				);

			if (
				variationForm instanceof
					HTMLFormElement
			) {
				return variationForm;
			}

			const form =
				product.querySelector(
					SELECTORS.productForm
				);

			if (
				form instanceof
					HTMLFormElement
			) {
				return form;
			}
		}

		const variationFallback =
			document.querySelector(
				SELECTORS.variationForm
			);

		if (
			variationFallback instanceof
				HTMLFormElement
		) {
			return variationFallback;
		}

		const fallback =
			document.querySelector(
				SELECTORS.productForm
			);

		return fallback instanceof
			HTMLFormElement
				? fallback
				: null;
	}

	/**
	 * Get variation ID.
	 *
	 * @param {HTMLFormElement|null} form Product form.
	 *
	 * @return {number}
	 */
	function getVariationId(
		form
	) {

		if (
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return 0;
		}

		const input =
			form.querySelector(
				SELECTORS.variationId
			);

		return input instanceof
			HTMLInputElement
				? toInteger(
					input.value
				)
				: 0;
	}

	/**
	 * Get WooCommerce variation collection.
	 *
	 * @param {HTMLFormElement|null} form Product form.
	 *
	 * @return {Array<Object>}
	 */
	function getVariationCollection(
		form
	) {

		if (
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return [];
		}

		if (
			window.jQuery &&
			typeof window.jQuery ===
				'function'
		) {

			const stored =
				window
					.jQuery(
						form
					)
					.data(
						'product_variations'
					);

			if (
				Array.isArray(
					stored
				)
			) {
				return stored;
			}
		}

		const raw =
			form.getAttribute(
				'data-product_variations'
			);

		if (!raw) {
			return [];
		}

		try {

			const parsed =
				JSON.parse(
					raw
				);

			return Array.isArray(
				parsed
			)
				? parsed
				: [];

		} catch (error) {

			return [];
		}
	}

	/**
	 * Find current variation data.
	 *
	 * @param {HTMLFormElement|null} form        Product form.
	 * @param {number}               variationId Variation ID.
	 *
	 * @return {Object|null}
	 */
	function getVariationData(
		form,
		variationId
	) {

		if (
			variationId <= 0
		) {
			return null;
		}

		const variations =
			getVariationCollection(
				form
			);

		for (
			let index = 0;
			index < variations.length;
			index += 1
		) {

			const variation =
				variations[
					index
				];

			if (
				variation &&
				typeof variation ===
					'object' &&
				toInteger(
					variation.variation_id
				) ===
					variationId
			) {
				return variation;
			}
		}

		return null;
	}

	/**
	 * Get selected variation labels.
	 *
	 * Example:
	 *
	 * Black / XL
	 *
	 * @param {HTMLFormElement|null} form Product form.
	 *
	 * @return {string}
	 */
	function getVariationText(
		form
	) {

		if (
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return '';
		}

		const values =
			[];

		form
			.querySelectorAll(
				SELECTORS.variationSelect
			)
			.forEach(
				function (select) {

					if (
						!(
							select instanceof
								HTMLSelectElement
						) ||
						!select.value
					) {
						return;
					}

					const option =
						select.options[
							select.selectedIndex
						];

					const text =
						option
							? normalizeText(
								option.textContent
							)
							: '';

					if (
						text &&
						!values.includes(
							text
						)
					) {
						values.push(
							text
						);
					}
				}
			);

		return values.join(
			' / '
		);
	}

	/**
	 * Get product title.
	 *
	 * @param {HTMLElement} root Root.
	 *
	 * @return {string}
	 */
	function getProductTitle(
		root
	) {

		const product =
			root.closest(
				'.product'
			);

		const title =
			product instanceof
				Element
					? product.querySelector(
						SELECTORS.productTitle
					)
					: document.querySelector(
						SELECTORS.productTitle
					);

		return title instanceof
			HTMLElement
				? normalizeText(
					title.textContent
				)
				: '';
	}

	/**
	 * Get current product price.
	 *
	 * For variable products, selected variation price
	 * is preferred.
	 *
	 * @param {HTMLElement}          root      Root.
	 * @param {HTMLFormElement|null} form      Product form.
	 * @param {Object|null}          variation Variation data.
	 *
	 * @return {string}
	 */
	function getProductPrice(
		root,
		form,
		variation
	) {

		if (
			variation &&
			typeof variation.price_html ===
				'string' &&
			variation.price_html.trim()
		) {
			return stripHTML(
				variation.price_html
			);
		}

		if (
			form instanceof
				HTMLFormElement
		) {

			const variationPrice =
				form.querySelector(
					SELECTORS.variationPrice
				);

			if (
				variationPrice instanceof
					HTMLElement
			) {

				const text =
					normalizeText(
						variationPrice.textContent
					);

				if (text) {
					return text;
				}
			}
		}

		const product =
			root.closest(
				'.product'
			);

		const price =
			product instanceof
				Element
					? product.querySelector(
						SELECTORS.productPrice
					)
					: document.querySelector(
						SELECTORS.productPrice
					);

		return price instanceof
			HTMLElement
				? normalizeText(
					price.textContent
				)
				: '';
	}

	/**
	 * Get current product URL.
	 *
	 * Hash fragments are removed.
	 *
	 * @return {string}
	 */
	function getProductUrl() {

		try {

			const url =
				new URL(
					window.location.href
				);

			url.hash =
				'';

			return url.toString();

		} catch (error) {

			return String(
				window.location.href ||
					''
			)
				.split(
					'#'
				)[0];
		}
	}

	/**
	 * Collect single-product WhatsApp data.
	 *
	 * @param {HTMLElement} root Root.
	 *
	 * @return {Object}
	 */
	function getProductData(
		root
	) {

		const form =
			getProductForm(
				root
			);

		const productType =
			normalizeText(
				root.dataset
					.productType ||
					''
			)
				.toLowerCase();

		const variationId =
			getVariationId(
				form
			);

		const variation =
			getVariationData(
				form,
				variationId
			);

		return {
			productType:
				productType,

			variationId:
				variationId,

			title:
				getProductTitle(
					root
				),

			variation:
				getVariationText(
					form
				),

			price:
				getProductPrice(
					root,
					form,
					variation
				),

			url:
				getProductUrl(),
		};
	}

	/**
	 * Build product details.
	 *
	 * Only these details are allowed in the Quick
	 * WhatsApp product block.
	 *
	 * @param {Object} product Product.
	 *
	 * @return {string}
	 */
	function buildProductBlock(
		product
	) {

		const lines =
			[];

		const title =
			normalizeText(
				product.title
			);

		const variation =
			normalizeText(
				product.variation
			);

		const price =
			normalizeText(
				product.price
			);

		const url =
			normalizeText(
				product.url
			);

		if (title) {
			lines.push(
				'Product: ' +
					title
			);
		}

		if (variation) {
			lines.push(
				'Variation: ' +
					variation
			);
		}

		if (price) {
			lines.push(
				'Price: ' +
					price
			);
		}

		if (url) {
			lines.push(
				'Product Link: ' +
					url
			);
		}

		return lines.join(
			'\n'
		);
	}

	/**
	 * Process configured WhatsApp template.
	 *
	 * Customer/delivery/payment/etc placeholders are
	 * intentionally empty.
	 *
	 * Any line containing an empty placeholder is
	 * removed completely.
	 *
	 * @param {string} template     Message template.
	 * @param {Object} replacements Replacements.
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

				if (
					0 ===
						placeholders.length
				) {
					output.push(
						line
					);

					return;
				}

				const hasEmptyValue =
					placeholders.some(
						function (match) {

							const key =
								String(
									match[1] ||
										''
								)
									.toLowerCase();

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
							)
								.toLowerCase();

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
	 * Build final WhatsApp message.
	 *
	 * @param {Object} product Product.
	 *
	 * @return {string}
	 */
	function buildMessage(
		product
	) {

		const config =
			getConfig();

		const productBlock =
			buildProductBlock(
				product
			);

		if (!productBlock) {
			return '';
		}

		const replacements = {
			customer_name:
				'',

			phone:
				'',

			email:
				'',

			address:
				'',

			products:
				productBlock,

			delivery:
				'',

			payment:
				'',

			total:
				'',

			checkout_url:
				'',
		};

		let message =
			processTemplate(
				String(
					config.messageTemplate ||
						''
				),
				replacements
			);

		/*
		 * Custom admin templates may not contain
		 * {products}. Product information is mandatory
		 * for this flow, so append it when required.
		 */
		if (
			!message.includes(
				productBlock
			)
		) {
			message =
				[
					message,
					productBlock,
				]
					.filter(
						Boolean
					)
					.join(
						'\n\n'
					);
		}

		return message.trim();
	}

	/**
	 * Open WhatsApp.
	 *
	 * @param {Object} product Product.
	 *
	 * @return {boolean}
	 */
	function openWhatsApp(
		product
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
			return false;
		}

		const message =
			buildMessage(
				product
			);

		if (!message) {
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
	 * Handle direct WhatsApp click.
	 *
	 * quick-checkout.js does not handle this selector,
	 * therefore this action never opens the drawer.
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

		event.preventDefault();

		/*
		 * Prevent any other generic product-page
		 * handler from treating this button as an
		 * Order/Checkout action.
		 */
		event.stopImmediatePropagation();

		setMessage(
			button,
			''
		);

		const root =
			getRoot(
				button
			);

		if (
			!(
				root instanceof
					HTMLElement
			)
		) {
			setMessage(
				button,
				getText(
					'unavailable',
					'WhatsApp ordering is currently unavailable.'
				)
			);

			return;
		}

		const product =
			getProductData(
				root
			);

		/*
		 * Variable product must have a concrete
		 * selected variation.
		 */
		if (
			'variable' ===
				product.productType &&
			product.variationId <= 0
		) {
			setMessage(
				button,
				String(
					button.dataset
						.variationRequired ||
					getText(
						'variationRequired',
						'Please select product options first.'
					)
				)
			);

			return;
		}

		if (
			!normalizeText(
				product.title
			) ||
			!normalizeText(
				product.url
			)
		) {
			setMessage(
				button,
				getText(
					'unavailable',
					'WhatsApp ordering is currently unavailable.'
				)
			);

			return;
		}

		const opened =
			openWhatsApp(
				product
			);

		if (!opened) {
			setMessage(
				button,
				getText(
					'unavailable',
					'WhatsApp ordering is currently unavailable.'
				)
			);
		}
	}

	/**
	 * Public API.
	 */
	window.eilmoCfQuickWhatsAppOrdering = {
		getProductData:
			getProductData,

		buildMessage:
			buildMessage,

		open:
			openWhatsApp,
	};

	document.addEventListener(
		'click',
		handleClick
	);
})();