/**
 * Eilmo Checkout Flow - Single Product Quick Checkout.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		actions:
			'[data-eilmo-quick-checkout-actions]',

		/*
		 * IMPORTANT:
		 *
		 * Only the Order Now button is allowed to open
		 * the Quick Checkout drawer.
		 *
		 * WhatsApp is intentionally excluded from this
		 * selector and is handled separately.
		 */
		trigger:
			'[data-eilmo-quick-checkout-trigger="order"]',

		productMessage:
			'[data-eilmo-quick-checkout-product-message]',

		root:
			'[data-eilmo-quick-checkout]',

		panel:
			'[data-eilmo-quick-checkout-panel]',

		close:
			'[data-eilmo-quick-checkout-close]',

		change:
			'[data-eilmo-quick-checkout-change]',

		checkout:
			'[data-eilmo-quick-checkout-context][data-eilmo-checkout]',

		adapter:
			'[data-eilmo-quick-checkout-product-adapter]',

		quantityAdapter:
			'[data-eilmo-quantity-input]',

		image:
			'[data-eilmo-quick-checkout-image]',

		productTitle:
			'[data-eilmo-quick-checkout-product-title]',

		variation:
			'[data-eilmo-quick-checkout-variation]',

		quantity:
			'[data-eilmo-quick-checkout-quantity]',

		price:
			'[data-eilmo-quick-checkout-price]',

		submit:
			'[data-eilmo-quick-checkout-submit]',

		submitText:
			'[data-eilmo-quick-checkout-submit-text]',

		orderError:
			'[data-eilmo-order-error]',

		summaryShell:
			'[data-eilmo-summary-shell]',

		productForm:
			'form.cart',

		variationForm:
			'form.variations_form',

		variationId:
			'input.variation_id, input[name="variation_id"]',

		pageQuantity:
			'input.qty',

		productTitlePage:
			'.product_title',

		variationPrice:
			'.woocommerce-variation-price .price',

		mainPrice:
			'.summary .price',

		galleryImage:
			'.woocommerce-product-gallery__image.flex-active-slide img, .woocommerce-product-gallery__wrapper img',
	};

	const foundVariations =
		new WeakMap();

	let activeRoot =
		null;

	function toPositiveInteger(
		value,
		fallback
	) {
		const parsed =
			Number.parseInt(
				String(
					value ?? ''
				),
				10
			);

		if (
			Number.isFinite(
				parsed
			) &&
			parsed > 0
		) {
			return parsed;
		}

		const fallbackValue =
			Number.parseInt(
				String(
					fallback ?? 1
				),
				10
			);

		return (
			Number.isFinite(
				fallbackValue
			) &&
			fallbackValue > 0
		)
			? fallbackValue
			: 1;
	}

	function toNumber(value) {
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

	function getRoot(element) {

		if (
			!(
				element instanceof
					Element
			)
		) {
			return null;
		}

		if (
			element.matches(
				SELECTORS.root
			)
		) {
			return element;
		}

		const root =
			element.closest(
				SELECTORS.root
			);

		return root instanceof
			HTMLElement
				? root
				: null;
	}

	function getCheckout(root) {

		const checkout =
			root.querySelector(
				SELECTORS.checkout
			);

		return checkout instanceof
			HTMLElement
				? checkout
				: null;
	}

	function getRootFromTrigger(
		trigger
	) {

		const controls =
			trigger.getAttribute(
				'aria-controls'
			);

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
			trigger.closest(
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

	function getProductForm(root) {

		const product =
			root.closest(
				'.product'
			);

		if (
			product instanceof
				Element
		) {

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

		const fallback =
			document.querySelector(
				SELECTORS.productForm
			);

		return fallback instanceof
			HTMLFormElement
				? fallback
				: null;
	}

	function getVariationForm(form) {

		if (
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return null;
		}

		return form.matches(
			SELECTORS.variationForm
		)
			? form
			: null;
	}

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

	function findVariationData(
		form,
		variationId
	) {

		if (
			variationId <= 0 ||
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return null;
		}

		const cached =
			foundVariations.get(
				form
			);

		if (
			cached &&
			Number.parseInt(
				String(
					cached.variation_id ||
						0
				),
				10
			) ===
				variationId
		) {
			return cached;
		}

		const variations =
			getVariationCollection(
				form
			);

		return (
			variations.find(
				function (
					variation
				) {

					return (
						variation &&
						typeof variation ===
							'object' &&
						Number.parseInt(
							String(
								variation
									.variation_id ||
									0
							),
							10
						) ===
							variationId
					);
				}
			) ||
			null
		);
	}

	function getVariationText(form) {

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
				'.variations select'
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
							? String(
								option
									.textContent ||
									''
							).trim()
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

	function getProductImage(
		root,
		variation
	) {

		if (
			variation &&
			variation.image &&
			typeof variation.image ===
				'object'
		) {

			const source =
				String(
					variation.image
						.full_src ||
					variation.image
						.src ||
					''
				).trim();

			if (source) {
				return source;
			}
		}

		const product =
			root.closest(
				'.product'
			);

		const image =
			product instanceof
				Element
					? product.querySelector(
						SELECTORS
							.galleryImage
					)
					: document.querySelector(
						SELECTORS
							.galleryImage
					);

		if (
			image instanceof
				HTMLImageElement &&
			image.currentSrc
		) {
			return image.currentSrc;
		}

		return image instanceof
			HTMLImageElement
				? image.src
				: '';
	}

	function getProductTitle(root) {

		const product =
			root.closest(
				'.product'
			);

		const title =
			product instanceof
				Element
					? product.querySelector(
						SELECTORS
							.productTitlePage
					)
					: document.querySelector(
						SELECTORS
							.productTitlePage
					);

		if (
			title instanceof
				HTMLElement &&
			title.textContent
		) {
			return title.textContent
				.trim();
		}

		const preview =
			root.querySelector(
				SELECTORS.productTitle
			);

		return preview instanceof
			HTMLElement
				? String(
					preview.textContent ||
						''
				).trim()
				: '';
	}

	function getPriceHtml(
		root,
		variation,
		variationForm
	) {

		if (
			variation &&
			typeof variation.price_html ===
				'string' &&
			variation.price_html.trim()
		) {
			return variation.price_html;
		}

		if (
			variationForm instanceof
				HTMLFormElement
		) {

			const variationPrice =
				variationForm
					.querySelector(
						SELECTORS
							.variationPrice
					);

			if (
				variationPrice instanceof
					HTMLElement &&
				variationPrice.innerHTML
					.trim()
			) {
				return variationPrice
					.innerHTML;
			}
		}

		const product =
			root.closest(
				'.product'
			);

		const mainPrice =
			product instanceof
				Element
					? product.querySelector(
						SELECTORS.mainPrice
					)
					: document.querySelector(
						SELECTORS.mainPrice
					);

		if (
			mainPrice instanceof
				HTMLElement &&
			mainPrice.innerHTML.trim()
		) {
			return mainPrice.innerHTML;
		}

		const preview =
			root.querySelector(
				SELECTORS.price
			);

		return preview instanceof
			HTMLElement
				? preview.innerHTML
				: '';
	}

	function captureProductState(
		root
	) {

		const productId =
			toPositiveInteger(
				root.dataset.productId,
				0
			);

		const productType =
			String(
				root.dataset.productType ||
					''
			).trim();

		const form =
			getProductForm(
				root
			);

		const variationForm =
			getVariationForm(
				form
			);

		const variationInput =
			variationForm instanceof
				HTMLFormElement
					? variationForm
						.querySelector(
							SELECTORS
								.variationId
						)
					: null;

		const variationId =
			variationInput instanceof
				HTMLInputElement
					? Number.parseInt(
						String(
							variationInput
								.value ||
								0
						),
						10
					) || 0
					: 0;

		const quantityInput =
			form instanceof
				HTMLFormElement
					? form.querySelector(
						SELECTORS
							.pageQuantity
					)
					: null;

		const quantity =
			quantityInput instanceof
				HTMLInputElement
					? toPositiveInteger(
						quantityInput.value,
						1
					)
					: 1;

		const variation =
			findVariationData(
				variationForm,
				variationId
			);

		let price =
			0;

		if (
			variation &&
			Number.isFinite(
				Number(
					variation
						.display_price
				)
			)
		) {

			price =
				toNumber(
					variation
						.display_price
				);

		} else {

			price =
				toNumber(
					root.dataset
						.basePrice
				);
		}

		return {
			productId:
				productId,

			productType:
				productType,

			variationId:
				Math.max(
					0,
					variationId
				),

			quantity:
				quantity,

			price:
				price,

			priceHtml:
				getPriceHtml(
					root,
					variation,
					variationForm
				),

			title:
				getProductTitle(
					root
				),

			variationText:
				getVariationText(
					variationForm
				),

			image:
				getProductImage(
					root,
					variation
				),

			form:
				form,

			variationForm:
				variationForm,
		};
	}

	function validateProductState(
		state
	) {

		if (
			state.productId <= 0
		) {
			return false;
		}

		if (
			state.productType ===
				'variable' &&
			state.variationId <= 0
		) {
			return false;
		}

		return state.quantity > 0;
	}

	function showProductMessage(
		root,
		message
	) {

		const actions =
			root.previousElementSibling;

		if (
			!(
				actions instanceof
					HTMLElement
			) ||
			!actions.matches(
				SELECTORS.actions
			)
		) {
			return;
		}

		const element =
			actions.querySelector(
				SELECTORS.productMessage
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

	function updateSelectionPreview(
		root,
		state
	) {

		const image =
			root.querySelector(
				SELECTORS.image
			);

		if (
			image instanceof
				HTMLImageElement &&
			state.image
		) {
			image.src =
				state.image;

			image.alt =
				state.title ||
				image.alt;
		}

		const title =
			root.querySelector(
				SELECTORS.productTitle
			);

		if (
			title instanceof
				HTMLElement &&
			state.title
		) {
			title.textContent =
				state.title;
		}

		const variation =
			root.querySelector(
				SELECTORS.variation
			);

		if (
			variation instanceof
				HTMLElement
		) {

			variation.textContent =
				state.variationText ||
					'';

			variation.hidden =
				!state.variationText;
		}

		const quantity =
			root.querySelector(
				SELECTORS.quantity
			);

		if (
			quantity instanceof
				HTMLElement
		) {
			quantity.textContent =
				String(
					state.quantity
				);
		}

		const price =
			root.querySelector(
				SELECTORS.price
			);

		if (
			price instanceof
				HTMLElement &&
			state.priceHtml
		) {
			price.innerHTML =
				state.priceHtml;
		}
	}

	function updateProductAdapter(
		checkout,
		state
	) {

		const adapter =
			checkout.querySelector(
				SELECTORS.adapter
			);

		if (
			!(
				adapter instanceof
					HTMLElement
			)
		) {
			return;
		}

		const input =
			adapter.querySelector(
				SELECTORS.quantityAdapter
			);

		const productId =
			String(
				state.productId ||
					0
			);

		const variationId =
			String(
				state.variationId ||
					0
			);

		const price =
			String(
				toNumber(
					state.price
				)
			);

		adapter.dataset.productId =
			productId;

		adapter.dataset.parentProductId =
			productId;

		adapter.dataset.parentId =
			productId;

		adapter.dataset.variationId =
			variationId;

		adapter.dataset.price =
			price;

		adapter.dataset.productPrice =
			price;
		adapter.dataset.itemName = state.title || '';
		adapter.dataset.itemVariation = state.variationText || '';
		adapter.dataset.itemImage = state.image || '';

		if (
			input instanceof
				HTMLInputElement
		) {

			input.value =
				String(
					state.quantity
				);

			input.dataset.productId =
				productId;

			input.dataset.parentProductId =
				productId;

			input.dataset.parentId =
				productId;

			input.dataset.variationId =
				variationId;

			input.dataset.price =
				price;
		}
	}

	function refreshModules(
		checkout
	) {

		const checkoutApi =
			window.eilmoCfCheckout;

		if (
			checkoutApi &&
			typeof checkoutApi.refresh ===
				'function'
		) {

			checkoutApi.refresh(
				checkout
			);

			return;
		}

		[
			window.eilmoCfComboOffers,
			window.eilmoCfOrderBump,
			window.eilmoCfDiscount,
			window.eilmoCfDelivery,
			window.eilmoCfAdvancePayment,
			window.eilmoCfPayment,
			window.eilmoCfSummary,
			window.eilmoCfCoupon,
		].forEach(
			function (api) {

				if (
					api &&
					typeof api.refresh ===
						'function'
				) {
					api.refresh(
						checkout
					);
				}
			}
		);
	}

	function synchronizeProduct(
		root,
		suppliedState
	) {

		const state =
			suppliedState &&
			typeof suppliedState ===
				'object'
				? suppliedState
				: captureProductState(
					root
				);

		updateSelectionPreview(
			root,
			state
		);

		const checkout =
			getCheckout(
				root
			);

		if (checkout) {

			updateProductAdapter(
				checkout,
				state
			);

			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:quantityChange',
					{
						bubbles:
							true,

						detail: {
							checkout:
								checkout,

							quickCheckout:
								true,

							product:
								state,
						},
					}
				)
			);

			refreshModules(
				checkout
			);
		}

		return state;
	}

	function applyDesktopWidth(root) {

		const panel =
			root.querySelector(
				SELECTORS.panel
			);

		if (
			!(
				panel instanceof
					HTMLElement
			)
		) {
			return;
		}

		const width =
			Math.max(
				1040,
				Math.min(
					1200,
					toPositiveInteger(
						root.dataset
							.desktopWidth,
						1180
					)
				)
			);

		panel.style.setProperty(
			'--eilmo-cf-quick-checkout-width',
			width + 'px'
		);
	}

	/**
	 * Drawer always operates in Order mode.
	 *
	 * WhatsApp is not a drawer mode.
	 *
	 * @param {HTMLElement} root Root.
	 *
	 * @return {void}
	 */
	function setOrderMode(root) {

		root.dataset.mode =
			'order';

		const checkout =
			getCheckout(
				root
			);

		if (checkout) {
			checkout.dataset
				.quickCheckoutMode =
					'order';
		}

		const button =
			root.querySelector(
				SELECTORS.submit
			);

		const text =
			root.querySelector(
				SELECTORS.submitText
			);

		const label =
			String(
				root.dataset
					.orderButtonText ||
					'Place Order'
			);

		if (
			button instanceof
				HTMLButtonElement
		) {

			button.removeAttribute(
				'data-eilmo-order-submit'
			);

			button.dataset.label =
				label;
		}

		if (
			text instanceof
				HTMLElement
		) {
			text.textContent =
				label;
		}
	}

	function submitDirectCheckout(
		root,
		trigger
	) {

		const state =
			captureProductState(
				root
			);

		if (
			!validateProductState(
				state
			)
		) {

			showProductMessage(
				root,
				String(
					trigger.dataset
						.variationRequired ||
						root.dataset
							.variationRequired ||
						'Please select product options first.'
				)
			);

			return;
		}

		if (
			!(
				state.form instanceof
					HTMLFormElement
			)
		) {

			showProductMessage(
				root,
				String(
					root.dataset
						.checkoutNotReady ||
						'Checkout is not ready. Please try again.'
				)
			);

			return;
		}

		const nativeButton =
			state.form.querySelector(
				'.single_add_to_cart_button, button[name="add-to-cart"]'
			);

		if (
			!(
				nativeButton instanceof
					HTMLButtonElement
			) ||
			nativeButton.disabled
		) {

			showProductMessage(
				root,
				String(
					trigger.dataset
						.variationRequired ||
						root.dataset
							.variationRequired ||
						'Please select product options first.'
				)
			);

			return;
		}

		showProductMessage(
			root,
			''
		);

		let marker =
			state.form.querySelector(
				'input[name="eilmo_cf_direct_checkout"]'
			);

		if (
			!(
				marker instanceof
					HTMLInputElement
			)
		) {
			marker =
				document.createElement(
					'input'
				);

			marker.type =
				'hidden';

			marker.name =
				'eilmo_cf_direct_checkout';

			state.form.appendChild(
				marker
			);
		}

		marker.value =
			'yes';

		trigger.disabled =
			true;

		trigger.setAttribute(
			'aria-busy',
			'true'
		);

		if (
			typeof state.form.requestSubmit ===
				'function'
		) {
			state.form.requestSubmit(
				nativeButton
			);
			return;
		}

		if (
			nativeButton.name &&
			!state.form.querySelector(
				'input[data-eilmo-native-submit-value]'
			)
		) {
			const submitValue =
				document.createElement(
					'input'
				);

			submitValue.type =
				'hidden';

			submitValue.name =
				nativeButton.name;

			submitValue.value =
				nativeButton.value;

			submitValue.setAttribute(
				'data-eilmo-native-submit-value',
				'yes'
			);

			state.form.appendChild(
				submitValue
			);
		}

		state.form.submit();
	}

	function openCheckout(
		root,
		trigger
	) {

		const state =
			captureProductState(
				root
			);

		if (
			!validateProductState(
				state
			)
		) {

			showProductMessage(
				root,
				String(
					(
						trigger instanceof
							HTMLElement &&
						trigger.dataset
							.variationRequired
					)
						? trigger.dataset
							.variationRequired
						: root.dataset
							.variationRequired ||
							'Please select product options first.'
				)
			);

			return;
		}

		showProductMessage(
			root,
			''
		);

		/*
		 * Quick Checkout drawer is Order-only.
		 */
		setOrderMode(
			root
		);

		applyDesktopWidth(
			root
		);

		synchronizeProduct(
			root,
			state
		);

		root.hidden =
			false;

		root.setAttribute(
			'aria-hidden',
			'false'
		);

		root.dataset.open =
			'yes';

		root.classList.remove(
			'is-closing'
		);

		activeRoot =
			root;

		if (
			trigger instanceof
				HTMLElement
		) {
			root
				._eilmoQuickCheckoutTrigger =
					trigger;
		}

		document.body
			.classList.add(
				'eilmo-cf-quick-checkout-open'
			);

		window.requestAnimationFrame(
			function () {

				root.classList.add(
					'is-open'
				);

				const close =
					root.querySelector(
						'.eilmo-cf-quick-checkout__close'
					);

				if (
					close instanceof
						HTMLButtonElement
				) {
					close.focus(
						{
							preventScroll:
								true,
						}
					);
				}
			}
		);
	}

	function closeCheckout(
		root,
		restoreFocus
	) {

		if (
			root.hidden ||
			root.dataset.open !==
				'yes'
		) {
			return;
		}

		const checkout =
			getCheckout(
				root
			);

		if (
			checkout &&
			window.eilmoCfSummary &&
			typeof window
				.eilmoCfSummary
				.close ===
					'function'
		) {
			window
				.eilmoCfSummary
				.close(
					checkout
				);
		}

		root.dataset.open =
			'no';

		root.classList.remove(
			'is-open'
		);

		root.classList.add(
			'is-closing'
		);

		root.setAttribute(
			'aria-hidden',
			'true'
		);

		window.setTimeout(
			function () {

				if (
					root.dataset.open ===
						'yes'
				) {
					return;
				}

				root.hidden =
					true;

				root.classList.remove(
					'is-closing'
				);

				if (
					activeRoot ===
						root
				) {
					activeRoot =
						null;
				}

				if (!activeRoot) {

					document.body
						.classList.remove(
							'eilmo-cf-quick-checkout-open'
						);
				}
			},
			190
		);

		if (
			restoreFocus &&
			root
				._eilmoQuickCheckoutTrigger instanceof
					HTMLElement
		) {
			root
				._eilmoQuickCheckoutTrigger
				.focus();
		}
	}

	function changeSelection(root) {

		const state =
			captureProductState(
				root
			);

		closeCheckout(
			root,
			false
		);

		const target =
			state.variationForm instanceof
				HTMLFormElement
					? state.variationForm
					: state.form;

		if (
			!(
				target instanceof
					HTMLElement
			)
		) {
			return;
		}

		window.setTimeout(
			function () {

				try {

					target.scrollIntoView(
						{
							behavior:
								'smooth',

							block:
								'center',
						}
					);

				} catch (error) {

					target.scrollIntoView();
				}
			},
			200
		);
	}

	function showCheckoutError(
		checkout,
		message
	) {

		const error =
			checkout.querySelector(
				SELECTORS.orderError
			);

		if (
			!(
				error instanceof
					HTMLElement
			)
		) {
			return;
		}

		error.textContent =
			String(
				message ||
					''
			);

		error.hidden =
			false;

		try {

			error.scrollIntoView(
				{
					behavior:
						'smooth',

					block:
						'center',
				}
			);

		} catch (exception) {

			error.scrollIntoView();
		}
	}

	async function submitOrderMode(
		root,
		button
	) {

		const state =
			captureProductState(
				root
			);

		if (
			!validateProductState(
				state
			)
		) {

			closeCheckout(
				root,
				false
			);

			showProductMessage(
				root,
				String(
					root.dataset
						.variationRequired ||
						'Please select product options first.'
				)
			);

			return;
		}

		synchronizeProduct(
			root,
			state
		);

		const checkout =
			getCheckout(
				root
			);

		if (!checkout) {
			return;
		}

		const api =
			window.eilmoCfCheckout;

		if (
			!api ||
			typeof api.submit !==
				'function'
		) {

			showCheckoutError(
				checkout,
				String(
					root.dataset
						.checkoutNotReady ||
						'Checkout is not ready. Please refresh the page and try again.'
				)
			);

			return;
		}

		button.dataset.label =
			String(
				root.dataset
					.orderButtonText ||
					'Place Order'
			);

		/*
		 * checkout.js needs this selector while it
		 * handles loading state.
		 *
		 * It is attached only during the direct
		 * existing checkout API submission.
		 */
		button.setAttribute(
			'data-eilmo-order-submit',
			''
		);

		try {

			await api.submit(
				checkout
			);

		} finally {

			button.removeAttribute(
				'data-eilmo-order-submit'
			);
		}
	}

	function handleSubmit(
		event,
		button,
		root
	) {

		event.preventDefault();

		event.stopImmediatePropagation();

		/*
		 * Drawer submission is always a normal order.
		 */
		void submitOrderMode(
			root,
			button
		);
	}

	function synchronizeRootsForForm(
		form
	) {

		document
			.querySelectorAll(
				SELECTORS.root
			)
			.forEach(
				function (root) {

					if (
						!(
							root instanceof
								HTMLElement
						)
					) {
						return;
					}

					const productForm =
						getProductForm(
							root
						);

					if (
						productForm !==
							form
					) {
						return;
					}

					const state =
						captureProductState(
							root
						);

					if (
						validateProductState(
							state
						)
					) {
						showProductMessage(
							root,
							''
						);
					}

					synchronizeProduct(
						root,
						state
					);
				}
			);
	}

	function initializeVariationListeners() {

		if (
			!window.jQuery ||
			typeof window.jQuery !==
				'function'
		) {
			return;
		}

		window
			.jQuery(
				document
			)
			.on(
				'found_variation',
				SELECTORS.variationForm,
				function (
					event,
					variation
				) {

					if (
						this instanceof
							HTMLFormElement &&
						variation &&
						typeof variation ===
							'object'
					) {

						foundVariations.set(
							this,
							variation
						);

						synchronizeRootsForForm(
							this
						);
					}
				}
			);

		window
			.jQuery(
				document
			)
			.on(
				'hide_variation reset_data',
				SELECTORS.variationForm,
				function () {

					if (
						this instanceof
							HTMLFormElement
					) {

						foundVariations.delete(
							this
						);

						synchronizeRootsForForm(
							this
						);
					}
				}
			);
	}

	function handleClick(event) {

		if (
			!(
				event.target instanceof
					Element
			)
		) {
			return;
		}

		/*
		 * --------------------------------------
		 * Order Now -> Quick Checkout drawer
		 * --------------------------------------
		 *
		 * SELECTORS.trigger only matches:
		 *
		 * data-eilmo-quick-checkout-trigger="order"
		 *
		 * WhatsApp will never enter this block.
		 */
		const trigger =
			event.target.closest(
				SELECTORS.trigger
			);

		if (
			trigger instanceof
				HTMLButtonElement
		) {

			const root =
				getRootFromTrigger(
					trigger
				);

			if (!root) {
				return;
			}

			event.preventDefault();

			event.stopPropagation();

			if (
				trigger.dataset
					.eilmoDirectCheckout ===
					'yes'
			) {
				submitDirectCheckout(
					root,
					trigger
				);
			} else {
				openCheckout(
					root,
					trigger
				);
			}

			return;
		}

		const submit =
			event.target.closest(
				SELECTORS.submit
			);

		if (
			submit instanceof
				HTMLButtonElement
		) {

			const root =
				getRoot(
					submit
				);

			if (root) {

				handleSubmit(
					event,
					submit,
					root
				);
			}

			return;
		}

		const close =
			event.target.closest(
				SELECTORS.close
			);

		if (close) {

			const root =
				getRoot(
					close
				);

			if (root) {

				event.preventDefault();

				closeCheckout(
					root,
					true
				);
			}

			return;
		}

		const change =
			event.target.closest(
				SELECTORS.change
			);

		if (change) {

			const root =
				getRoot(
					change
				);

			if (root) {

				event.preventDefault();

				changeSelection(
					root
				);
			}
		}
	}

	function handleProductChange(
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

		if (
			!event.target.matches(
				'input.qty, .variations select, input.variation_id, input[name="variation_id"]'
			)
		) {
			return;
		}

		const form =
			event.target.closest(
				SELECTORS.productForm
			);

		if (
			!(
				form instanceof
					HTMLFormElement
			)
		) {
			return;
		}

		window.requestAnimationFrame(
			function () {

				synchronizeRootsForForm(
					form
				);
			}
		);
	}

	function handleKeydown(event) {

		if (
			event.key !==
				'Escape' ||
			!(
				activeRoot instanceof
					HTMLElement
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				activeRoot
			);

		const summaryShell =
			checkout
				? checkout.querySelector(
					SELECTORS.summaryShell
				)
				: null;

		/*
		 * On mobile, Escape closes Summary first.
		 */
		if (
			summaryShell instanceof
				HTMLElement &&
			summaryShell.dataset
				.summaryOpen ===
					'yes'
		) {

			if (
				window.eilmoCfSummary &&
				typeof window
					.eilmoCfSummary
					.close ===
						'function'
			) {

				event.preventDefault();

				window
					.eilmoCfSummary
					.close(
						checkout
					);
			}

			return;
		}

		event.preventDefault();

		closeCheckout(
			activeRoot,
			true
		);
	}

	function initializeRoot(root) {

		if (
			root.dataset
				.eilmoQuickCheckoutInitialized ===
					'yes'
		) {
			return;
		}

		root.dataset
			.eilmoQuickCheckoutInitialized =
				'yes';

		applyDesktopWidth(
			root
		);

		/*
		 * Drawer is always Order mode.
		 */
		setOrderMode(
			root
		);

		const state =
			captureProductState(
				root
			);

		updateSelectionPreview(
			root,
			state
		);

		const checkout =
			getCheckout(
				root
			);

		if (checkout) {

			updateProductAdapter(
				checkout,
				state
			);

			refreshModules(
				checkout
			);
		}
	}

	function initialize() {

		document
			.querySelectorAll(
				SELECTORS.root
			)
			.forEach(
				function (root) {

					if (
						root instanceof
							HTMLElement
					) {
						initializeRoot(
							root
						);
					}
				}
			);
	}

	/**
	 * Public API.
	 *
	 * Quick Checkout drawer is intentionally Order-only.
	 */
	window.eilmoCfQuickCheckout = {
		open:
			function (root) {

				if (
					root instanceof
						HTMLElement
				) {

					openCheckout(
						root,
						null
					);
				}
			},

		close:
			function (root) {

				if (
					root instanceof
						HTMLElement
				) {

					closeCheckout(
						root,
						false
					);
				}
			},

		getProductState:
			captureProductState,

		synchronize:
			synchronizeProduct,
	};

	document.addEventListener(
		'click',
		handleClick
	);

	document.addEventListener(
		'change',
		handleProductChange
	);

	// Summary quantity controls edit the hidden adapter. Keep the visible
	// selection and native product form in sync without redispatching a loop.
	document.addEventListener('eilmo:quantityChange', function (event) {
		const detail = event.detail || {};
		if (!(detail.item instanceof Element) || !detail.item.matches(SELECTORS.adapter)) return;
		const root = detail.checkout instanceof Element ? detail.checkout.closest(SELECTORS.root) : null;
		if (!root) return;
		const value = String(Math.max(0, parseInt(detail.quantity, 10) || 0));
		const badge = root.querySelector(SELECTORS.quantity);
		if (badge) badge.textContent = value;
		const form = getProductForm(root);
		const input = form ? form.querySelector(SELECTORS.pageQuantity) : null;
		if (input instanceof HTMLInputElement) input.value = value;
	});

	document.addEventListener(
		'input',
		handleProductChange
	);

	document.addEventListener(
		'keydown',
		handleKeydown,
		true
	);

	initializeVariationListeners();

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
