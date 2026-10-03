/**
 * Eilmo Checkout Flow - Combo Offers.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		section:
			'[data-eilmo-combo-offers]',

		offer:
			'[data-eilmo-combo-offer]',

		input:
			'[data-eilmo-combo-input]',

		action:
			'.eilmo-cf-combo-offer__action',

		trigger:
			'[data-eilmo-combo-trigger]',
	};

	const initializedSections =
		new WeakSet();

	/**
	 * Normalize Combo ID.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizeId(value) {
		return String(
			value ?? ''
		)
			.trim()
			.toLowerCase()
			.replace(
				/[^a-z0-9_-]/g,
				''
			);
	}

	/**
	 * Normalize IDs.
	 *
	 * @param {*} values Values.
	 *
	 * @return {Array<string>}
	 */
	function normalizeIds(values) {
		if (
			!Array.isArray(
				values
			)
		) {
			return [];
		}

		const result =
			[];

		values.forEach(
			function (value) {
				const id =
					normalizeId(
						value
					);

				if (
					!id ||
					result.includes(
						id
					)
				) {
					return;
				}

				result.push(
					id
				);
			}
		);

		return result;
	}

	/**
	 * Normalize selection mode.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizeSelectionMode(
		value
	) {
		return value ===
			'single'
				? 'single'
				: 'multiple';
	}

	/**
	 * Get checkout.
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
	 * Get section.
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
	 * Get localized text.
	 *
	 * @param {string} key Key.
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
			config.i18n &&
			typeof config.i18n ===
				'object'
				? config.i18n
				: {};

		if (
			typeof i18n[key] ===
				'string' &&
			i18n[key] !== ''
		) {
			return i18n[key];
		}

		return fallback;
	}

	/**
	 * Get signed context.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getContext(checkout) {
		const section =
			getSection(
				checkout
			);

		const empty = {
			allowed_ids:
				[],

			instance_id:
				'',

			selection_mode:
				'multiple',

			signature:
				'',
		};

		if (!section) {
			return empty;
		}

		const raw =
			String(
				section.dataset
					.eilmoComboContext ||
					''
			).trim();

		if (!raw) {
			return empty;
		}

		try {
			const parsed =
				JSON.parse(
					raw
				);

			if (
				!parsed ||
				typeof parsed !==
					'object' ||
				Array.isArray(
					parsed
				)
			) {
				return empty;
			}

			return {
				allowed_ids:
					normalizeIds(
						parsed.allowed_ids
					),

				instance_id:
					String(
						parsed.instance_id ||
							''
					).trim(),

				selection_mode:
					normalizeSelectionMode(
						parsed.selection_mode
					),

				signature:
					String(
						parsed.signature ||
							''
					).trim(),
			};

		} catch (error) {
			return empty;
		}
	}

	/**
	 * Get UI selection mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getUiSelectionMode(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return 'multiple';
		}

		return normalizeSelectionMode(
			section.dataset
				.selectionMode
		);
	}

	/**
	 * Get authoritative frontend mode.
	 *
	 * Signed context is preferred.
	 *
	 * Server still verifies the signature.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getSelectionMode(
		checkout
	) {
		const context =
			getContext(
				checkout
			);

		if (
			context.signature &&
			context.selection_mode
		) {
			return context.selection_mode;
		}

		return getUiSelectionMode(
			checkout
		);
	}

	/**
	 * Get offer cards.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<HTMLElement>}
	 */
	function getOfferCards(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return [];
		}

		return Array.from(
			section.querySelectorAll(
				SELECTORS.offer
			)
		).filter(
			function (offer) {
				return window.eilmoCfDom.isElement(offer);
			}
		);
	}

	/**
	 * Get input.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getOfferInput(
		offer
	) {
		const input =
			offer.querySelector(
				SELECTORS.input
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Get offer ID.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {string}
	 */
	function getOfferId(
		offer
	) {
		const datasetId =
			normalizeId(
				offer.dataset
					.comboId ||
					''
			);

		if (datasetId) {
			return datasetId;
		}

		const input =
			getOfferInput(
				offer
			);

		return input
			? normalizeId(
				input.value
			)
			: '';
	}

	/**
	 * Is available.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {boolean}
	 */
	function isOfferAvailable(
		offer
	) {
		const input =
			getOfferInput(
				offer
			);

		if (
			input &&
			input.disabled
		) {
			return false;
		}

		return offer.dataset
			.available !==
				'no';
	}

	/**
	 * Is selected.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {boolean}
	 */
	function isOfferSelected(
		offer
	) {
		const input =
			getOfferInput(
				offer
			);

		return Boolean(
			input &&
			input.checked &&
			!input.disabled
		);
	}

	/**
	 * Get selected IDs.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getSelectedIds(
		checkout
	) {
		const selected =
			[];

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				if (
					!isOfferSelected(
						offer
					)
				) {
					return;
				}

				const id =
					getOfferId(
						offer
					);

				if (
					id &&
					!selected.includes(
						id
					)
				) {
					selected.push(
						id
					);
				}
			}
		);

		return selected;
	}

	/**
	 * Numeric dataset.
	 *
	 * @param {HTMLElement} offer Offer.
	 * @param {string} key Key.
	 *
	 * @return {number}
	 */
	function getNumericDataset(
		offer,
		key
	) {
		const value =
			Number.parseFloat(
				String(
					offer.dataset[
						key
					] ||
						'0'
				)
			);

		return Number.isFinite(
			value
		)
			? Math.max(
				0,
				value
			)
			: 0;
	}

	/**
	 * Selected Combo presentation values.
	 *
	 * Display only.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<Object>}
	 */
	function getSelectedOffers(
		checkout
	) {
		const selected =
			[];

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				if (
					!isOfferSelected(
						offer
					)
				) {
					return;
				}

				const id =
					getOfferId(
						offer
					);

				if (!id) {
					return;
				}

				selected.push(
					{
						combo_id:
							id,

						regular_total:
							getNumericDataset(
								offer,
								'regularTotal'
							),

						combo_total:
							getNumericDataset(
								offer,
								'comboTotal'
							),

						discount:
							getNumericDataset(
								offer,
								'comboDiscount'
							),
					}
				);
			}
		);

		return selected;
	}

	/**
	 * Get payload data.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getData(
		checkout
	) {
		const context =
			getContext(
				checkout
			);

		return {
			selected_ids:
				getSelectedIds(
					checkout
				),

			selection_mode:
				getSelectionMode(
					checkout
				),

			context: {
				allowed_ids:
					context.allowed_ids,

				instance_id:
					context.instance_id,

				selection_mode:
					context.selection_mode,

				signature:
					context.signature,
			},
		};
	}

	/**
	 * Action label.
	 *
	 * @param {HTMLElement} offer Offer.
	 * @param {boolean} selected Selected.
	 *
	 * @return {void}
	 */
	function updateActionLabel(
		offer,
		selected
	) {
		const action =
			offer.querySelector(
				SELECTORS.action
			);

		if (
			!(
				window.eilmoCfDom.isElement(action)
			)
		) {
			return;
		}

		action.textContent =
			selected
				? getText(
					'comboSelected',
					'Selected'
				)
				: getText(
					'comboAdd',
					'Add Combo'
				);
	}

	/**
	 * Sync offer.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {void}
	 */
	function syncOfferState(
		offer
	) {
		const selected =
			isOfferSelected(
				offer
			);

		const available =
			isOfferAvailable(
				offer
			);

		offer.dataset.selected =
			selected
				? 'yes'
				: 'no';

		offer.classList.toggle(
			'is-selected',
			selected
		);

		offer.classList.toggle(
			'is-unavailable',
			!available
		);

		updateActionLabel(
			offer,
			selected
		);
	}

	/**
	 * Single mode enforcement.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLInputElement|null} preferred Preferred.
	 *
	 * @return {void}
	 */
	function enforceSingleSelection(
		checkout,
		preferred = null
	) {
		if (
			getSelectionMode(
				checkout
			) !== 'single'
		) {
			return;
		}

		const checked =
			getOfferCards(
				checkout
			)
				.map(
					getOfferInput
				)
				.filter(
					function (input) {
						return (
							window.eilmoCfDom.isElement(input, 'INPUT') &&
							input.checked &&
							!input.disabled
						);
					}
				);

		if (
			checked.length <= 1
		) {
			return;
		}

		const keep =
			window.eilmoCfDom.isElement(preferred, 'INPUT') &&
			preferred.checked &&
			!preferred.disabled
				? preferred
				: checked[0];

		checked.forEach(
			function (input) {
				if (
					input !==
						keep
				) {
					input.checked =
						false;
				}
			}
		);
	}

	/**
	 * Sync checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout
	) {
		enforceSingleSelection(
			checkout
		);

		getOfferCards(
			checkout
		).forEach(
			syncOfferState
		);
	}

	/**
	 * Find the checkout controlled by an external Combo Button.
	 *
	 * A button inside a checkout controls that checkout. A button elsewhere on
	 * the Elementor page controls the first checkout whose signed Combo state
	 * contains the requested Combo ID.
	 *
	 * @param {HTMLElement} trigger Trigger button.
	 *
	 * @return {HTMLElement|null}
	 */
	function getTriggerCheckout(trigger) {
		const direct = getCheckout(trigger);
		if (direct) {
			return direct;
		}

		const comboId = normalizeId(trigger.dataset.comboId || '');
		if (!comboId) {
			return null;
		}

		const checkouts = Array.from(document.querySelectorAll(SELECTORS.checkout));
		for (const checkout of checkouts) {
			if (!(window.eilmoCfDom.isElement(checkout))) {
				continue;
			}

			const hasOffer = getOfferCards(checkout).some(function (offer) {
				return getOfferId(offer) === comboId;
			});

			if (hasOffer) {
				return checkout;
			}
		}

		return null;
	}

	/**
	 * Synchronize one external Combo Button with checkout state.
	 *
	 * @param {HTMLElement} trigger Trigger button.
	 *
	 * @return {void}
	 */
	function syncTrigger(trigger) {
		if (!(window.eilmoCfDom.isElement(trigger))) {
			return;
		}

		const comboId = normalizeId(trigger.dataset.comboId || '');
		const checkout = getTriggerCheckout(trigger);
		const selected = Boolean(
			comboId &&
			checkout &&
			getSelectedIds(checkout).includes(comboId)
		);

		trigger.classList.toggle('is-selected', selected);
		trigger.setAttribute('aria-pressed', selected ? 'true' : 'false');

		const label = trigger.querySelector('[data-eilmo-combo-trigger-text]');
		if (window.eilmoCfDom.isElement(label)) {
			const addLabel = String(trigger.dataset.addLabel || getText('comboAdd', 'Add Combo'));
			const addedLabel = String(trigger.dataset.addedLabel || getText('comboSelected', 'Combo Added'));
			label.textContent = selected ? addedLabel : addLabel;
		}
	}

	/**
	 * Synchronize all external Combo Buttons.
	 *
	 * @return {void}
	 */
	function syncTriggers() {
		document.querySelectorAll(SELECTORS.trigger).forEach(function (trigger) {
			if (window.eilmoCfDom.isElement(trigger)) {
				syncTrigger(trigger);
			}
		});
	}

	/**
	 * Dispatch change.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} changedId Changed Combo.
	 *
	 * @return {void}
	 */
	function dispatchChange(
		checkout,
		changedId = ''
	) {
		const data =
			getData(
				checkout
			);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:comboChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						changedId:
							normalizeId(
								changedId
							),

						selectedIds:
							data.selected_ids,

						selectedOffers:
							getSelectedOffers(
								checkout
							),

						selectionMode:
							data.selection_mode,

						context:
							data.context,
					},
				}
			)
		);

		syncTriggers();
	}

	/**
	 * Set selection.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} offer Offer.
	 * @param {boolean} selected Selected.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function setOfferSelected(
		checkout,
		offer,
		selected,
		notify = true
	) {
		if (
			!isOfferAvailable(
				offer
			)
		) {
			return false;
		}

		const input =
			getOfferInput(
				offer
			);

		if (!input) {
			return false;
		}

		if (
			selected &&
			getSelectionMode(
				checkout
			) === 'single'
		) {
			getOfferCards(
				checkout
			).forEach(
				function (
					otherOffer
				) {
					if (
						otherOffer ===
							offer
					) {
						return;
					}

					const otherInput =
						getOfferInput(
							otherOffer
						);

					if (otherInput) {
						otherInput.checked =
							false;
					}
				}
			);
		}

		input.checked =
			Boolean(
				selected
			);

		enforceSingleSelection(
			checkout,
			input
		);

		syncCheckout(
			checkout
		);

		if (notify) {
			dispatchChange(
				checkout,
				getOfferId(
					offer
				)
			);
		}

		return true;
	}

	/**
	 * Select Combo.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} comboId Combo.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function selectCombo(
		checkout,
		comboId,
		notify = true
	) {
		const id =
			normalizeId(
				comboId
			);

		const offer =
			getOfferCards(
				checkout
			).find(
				function (candidate) {
					return (
						getOfferId(
							candidate
						) === id
					);
				}
			);

		if (!offer) {
			return false;
		}

		return setOfferSelected(
			checkout,
			offer,
			true,
			notify
		);
	}

	/**
	 * Deselect Combo.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} comboId Combo.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function deselectCombo(
		checkout,
		comboId,
		notify = true
	) {
		const id =
			normalizeId(
				comboId
			);

		const offer =
			getOfferCards(
				checkout
			).find(
				function (candidate) {
					return (
						getOfferId(
							candidate
						) === id
					);
				}
			);

		if (!offer) {
			return false;
		}

		return setOfferSelected(
			checkout,
			offer,
			false,
			notify
		);
	}

	/**
	 * Clear selection.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean} notify Notify.
	 *
	 * @return {void}
	 */
	function clearSelection(
		checkout,
		notify = true
	) {
		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				const input =
					getOfferInput(
						offer
					);

				if (input) {
					input.checked =
						false;
				}
			}
		);

		syncCheckout(
			checkout
		);

		if (notify) {
			dispatchChange(
				checkout
			);
		}
	}

	/**
	 * Toggle offer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {void}
	 */
	function toggleOffer(
		checkout,
		offer
	) {
		if (
			!isOfferAvailable(
				offer
			)
		) {
			return;
		}

		const input =
			getOfferInput(
				offer
			);

		if (!input) {
			return;
		}

		if (
			getSelectionMode(
				checkout
			) === 'single' &&
			input.checked
		) {
			setOfferSelected(
				checkout,
				offer,
				false,
				true
			);

			return;
		}

		setOfferSelected(
			checkout,
			offer,
			!input.checked,
			true
		);
	}

	/**
	 * Validate.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean} showMessages Messages.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout,
		showMessages = true
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return true;
		}

		const context =
			getContext(
				checkout
			);

		if (
			!context.signature ||
			!context.instance_id
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'Combo Offer verification failed. Please refresh the page and try again.'
					)
				);
			}

			return false;
		}

		/*
		 * UI mode and signed mode must match.
		 */
		if (
			getUiSelectionMode(
				checkout
			) !==
				context.selection_mode
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'Combo Offer settings have changed. Please refresh the page and try again.'
					)
				);
			}

			return false;
		}

		const selectedIds =
			getSelectedIds(
				checkout
			);

		const invalid =
			selectedIds.some(
				function (id) {
					return (
						!context.allowed_ids.includes(
							id
						)
					);
				}
			);

		if (invalid) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'One of the selected Combo Offers is no longer available.'
					)
				);
			}

			return false;
		}

		if (
			context.selection_mode ===
				'single' &&
			selectedIds.length > 1
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboSingle',
						'Please select only one Combo Offer.'
					)
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Dispatch validation error.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} message Message.
	 *
	 * @return {void}
	 */
	function dispatchValidationError(
		checkout,
		message
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:comboValidationError',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						message:
							String(
								message || ''
							),
					},
				}
			)
		);
	}

	/**
	 * Refresh.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refresh(
		checkout
	) {
		if (
			window.eilmoCfDom.isElement(checkout)
		) {
			syncCheckout(
				checkout
			);
		}
	}

	/**
	 * Native input change.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target, 'INPUT')
			) ||
			!event.target.matches(
				SELECTORS.input
			)
		) {
			return;
		}

		const input =
			event.target;

		const checkout =
			getCheckout(
				input
			);

		if (!checkout) {
			return;
		}

		const offer =
			input.closest(
				SELECTORS.offer
			);

		if (
			!(
				window.eilmoCfDom.isElement(offer)
			)
		) {
			return;
		}

		enforceSingleSelection(
			checkout,
			input
		);

		syncCheckout(
			checkout
		);

		dispatchChange(
			checkout,
			getOfferId(
				offer
			)
		);
	}

	/**
	 * Click handler.
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

		const trigger = event.target.closest(SELECTORS.trigger);
		if (window.eilmoCfDom.isElement(trigger)) {
			event.preventDefault();

			const checkout = getTriggerCheckout(trigger);
			const comboId = normalizeId(trigger.dataset.comboId || '');
			if (!checkout || !comboId) {
				return;
			}

			const selected = getSelectedIds(checkout).includes(comboId);
			if (selected) {
				if (trigger.dataset.allowRemove !== 'yes') {
					syncTrigger(trigger);
					return;
				}
				deselectCombo(checkout, comboId, true);
			} else {
				selectCombo(checkout, comboId, true);
			}

			syncTriggers();
			return;
		}

		const offer =
			event.target.closest(
				SELECTORS.offer
			);

		if (
			!(
				window.eilmoCfDom.isElement(offer)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				offer
			);

		if (!checkout) {
			return;
		}

		if (
			event.target.closest(
				[
					'input',
					'button',
					'a',
					'select',
					'textarea',
					'label',
				].join(',')
			)
		) {
			return;
		}

		event.preventDefault();

		toggleOffer(
			checkout,
			offer
		);
	}

	/**
	 * Initialize checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (
			!section ||
			initializedSections.has(
				section
			)
		) {
			return;
		}

		initializedSections.add(
			section
		);

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				const input =
					getOfferInput(
						offer
					);

				if (
					input &&
					!isOfferAvailable(
						offer
					)
				) {
					input.checked =
						false;

					input.disabled =
						true;
				}
			}
		);

		syncCheckout(
			checkout
		);
	}

	/**
	 * Initialize.
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

		syncTriggers();
	}

	window.eilmoCfComboOffers = {
		getData:
			getData,

		getContext:
			getContext,

		getSelectedIds:
			getSelectedIds,

		getSelectedOffers:
			getSelectedOffers,

		getSelectionMode:
			getSelectionMode,

		validateCheckout:
			validateCheckout,

		refresh:
			refresh,

		select:
			selectCombo,

		deselect:
			deselectCombo,

		clear:
			clearSelection,

		syncTriggers:
			syncTriggers,
	};

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'click',
		handleClick
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