/**
 * Eilmo Checkout Flow payment methods.
 *
 * Handles:
 * - Eilmo manual payment methods.
 * - WooCommerce native payment gateways.
 * - Payment selection.
 * - Manual transaction IDs.
 * - WooCommerce classic gateway events.
 * - Native payment-box visibility.
 * - Checkout state synchronization.
 * - Payment Option synchronization.
 * - Cash on Delivery virtual payment data.
 * - Frontend validation.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		section:
			'[data-eilmo-payment-methods]',

		paymentType:
			'[data-eilmo-payment-type]',

		method:
			'[data-eilmo-payment-method]',

		radio:
			'[data-eilmo-payment-radio]',

		details:
			'[data-eilmo-payment-details]',

		nativeBox:
			'[data-eilmo-native-payment-box]',

		gatewayFields:
			'[data-eilmo-gateway-fields]',

		transaction:
			'[data-eilmo-payment-transaction]',

		transactionWrap:
			'[data-eilmo-payment-transaction-wrap]',

		proof:
			'[data-eilmo-payment-proof]',

		proofToken:
			'[data-eilmo-payment-proof-token]',

		proofStatus:
			'[data-eilmo-payment-proof-status]',

		proofError:
			'[data-eilmo-payment-proof-error]',

		fieldError:
			'[data-eilmo-payment-field-error]',

		formError:
			'[data-eilmo-payment-form-error]',
	};

	/** Upload a selected manual-payment screenshot and store its temporary token. */
	async function uploadPaymentProof(input) {
		const method = input.closest(SELECTORS.method);
		if (!(window.eilmoCfDom.isElement(method)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
			return;
		}

		const file = input.files && input.files[0];
		const tokenInput = method.querySelector(SELECTORS.proofToken);
		const status = method.querySelector(SELECTORS.proofStatus);
		const errorBox = method.querySelector(SELECTORS.proofError);
		const config = window.eilmoCf && window.eilmoCf.paymentProof ? window.eilmoCf.paymentProof : {};
		const maxMb = Math.max(1, Number(method.dataset.proofMaxMb || 5));

		if (window.eilmoCfDom.isElement(tokenInput, 'INPUT')) {
			tokenInput.value = '';
		}
		setError(errorBox, '');

		if (!file) {
			method.dataset.proofUploading = 'no';
			return;
		}

		if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > maxMb * 1024 * 1024) {
			input.value = '';
			setError(errorBox, `Choose a JPG, PNG or WebP image up to ${maxMb} MB.`);
			return;
		}

		method.dataset.proofUploading = 'yes';
		input.disabled = true;
		if (status) {
			status.textContent = config.uploading || 'Uploading screenshot…';
		}

		try {
			const body = new FormData();
			body.append('action', config.action || 'eilmo_cf_upload_payment_proof');
			body.append('nonce', config.nonce || '');
			body.append('method_key', method.dataset.methodKey || '');
			body.append('proof', file, file.name);
			const response = await window.fetch((window.eilmoCf && window.eilmoCf.ajaxUrl) || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			});
			const result = await response.json();
			if (!response.ok || !result.success || !result.data || !result.data.token) {
				throw new Error(result.data && result.data.message ? result.data.message : (config.failed || 'Screenshot upload failed.'));
			}
			if (window.eilmoCfDom.isElement(tokenInput, 'INPUT')) {
				tokenInput.value = String(result.data.token);
			}
			if (status) {
				status.textContent = `${result.data.name || file.name} — ${result.data.message || config.uploaded || 'Uploaded'}`;
			}
		} catch (error) {
			input.value = '';
			setError(errorBox, error instanceof Error ? error.message : (config.failed || 'Screenshot upload failed.'));
		} finally {
			method.dataset.proofUploading = 'no';
			input.disabled = false;
			const checkout = getCheckout(method);
			if (checkout) {
				syncCheckout(checkout, true);
			}
		}
	}

	/**
	 * Native gateway refresh timers.
	 *
	 * @type {WeakMap<HTMLElement, number>}
	 */
	const refreshTimers =
		new WeakMap();

	/**
	 * Initialized payment sections.
	 *
	 * @type {WeakSet<HTMLElement>}
	 */
	const initializedSections =
		new WeakSet();

	/**
	 * Get Eilmo checkout wrapper.
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

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
				? checkout
				: null;
	}

	/**
	 * Get payment section.
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
	 * Get selected top-level Payment Option.
	 *
	 * Supported values:
	 *
	 * - cash_on_delivery
	 * - advance
	 * - full
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentType(checkout) {
		const checked =
			checkout.querySelector(
				`${SELECTORS.paymentType}:checked`
			);

		if (
			window.eilmoCfDom.isElement(checked, 'INPUT')
		) {
			const value =
				String(
					checked.value ||
					''
				);

			if (
				[
					'cash_on_delivery',
					'advance',
					'full',
				].includes(
					value
				)
			) {
				return value;
			}
		}

		const stored =
			String(
				checkout.dataset
					.paymentType ||
				''
			);

		if (
			[
				'cash_on_delivery',
				'advance',
				'full',
			].includes(
				stored
			)
		) {
			return stored;
		}

		return '';
	}

	/**
	 * Is Cash on Delivery the selected Payment Option?
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isCashOnDelivery(
		checkout
	) {
		return (
			'cash_on_delivery' ===
				getPaymentType(
					checkout
				)
		);
	}

	/**
	 * Determine whether a rendered Payment Method is COD.
	 *
	 * COD is no longer a Payment Method choice. It is now
	 * a top-level Payment Option. This helper also excludes
	 * WooCommerce's native "cod" gateway as a defensive
	 * fallback until the PHP renderer has removed it.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {boolean}
	 */
	function isCashOnDeliveryMethod(
		method
	) {
		if (
			!(
				window.eilmoCfDom.isElement(method)
			)
		) {
			return false;
		}

		const radio =
			method.querySelector(
				SELECTORS.radio
			);

		const methodValue =
			String(
				method.dataset
					.methodValue ||
				''
			);

		const methodKey =
			String(
				method.dataset
					.methodKey ||
				''
			);

		const gatewayId =
			String(
				method.dataset
					.gatewayId ||
				''
			);

		const nativeValue =
			window.eilmoCfDom.isElement(radio, 'INPUT')
					? String(
						radio.value ||
							''
					)
					: String(
						method.dataset
							.nativePaymentValue ||
							''
					);

		return (
			'eilmo_cash_on_delivery' ===
				methodValue ||
			'cash_on_delivery' ===
				methodKey ||
			'wc__cod' ===
				methodValue ||
			'cod' ===
				gatewayId ||
			'cod' ===
				nativeValue
		);
	}

	/**
	 * Permanently exclude COD from the Payment Method list.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function excludeCashOnDeliveryMethods(
		section
	) {
		section
			.querySelectorAll(
				SELECTORS.method
			)
			.forEach(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						) ||
						!isCashOnDeliveryMethod(
							method
						)
					) {
						return;
					}

					method.hidden =
						true;

					method.setAttribute(
						'aria-hidden',
						'true'
					);

					method.dataset
						.paymentOptionExcluded =
							'yes';

					const radio =
						method.querySelector(
							SELECTORS.radio
						);

					if (
						window.eilmoCfDom.isElement(radio, 'INPUT')
					) {
						radio.checked =
							false;

						radio.disabled =
							true;
					}

					const details =
						method.querySelector(
							SELECTORS.details
						);

					if (
						window.eilmoCfDom.isElement(details)
					) {
						hideDetails(
							details
						);
					}
				}
			);
	}

	/**
	 * Show / hide the Payment Method section according
	 * to the currently selected top-level Payment Option.
	 *
	 * Cash on Delivery:
	 * - Hide Payment Methods.
	 * - Payment validation is bypassed.
	 * - getData() exposes virtual Eilmo COD data.
	 *
	 * Advance / Full:
	 * - Show Payment Methods.
	 * - COD methods remain excluded.
	 * - A non-COD method is required according to section
	 *   configuration.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function syncPaymentOptionState(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		excludeCashOnDeliveryMethods(
			section
		);

		if (
			isCashOnDelivery(
				checkout
			)
		) {
			clearErrors(
				section
			);

			section.hidden =
				true;

			section.setAttribute(
				'hidden',
				''
			);

			section.setAttribute(
				'aria-hidden',
				'true'
			);

			section.dataset
				.paymentOptionHidden =
					'yes';

			section
				.querySelectorAll(
					SELECTORS.details
				)
				.forEach(
					function (details) {
						if (
							window.eilmoCfDom.isElement(details)
						) {
							hideDetails(
								details
							);
						}
					}
				);

			const timer =
				refreshTimers.get(
					checkout
				);

			if (timer) {
				window.clearTimeout(
					timer
				);

				refreshTimers.delete(
					checkout
				);
			}

			return;
		}

		section.hidden =
			false;

		section.removeAttribute(
			'hidden'
		);

		section.removeAttribute(
			'aria-hidden'
		);

		delete section.dataset
			.paymentOptionHidden;

		ensureInitialSelection(
			section
		);

		updateMethodState(
			section
		);
	}

	/**
	 * Get selected payment radio.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getSelectedRadio(section) {
		const radio =
			section.querySelector(
				`${SELECTORS.radio}:checked`
			);

		return window.eilmoCfDom.isElement(radio, 'INPUT')
				? radio
				: null;
	}

	/**
	 * Get selected payment method.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSelectedMethod(section) {
		const radio =
			getSelectedRadio(
				section
			);

		if (!radio) {
			return null;
		}

		const method =
			radio.closest(
				SELECTORS.method
			);

		return window.eilmoCfDom.isElement(method)
				? method
				: null;
	}

	/**
	 * Determine whether method is a native
	 * WooCommerce gateway.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {boolean}
	 */
	function isWooCommerceMethod(
		method
	) {
		return (
			window.eilmoCfDom.isElement(method) &&
			method.dataset.paymentSource ===
				'woocommerce_gateway' &&
			Boolean(
				method.dataset.gatewayId
			)
		);
	}

	/**
	 * Determine whether section contains at least
	 * one native WooCommerce gateway.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {boolean}
	 */
	function hasWooCommerceGateway(
		section
	) {
		return Array.from(
			section.querySelectorAll(
				SELECTORS.method
			)
		).some(
			function (method) {
				return (
					window.eilmoCfDom.isElement(method) &&
					!isCashOnDeliveryMethod(
						method
					) &&
					method.dataset
						.paymentSource ===
							'woocommerce_gateway' &&
					Boolean(
						method.dataset
							.gatewayId
					)
				);
			}
		);
	}

	/**
	 * Get transaction input.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getTransactionInput(
		method
	) {
		if (
			!(
				window.eilmoCfDom.isElement(method)
			)
		) {
			return null;
		}

		const input =
			method.querySelector(
				SELECTORS.transaction
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Set error message.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {string}           message Message.
	 *
	 * @return {void}
	 */
	function setError(
		element,
		message
	) {
		if (
			!(
				window.eilmoCfDom.isElement(element)
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
	 * Clear payment errors.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function clearErrors(section) {
		setError(
			section.querySelector(
				SELECTORS.formError
			),
			''
		);

		section
			.querySelectorAll(
				SELECTORS.fieldError
			)
			.forEach(
				function (error) {
					if (
						window.eilmoCfDom.isElement(error)
					) {
						setError(
							error,
							''
						);
					}
				}
			);

		section
			.querySelectorAll(
				[
					'input[aria-invalid="true"]',
					'select[aria-invalid="true"]',
					'textarea[aria-invalid="true"]',
				].join(',')
			)
			.forEach(
				function (field) {
					field.removeAttribute(
						'aria-invalid'
					);
				}
			);
	}

	/**
	 * Show one payment detail box.
	 *
	 * Native WooCommerce gateways require the
	 * payment_box element to be actually visible,
	 * not merely present in the DOM.
	 *
	 * @param {HTMLElement} details Details.
	 *
	 * @return {void}
	 */
	function showDetails(details) {
		details.hidden =
			false;

		details.removeAttribute(
			'hidden'
		);

		details.removeAttribute(
			'aria-hidden'
		);

		if (
			details.style.display ===
				'none'
		) {
			details.style.removeProperty(
				'display'
			);
		}
	}

	/**
	 * Hide one payment detail box.
	 *
	 * @param {HTMLElement} details Details.
	 *
	 * @return {void}
	 */
	function hideDetails(details) {
		details.hidden =
			true;

		details.setAttribute(
			'hidden',
			''
		);

		details.setAttribute(
			'aria-hidden',
			'true'
		);

		details.style.display =
			'none';
	}

	/**
	 * Update payment method visual state.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function updateMethodState(section) {
		section
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

					const radio =
						method.querySelector(
							SELECTORS.radio
						);

					const details =
						method.querySelector(
							SELECTORS.details
						);

					const selected =
						window.eilmoCfDom.isElement(radio, 'INPUT') &&
						radio.checked;

					method.classList.toggle(
						'is-selected',
						selected
					);

					method.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);

					if (
						!(
							window.eilmoCfDom.isElement(details)
						)
					) {
						return;
					}

					if (selected) {
						showDetails(
							details
						);

						return;
					}

					hideDetails(
						details
					);
				}
			);

		const selected =
			getSelectedMethod(
				section
			);

		section.dataset.selectedMethod =
			window.eilmoCfDom.isElement(selected)
					? (
						selected.dataset
							.methodValue ||
						''
					)
					: '';
	}

	/**
	 * Trigger a WooCommerce jQuery body event.
	 *
	 * @param {string} eventName Event.
	 * @param {Array<*>} args    Arguments.
	 *
	 * @return {void}
	 */
	function triggerWooEvent(
		eventName,
		args = []
	) {
		if (
			typeof window.jQuery !==
				'function'
		) {
			return;
		}

		try {
			window.jQuery(
				document.body
			).trigger(
				eventName,
				args
			);
		} catch (error) {
			/*
			 * Third-party gateway failures must not
			 * break Eilmo payment selection.
			 */
		}
	}

	/**
	 * Notify native WooCommerce payment scripts.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function notifyPaymentMethodSelected(
		checkout,
		reason = 'selection'
	) {
		const section =
			getSection(
				checkout
			);

		if (
			isCashOnDelivery(
				checkout
			) ||
			!section ||
			!hasWooCommerceGateway(
				section
			)
		) {
			return;
		}

		triggerWooEvent(
			'payment_method_selected'
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:nativePaymentMethodSelected',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						reason:
							reason,

						payment:
							getData(
								checkout
							),
					},
				}
			)
		);
	}

	/**
	 * Notify native gateway scripts that checkout
	 * payment state changed.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function notifyUpdatedCheckout(
		checkout,
		reason = 'refresh'
	) {
		const section =
			getSection(
				checkout
			);

		if (
			isCashOnDelivery(
				checkout
			) ||
			!section ||
			!hasWooCommerceGateway(
				section
			)
		) {
			return;
		}

		triggerWooEvent(
			'updated_checkout',
			[
				{
					eilmoCf:
						true,

					reason:
						reason,
				},
			]
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:nativePaymentUpdated',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						reason:
							reason,
					},
				}
			)
		);
	}

	/**
	 * Schedule native gateway refresh.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 * @param {number}      delay    Delay.
	 *
	 * @return {void}
	 */
	function scheduleNativeRefresh(
		checkout,
		reason = 'refresh',
		delay = 0
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			const pending =
				refreshTimers.get(
					checkout
				);

			if (pending) {
				window.clearTimeout(
					pending
				);

				refreshTimers.delete(
					checkout
				);
			}

			return;
		}

		const existing =
			refreshTimers.get(
				checkout
			);

		if (existing) {
			window.clearTimeout(
				existing
			);
		}

		const timer =
			window.setTimeout(
				function () {
					refreshTimers.delete(
						checkout
					);

					notifyPaymentMethodSelected(
						checkout,
						reason
					);

					notifyUpdatedCheckout(
						checkout,
						reason
					);
				},
				Math.max(
					0,
					delay
				)
			);

		refreshTimers.set(
			checkout,
			timer
		);
	}

	/**
	 * Get selected payment data.
	 *
	 * COD returns a virtual Eilmo payment method so
	 * checkout.js can continue using the existing
	 * payment payload shape.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getData(checkout) {
		const paymentType =
			getPaymentType(
				checkout
			);

		if (
			'cash_on_delivery' ===
				paymentType
		) {
			return {
				selected:
					true,

				method:
					'eilmo_cash_on_delivery',

				methodKey:
					'cash_on_delivery',

				source:
					'eilmo_manual',

				gatewayId:
					'',

				nativePaymentValue:
					'cod',

				transactionId:
					'',

				transactionRequired:
					false,

				proofToken:
					'',

				proofEnabled:
					false,

				isWooCommerceGateway:
					false,

				paymentType:
					'cash_on_delivery',

				isCashOnDelivery:
					true,
			};
		}

		const emptyData = {
			selected:
				false,

			method:
				'',

			methodKey:
				'',

			source:
				'',

			gatewayId:
				'',

			nativePaymentValue:
				'',

			transactionId:
				'',

			transactionRequired:
				false,

			proofToken:
				'',

			proofEnabled:
				false,

			isWooCommerceGateway:
				false,

			paymentType:
				paymentType,

			isCashOnDelivery:
				false,
		};

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return emptyData;
		}

		excludeCashOnDeliveryMethods(
			section
		);

		const method =
			getSelectedMethod(
				section
			);

		if (
			!method ||
			isCashOnDeliveryMethod(
				method
			)
		) {
			return emptyData;
		}

		const radio =
			method.querySelector(
				SELECTORS.radio
			);

		const transactionInput =
			getTransactionInput(
				method
			);

		const proofTokenInput = method.querySelector(SELECTORS.proofToken);

		const source =
			method.dataset
				.paymentSource ||
			'';

		const gatewayId =
			method.dataset.gatewayId ||
			'';

		return {
			selected:
				true,

			method:
				method.dataset
					.methodValue ||
				'',

			methodKey:
				method.dataset
					.methodKey ||
				'',

			source:
				source,

			gatewayId:
				gatewayId,

			nativePaymentValue:
				window.eilmoCfDom.isElement(radio, 'INPUT')
						? radio.value
						: (
							method.dataset
								.nativePaymentValue ||
							''
						),

			transactionId:
				transactionInput
					? transactionInput
						.value
						.trim()
					: '',

			transactionRequired:
				method.dataset
					.transactionRequired ===
				'yes',

			proofToken:
				window.eilmoCfDom.isElement(proofTokenInput, 'INPUT') ? proofTokenInput.value.trim() : '',

			proofEnabled:
				method.dataset.proofEnabled === 'yes',

			isWooCommerceGateway:
				source ===
					'woocommerce_gateway' &&
				Boolean(
					gatewayId
				),

			paymentType:
				paymentType,

			isCashOnDelivery:
				false,
		};
	}

	/**
	 * Sync payment data to Eilmo checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     dispatch Dispatch event.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout,
		dispatch
	) {
		const data =
			getData(
				checkout
			);

		checkout.dataset.paymentMethod =
			data.method;

		checkout.dataset.paymentMethodKey =
			data.methodKey;

		checkout.dataset.paymentSource =
			data.source;

		checkout.dataset.paymentGatewayId =
			data.gatewayId;

		checkout.dataset.paymentNativeValue =
			data.nativePaymentValue;

		checkout.dataset.paymentTransactionId =
			data.transactionId;

		/*
		 * Keep an order-submission fallback outside the payment card. Dynamic
		 * gateway refreshes can replace method markup after an upload, while the
		 * private upload token must survive until checkout.js builds its payload.
		 */
		checkout.dataset.paymentProofToken =
			data.proofToken;

		if (!dispatch) {
			return;
		}

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:paymentChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						...data,
					},
				}
			)
		);
	}

	/**
	 * Get invalid required native HTML field.
	 *
	 * @param {HTMLElement} method Method.
	 *
	 * @return {HTMLElement|null}
	 */
	function getInvalidGatewayField(
		method
	) {
		const fields =
			method.querySelectorAll(
				[
					'input[required]',
					'select[required]',
					'textarea[required]',
				].join(',')
			);

		for (const field of fields) {
			if (
				!(
					window.eilmoCfDom.isElement(field, 'INPUT') ||
					window.eilmoCfDom.isElement(field, 'SELECT') ||
					window.eilmoCfDom.isElement(field, 'TEXTAREA')
				)
			) {
				continue;
			}

			if (
				field.matches(
					SELECTORS.transaction
				)
			) {
				continue;
			}

			if (
				field.matches(
					SELECTORS.radio
				)
			) {
				continue;
			}

			if (field.disabled) {
				continue;
			}

			if (
				!field.checkValidity()
			) {
				return field;
			}
		}

		return null;
	}

	/**
	 * Validate payment section.
	 *
	 * COD does not require a Payment Method.
	 *
	 * Advance / Full continue using normal Payment
	 * Method validation.
	 *
	 * @param {HTMLElement} checkout     Checkout.
	 * @param {boolean}     showMessages Show messages.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout,
		showMessages = true
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			const section =
				getSection(
					checkout
				);

			if (section) {
				clearErrors(
					section
				);
			}

			return true;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return true;
		}

		clearErrors(
			section
		);

		const required =
			section.dataset.required ===
				'yes';

		const method =
			getSelectedMethod(
				section
			);

		if (!method) {
			if (!required) {
				return true;
			}

			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please select a payment method.'
				);
			}

			return false;
		}

		if (
			isCashOnDeliveryMethod(
				method
			)
		) {
			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please select a payment method.'
				);
			}

			return false;
		}

		const transactionRequired =
			method.dataset
				.transactionRequired ===
					'yes';

		const transactionInput =
			getTransactionInput(
				method
			);

		const proofEnabled = method.dataset.proofEnabled === 'yes';
		const proofTokenInput = method.querySelector(SELECTORS.proofToken);
		const proofToken = window.eilmoCfDom.isElement(proofTokenInput, 'INPUT') ? proofTokenInput.value.trim() : '';
		const hasTransaction = Boolean(transactionInput && transactionInput.value.trim());

		if (method.dataset.proofUploading === 'yes') {
			if (showMessages) {
				setError(method.querySelector(SELECTORS.proofError), 'Please wait for the screenshot upload to finish.');
			}
			return false;
		}

		if (proofEnabled && !proofToken && !hasTransaction) {
			if (showMessages) {
				setError(method.querySelector(SELECTORS.proofError), transactionRequired ? 'Enter the Transaction ID or upload a payment screenshot.' : 'Please upload a payment screenshot.');
			}
			return false;
		}

		if (
			transactionRequired &&
			!proofEnabled &&
			(
				!transactionInput ||
				'' ===
					transactionInput
						.value
						.trim()
			)
		) {
			if (showMessages) {
				const fieldError =
					method.querySelector(
						SELECTORS.fieldError
					);

				setError(
					fieldError,
					'Please enter the transaction ID.'
				);

				if (transactionInput) {
					transactionInput
						.setAttribute(
							'aria-invalid',
							'true'
						);
				}
			}

			return false;
		}

		const invalidGatewayField =
			getInvalidGatewayField(
				method
			);

		if (invalidGatewayField) {
			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please complete the required payment details.'
				);

				invalidGatewayField
					.setAttribute(
						'aria-invalid',
						'true'
					);
			}

			return false;
		}

		return true;
	}

	/**
	 * Focus first invalid payment field.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function focusFirstInvalid(
		checkout
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return false;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return false;
		}

		const method =
			getSelectedMethod(
				section
			);

		if (!method) {
			const radios =
				Array.from(
					section.querySelectorAll(
						SELECTORS.radio
					)
				);

			for (
				let index = 0;
				index < radios.length;
				index += 1
			) {
				const radio =
					radios[index];

				if (
					!(
						window.eilmoCfDom.isElement(radio, 'INPUT')
					) ||
					radio.disabled
				) {
					continue;
				}

				const paymentMethod =
					radio.closest(
						SELECTORS.method
					);

				if (
					!(
						window.eilmoCfDom.isElement(paymentMethod)
					) ||
					isCashOnDeliveryMethod(
						paymentMethod
					)
				) {
					continue;
				}

				radio.focus();

				return true;
			}

			return false;
		}

		if (
			method.dataset
				.transactionRequired ===
					'yes' &&
			method.dataset.proofEnabled !== 'yes'
		) {
			const transactionInput =
				getTransactionInput(
					method
				);

			if (
				transactionInput &&
				'' ===
					transactionInput
						.value
						.trim()
			) {
				transactionInput.focus();

				return true;
			}
		}

		if (method.dataset.proofEnabled === 'yes') {
			const proof = method.querySelector(SELECTORS.proof);
			const token = method.querySelector(SELECTORS.proofToken);
			const transaction = getTransactionInput(method);
			if (
				window.eilmoCfDom.isElement(proof, 'INPUT') &&
				(!(window.eilmoCfDom.isElement(token, 'INPUT')) || !token.value.trim()) &&
				(!transaction || !transaction.value.trim())
			) {
				proof.focus();
				return true;
			}
		}

		const invalidGatewayField =
			getInvalidGatewayField(
				method
			);

		if (
			window.eilmoCfDom.isElement(invalidGatewayField)
		) {
			invalidGatewayField.focus();

			return true;
		}

		if (
			isWooCommerceMethod(
				method
			)
		) {
			const iframe =
				method.querySelector(
					[
						SELECTORS.gatewayFields,
						' iframe',
					].join('')
				);

			if (
				window.eilmoCfDom.isElement(iframe, 'IFRAME')
			) {
				iframe.focus();

				return true;
			}
		}

		return false;
	}

	/**
	 * Ensure an initial non-COD payment method
	 * is selected.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function ensureInitialSelection(
		section
	) {
		excludeCashOnDeliveryMethods(
			section
		);

		const selected =
			getSelectedRadio(
				section
			);

		if (
			window.eilmoCfDom.isElement(selected, 'INPUT') &&
			!selected.disabled
		) {
			const selectedMethod =
				selected.closest(
					SELECTORS.method
				);

			if (
				window.eilmoCfDom.isElement(selectedMethod) &&
				!isCashOnDeliveryMethod(
					selectedMethod
				)
			) {
				return;
			}
		}

		const radios =
			Array.from(
				section.querySelectorAll(
					SELECTORS.radio
				)
			);

		for (
			let index = 0;
			index < radios.length;
			index += 1
		) {
			const radio =
				radios[index];

			if (
				!(
					window.eilmoCfDom.isElement(radio, 'INPUT')
				) ||
				radio.disabled
			) {
				continue;
			}

			const method =
				radio.closest(
					SELECTORS.method
				);

			if (
				!(
					window.eilmoCfDom.isElement(method)
				) ||
				method.hidden ||
				isCashOnDeliveryMethod(
					method
				)
			) {
				continue;
			}

			radio.checked =
				true;

			return;
		}
	}

	/**
	 * Prepare Eilmo checkout DOM for classic
	 * WooCommerce gateway integrations.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function prepareNativeCheckoutDom(
		checkout
	) {
		checkout.classList.add(
			'woocommerce-checkout'
		);

		checkout.dataset
			.eilmoNativePaymentRuntime =
				'yes';
	}

	/**
	 * Initialize one payment section.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function initializeSection(
		section
	) {
		const checkout =
			getCheckout(
				section
			);

		if (!checkout) {
			return;
		}

		prepareNativeCheckoutDom(
			checkout
		);

		syncPaymentOptionState(
			checkout
		);

		syncCheckout(
			checkout,
			false
		);

		if (
			initializedSections.has(
				section
			)
		) {
			if (
				!isCashOnDelivery(
					checkout
				)
			) {
				scheduleNativeRefresh(
					checkout,
					'refresh',
					30
				);
			}

			return;
		}

		initializedSections.add(
			section
		);

		if (
			!isCashOnDelivery(
				checkout
			)
		) {
			scheduleNativeRefresh(
				checkout,
				'initial',
				0
			);
		}

		window.setTimeout(
			function () {
				if (
					document.contains(
						section
					) &&
					!isCashOnDelivery(
						checkout
					)
				) {
					notifyPaymentMethodSelected(
						checkout,
						'initial-late'
					);

					notifyUpdatedCheckout(
						checkout,
						'initial-late'
					);
				}
			},
			350
		);
	}

	/**
	 * Refresh payment section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refresh(checkout) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		syncPaymentOptionState(
			checkout
		);

		initializeSection(
			section
		);

		syncCheckout(
			checkout,
			true
		);
	}

	/**
	 * Refresh only native gateway runtime.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function refreshNativeGateway(
		checkout,
		reason = 'manual-refresh'
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		scheduleNativeRefresh(
			checkout,
			reason,
			0
		);
	}

	/**
	 * Handle payment field change.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const section =
			event.target.closest(
				SELECTORS.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				section
			);

		if (!checkout) {
			return;
		}

		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		if (
			event.target.matches(
				SELECTORS.radio
			)
		) {
			const method =
				event.target.closest(
					SELECTORS.method
				);

			if (
				window.eilmoCfDom.isElement(method) &&
				isCashOnDeliveryMethod(
					method
				)
			) {
				event.target.checked =
					false;

				return;
			}

			clearErrors(
				section
			);

			updateMethodState(
				section
			);

			syncCheckout(
				checkout,
				true
			);

			notifyPaymentMethodSelected(
				checkout,
				'payment-method-change'
			);

			scheduleNativeRefresh(
				checkout,
				'payment-method-change',
				50
			);

			return;
		}

		if (event.target.matches(SELECTORS.proof)) {
			uploadPaymentProof(event.target);
			return;
		}

		if (
			event.target.matches(
				[
					'input',
					'select',
					'textarea',
				].join(',')
			)
		) {
			syncCheckout(
				checkout,
				true
			);
		}
	}

	/**
	 * Handle payment field input.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleInput(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const section =
			event.target.closest(
				SELECTORS.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				section
			);

		if (
			!checkout ||
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		if (
			event.target.matches(
				SELECTORS.transaction
			)
		) {
			const wrap =
				event.target.closest(
					SELECTORS.transactionWrap
				);

			if (
				window.eilmoCfDom.isElement(wrap)
			) {
				setError(
					wrap.querySelector(
						SELECTORS.fieldError
					),
					''
				);
			}

			event.target.removeAttribute(
				'aria-invalid'
			);
		}

		syncCheckout(
			checkout,
			true
		);
	}

	/**
	 * Customer information changed.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleCustomerChange(
		event
	) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				event.target
			);

		if (
			!checkout ||
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		scheduleNativeRefresh(
			checkout,
			'customer-change',
			100
		);
	}

	/**
	 * Top-level Payment Option changed.
	 *
	 * @param {Event} event Payment option event.
	 *
	 * @return {void}
	 */
	function handlePaymentOptionChange(
		event
	) {
		const checkout =
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
					? event.detail.checkout
					: (
						window.eilmoCfDom.isElement(event.target)
								? getCheckout(
									event.target
								)
								: null
					);

		if (!checkout) {
			return;
		}

		syncPaymentOptionState(
			checkout
		);

		syncCheckout(
			checkout,
			true
		);

		if (
			!isCashOnDelivery(
				checkout
			)
		) {
			scheduleNativeRefresh(
				checkout,
				'payment-option-change',
				50
			);
		}
	}

	/**
	 * Initialize all payment sections.
	 *
	 * @return {void}
	 */
	function initialize() {
		document
			.querySelectorAll(
				SELECTORS.section
			)
			.forEach(
				function (section) {
					if (
						window.eilmoCfDom.isElement(section)
					) {
						initializeSection(
							section
						);
					}
				}
			);
	}

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'input',
		handleInput
	);

	document.addEventListener(
		'eilmo:customerChange',
		handleCustomerChange
	);

	document.addEventListener(
		'eilmo:advancePaymentChange',
		handlePaymentOptionChange
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

	window.addEventListener(
		'load',
		function () {
			document
				.querySelectorAll(
					SELECTORS.section
				)
				.forEach(
					function (section) {
						if (
							!(
								window.eilmoCfDom.isElement(section)
							)
						) {
							return;
						}

						const checkout =
							getCheckout(
								section
							);

						if (!checkout) {
							return;
						}

						syncPaymentOptionState(
							checkout
						);

						syncCheckout(
							checkout,
							false
						);

						if (
							!isCashOnDelivery(
								checkout
							)
						) {
							scheduleNativeRefresh(
								checkout,
								'window-load',
								50
							);
						}
					}
				);
		}
	);

	/**
	 * Public Payment API.
	 */
	window.eilmoCfPayment = {

		getData:
			getData,

		validateCheckout:
			validateCheckout,

		focusFirstInvalid:
			focusFirstInvalid,

		refresh:
			refresh,

		refreshNativeGateway:
			refreshNativeGateway,

		isCashOnDelivery:
			isCashOnDelivery,

		syncPaymentOptionState:
			syncPaymentOptionState,

		getSelectedMethod:
			function (checkout) {
				const section =
					getSection(
						checkout
					);

				return section
					? getSelectedMethod(
						section
					)
					: null;
			},

		isWooCommerceGateway:
			function (checkout) {
				if (
					isCashOnDelivery(
						checkout
					)
				) {
					return false;
				}

				const section =
					getSection(
						checkout
					);

				if (!section) {
					return false;
				}

				return isWooCommerceMethod(
					getSelectedMethod(
						section
					)
				);
			},
	};
})();
