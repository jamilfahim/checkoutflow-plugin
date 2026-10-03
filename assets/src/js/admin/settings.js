/**
 * Eilmo Checkout Flow admin settings.
 *
 * Handles dynamic settings repeaters, including
 * the global Combo Offer and Order Bump libraries.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	if (window.eilmoCfSettingsAdminReady) {
		return;
	}
	window.eilmoCfSettingsAdminReady = true;

	const SELECTORS = {
		deliveryContainer: '[data-eilmo-delivery-methods]',
		advanceContainer: '[data-eilmo-advance-rules]',
		discountContainer: '[data-eilmo-discount-rules]',
		couponContainer: '[data-eilmo-coupons]',
		paymentContainer: '[data-eilmo-payment-methods]',
		comboContainer: '[data-eilmo-combo-offers]',
		comboItemsContainer: '[data-eilmo-combo-items]',
		orderBumpContainer: '[data-eilmo-order-bumps]',
		galleryContainer: '[data-eilmo-product-galleries]',

		addDelivery: '[data-eilmo-add-delivery-method]',
		addAdvance: '[data-eilmo-add-advance-rule]',
		addDiscount: '[data-eilmo-add-discount-rule]',
		addCoupon: '[data-eilmo-add-coupon]',
		addPayment: '[data-eilmo-add-payment-method]',
		addCombo: '[data-eilmo-add-combo-offer]',
		addComboItem: '[data-eilmo-add-combo-item]',
		addOrderBump: '[data-eilmo-add-order-bump]',
		addGallery: '[data-eilmo-add-product-gallery]',

		remove: '[data-eilmo-remove-repeater-item]',
		item: '.eilmo-cf-admin-repeater__item',

		paymentItem: '[data-eilmo-payment-method]',
		paymentTitle: '[data-eilmo-payment-method-title]',
		paymentName: '[data-eilmo-payment-method-name]',
		paymentId: '[data-eilmo-payment-method-id]',
		defaultPaymentMethod: '#eilmo-cf-default-payment-method',

		comboItem: '[data-eilmo-combo-offer]',
		comboTitle: '[data-eilmo-combo-title]',
		comboTitleInput: '[data-eilmo-combo-title-input]',
		comboId: '[data-eilmo-combo-id]',

		/*
		 * Combo accordion selectors.
		 */
		comboToggle: '[data-eilmo-combo-toggle]',
		comboContent: '[data-eilmo-combo-content]',
		comboToggleIcon: '[data-eilmo-combo-toggle-icon]',

		comboProductItem: '[data-eilmo-combo-item]',
		comboProductId: '[data-eilmo-combo-product-id]',
		comboVariationId: '[data-eilmo-combo-variation-id]',
		comboIconType: '[data-eilmo-combo-icon-type]',
		comboIconPresetWrap: '[data-eilmo-combo-icon-preset-wrap]',
		comboIconMediaWrap: '[data-eilmo-combo-icon-media-wrap]',
		comboImageSource: '[data-eilmo-combo-image-source]',
		comboItemMediaWrap: '[data-eilmo-combo-item-media-wrap]',
		comboMediaPicker: '[data-eilmo-combo-media-picker]',
		comboMediaId: '[data-eilmo-combo-media-id]',
		comboMediaPreview: '[data-eilmo-combo-media-preview]',
		selectComboMedia: '[data-eilmo-select-combo-media]',
		clearComboMedia: '[data-eilmo-clear-combo-media]',
		comboStyle: '[data-eilmo-combo-style]',
		comboStylePreview: '[data-eilmo-combo-style-preview]',
		specialStyle: '[data-eilmo-special-style]',
		specialStylePreview: '[data-eilmo-special-style-preview]',
		specialPreviewStage: '[data-eilmo-special-preview-stage]',
		specialPreviewLayout: '[data-eilmo-special-preview-layout]',
		specialPreviewType: '[data-eilmo-special-preview-type]',
		specialCardStyle: '[data-eilmo-special-card-style]',
		specialStyleChoice: '[data-eilmo-special-style-choice]',
		specialColumns: '[data-eilmo-special-columns]',
		specialColumnsHelp: '[data-eilmo-special-columns-help]',
		specialStyleField: '[data-eilmo-special-style-field]',

		orderBumpItem: '[data-eilmo-order-bump]',
		orderBumpTitle: '[data-eilmo-order-bump-title]',
		orderBumpTitleInput: '[data-eilmo-order-bump-title-input]',
		orderBumpId: '[data-eilmo-order-bump-id]',

		galleryItem: '[data-eilmo-product-gallery-item]',
		galleryTitle: '[data-eilmo-gallery-title]',
		galleryName: '[data-eilmo-gallery-name]',
		galleryId: '[data-eilmo-gallery-id]',
		galleryProductField: '[data-eilmo-gallery-product-field]',
		galleryProduct: '[data-eilmo-gallery-product]',
		galleryToggle: '[data-eilmo-gallery-toggle]',
		galleryContent: '[data-eilmo-gallery-content]',
		galleryToggleIcon: '[data-eilmo-gallery-toggle-icon]',
		galleryImages: '[data-eilmo-gallery-images]',
		galleryImage: '[data-eilmo-gallery-image]',
		galleryImageIds: '[data-eilmo-gallery-image-ids]',
		selectGalleryImages: '[data-eilmo-select-gallery-images]',
		clearGalleryImages: '[data-eilmo-clear-gallery-images]',
		removeGalleryImage: '[data-eilmo-remove-gallery-image]',
		checkoutStyle: '[data-eilmo-checkout-style]',
		stylePreview: '[data-eilmo-style-preview]',
		singleVariationLayout: '[data-eilmo-single-variation-layout]',
		singleVariationSelection: '[data-eilmo-single-variation-selection]',
		multipleVariationLayout: '[data-eilmo-multiple-variation-layout]',
		multipleVariationSelection: '[data-eilmo-multiple-variation-selection]',
	};

	const STYLE_VARIABLES = {
		primary: '--eilmo-cf-theme-primary',
		primary_hover: '--eilmo-cf-theme-primary-hover',
		primary_soft: '--eilmo-cf-theme-primary-soft',
		text: '--eilmo-cf-theme-main-text',
		muted_text: '--eilmo-cf-theme-secondary-text',
		background: '--eilmo-cf-theme-card-background',
		muted_background: '--eilmo-cf-theme-muted-surface',
		soft_background: '--eilmo-cf-theme-soft-background',
		border: '--eilmo-cf-theme-border',
		border_strong: '--eilmo-cf-theme-strong-border',
		success: '--eilmo-cf-theme-success',
		warning: '--eilmo-cf-theme-warning',
		danger: '--eilmo-cf-theme-error',
		button_text: '--eilmo-cf-theme-button-text',
	};

	const PREMIUM_PURPLE_STYLE = {
		primary: '#6d28d9',
		text: '#111827',
		muted_text: '#64748b',
		background: '#ffffff',
		soft_background: '#f3f4f6',
		border: '#e5e7eb',
		button_text: '#ffffff',
	};

	/**
	 * Escape HTML.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * Convert text to slug.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function slugify(value) {
		return String(value)
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9\s-]/g, '')
			.replace(/\s+/g, '-')
			.replace(/-+/g, '-')
			.replace(/^-|-$/g, '');
	}

	/**
	 * Get direct children matching selector.
	 *
	 * @param {HTMLElement} container Container.
	 * @param {string} selector Selector.
	 *
	 * @return {Array<HTMLElement>}
	 */
	function getDirectChildren(
		container,
		selector
	) {
		return Array.from(
			container.children
		).filter(function (child) {
			return (
				child instanceof
					HTMLElement &&
				child.matches(
					selector
				)
			);
		});
	}

	/**
	 * Get next direct repeater index.
	 *
	 * @param {HTMLElement} container Container.
	 *
	 * @return {number}
	 */
	function getNextIndex(container) {
		return getDirectChildren(
			container,
			SELECTORS.item
		).length;
	}

	/**
	 * Build yes/no switch.
	 *
	 * Hidden input ensures "no" is submitted when
	 * the checkbox is unchecked.
	 *
	 * @param {string} name Field name.
	 * @param {boolean} checked Checked state.
	 *
	 * @return {string}
	 */
	function buildSwitch(
		name,
		checked
	) {
		return `
			<input
				type="hidden"
				name="${escapeHtml(name)}"
				value="no"
			>

			<label class="eilmo-cf-admin-switch">
				<input
					type="checkbox"
					name="${escapeHtml(name)}"
					value="yes"
					${checked ? 'checked' : ''}
				>

				<span
					class="eilmo-cf-admin-switch__slider"
				></span>
			</label>
		`;
	}

	/**
	 * Append repeater item.
	 *
	 * @param {HTMLElement} container Container.
	 * @param {string} html HTML.
	 *
	 * @return {HTMLElement|null}
	 */
	function appendItem(
		container,
		html
	) {
		container.insertAdjacentHTML(
			'beforeend',
			html
		);

		const item =
			container.lastElementChild;

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return null;
		}

		const firstInput =
			item.querySelector(
				[
					'input:not([type="hidden"])',
					'select',
					'textarea',
				].join(',')
			);

		if (
			firstInput instanceof
				HTMLElement
		) {
			firstInput.focus();
		}

		item.scrollIntoView(
			{
				behavior:
					'smooth',

				block:
					'nearest',
			}
		);

		return item;
	}

	/**
	 * Build delivery method.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildDeliveryMethod(index) {
		const prefix =
			`eilmo_cf_settings[delivery][methods][${index}]`;

		return `
			<div
				class="eilmo-cf-admin-repeater__item"
				data-eilmo-delivery-method
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-item-title
					>
						Delivery Method
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div
					class="eilmo-cf-admin-grid"
				>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>Method Name — English</label>

						<input
							type="text"
							name="${prefix}[label]"
							value=""
							data-eilmo-delivery-label
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>Method Name — বাংলা (optional)</label>
						<input
							type="text"
							name="${prefix}[label_bn]"
							value=""
							placeholder="ঢাকার ভেতরে"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Method ID
						</label>

						<input
							type="text"
							name="${prefix}[id]"
							value=""
							placeholder="inside-dhaka"
							data-eilmo-delivery-id
							data-auto-id="yes"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Charge
						</label>

						<input
							type="number"
							name="${prefix}[charge]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Sort Order
						</label>

						<input
							type="number"
							name="${prefix}[sort_order]"
							value="${(index + 1) * 10}"
							min="0"
							step="1"
						>
					</div>

					<div
						class="
							eilmo-cf-admin-field
							eilmo-cf-admin-field--wide
						"
					>
						<label>Description — English</label>

						<textarea
							name="${prefix}[description]"
							rows="3"
						></textarea>
					</div>

					<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
						<label>Description — বাংলা (optional)</label>
						<textarea
							name="${prefix}[description_bn]"
							rows="3"
						></textarea>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Enabled
						</label>

						${buildSwitch(
							`${prefix}[enabled]`,
							true
						)}
					</div>

				</div>

			</div>
		`;
	}

	/**
	 * Build advance rule.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildAdvanceRule(index) {
		const prefix =
			`eilmo_cf_settings[advance_payment][rules][${index}]`;

		return `
			<div
				class="eilmo-cf-admin-repeater__item"
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-item-title
					>
						Advance Rule
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div
					class="eilmo-cf-admin-grid"
				>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Rule Name
						</label>

						<input
							type="text"
							name="${prefix}[name]"
							value=""
							data-eilmo-rule-name
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Minimum Amount
						</label>

						<input
							type="number"
							name="${prefix}[minimum_amount]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Amount
						</label>

						<input
							type="number"
							name="${prefix}[maximum_amount]"
							value=""
							min="0"
							step="0.01"
							placeholder="No maximum"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Advance Type
						</label>

						<select
							name="${prefix}[type]"
							data-eilmo-advance-type
						>
							<option value="fixed">
								Fixed Amount
							</option>

							<option value="percentage">
								Percentage
							</option>

							<option value="full_payment">
								Full Payment
							</option>

							<option value="no_advance">
								No Advance
							</option>
						</select>
					</div>

					<div
						class="eilmo-cf-admin-field"
						data-eilmo-value-field
					>
						<label>
							Value
						</label>

						<input
							type="number"
							name="${prefix}[value]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Minimum Pay
						</label>

						<input
							type="number"
							name="${prefix}[minimum_pay_amount]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Pay
						</label>

						<input
							type="number"
							name="${prefix}[maximum_pay_amount]"
							value=""
							min="0"
							step="0.01"
							placeholder="No maximum"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Priority
						</label>

						<input
							type="number"
							name="${prefix}[priority]"
							value="10"
							min="0"
							step="1"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Enabled
						</label>

						${buildSwitch(
							`${prefix}[enabled]`,
							true
						)}
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Stop Processing
						</label>

						${buildSwitch(
							`${prefix}[stop_processing]`,
							true
						)}
					</div>

					<input
						type="hidden"
						name="${prefix}[id]"
						value=""
					>

				</div>

			</div>
		`;
	}

	/**
	 * Build automatic discount rule.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildDiscountRule(index) {
		const prefix =
			`eilmo_cf_settings[discounts][automatic_rules][${index}]`;

		return `
			<div
				class="eilmo-cf-admin-repeater__item"
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-item-title
					>
						Discount Rule
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div
					class="eilmo-cf-admin-grid"
				>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Rule Name
						</label>

						<input
							type="text"
							name="${prefix}[name]"
							value=""
							data-eilmo-rule-name
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Minimum Amount
						</label>

						<input
							type="number"
							name="${prefix}[minimum]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Amount
						</label>

						<input
							type="number"
							name="${prefix}[maximum]"
							value=""
							min="0"
							step="0.01"
							placeholder="No maximum"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Discount Type
						</label>

						<select
							name="${prefix}[type]"
							data-eilmo-discount-type
						>
							<option value="percentage">
								Percentage
							</option>

							<option value="fixed">
								Fixed Amount
							</option>

							<option value="free_delivery">
								Free Delivery
							</option>
						</select>
					</div>

					<div
						class="eilmo-cf-admin-field"
						data-eilmo-value-field
					>
						<label>
							Value
						</label>

						<input
							type="number"
							name="${prefix}[value]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Discount
						</label>

						<input
							type="number"
							name="${prefix}[maximum_discount]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Priority
						</label>

						<input
							type="number"
							name="${prefix}[priority]"
							value="10"
							min="0"
							step="1"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Enabled
						</label>

						${buildSwitch(
							`${prefix}[enabled]`,
							true
						)}
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Stop Processing
						</label>

						${buildSwitch(
							`${prefix}[stop_processing]`,
							true
						)}
					</div>

					<input
						type="hidden"
						name="${prefix}[id]"
						value=""
					>

				</div>

			</div>
		`;
	}

	/**
	 * Build custom coupon.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildCoupon(index) {
		const prefix =
			`eilmo_cf_settings[coupons][custom_coupons][${index}]`;

		return `
			<div
				class="eilmo-cf-admin-repeater__item"
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-item-title
					>
						Coupon
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div
					class="eilmo-cf-admin-grid"
				>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Coupon Code
						</label>

						<input
							type="text"
							name="${prefix}[code]"
							value=""
							data-eilmo-coupon-code
							autocomplete="off"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Discount Type
						</label>

						<select
							name="${prefix}[type]"
							data-eilmo-coupon-type
						>
							<option value="percentage">
								Percentage
							</option>

							<option value="fixed">
								Fixed Amount
							</option>

							<option value="free_delivery">
								Free Delivery
							</option>
						</select>
					</div>

					<div
						class="eilmo-cf-admin-field"
						data-eilmo-value-field
					>
						<label>
							Value
						</label>

						<input
							type="number"
							name="${prefix}[value]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Minimum Spend
						</label>

						<input
							type="number"
							name="${prefix}[minimum_spend]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Spend
						</label>

						<input
							type="number"
							name="${prefix}[maximum_spend]"
							value=""
							min="0"
							step="0.01"
							placeholder="No maximum"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Maximum Discount
						</label>

						<input
							type="number"
							name="${prefix}[maximum_discount]"
							value="0"
							min="0"
							step="0.01"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Usage Limit
						</label>

						<input
							type="number"
							name="${prefix}[usage_limit]"
							value="0"
							min="0"
							step="1"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Per Customer Limit
						</label>

						<input
							type="number"
							name="${prefix}[usage_limit_per_customer]"
							value="0"
							min="0"
							step="1"
						>
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Enabled
						</label>

						${buildSwitch(
							`${prefix}[enabled]`,
							true
						)}
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							Allow Stacking
						</label>

						${buildSwitch(
							`${prefix}[allow_stacking]`,
							false
						)}
					</div>

					<div
						class="eilmo-cf-admin-field"
					>
						<label>
							New Customer Only
						</label>

						${buildSwitch(
							`${prefix}[new_customer_only]`,
							false
						)}
					</div>

				</div>

			</div>
		`;
	}

	/**
	 * Build custom manual payment method.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildPaymentMethod(index) {
		const prefix =
			`eilmo_cf_settings[payment_methods][custom_methods][${index}]`;

		const sortOrder =
			40 +
			(
				index *
				10
			);

		return `
			<div
				class="eilmo-cf-admin-repeater__item"
				data-eilmo-payment-method
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-payment-method-title
					>
						Payment Method
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div
					class="eilmo-cf-admin-grid"
				>

					<div class="eilmo-cf-admin-field">
						<label>
							Method Name
						</label>

						<input
							type="text"
							name="${prefix}[title]"
							value=""
							placeholder="e.g. bKash"
							data-eilmo-payment-method-name
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Method ID
						</label>

						<input
							type="text"
							name="${prefix}[id]"
							value=""
							placeholder="bkash"
							data-eilmo-payment-method-id
							data-auto-id="yes"
						>

						<p class="description">
							Leave empty to generate automatically from the method name.
						</p>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Account Label
						</label>

						<input
							type="text"
							name="${prefix}[account_label]"
							value=""
							placeholder="e.g. bKash Number"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Account Number / ID
						</label>

						<input
							type="text"
							name="${prefix}[account_value]"
							value=""
							placeholder="Account number or payment ID"
						>
					</div>

					<div
						class="
							eilmo-cf-admin-field
							eilmo-cf-admin-field--wide
						"
					>
						<label>
							Description
						</label>

						<textarea
							name="${prefix}[description]"
							rows="3"
							placeholder="Short description shown with the payment method."
						></textarea>
					</div>

					<div
						class="
							eilmo-cf-admin-field
							eilmo-cf-admin-field--wide
						"
					>
						<label>
							Payment Instructions
						</label>

						<textarea
							name="${prefix}[instructions]"
							rows="4"
							placeholder="Tell the customer how to complete the payment."
						></textarea>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Transaction ID Required
						</label>

						${buildSwitch(
							`${prefix}[transaction_id_required]`,
							false
						)}
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Transaction ID Label
						</label>

						<input
							type="text"
							name="${prefix}[transaction_id_label]"
							value="Transaction ID"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Accept Payment Screenshot
						</label>

						${buildSwitch(
							`${prefix}[payment_proof_enabled]`,
							true
						)}

						<p class="description">
							If Transaction ID and screenshot are enabled, either one is sufficient.
						</p>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Screenshot Label
						</label>

						<input
							type="text"
							name="${prefix}[payment_proof_label]"
							value="Payment Screenshot"
						>
					</div>

					<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
						<label>
							Screenshot Help Text
						</label>

						<textarea
							name="${prefix}[payment_proof_help]"
							rows="2"
						>Upload a JPG, PNG or WebP payment screenshot (maximum 5 MB).</textarea>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Maximum Screenshot Size (MB)
						</label>

						<input
							type="number"
							name="${prefix}[payment_proof_max_mb]"
							value="5"
							min="1"
							max="10"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Transaction ID Placeholder
						</label>

						<input
							type="text"
							name="${prefix}[transaction_id_placeholder]"
							value="Enter transaction ID"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Icon URL
						</label>

						<input
							type="url"
							name="${prefix}[icon_url]"
							value=""
							placeholder="https://example.com/icon.png"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Sort Order
						</label>

						<input
							type="number"
							name="${prefix}[sort_order]"
							value="${sortOrder}"
							min="0"
							step="1"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Enabled
						</label>

						${buildSwitch(
							`${prefix}[enabled]`,
							true
						)}
					</div>

				</div>

			</div>
		`;
	}

	/**
	 * Build the default required Special Discount end time.
	 *
	 * @return {string}
	 */
	function defaultSpecialOfferEndValue() {
		const date = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
		const pad = function (value) {
			return String(value).padStart(2, '0');
		};

		return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
	}

	/**
	 * Build Special Discount.
	 *
	 * @param {number} index Index.
	 *
	 * @return {string}
	 */
	function buildOrderBump(index) {
		const prefix = `eilmo_cf_settings[order_bumps][offers][${index}]`;
		return `
			<div class="eilmo-cf-admin-repeater__item eilmo-cf-admin-special-offer is-open" data-eilmo-special-offer-admin data-eilmo-order-bump>
				<div class="eilmo-cf-admin-repeater__item-header">
					<button type="button" class="button-link eilmo-cf-admin-special-offer__toggle" data-eilmo-special-offer-toggle aria-expanded="true"><span class="eilmo-cf-admin-special-offer__toggle-icon" data-eilmo-special-offer-toggle-icon aria-hidden="true">▼</span><strong data-eilmo-order-bump-title>Special Discount</strong><small class="eilmo-cf-admin-rule-summary">Enabled · Always → Percentage Discount</small></button>
					<button type="button" class="button-link-delete" data-eilmo-remove-repeater-item>Remove</button>
				</div>
				<div class="eilmo-cf-admin-special-offer__content" data-eilmo-special-offer-content>
					<div class="eilmo-cf-admin-grid">
						<div class="eilmo-cf-admin-field"><label>Rule Name</label><input type="text" name="${prefix}[title]" value="" placeholder="Buy 2 Get Free Delivery" data-eilmo-order-bump-title-input><input type="hidden" name="${prefix}[id]" value="" data-eilmo-order-bump-id data-auto-id="yes"></div>
						<div class="eilmo-cf-admin-field"><label>Enable</label>${buildSwitch(`${prefix}[enabled]`, true)}</div>
						<div class="eilmo-cf-admin-field"><label>Scope</label><select name="${prefix}[rule_scope]" data-eilmo-special-rule-scope><option value="current_product">Current Checkout Product</option><option value="selected_products">Selected Products</option><option value="whole_cart" selected>Whole Cart / Order</option></select></div>
						<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-special-scope-products hidden><label>Products</label><select class="wc-product-search" multiple="multiple" style="width:100%" name="${prefix}[scope_product_ids][]" data-placeholder="Search products…" data-action="woocommerce_json_search_products_and_variations"></select></div>
						<div class="eilmo-cf-admin-field"><label>Condition Type</label><select name="${prefix}[condition_type]" data-eilmo-special-condition-type><option value="always">Always / No minimum</option><option value="minimum_quantity">Minimum Quantity</option><option value="minimum_spend">Minimum Spend</option><option value="minimum_subtotal">Minimum Subtotal</option><option value="specific_product">Selected Product Exists</option></select></div>
						<div class="eilmo-cf-admin-field" data-eilmo-condition-cart-quantity hidden><label>Quantity</label><input type="number" min="1" step="1" name="${prefix}[condition_cart_quantity]" value="1"></div>
						<div class="eilmo-cf-admin-field" data-eilmo-condition-amount hidden><label data-eilmo-condition-amount-label>Minimum Amount</label><input type="number" min="0" step="0.01" name="${prefix}[condition_minimum]" value="0"></div>
						<div class="eilmo-cf-admin-field" data-eilmo-condition-product hidden><label>Required Product ID</label><input type="number" min="1" step="1" name="${prefix}[condition_product_id]" value="0"></div>
						<input type="hidden" name="${prefix}[condition_product_quantity]" value="1"><input type="hidden" name="${prefix}[condition_maximum]" value="0">
						<div class="eilmo-cf-admin-field"><label>Reward Type</label><select name="${prefix}[offer_type]" data-eilmo-special-offer-type><option value="percentage_discount">Percentage Discount</option><option value="fixed_discount">Fixed Amount Discount</option><option value="free_gift">Free Gift</option><option value="free_delivery">Free Delivery</option></select></div>
						<div class="eilmo-cf-admin-field" data-eilmo-special-pricing-value-field><label>Discount Percentage</label><input type="number" min="0" step="0.01" name="${prefix}[pricing_value]" value="0"></div>
						<input type="hidden" name="${prefix}[pricing_type]" value="percentage_discount"><input type="hidden" name="${prefix}[maximum_discount]" value="0">
						<div class="eilmo-cf-admin-field" data-eilmo-special-product-field hidden><label>Gift Product</label><select class="wc-product-search" style="width:100%" name="${prefix}[product_id]" data-placeholder="Search a gift product…" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select></div>
						<div class="eilmo-cf-admin-field" data-eilmo-special-product-field hidden><label>Gift Quantity</label><input type="number" min="1" step="1" name="${prefix}[quantity]" value="1"></div><input type="hidden" name="${prefix}[variation_id]" value="0">
						<div class="eilmo-cf-admin-field" data-eilmo-special-free-delivery-field hidden><label>Delivery Method</label><select name="${prefix}[free_delivery_method_id]"><option value="">All Delivery Methods</option></select><p class="description">Saved delivery methods appear after the first save. Empty means all methods.</p></div>
						<div class="eilmo-cf-admin-field" data-eilmo-special-free-delivery-field hidden><label>Hide Other Methods</label>${buildSwitch(`${prefix}[free_delivery_hide_other_methods]`, false)}</div>
						<div class="eilmo-cf-admin-field"><label>Start Date (Optional)</label><input type="datetime-local" name="${prefix}[start_at]"></div><div class="eilmo-cf-admin-field"><label>End Date (Optional)</label><input type="datetime-local" name="${prefix}[end_at]"></div>
						<div class="eilmo-cf-admin-field"><label>Priority</label><input type="number" min="0" step="1" name="${prefix}[sort_order]" value="${(index + 1) * 10}"></div>
						<input type="hidden" name="${prefix}[apply_behavior]" value="auto_apply"><input type="hidden" name="${prefix}[ineligible_action]" value="hide"><input type="hidden" name="${prefix}[stop_processing]" value="no">
					</div>
				</div>
			</div>`;
	}

	/**
	 * Build product row inside Combo Offer.
	 *
	 * @param {number} comboIndex Combo index.
	 * @param {number} itemIndex Item index.
	 *
	 * @return {string}
	 */
	function buildComboItem(
		comboIndex,
		itemIndex
	) {
		const prefix =
			`eilmo_cf_settings[combo_offers][offers][${comboIndex}][items][${itemIndex}]`;

		return `
			<div
				class="
					eilmo-cf-admin-repeater__item
					eilmo-cf-admin-combo__product
				"
				data-eilmo-combo-item
				data-eilmo-combo-item-index="${itemIndex}"
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>
					<strong
						data-eilmo-combo-item-title
					>
						Combo Product
					</strong>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>
				</div>

				<div class="eilmo-cf-admin-grid">

					<div class="eilmo-cf-admin-field">
						<label>
							Product ID
						</label>

						<input
							type="number"
							name="${prefix}[product_id]"
							value=""
							min="1"
							step="1"
							placeholder="123"
							data-eilmo-combo-product-id
						>

						<p class="description">
							Enter the WooCommerce parent/simple product ID.
						</p>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Variation ID
						</label>

						<input
							type="number"
							name="${prefix}[variation_id]"
							value="0"
							min="0"
							step="1"
							placeholder="0"
							data-eilmo-combo-variation-id
						>

						<p class="description">
							Use 0 for a simple product. For a variable product, enter the exact variation ID.
						</p>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>
							Quantity
						</label>

						<input
							type="number"
							name="${prefix}[quantity]"
							value="1"
							min="0.01"
							step="0.01"
						>
					</div>

					<div class="eilmo-cf-admin-field">
						<label>Image Source</label>

						<select
							name="${prefix}[image_source]"
							data-eilmo-combo-image-source
						>
							<option value="product">Product Image</option>
							<option value="custom">Custom Media Image</option>
							<option value="hidden">Hide Image</option>
						</select>
					</div>

					<div
						class="eilmo-cf-admin-field eilmo-cf-admin-field--wide"
						data-eilmo-combo-item-media-wrap
					>
						<label>Custom Product Image</label>

						<div class="eilmo-cf-admin-single-media" data-eilmo-combo-media-picker>
							<input type="hidden" name="${prefix}[image_id]" value="" data-eilmo-combo-media-id>
							<div class="eilmo-cf-admin-single-media__preview" data-eilmo-combo-media-preview></div>
							<div class="eilmo-cf-admin-single-media__actions">
								<button type="button" class="button button-secondary" data-eilmo-select-combo-media>Select Image</button>
								<button type="button" class="button button-link-delete" data-eilmo-clear-combo-media hidden>Remove</button>
							</div>
						</div>
					</div>

				</div>

			</div>
		`;
	}

	/**
	 * Build Combo Offer.
	 *
	 * Newly created Combo Offers start expanded.
	 *
	 * @param {number} index Combo index.
	 *
	 * @return {string}
	 */
	function buildComboOffer(index) {
		const prefix =
			`eilmo_cf_settings[combo_offers][offers][${index}]`;

		return `
			<div
				class="
					eilmo-cf-admin-repeater__item
					eilmo-cf-admin-combo
					is-open
				"
				data-eilmo-combo-offer
				data-eilmo-combo-index="${index}"
			>

				<div
					class="eilmo-cf-admin-repeater__item-header"
				>

					<button
						type="button"
						class="
							button-link
							eilmo-cf-admin-combo__toggle
						"
						data-eilmo-combo-toggle
						aria-expanded="true"
					>
						<span
							class="eilmo-cf-admin-combo__toggle-icon"
							data-eilmo-combo-toggle-icon
							aria-hidden="true"
						>
							▼
						</span>

						<strong
							data-eilmo-combo-title
						>
							Combo Offer
						</strong>
					</button>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>
						Remove
					</button>

				</div>

				<div
					class="eilmo-cf-admin-combo__content"
					data-eilmo-combo-content
				>

					<div class="eilmo-cf-admin-grid">

						<div class="eilmo-cf-admin-field">
							<label>
								Combo Title
							</label>

							<input
								type="text"
								name="${prefix}[title]"
								value=""
								placeholder="e.g. T-Shirt + Cap Combo"
								data-eilmo-combo-title-input
							>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Combo ID
							</label>

							<input
								type="text"
								name="${prefix}[id]"
								value=""
								placeholder="tshirt-cap-combo"
								data-eilmo-combo-id
								data-auto-id="yes"
							>

							<p class="description">
								Leave empty to generate automatically from the Combo Title.
							</p>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Badge
							</label>

							<input
								type="text"
								name="${prefix}[badge]"
								value=""
								placeholder="e.g. Best Deal"
							>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>Eyebrow Label</label>
							<input type="text" name="${prefix}[eyebrow]" value="COMBO OFFER" placeholder="COMBO OFFER">
						</div>

						<div class="eilmo-cf-admin-field">
							<label>Combo Icon</label>
							<select name="${prefix}[icon_type]" data-eilmo-combo-icon-type>
								<option value="preset">Preset SVG Icon</option>
								<option value="custom">Custom Media Icon</option>
								<option value="none">No Icon</option>
							</select>
						</div>

						<div class="eilmo-cf-admin-field" data-eilmo-combo-icon-preset-wrap>
							<label>SVG Icon</label>
							<select name="${prefix}[icon_preset]">
								<option value="flame">Flame</option>
								<option value="gift">Gift</option>
								<option value="star">Star</option>
								<option value="bolt">Lightning</option>
							</select>
						</div>

						<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-combo-icon-media-wrap>
							<label>Custom Icon</label>
							<div class="eilmo-cf-admin-single-media" data-eilmo-combo-media-picker>
								<input type="hidden" name="${prefix}[icon_media_id]" value="" data-eilmo-combo-media-id>
								<div class="eilmo-cf-admin-single-media__preview" data-eilmo-combo-media-preview></div>
								<div class="eilmo-cf-admin-single-media__actions">
									<button type="button" class="button button-secondary" data-eilmo-select-combo-media>Select Icon</button>
									<button type="button" class="button button-link-delete" data-eilmo-clear-combo-media hidden>Remove</button>
								</div>
							</div>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Pricing Type
							</label>

							<select
								name="${prefix}[pricing_type]"
								data-eilmo-combo-pricing-type
							>
								<option value="fixed_price">
									Fixed Combo Price
								</option>

								<option value="percentage_discount">
									Percentage Discount
								</option>

								<option value="fixed_discount">
									Fixed Discount
								</option>
							</select>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Pricing Value
							</label>

							<input
								type="number"
								name="${prefix}[pricing_value]"
								value="0"
								min="0"
								step="0.01"
							>

							<p class="description">
								Fixed Price = final combo price. Percentage = percent off. Fixed Discount = amount off.
							</p>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Sort Order
							</label>

							<input
								type="number"
								name="${prefix}[sort_order]"
								value="${(index + 1) * 10}"
								min="0"
								step="1"
							>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>
								Enabled
							</label>

							${buildSwitch(
								`${prefix}[enabled]`,
								true
							)}
						</div>

						<div
							class="
								eilmo-cf-admin-field
								eilmo-cf-admin-field--wide
							"
						>
							<label>
								Description
							</label>

							<textarea
								name="${prefix}[description]"
								rows="3"
								placeholder="Short description shown with this combo offer."
							></textarea>
						</div>

					</div>

					<div class="eilmo-cf-admin-combo__products">

						<div
							class="eilmo-cf-admin-repeater__heading"
						>

							<div>
								<h3>
									Combo Products
								</h3>

								<p>
									Add every product that belongs to this combo.
								</p>
							</div>

							<button
								type="button"
								class="button button-secondary"
								data-eilmo-add-combo-item
								data-eilmo-combo-index="${index}"
							>
								Add Product
							</button>

						</div>

						<div
							class="eilmo-cf-admin-repeater__items"
							data-eilmo-combo-items
						></div>

						<p
							class="description"
							data-eilmo-combo-items-empty
						>
							Add at least one product to this combo.
						</p>

					</div>

				</div>

			</div>
		`;
	}

	/**
	 * Set Combo Offer accordion state.
	 *
	 * @param {HTMLElement} combo Combo.
	 * @param {boolean} open Open state.
	 *
	 * @return {void}
	 */
	function setComboOpenState(
		combo,
		open
	) {
		const toggle =
			combo.querySelector(
				SELECTORS.comboToggle
			);

		const content =
			combo.querySelector(
				SELECTORS.comboContent
			);

		if (
			!(
				toggle instanceof
					HTMLElement
			) ||
			!(
				content instanceof
					HTMLElement
			)
		) {
			return;
		}

		toggle.setAttribute(
			'aria-expanded',
			open
				? 'true'
				: 'false'
		);

		content.hidden =
			!open;

		combo.classList.toggle(
			'is-open',
			open
		);

		const icon =
			toggle.querySelector(
				SELECTORS.comboToggleIcon
			);

		if (
			icon instanceof
				HTMLElement
		) {
			icon.textContent =
				open
					? '▼'
					: '▶';
		}
	}

	/**
	 * Toggle Combo Offer content.
	 *
	 * @param {HTMLElement} button Toggle button.
	 *
	 * @return {void}
	 */
	function toggleComboOffer(button) {
		const combo =
			button.closest(
				SELECTORS.comboItem
			);

		if (
			!(
				combo instanceof
					HTMLElement
			)
		) {
			return;
		}

		const isOpen =
			button.getAttribute(
				'aria-expanded'
			) === 'true';

		setComboOpenState(
			combo,
			!isOpen
		);
	}

	/**
	 * Escape string for regular expression.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function escapeRegExp(value) {
		return String(value)
			.replace(
				/[.*+?^${}()|[\]\\]/g,
				'\\$&'
			);
	}

	/**
	 * Re-index a normal top-level repeater.
	 *
	 * @param {HTMLElement} container Container.
	 * @param {string} collection Collection name.
	 *
	 * @return {void}
	 */
	function reindex(
		container,
		collection
	) {
		const expression =
			new RegExp(
				`(${escapeRegExp(collection)})\\[\\d+\\]`
			);

		getDirectChildren(
			container,
			SELECTORS.item
		).forEach(
			function (
				item,
				index
			) {
				item
					.querySelectorAll(
						'[name]'
					)
					.forEach(
						function (field) {
							const name =
								field.getAttribute(
									'name'
								);

							if (!name) {
								return;
							}

							field.setAttribute(
								'name',
								name.replace(
									expression,
									`$1[${index}]`
								)
							);
						}
					);
			}
		);
	}

	/**
	 * Update Combo Offer index in all nested names.
	 *
	 * @param {HTMLElement} combo Combo.
	 * @param {number} comboIndex Combo index.
	 *
	 * @return {void}
	 */
	function updateComboFieldIndex(
		combo,
		comboIndex
	) {
		const expression =
			/eilmo_cf_settings\[combo_offers\]\[offers\]\[\d+\]/;

		combo
			.querySelectorAll(
				'[name]'
			)
			.forEach(
				function (field) {
					const name =
						field.getAttribute(
							'name'
						);

					if (!name) {
						return;
					}

					field.setAttribute(
						'name',
						name.replace(
							expression,
							`eilmo_cf_settings[combo_offers][offers][${comboIndex}]`
						)
					);
				}
			);
	}

	/**
	 * Ensure Combo Product empty message exists and
	 * update visibility.
	 *
	 * @param {HTMLElement} combo Combo.
	 *
	 * @return {void}
	 */
	function updateComboItemsEmptyState(
		combo
	) {
		const container =
			combo.querySelector(
				SELECTORS.comboItemsContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		let notice =
			combo.querySelector(
				'[data-eilmo-combo-items-empty]'
			);

		if (
			!(
				notice instanceof
					HTMLElement
			)
		) {
			notice =
				document.createElement(
					'p'
				);

			notice.className =
				'description';

			notice.dataset
				.eilmoComboItemsEmpty =
					'yes';

			notice.textContent =
				'Add at least one product to this combo.';

			container.insertAdjacentElement(
				'afterend',
				notice
			);
		}

		notice.hidden =
			getDirectChildren(
				container,
				SELECTORS.comboProductItem
			).length > 0;
	}

	/**
	 * Update global Combo Offer empty message.
	 *
	 * @return {void}
	 */
	function updateComboEmptyState() {
		const container =
			document.querySelector(
				SELECTORS.comboContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		let notice =
			document.querySelector(
				'[data-eilmo-combo-empty-notice]'
			);

		if (
			!(
				notice instanceof
					HTMLElement
			)
		) {
			notice =
				document.createElement(
					'div'
				);

			notice.className =
				'notice notice-info inline';

			notice.dataset
				.eilmoComboEmptyNotice =
					'yes';

			const paragraph =
				document.createElement(
					'p'
				);

			paragraph.textContent =
				'No combo offers have been created yet. Click Add Combo Offer to create your first reusable offer.';

			notice.appendChild(
				paragraph
			);

			container.insertAdjacentElement(
				'afterend',
				notice
			);
		}

		notice.hidden =
			getDirectChildren(
				container,
				SELECTORS.comboItem
			).length > 0;
	}

	/**
	 * Re-index Combo Product rows.
	 *
	 * @param {HTMLElement} combo Combo.
	 * @param {number} comboIndex Combo index.
	 *
	 * @return {void}
	 */
	function reindexComboItems(
		combo,
		comboIndex
	) {
		const container =
			combo.querySelector(
				SELECTORS.comboItemsContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		const expression =
			/eilmo_cf_settings\[combo_offers\]\[offers\]\[\d+\]\[items\]\[\d+\]/;

		getDirectChildren(
			container,
			SELECTORS.comboProductItem
		).forEach(
			function (
				item,
				itemIndex
			) {
				item.dataset
					.eilmoComboItemIndex =
						String(
							itemIndex
						);

				item
					.querySelectorAll(
						'[name]'
					)
					.forEach(
						function (field) {
							const name =
								field.getAttribute(
									'name'
								);

							if (!name) {
								return;
							}

							field.setAttribute(
								'name',
								name.replace(
									expression,
									`eilmo_cf_settings[combo_offers][offers][${comboIndex}][items][${itemIndex}]`
								)
							);
						}
					);
			}
		);

		updateComboItemsEmptyState(
			combo
		);
	}

	/**
	 * Re-index Combo Offers and nested products.
	 *
	 * @return {void}
	 */
	function reindexComboOffers() {
		const container =
			document.querySelector(
				SELECTORS.comboContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		getDirectChildren(
			container,
			SELECTORS.comboItem
		).forEach(
			function (
				combo,
				comboIndex
			) {
				combo.dataset
					.eilmoComboIndex =
						String(
							comboIndex
						);

				const addProduct =
					combo.querySelector(
						SELECTORS.addComboItem
					);

				if (
					addProduct instanceof
						HTMLElement
				) {
					addProduct.dataset
						.eilmoComboIndex =
							String(
								comboIndex
							);
				}

				updateComboFieldIndex(
					combo,
					comboIndex
				);

				reindexComboItems(
					combo,
					comboIndex
				);
			}
		);

		updateComboEmptyState();
	}

	/**
	 * Update Order Bump empty message.
	 *
	 * @return {void}
	 */
	function updateOrderBumpEmptyState() {
		const container =
			document.querySelector(
				SELECTORS.orderBumpContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		let notice =
			document.querySelector(
				'[data-eilmo-order-bump-empty]'
			);

		if (
			!(
				notice instanceof
					HTMLElement
			)
		) {
			notice =
				document.createElement(
					'div'
				);

			notice.className =
				'notice notice-info inline';

			notice.dataset
				.eilmoOrderBumpEmpty =
					'yes';

			const paragraph =
				document.createElement(
					'p'
				);

			paragraph.textContent =
				'No Special Discounts have been created yet. Click Add Special Discount to create your first rule.';

			notice.appendChild(
				paragraph
			);

			container.insertAdjacentElement(
				'afterend',
				notice
			);
		}

		notice.hidden =
			getDirectChildren(
				container,
				SELECTORS.orderBumpItem
			).length > 0;
	}

	/**
	 * Re-index Order Bumps.
	 *
	 * @return {void}
	 */
	function reindexOrderBumps() {
		const container =
			document.querySelector(
				SELECTORS.orderBumpContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		const expression =
			/eilmo_cf_settings\[order_bumps\]\[offers\]\[\d+\]/;

		getDirectChildren(
			container,
			SELECTORS.orderBumpItem
		).forEach(
			function (
				item,
				index
			) {
				item
					.querySelectorAll(
						'[name]'
					)
					.forEach(
						function (field) {
							const name =
								field.getAttribute(
									'name'
								);

							if (!name) {
								return;
							}

							field.setAttribute(
								'name',
								name.replace(
									expression,
									`eilmo_cf_settings[order_bumps][offers][${index}]`
								)
							);
						}
					);
			}
		);

		updateOrderBumpEmptyState();
	}

	/**
	 * Re-index normal container.
	 *
	 * @param {HTMLElement} container Container.
	 *
	 * @return {void}
	 */
	function reindexContainer(
		container
	) {
		if (
			container.matches(
				SELECTORS.deliveryContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[delivery][methods]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.advanceContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[advance_payment][rules]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.discountContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[discounts][automatic_rules]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.couponContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[coupons][custom_coupons]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.paymentContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[payment_methods][custom_methods]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.galleryContainer
			)
		) {
			reindex(
				container,
				'eilmo_cf_settings[product_gallery][galleries]'
			);

			return;
		}

		if (
			container.matches(
				SELECTORS.orderBumpContainer
			)
		) {
			reindexOrderBumps();
		}
	}

	/**
	 * Re-index all repeaters before save.
	 *
	 * @return {void}
	 */
	function reindexAll() {
		document
			.querySelectorAll(
				[
					SELECTORS.deliveryContainer,
					SELECTORS.advanceContainer,
					SELECTORS.discountContainer,
					SELECTORS.couponContainer,
					SELECTORS.paymentContainer,
					SELECTORS.orderBumpContainer,
					SELECTORS.galleryContainer,
				].join(',')
			)
			.forEach(
				function (container) {
					if (
						container instanceof
							HTMLElement
					) {
						reindexContainer(
							container
						);
					}
				}
			);

		reindexComboOffers();

		reindexOrderBumps();
	}

	/**
	 * Get payment method ID input.
	 *
	 * @param {HTMLElement} item Payment item.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getPaymentMethodIdInput(
		item
	) {
		const direct =
			item.querySelector(
				SELECTORS.paymentId
			);

		if (
			direct instanceof
				HTMLInputElement
		) {
			return direct;
		}

		const fallback =
			item.querySelector(
				'input[name$="[id]"]'
			);

		return fallback instanceof
			HTMLInputElement
				? fallback
				: null;
	}

	/**
	 * Get Combo ID input.
	 *
	 * @param {HTMLElement} combo Combo.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getComboIdInput(
		combo
	) {
		const direct =
			combo.querySelector(
				SELECTORS.comboId
			);

		if (
			direct instanceof
				HTMLInputElement
		) {
			return direct;
		}

		const candidates =
			combo.querySelectorAll(
				'input[name$="[id]"]'
			);

		for (
			let index = 0;
			index < candidates.length;
			index += 1
		) {
			const candidate =
				candidates[index];

			if (
				candidate instanceof
					HTMLInputElement &&
				!candidate.closest(
					SELECTORS.comboProductItem
				)
			) {
				return candidate;
			}
		}

		return null;
	}

	/**
	 * Get Order Bump ID input.
	 *
	 * @param {HTMLElement} item Order Bump item.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getOrderBumpIdInput(
		item
	) {
		const direct =
			item.querySelector(
				SELECTORS.orderBumpId
			);

		if (
			direct instanceof
				HTMLInputElement
		) {
			return direct;
		}

		const fallback =
			item.querySelector(
				'input[name$="[id]"]'
			);

		return fallback instanceof
			HTMLInputElement
				? fallback
				: null;
	}

	/**
	 * Synchronize custom payment methods with the
	 * default payment method select.
	 *
	 * @return {void}
	 */
	function syncPaymentDefaultOptions() {
		const select =
			document.querySelector(
				SELECTORS.defaultPaymentMethod
			);

		if (
			!(
				select instanceof
					HTMLSelectElement
			)
		) {
			return;
		}

		const currentValue =
			select.value;

		const fixedValues =
			new Set(
				[
					'first_available',
					'cash_on_delivery',
					'bank_transfer',
				]
			);

		Array.from(
			select.options
		).forEach(
			function (option) {
				if (
					!fixedValues.has(
						option.value
					)
				) {
					option.remove();
				}
			}
		);

		const usedIds =
			new Set();

		document
			.querySelectorAll(
				SELECTORS.paymentItem
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

					const nameInput =
						item.querySelector(
							SELECTORS.paymentName
						);

					const idInput =
						getPaymentMethodIdInput(
							item
						);

					if (
						!(
							nameInput instanceof
								HTMLInputElement
						) ||
						!(
							idInput instanceof
								HTMLInputElement
						)
					) {
						return;
					}

					const title =
						nameInput.value.trim();

					const id =
						idInput.value.trim();

					if (
						!title ||
						!id ||
						usedIds.has(id)
					) {
						return;
					}

					usedIds.add(
						id
					);

					const option =
						document.createElement(
							'option'
						);

					option.value =
						id;

					option.textContent =
						title;

					option.dataset
						.eilmoCustomPaymentOption =
							'yes';

					select.appendChild(
						option
					);
				}
			);

		const valueExists =
			Array.from(
				select.options
			).some(
				function (option) {
					return (
						option.value ===
							currentValue
					);
				}
			);

		select.value =
			valueExists
				? currentValue
				: 'first_available';
	}

	/**
	 * Add delivery method.
	 *
	 * @return {void}
	 */
	function addDeliveryMethod() {
		const container =
			document.querySelector(
				SELECTORS.deliveryContainer
			);

		if (
			container instanceof
				HTMLElement
		) {
			appendItem(
				container,
				buildDeliveryMethod(
					getNextIndex(
						container
					)
				)
			);
		}
	}

	/**
	 * Add advance rule.
	 *
	 * @return {void}
	 */
	function addAdvanceRule() {
		const container =
			document.querySelector(
				SELECTORS.advanceContainer
			);

		if (
			container instanceof
				HTMLElement
		) {
			appendItem(
				container,
				buildAdvanceRule(
					getNextIndex(
						container
					)
				)
			);
		}
	}

	/**
	 * Add discount rule.
	 *
	 * @return {void}
	 */
	function addDiscountRule() {
		const container =
			document.querySelector(
				SELECTORS.discountContainer
			);

		if (
			container instanceof
				HTMLElement
		) {
			appendItem(
				container,
				buildDiscountRule(
					getNextIndex(
						container
					)
				)
			);
		}
	}

	/**
	 * Add coupon.
	 *
	 * @return {void}
	 */
	function addCoupon() {
		const container =
			document.querySelector(
				SELECTORS.couponContainer
			);

		if (
			container instanceof
				HTMLElement
		) {
			appendItem(
				container,
				buildCoupon(
					getNextIndex(
						container
					)
				)
			);
		}
	}

	/**
	 * Add custom payment method.
	 *
	 * @return {void}
	 */
	function addPaymentMethod() {
		const container =
			document.querySelector(
				SELECTORS.paymentContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		appendItem(
			container,
			buildPaymentMethod(
				getNextIndex(
					container
				)
			)
		);

		syncPaymentDefaultOptions();
	}

	/**
	 * Add Order Bump.
	 *
	 * @return {void}
	 */
	function addOrderBump() {
		const container =
			document.querySelector(
				SELECTORS.orderBumpContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		reindexOrderBumps();

		const index =
			getDirectChildren(
				container,
				SELECTORS.orderBumpItem
			).length;

		const item =
			appendItem(
				container,
				buildOrderBump(
					index
				)
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const idInput =
			getOrderBumpIdInput(
				item
			);

		if (idInput) {
			idInput.dataset.autoId =
				'yes';
		}

		reindexOrderBumps();

		window.setTimeout(function () {
			syncSpecialDiscountStyleControls(false);
		}, 0);
	}

	/**
	 * Add Combo Offer with first product row.
	 *
	 * Newly created Combo remains open.
	 *
	 * @return {void}
	 */
	function addComboOffer() {
		const container =
			document.querySelector(
				SELECTORS.comboContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		reindexComboOffers();

		const comboIndex =
			getDirectChildren(
				container,
				SELECTORS.comboItem
			).length;

		const combo =
			appendItem(
				container,
				buildComboOffer(
					comboIndex
				)
			);

		if (
			!(
				combo instanceof
					HTMLElement
			)
		) {
			return;
		}

		const itemsContainer =
			combo.querySelector(
				SELECTORS.comboItemsContainer
			);

		if (
			itemsContainer instanceof
				HTMLElement
		) {
			appendItem(
				itemsContainer,
				buildComboItem(
					comboIndex,
					0
				)
			);
		}

		setComboOpenState(
			combo,
			true
		);

		syncComboMediaControls(combo);

		reindexComboOffers();
	}

	/**
	 * Add product to Combo Offer.
	 *
	 * @param {HTMLElement} button Add button.
	 *
	 * @return {void}
	 */
	function addComboItem(button) {
		const combo =
			button.closest(
				SELECTORS.comboItem
			);

		if (
			!(
				combo instanceof
					HTMLElement
			)
		) {
			return;
		}

		reindexComboOffers();

		const container =
			combo.querySelector(
				SELECTORS.comboItemsContainer
			);

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		const comboIndex =
			Number.parseInt(
				combo.dataset
					.eilmoComboIndex ||
					'0',
				10
			);

		const itemIndex =
			getDirectChildren(
				container,
				SELECTORS.comboProductItem
			).length;

		const item = appendItem(
			container,
			buildComboItem(
				Number.isFinite(
					comboIndex
				)
					? comboIndex
					: 0,
				itemIndex
			)
		);

		if (item instanceof HTMLElement) {
			syncComboMediaControls(item);
		}

		setComboOpenState(
			combo,
			true
		);

		reindexComboOffers();
	}

	/**
	 * Remove repeater item.
	 *
	 * @param {HTMLElement} button Remove button.
	 *
	 * @return {void}
	 */
	function removeItem(button) {
		const comboProduct =
			button.closest(
				SELECTORS.comboProductItem
			);

		if (
			comboProduct instanceof
				HTMLElement
		) {
			const combo =
				comboProduct.closest(
					SELECTORS.comboItem
				);

			comboProduct.remove();

			reindexComboOffers();

			if (
				combo instanceof
					HTMLElement
			) {
				updateComboItemsEmptyState(
					combo
				);
			}

			return;
		}

		const combo =
			button.closest(
				SELECTORS.comboItem
			);

		if (
			combo instanceof
				HTMLElement
		) {
			combo.remove();

			reindexComboOffers();

			return;
		}

		const item =
			button.closest(
				SELECTORS.item
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const container =
			item.parentElement;

		if (
			!(
				container instanceof
					HTMLElement
			)
		) {
			return;
		}

		const paymentContainer =
			container.matches(
				SELECTORS.paymentContainer
			);

		const orderBumpContainer =
			container.matches(
				SELECTORS.orderBumpContainer
			);

		const galleryContainer =
			container.matches(
				SELECTORS.galleryContainer
			);

		item.remove();

		reindexContainer(
			container
		);

		if (
			paymentContainer
		) {
			syncPaymentDefaultOptions();
		}

		if (
			orderBumpContainer
		) {
			updateOrderBumpEmptyState();
		}

		if (galleryContainer) {
			updateGalleryEmptyState();
		}
	}

	/**
	 * Update delivery title and generated ID.
	 *
	 * @param {HTMLInputElement} input Name input.
	 *
	 * @return {void}
	 */
	function handleDeliveryLabel(input) {
		const item =
			input.closest(
				SELECTORS.item
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				'[data-eilmo-item-title]'
			);

		const idInput =
			item.querySelector(
				'[data-eilmo-delivery-id]'
			);

		const label =
			input.value.trim();

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				label ||
				'Delivery Method';
		}

		if (
			idInput instanceof
				HTMLInputElement &&
			idInput.dataset.autoId ===
				'yes'
		) {
			idInput.value =
				slugify(
					label
				);
		}
	}

	/**
	 * Stop automatic delivery ID updates.
	 *
	 * @param {HTMLInputElement} input ID input.
	 *
	 * @return {void}
	 */
	function handleDeliveryId(input) {
		input.dataset.autoId =
			input.value.trim() ===
				''
				? 'yes'
				: 'no';
	}

	/**
	 * Update payment method title and generated ID.
	 *
	 * @param {HTMLInputElement} input Name input.
	 *
	 * @return {void}
	 */
	function handlePaymentMethodName(
		input
	) {
		const item =
			input.closest(
				SELECTORS.paymentItem
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				SELECTORS.paymentTitle
			);

		const value =
			input.value.trim();

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				value ||
				'Payment Method';
		}

		const idInput =
			getPaymentMethodIdInput(
				item
			);

		if (
			idInput &&
			idInput.dataset.autoId ===
				'yes'
		) {
			idInput.value =
				slugify(
					value
				);
		}

		syncPaymentDefaultOptions();
	}

	/**
	 * Stop automatic payment method ID updates.
	 *
	 * @param {HTMLInputElement} input ID input.
	 *
	 * @return {void}
	 */
	function handlePaymentMethodId(
		input
	) {
		input.dataset.autoId =
			input.value.trim() ===
				''
				? 'yes'
				: 'no';

		syncPaymentDefaultOptions();
	}

	/**
	 * Update Combo title and generated ID.
	 *
	 * @param {HTMLInputElement} input Title input.
	 *
	 * @return {void}
	 */
	function handleComboTitle(input) {
		const combo =
			input.closest(
				SELECTORS.comboItem
			);

		if (
			!(
				combo instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			combo.querySelector(
				SELECTORS.comboTitle
			);

		const value =
			input.value.trim();

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				value ||
				'Combo Offer';
		}

		const idInput =
			getComboIdInput(
				combo
			);

		if (
			idInput &&
			idInput.dataset.autoId ===
				'yes'
		) {
			idInput.value =
				slugify(
					value
				);
		}
	}

	/**
	 * Stop automatic Combo ID updates.
	 *
	 * @param {HTMLInputElement} input ID input.
	 *
	 * @return {void}
	 */
	function handleComboId(input) {
		input.dataset.autoId =
			input.value.trim() ===
				''
				? 'yes'
				: 'no';
	}

	/**
	 * Update Order Bump title and generated ID.
	 *
	 * @param {HTMLInputElement} input Title input.
	 *
	 * @return {void}
	 */
	function handleOrderBumpTitle(
		input
	) {
		const item =
			input.closest(
				SELECTORS.orderBumpItem
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				SELECTORS.orderBumpTitle
			);

		const value =
			input.value.trim();

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				value ||
					'Special Offer';
		}

		const idInput =
			getOrderBumpIdInput(
				item
			);

		if (
			idInput &&
			idInput.dataset.autoId ===
				'yes'
		) {
			idInput.value =
				slugify(
					value
				);
		}
	}

	/**
	 * Stop automatic Order Bump ID updates.
	 *
	 * @param {HTMLInputElement} input ID input.
	 *
	 * @return {void}
	 */
	function handleOrderBumpId(
		input
	) {
		input.dataset.autoId =
			input.value.trim() ===
				''
				? 'yes'
				: 'no';
	}

	/**
	 * Update Combo Product title using entered IDs.
	 *
	 * @param {HTMLInputElement} input ID input.
	 *
	 * @return {void}
	 */
	function handleComboProductIdentity(
		input
	) {
		const item =
			input.closest(
				SELECTORS.comboProductItem
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				'[data-eilmo-combo-item-title]'
			);

		if (
			!(
				title instanceof
					HTMLElement
			)
		) {
			return;
		}

		const productInput =
			item.querySelector(
				SELECTORS.comboProductId
			);

		const variationInput =
			item.querySelector(
				SELECTORS.comboVariationId
			);

		const productId =
			productInput instanceof
				HTMLInputElement
					? Number.parseInt(
						productInput.value ||
							'0',
						10
					)
					: 0;

		const variationId =
			variationInput instanceof
				HTMLInputElement
					? Number.parseInt(
						variationInput.value ||
							'0',
						10
					)
					: 0;

		if (
			Number.isFinite(
				variationId
			) &&
			variationId > 0
		) {
			title.textContent =
				`Variation #${variationId}`;

			return;
		}

		if (
			Number.isFinite(
				productId
			) &&
			productId > 0
		) {
			title.textContent =
				`Product #${productId}`;

			return;
		}

		title.textContent =
			'Combo Product';
	}

	/**
	 * Update rule title.
	 *
	 * @param {HTMLInputElement} input Rule name.
	 *
	 * @return {void}
	 */
	function handleRuleName(input) {
		const item =
			input.closest(
				SELECTORS.item
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				'[data-eilmo-item-title]'
			);

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				input.value.trim() ||
				'Rule';
		}
	}

	/**
	 * Update coupon title.
	 *
	 * @param {HTMLInputElement} input Coupon code.
	 *
	 * @return {void}
	 */
	function handleCouponCode(input) {
		const item =
			input.closest(
				SELECTORS.item
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const title =
			item.querySelector(
				'[data-eilmo-item-title]'
			);

		if (
			title instanceof
				HTMLElement
		) {
			title.textContent =
				input.value
					.trim()
					.toUpperCase() ||
				'Coupon';
		}
	}

	/**
	 * Toggle value field.
	 *
	 * @param {HTMLSelectElement} select Type select.
	 *
	 * @return {void}
	 */
	function updateValueField(select) {
		const item =
			select.closest(
				SELECTORS.item
			);

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const field =
			item.querySelector(
				'[data-eilmo-value-field]'
			);

		if (
			!(
				field instanceof
					HTMLElement
			)
		) {
			return;
		}

		field.hidden =
			[
				'free_delivery',
				'full_payment',
				'no_advance',
			].includes(
				select.value
			);
	}

	/**
	 * Initialize custom payment IDs.
	 *
	 * @return {void}
	 */
	function initializePaymentMethodIds() {
		document
			.querySelectorAll(
				SELECTORS.paymentItem
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

					const idInput =
						getPaymentMethodIdInput(
							item
						);

					if (!idInput) {
						return;
					}

					if (
						typeof idInput
							.dataset
							.autoId !==
								'undefined'
					) {
						return;
					}

					idInput.dataset.autoId =
						idInput.value
							.trim() ===
								''
							? 'yes'
							: 'no';
				}
			);
	}

	/**
	 * Initialize existing Order Bumps.
	 *
	 * @return {void}
	 */
	function initializeOrderBumps() {
		document
			.querySelectorAll(
				SELECTORS.orderBumpItem
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

					const titleInput =
						item.querySelector(
							SELECTORS.orderBumpTitleInput
						);

					const title =
						item.querySelector(
							SELECTORS.orderBumpTitle
						);

					if (
						title instanceof
							HTMLElement &&
						titleInput instanceof
							HTMLInputElement
					) {
						title.textContent =
							titleInput.value
								.trim() ||
								'Special Offer';
					}

					const idInput =
						getOrderBumpIdInput(
							item
						);

					if (
						idInput &&
						typeof idInput
							.dataset
							.autoId ===
								'undefined'
					) {
						idInput.dataset.autoId =
							idInput.value
								.trim() ===
									''
								? 'yes'
								: 'no';
					}
				}
			);

		reindexOrderBumps();
	}

	/**
	 * Synchronize conditional Combo icon and product-image controls.
	 *
	 * @param {ParentNode} root Scope.
	 *
	 * @return {void}
	 */
	function syncComboMediaControls(root) {
		const scope = root && typeof root.querySelectorAll === 'function'
			? root
			: document;

		const combos = Array.from(scope.querySelectorAll(SELECTORS.comboItem));
		const products = Array.from(scope.querySelectorAll(SELECTORS.comboProductItem));

		if (scope instanceof Element && scope.matches(SELECTORS.comboItem)) {
			combos.unshift(scope);
		}

		if (scope instanceof Element && scope.matches(SELECTORS.comboProductItem)) {
			products.unshift(scope);
		}

		combos.forEach(function (combo) {
			if (!(combo instanceof HTMLElement)) {
				return;
			}

			const type = combo.querySelector(SELECTORS.comboIconType);
			const preset = combo.querySelector(SELECTORS.comboIconPresetWrap);
			const media = combo.querySelector(SELECTORS.comboIconMediaWrap);

			if (type instanceof HTMLSelectElement) {
				if (preset instanceof HTMLElement) {
					preset.hidden = type.value !== 'preset';
				}

				if (media instanceof HTMLElement) {
					media.hidden = type.value !== 'custom';
				}
			}
		});

		products.forEach(function (item) {
			if (!(item instanceof HTMLElement)) {
				return;
			}

			const source = item.querySelector(SELECTORS.comboImageSource);
			const media = item.querySelector(SELECTORS.comboItemMediaWrap);

			if (source instanceof HTMLSelectElement && media instanceof HTMLElement) {
				media.hidden = source.value !== 'custom';
			}
		});
	}

	/**
	 * Select one image from the WordPress Media Library.
	 *
	 * @param {HTMLElement} button Select button.
	 *
	 * @return {void}
	 */
	function selectComboMedia(button) {
		const picker = button.closest(SELECTORS.comboMediaPicker);

		if (
			!(picker instanceof HTMLElement) ||
			!window.wp ||
			!window.wp.media
		) {
			return;
		}

		const frame = window.wp.media({
			title: 'Select Combo Image',
			button: { text: 'Use This Image' },
			library: { type: 'image' },
			multiple: false,
		});

		frame.on('select', function () {
			const attachment = frame.state().get('selection').first();

			if (!attachment) {
				return;
			}

			const data = attachment.toJSON();
			const field = picker.querySelector(SELECTORS.comboMediaId);
			const preview = picker.querySelector(SELECTORS.comboMediaPreview);
			const clear = picker.querySelector(SELECTORS.clearComboMedia);
			const url = data.sizes && data.sizes.thumbnail
				? data.sizes.thumbnail.url
				: data.url;

			if (field instanceof HTMLInputElement) {
				field.value = String(data.id || '');
			}

			if (preview instanceof HTMLElement) {
				preview.innerHTML = '';
				if (url) {
					const image = document.createElement('img');
					image.src = String(url);
					image.alt = '';
					preview.appendChild(image);
				}
			}

			if (clear instanceof HTMLButtonElement) {
				clear.hidden = false;
			}
		});

		frame.open();
	}

	/**
	 * Clear one selected Combo media image.
	 *
	 * @param {HTMLElement} button Clear button.
	 *
	 * @return {void}
	 */
	function clearComboMedia(button) {
		const picker = button.closest(SELECTORS.comboMediaPicker);

		if (!(picker instanceof HTMLElement)) {
			return;
		}

		const field = picker.querySelector(SELECTORS.comboMediaId);
		const preview = picker.querySelector(SELECTORS.comboMediaPreview);

		if (field instanceof HTMLInputElement) {
			field.value = '';
		}

		if (preview instanceof HTMLElement) {
			preview.innerHTML = '';
		}

		if (button instanceof HTMLButtonElement) {
			button.hidden = true;
		}
	}

	/**
	 * Keep the Combo style preview synchronized without saving.
	 *
	 * @return {void}
	 */
	function syncComboStylePreview() {
		const section = document.querySelector(SELECTORS.comboStyle);
		const preview = document.querySelector(SELECTORS.comboStylePreview);

		if (!(section instanceof HTMLElement) || !(preview instanceof HTMLElement)) {
			return;
		}

		section.querySelectorAll('[data-eilmo-combo-style-color]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-combo-style-color') || '';
			if (!key) {
				return;
			}

			preview.style.setProperty(
				`--eilmo-cf-combo-${key.replace(/_/g, '-')}`,
				input.value
			);

			const code = input.parentElement
				? input.parentElement.querySelector('code')
				: null;

			if (code instanceof HTMLElement) {
				code.textContent = input.value;
			}
		});

		section.querySelectorAll('[data-eilmo-combo-style-number]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-combo-style-number') || '';
			if (key) {
				preview.style.setProperty(
					`--eilmo-cf-combo-${key.replace(/_/g, '-')}`,
					`${input.value || 0}px`
				);
			}
		});
	}

	/**
	 * Keep the Special Offer preview synchronized without saving.
	 *
	 * @return {void}
	 */
	function syncSpecialStylePreview(styleChanged) {
		const section = document.querySelector(SELECTORS.specialStyle);
		const previews = section instanceof HTMLElement
			? Array.from(section.querySelectorAll(SELECTORS.specialStylePreview))
			: [];
		const stage = document.querySelector(SELECTORS.specialPreviewStage);

		if (!(section instanceof HTMLElement) || !(stage instanceof HTMLElement) || !previews.length) {
			return;
		}

		section.querySelectorAll('[data-eilmo-special-style-color]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-special-style-color') || '';
			if (!key) {
				return;
			}

			stage.style.setProperty(
				`--eilmo-so-${key.replace(/_/g, '-')}`,
				input.value
			);

			const code = input.parentElement
				? input.parentElement.querySelector('code')
				: null;

			if (code instanceof HTMLElement) {
				code.textContent = input.value;
			}
		});

		section.querySelectorAll('[data-eilmo-special-style-number]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-special-style-number') || '';
			if (key) {
				const unit = key.endsWith('_angle') ? 'deg' : 'px';
				stage.style.setProperty(
					`--eilmo-so-${key.replace(/_/g, '-')}`,
					`${input.value || 0}${unit}`
				);
			}
		});

		const surfaceDefinitions = {
			'card': ['background_type', 'gradient_start', 'gradient_end', 'gradient_angle'],
			'selected-card': ['selected_background_type', 'selected_gradient_start', 'selected_gradient_end', 'selected_gradient_angle'],
			'selected-border': ['selected_border_type', 'selected_border_start', 'selected_border_end', 'selected_border_angle'],
			'badge': ['badge_background_type', 'badge_background', 'badge_end', 'badge_gradient_angle'],
			'selected-badge': ['selected_badge_background_type', 'selected_badge_background', 'selected_badge_end', 'selected_badge_gradient_angle'],
			'button': ['button_background_type', 'button_background', 'button_background_end', 'button_gradient_angle'],
			'added': ['added_background_type', 'added_background', 'added_background_end', 'added_gradient_angle'],
			'progress': ['progress_background_type', 'progress_background', 'progress_background_end', 'progress_gradient_angle'],
			'promo-icon': ['promo_icon_background_type', 'image_background', 'image_background_end', 'icon_gradient_angle'],
			'icon': ['icon_background_type', 'image_background', 'image_background_end', 'icon_gradient_angle'],
			'selected-icon': ['selected_icon_background_type', 'selected_icon_background', 'selected_icon_background_end', 'selected_icon_gradient_angle']
		};
		const values = {};
		section.querySelectorAll('[data-eilmo-special-style-color], [data-eilmo-special-style-number], [data-eilmo-special-style-surface-type]').forEach(function (control) {
			if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement)) {
				return;
			}
			const key = control.getAttribute('data-eilmo-special-style-color') ||
				control.getAttribute('data-eilmo-special-style-number') ||
				control.getAttribute('data-eilmo-special-style-surface-type') || '';
			if (key) {
				values[key] = control.value;
			}
		});
		Object.entries(surfaceDefinitions).forEach(function (entry) {
			const name = entry[0];
			const definition = entry[1];
			const start = values[definition[1]] || '#7c3aed';
			const end = values[definition[2]] || start;
			const angle = Math.max(0, Math.min(360, Number(values[definition[3]]) || 135));
			const surfaceType = values[definition[0]] || 'gradient';
			const surface = 'transparent' === surfaceType
				? 'transparent'
				: ('solid' === surfaceType
					? start
					: `linear-gradient(${angle}deg, ${start}, ${end})`);
			stage.style.setProperty(`--eilmo-so-${name}-surface`, surface);
		});

		const cardStyle = section.querySelector(SELECTORS.specialCardStyle);
		const previewStyle = cardStyle instanceof HTMLSelectElement && ['promo_banner', 'reward_tiles', 'compact_strip'].includes(cardStyle.value)
			? cardStyle.value
			: 'promo_banner';
		stage.dataset.previewCardStyle = previewStyle;
		const presetClass = {
			'promo_banner': 'promo',
			'reward_tiles': 'reward',
			'compact_strip': 'compact'
		}[previewStyle] || 'promo';
		previews.forEach(function (preview) {
			if (!(preview instanceof HTMLElement)) {
				return;
			}
			preview.classList.remove(
				'eilmo-cf-special-discount-card--promo',
				'eilmo-cf-special-discount-card--reward',
				'eilmo-cf-special-discount-card--compact'
			);
			preview.classList.add(`eilmo-cf-special-discount-card--${presetClass}`);
		});

		const layout = section.querySelector(SELECTORS.specialPreviewLayout);
		if (layout instanceof HTMLSelectElement) {
			Array.from(layout.options).forEach(function (option) {
				option.disabled = 'promo_banner' === previewStyle && Number(option.value) > 2;
			});

			if (!stage.dataset.previewInitialized || styleChanged) {
					layout.value = 'promo_banner' === previewStyle ? '2' : '3';
			}
			if ('promo_banner' === previewStyle && Number(layout.value) > 2) {
				layout.value = '2';
			}

			const columns = Math.max(1, Math.min('promo_banner' === previewStyle ? 2 : 3, Number(layout.value) || 1));
			stage.dataset.previewColumns = String(columns);
			previews.forEach(function (preview, index) {
				if (preview instanceof HTMLElement) {
					preview.hidden = index >= columns;
				}
			});
		}

		const type = section.querySelector(SELECTORS.specialPreviewType);
		const primaryPreview = previews[0];
		if (type instanceof HTMLSelectElement && primaryPreview instanceof HTMLElement) {
			primaryPreview.dataset.previewOfferType = type.value;
			const badge = primaryPreview.querySelector('.eilmo-cf-admin-special-preview__eyebrow em');
			const option = type.options[type.selectedIndex];
			if (badge instanceof HTMLElement && option) {
				badge.textContent = option.textContent || '';
			}
		}

		stage.dataset.previewInitialized = 'yes';
	}

	/**
	 * Return the merchant-facing Special Discount preset selector.
	 * The selector in the Style tab is deliberately preview-only.
	 *
	 * @return {HTMLSelectElement|null}
	 */
	function getEffectiveSpecialCardStyle() {
		const selectors = Array.from(document.querySelectorAll(SELECTORS.specialCardStyle));
		const effective = selectors.find(function (select) {
			return select instanceof HTMLSelectElement && Boolean(select.name);
		});

		return effective instanceof HTMLSelectElement ? effective : null;
	}

	/**
	 * Keep style cards, available column counts and per-rule presentation
	 * fields synchronized with the effective frontend preset.
	 *
	 * @param {boolean} styleChanged Whether the merchant selected a new preset.
	 * @return {void}
	 */
	function syncSpecialDiscountStyleControls(styleChanged) {
		const styleSelect = getEffectiveSpecialCardStyle();
		if (!(styleSelect instanceof HTMLSelectElement)) {
			return;
		}

		const style = ['promo_banner', 'reward_tiles', 'compact_strip'].includes(styleSelect.value)
			? styleSelect.value
			: 'promo_banner';

		document.querySelectorAll(SELECTORS.specialStyleChoice).forEach(function (choice) {
			if (!(choice instanceof HTMLButtonElement)) {
				return;
			}
			const selected = choice.getAttribute('data-eilmo-special-style-choice') === style;
			choice.classList.toggle('is-selected', selected);
			choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});

		const columns = document.querySelector(SELECTORS.specialColumns);
		if (columns instanceof HTMLSelectElement) {
			Array.from(columns.options).forEach(function (option) {
				option.disabled = 'promo_banner' === style && Number(option.value) > 2;
			});

			if ('promo_banner' === style && Number(columns.value) > 2) {
				columns.value = '2';
			} else if (styleChanged && ('reward_tiles' === style || 'compact_strip' === style)) {
				columns.value = '3';
			}
		}

		const help = document.querySelector(SELECTORS.specialColumnsHelp);
		if (help instanceof HTMLElement) {
			help.textContent = 'promo_banner' === style
				? 'Promo Grid supports 1 or 2 responsive columns.'
				: 'This preset supports 1 to 3 columns. Tablet uses 2 columns and mobile uses 1.';
		}

		document.querySelectorAll(SELECTORS.specialStyleField).forEach(function (field) {
			if (!(field instanceof HTMLElement)) {
				return;
			}
			const allowed = (field.getAttribute('data-eilmo-special-style-field') || '')
				.split(',')
				.map(function (value) { return value.trim(); });
			field.hidden = !allowed.includes(style);
		});
	}

	/**
	 * Initialize existing Combo Offers.
	 *
	 * Existing saved combos start collapsed.
	 *
	 * @return {void}
	 */
	function initializeComboOffers() {
		document
			.querySelectorAll(
				SELECTORS.comboItem
			)
			.forEach(
				function (combo) {
					if (
						!(
							combo instanceof
								HTMLElement
						)
					) {
						return;
					}

					const idInput =
						getComboIdInput(
							combo
						);

					if (
						idInput &&
						typeof idInput
							.dataset
							.autoId ===
								'undefined'
					) {
						idInput.dataset.autoId =
							idInput.value
								.trim() ===
									''
								? 'yes'
								: 'no';
					}

					updateComboItemsEmptyState(
						combo
					);

					syncComboMediaControls(combo);

					/*
					 * PHP-rendered existing combos
					 * should start collapsed.
					 */
					setComboOpenState(
						combo,
						false
					);
				}
			);

		reindexComboOffers();
	}


	/**
	 * Build one Product Gallery repeater item.
	 *
	 * @param {number} index Gallery index.
	 *
	 * @return {string}
	 */
	function buildProductGallery(index) {
		const prefix =
			`eilmo_cf_settings[product_gallery][galleries][${index}]`;

		return `
			<div
				class="eilmo-cf-admin-repeater__item eilmo-cf-admin-gallery"
				data-eilmo-product-gallery-item
				data-eilmo-gallery-index="${index}"
			>
				<div class="eilmo-cf-admin-repeater__item-header">
					<button
						type="button"
						class="button-link eilmo-cf-admin-gallery__toggle"
						data-eilmo-gallery-toggle
						aria-expanded="true"
					>
						<span
							class="eilmo-cf-admin-gallery__toggle-icon"
							data-eilmo-gallery-toggle-icon
							aria-hidden="true"
						>▼</span>
						<strong data-eilmo-gallery-title>Product Gallery</strong>
					</button>

					<button
						type="button"
						class="button-link-delete"
						data-eilmo-remove-repeater-item
					>Remove</button>
				</div>

				<div
					class="eilmo-cf-admin-gallery__content"
					data-eilmo-gallery-content
				>
					<div class="eilmo-cf-admin-grid">
						<div class="eilmo-cf-admin-field">
							<label>Gallery Name</label>
							<input
								type="text"
								name="${prefix}[name]"
								placeholder="e.g. Red Set - Facebook Ad"
								data-eilmo-gallery-name
								required
							>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>Gallery ID</label>
							<input
								type="text"
								name="${prefix}[id]"
								placeholder="red-set-facebook-ad"
								data-eilmo-gallery-id
								readonly
							>
							<p class="description">Automatically generated from Gallery Name.</p>
						</div>

						<input type="hidden" name="${prefix}[scope]" value="product">

						<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-gallery-product-field>
							<label>Linked Product</label>
							<select
								class="wc-product-search"
								style="width:100%;"
								name="${prefix}[product_id]"
								data-placeholder="Search for a product…"
								data-action="woocommerce_json_search_products"
								data-allow_clear="true"
								data-eilmo-gallery-product
								required
							></select>
							<p class="description">Required. Used automatically in Single Product and Multiple Products modes.</p>
						</div>

						<div class="eilmo-cf-admin-field">
							<label>Enabled</label>
							${buildSwitch(`${prefix}[enabled]`, true)}
						</div>

						<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
							<label>Gallery Images</label>
							<input
								type="hidden"
								name="${prefix}[image_ids]"
								value=""
								data-eilmo-gallery-image-ids
							>

							<div class="eilmo-cf-admin-gallery__media-actions">
								<button
									type="button"
									class="button button-secondary"
									data-eilmo-select-gallery-images
								>Select Images</button>

								<button
									type="button"
									class="button button-link-delete"
									data-eilmo-clear-gallery-images
									disabled
								>Clear Images</button>
							</div>

							<div
								class="eilmo-cf-admin-gallery__images"
								data-eilmo-gallery-images
							></div>

							<p class="description">Select multiple real-product images and drag thumbnails to set slider order.</p>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	/**
	 * Refresh WooCommerce enhanced product search controls.
	 *
	 * @return {void}
	 */
	function initializeWooProductSearch() {
		if (
			typeof window.jQuery !== 'function'
		) {
			return;
		}

		window.jQuery(
			document.body
		).trigger(
			'wc-enhanced-select-init'
		);
	}

	/**
	 * Set Product Gallery accordion state.
	 *
	 * @param {HTMLElement} gallery Gallery item.
	 * @param {boolean} open Open state.
	 *
	 * @return {void}
	 */
	function setGalleryOpenState(
		gallery,
		open
	) {
		const content =
			gallery.querySelector(
				SELECTORS.galleryContent
			);

		const toggle =
			gallery.querySelector(
				SELECTORS.galleryToggle
			);

		if (
			content instanceof HTMLElement
		) {
			content.hidden = !open;
		}

		if (
			!(toggle instanceof HTMLElement)
		) {
			return;
		}

		toggle.setAttribute(
			'aria-expanded',
			open ? 'true' : 'false'
		);

		const icon =
			toggle.querySelector(
				SELECTORS.galleryToggleIcon
			);

		if (
			icon instanceof HTMLElement
		) {
			icon.textContent =
				open ? '▼' : '▶';
		}
	}

	/**
	 * Toggle Product Gallery accordion.
	 *
	 * @param {HTMLElement} button Toggle button.
	 *
	 * @return {void}
	 */
	function toggleGallery(button) {
		const gallery =
			button.closest(
				SELECTORS.galleryItem
			);

		if (
			!(gallery instanceof HTMLElement)
		) {
			return;
		}

		setGalleryOpenState(
			gallery,
			button.getAttribute('aria-expanded') !== 'true'
		);
	}

	/**
	 * Keep Product Gallery image IDs synchronized with
	 * the visible draggable thumbnail order.
	 *
	 * @param {HTMLElement} gallery Gallery item.
	 *
	 * @return {void}
	 */
	function syncGalleryImageIds(gallery) {
		const input =
			gallery.querySelector(
				SELECTORS.galleryImageIds
			);

		const images =
			gallery.querySelector(
				SELECTORS.galleryImages
			);

		if (
			!(input instanceof HTMLInputElement) ||
			!(images instanceof HTMLElement)
		) {
			return;
		}

		const ids =
			Array.from(
				images.querySelectorAll(
					SELECTORS.galleryImage
				)
			)
				.map(function (image) {
					return image.getAttribute('data-image-id') || '';
				})
				.filter(Boolean);

		input.value = ids.join(',');

		const clear =
			gallery.querySelector(
				SELECTORS.clearGalleryImages
			);

		if (
			clear instanceof HTMLButtonElement
		) {
			clear.disabled = ids.length === 0;
		}
	}

	/**
	 * Render selected gallery attachments.
	 *
	 * @param {HTMLElement} gallery Gallery item.
	 * @param {Array<Object>} attachments Attachment data.
	 *
	 * @return {void}
	 */
	function renderGalleryAttachments(
		gallery,
		attachments
	) {
		const container =
			gallery.querySelector(
				SELECTORS.galleryImages
			);

		if (
			!(container instanceof HTMLElement)
		) {
			return;
		}

		const existingIds = new Set(
			Array.from(
				container.querySelectorAll(
					SELECTORS.galleryImage
				)
			).map(function (image) {
				return image.getAttribute('data-image-id') || '';
			})
		);

		const html = attachments
			.map(function (attachment) {
				const id = Number.parseInt(
					attachment.id,
					10
				);

				if (
					!Number.isFinite(id) ||
					id <= 0 ||
					existingIds.has(String(id))
				) {
					return '';
				}

				let url = attachment.url || '';

				if (
					attachment.sizes &&
					attachment.sizes.thumbnail &&
					attachment.sizes.thumbnail.url
				) {
					url = attachment.sizes.thumbnail.url;
				}

				if (!url) {
					return '';
				}

				return `
					<div
						class="eilmo-cf-admin-gallery__image"
						data-eilmo-gallery-image
						data-image-id="${id}"
						draggable="true"
					>
						<img src="${escapeHtml(url)}" alt="">
						<button
							type="button"
							class="eilmo-cf-admin-gallery__image-remove"
							data-eilmo-remove-gallery-image
							aria-label="Remove image"
						>
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M6 6l12 12M18 6L6 18"></path>
							</svg>
						</button>
					</div>
				`;
			})
			.join('');

		if (html) {
			container.insertAdjacentHTML(
				'beforeend',
				html
			);
		}

		syncGalleryImageIds(gallery);
	}

	/**
	 * Open WordPress Media Library for one Product Gallery.
	 *
	 * @param {HTMLElement} button Select Images button.
	 *
	 * @return {void}
	 */
	function selectGalleryImages(button) {
		const gallery =
			button.closest(
				SELECTORS.galleryItem
			);

		if (
			!(gallery instanceof HTMLElement) ||
			!window.wp ||
			!window.wp.media
		) {
			return;
		}

		const frame = window.wp.media({
			title: 'Select Product Gallery Images',
			button: {
				text: 'Use Selected Images',
			},
			library: {
				type: 'image',
			},
			multiple: true,
		});


		frame.on('select', function () {
			const selected =
				frame.state()
					.get('selection')
					.toJSON();

			renderGalleryAttachments(
				gallery,
				selected
			);
		});

		frame.open();
	}

	/**
	 * Clear all images from one Product Gallery.
	 *
	 * @param {HTMLElement} button Clear button.
	 *
	 * @return {void}
	 */
	function clearGalleryImages(button) {
		const gallery =
			button.closest(
				SELECTORS.galleryItem
			);

		if (
			!(gallery instanceof HTMLElement)
		) {
			return;
		}

		const container =
			gallery.querySelector(
				SELECTORS.galleryImages
			);

		if (
			container instanceof HTMLElement
		) {
			container.innerHTML = '';
		}

		syncGalleryImageIds(gallery);
	}

	/**
	 * Remove one image thumbnail from a Product Gallery.
	 *
	 * @param {HTMLElement} button Remove image button.
	 *
	 * @return {void}
	 */
	function removeGalleryImage(button) {
		const gallery =
			button.closest(
				SELECTORS.galleryItem
			);

		const image =
			button.closest(
				SELECTORS.galleryImage
			);

		if (
			!(gallery instanceof HTMLElement) ||
			!(image instanceof HTMLElement)
		) {
			return;
		}

		image.remove();
		syncGalleryImageIds(gallery);
	}

	/**
	 * Update gallery title and readonly ID from name.
	 *
	 * @param {HTMLInputElement} input Name input.
	 *
	 * @return {void}
	 */
	function handleGalleryName(input) {
		const gallery =
			input.closest(
				SELECTORS.galleryItem
			);

		if (
			!(gallery instanceof HTMLElement)
		) {
			return;
		}

		const value = input.value.trim();

		const title =
			gallery.querySelector(
				SELECTORS.galleryTitle
			);

		if (
			title instanceof HTMLElement
		) {
			title.textContent =
				value || 'Product Gallery';
		}

		const idInput =
			gallery.querySelector(
				SELECTORS.galleryId
			);

		if (
			idInput instanceof HTMLInputElement
		) {
			idInput.value = slugify(value);
		}
	}

	/**
	 * Update Product Gallery empty-state notice.
	 *
	 * @return {void}
	 */
	function updateGalleryEmptyState() {
		const container =
			document.querySelector(
				SELECTORS.galleryContainer
			);

		if (
			!(container instanceof HTMLElement)
		) {
			return;
		}

		let notice =
			document.querySelector(
				'[data-eilmo-gallery-empty-notice]'
			);

		if (
			!(notice instanceof HTMLElement)
		) {
			notice = document.createElement('div');
			notice.className = 'notice notice-info inline';
			notice.setAttribute(
				'data-eilmo-gallery-empty-notice',
				''
			);

			const paragraph = document.createElement('p');
			paragraph.textContent =
				'No Product Galleries have been created yet. Click Add Gallery to create the first reusable gallery.';
			notice.appendChild(paragraph);

			container.insertAdjacentElement(
				'afterend',
				notice
			);
		}

		const empty =
			getDirectChildren(
				container,
				SELECTORS.galleryItem
			).length === 0;

		notice.hidden = !empty;
	}


	/**
	 * Keep Product Gallery scope and product assignment in sync.
	 *
	 * @param {HTMLElement} gallery Gallery item.
	 *
	 * @return {void}
	 */
	/**
	 * Add a new Product Gallery item.
	 *
	 * @return {void}
	 */
	function addProductGallery() {
		const container =
			document.querySelector(
				SELECTORS.galleryContainer
			);

		if (
			!(container instanceof HTMLElement)
		) {
			return;
		}

		reindexContainer(container);

		const index =
			getDirectChildren(
				container,
				SELECTORS.galleryItem
			).length;

		const gallery = appendItem(
			container,
			buildProductGallery(index)
		);

		if (
			!(gallery instanceof HTMLElement)
		) {
			return;
		}

		setGalleryOpenState(
			gallery,
			true
		);

		initializeWooProductSearch();
		updateGalleryEmptyState();
	}

	/**
	 * Initialize Product Gallery admin UI.
	 *
	 * @return {void}
	 */
	function initializeProductGalleries() {
		document
			.querySelectorAll(
				SELECTORS.galleryItem
			)
			.forEach(function (gallery) {
				if (
					gallery instanceof HTMLElement
				) {
					setGalleryOpenState(
						gallery,
						false
					);
					syncGalleryImageIds(gallery);
				}
			});

		initializeWooProductSearch();
		updateGalleryEmptyState();
	}

	let draggedGalleryImage = null;

	/**
	 * Handle Product Gallery thumbnail drag start.
	 *
	 * @param {DragEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleGalleryDragStart(event) {
		if (
			!(event.target instanceof Element)
		) {
			return;
		}

		const image = event.target.closest(
			SELECTORS.galleryImage
		);

		if (
			!(image instanceof HTMLElement)
		) {
			return;
		}

		draggedGalleryImage = image;

		if (event.dataTransfer) {
			event.dataTransfer.effectAllowed = 'move';
		}

		image.classList.add(
			'is-dragging'
		);
	}

	/**
	 * Handle Product Gallery thumbnail drag over.
	 *
	 * @param {DragEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleGalleryDragOver(event) {
		if (
			!(event.target instanceof Element) ||
			!(draggedGalleryImage instanceof HTMLElement)
		) {
			return;
		}

		const target = event.target.closest(
			SELECTORS.galleryImage
		);

		if (
			!(target instanceof HTMLElement) ||
			target === draggedGalleryImage ||
			target.parentElement !== draggedGalleryImage.parentElement
		) {
			return;
		}

		event.preventDefault();

		const rect = target.getBoundingClientRect();
		const after =
			event.clientX > rect.left + rect.width / 2;

		target.parentElement.insertBefore(
			draggedGalleryImage,
			after ? target.nextSibling : target
		);
	}

	/**
	 * Handle Product Gallery thumbnail drag end.
	 *
	 * @return {void}
	 */
	function handleGalleryDragEnd() {
		if (
			!(draggedGalleryImage instanceof HTMLElement)
		) {
			return;
		}

		const gallery = draggedGalleryImage.closest(
			SELECTORS.galleryItem
		);

		draggedGalleryImage.classList.remove(
			'is-dragging'
		);

		draggedGalleryImage = null;

		if (
			gallery instanceof HTMLElement
		) {
			syncGalleryImageIds(gallery);
		}
	}

	/**
	 * Handle document click.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(event) {
		if (
			!(
				event.target instanceof
					Element
			)
		) {
			return;
		}

		const selectComboMediaButton = event.target.closest(
			SELECTORS.selectComboMedia
		);

		if (selectComboMediaButton instanceof HTMLElement) {
			event.preventDefault();
			selectComboMedia(selectComboMediaButton);
			return;
		}

		const clearComboMediaButton = event.target.closest(
			SELECTORS.clearComboMedia
		);

		if (clearComboMediaButton instanceof HTMLElement) {
			event.preventDefault();
			clearComboMedia(clearComboMediaButton);
			return;
		}

		if (
			event.target.closest(
				SELECTORS.addGallery
			)
		) {
			event.preventDefault();

			addProductGallery();

			return;
		}

		const galleryToggle =
			event.target.closest(
				SELECTORS.galleryToggle
			);

		if (
			galleryToggle instanceof HTMLElement
		) {
			event.preventDefault();
			event.stopPropagation();

			toggleGallery(galleryToggle);

			return;
		}

		const selectGalleryButton =
			event.target.closest(
				SELECTORS.selectGalleryImages
			);

		if (
			selectGalleryButton instanceof HTMLElement
		) {
			event.preventDefault();

			selectGalleryImages(selectGalleryButton);

			return;
		}

		const clearGalleryButton =
			event.target.closest(
				SELECTORS.clearGalleryImages
			);

		if (
			clearGalleryButton instanceof HTMLElement
		) {
			event.preventDefault();

			clearGalleryImages(clearGalleryButton);

			return;
		}

		const removeGalleryImageButton =
			event.target.closest(
				SELECTORS.removeGalleryImage
			);

		if (
			removeGalleryImageButton instanceof HTMLElement
		) {
			event.preventDefault();

			removeGalleryImage(removeGalleryImageButton);

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addDelivery
			)
		) {
			event.preventDefault();

			addDeliveryMethod();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addAdvance
			)
		) {
			event.preventDefault();

			addAdvanceRule();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addDiscount
			)
		) {
			event.preventDefault();

			addDiscountRule();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addCoupon
			)
		) {
			event.preventDefault();

			addCoupon();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addPayment
			)
		) {
			event.preventDefault();

			addPaymentMethod();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addCombo
			)
		) {
			event.preventDefault();

			addComboOffer();

			return;
		}

		if (
			event.target.closest(
				SELECTORS.addOrderBump
			)
		) {
			event.preventDefault();

			addOrderBump();

			return;
		}

		const addComboProduct =
			event.target.closest(
				SELECTORS.addComboItem
			);

		if (
			addComboProduct instanceof
				HTMLElement
		) {
			event.preventDefault();

			addComboItem(
				addComboProduct
			);

			return;
		}

		/*
		 * Combo accordion.
		 */
		const comboToggle =
			event.target.closest(
				SELECTORS.comboToggle
			);

		if (
			comboToggle instanceof
				HTMLElement
		) {
			event.preventDefault();
			event.stopPropagation();

			toggleComboOffer(
				comboToggle
			);

			return;
		}

		const remove =
			event.target.closest(
				SELECTORS.remove
			);

		if (
			remove instanceof
				HTMLElement
		) {
			event.preventDefault();

			removeItem(
				remove
			);
		}
	}

	/**
	 * Handle text/number input.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleInput(event) {
		if (
			!(
				event.target instanceof
					HTMLInputElement
			)
		) {
			return;
		}

		const input =
			event.target;

		if (
			input.matches('[data-eilmo-combo-style-color], [data-eilmo-combo-style-number]')
		) {
			syncComboStylePreview();
			return;
		}

		if (
			input.matches('[data-eilmo-special-style-color], [data-eilmo-special-style-number]')
		) {
			syncSpecialStylePreview();
			return;
		}

		if (
			input.matches(
				SELECTORS.galleryName
			)
		) {
			handleGalleryName(input);

			return;
		}

		if (
			input.matches(
				'[data-eilmo-delivery-label]'
			)
		) {
			handleDeliveryLabel(
				input
			);

			return;
		}

		if (
			input.matches(
				'[data-eilmo-delivery-id]'
			)
		) {
			handleDeliveryId(
				input
			);

			return;
		}

		if (
			input.matches(
				SELECTORS.paymentName
			)
		) {
			handlePaymentMethodName(
				input
			);

			return;
		}

		const paymentItem =
			input.closest(
				SELECTORS.paymentItem
			);

		if (
			paymentItem instanceof
				HTMLElement
		) {
			const paymentIdInput =
				getPaymentMethodIdInput(
					paymentItem
				);

			if (
				input ===
					paymentIdInput
			) {
				handlePaymentMethodId(
					input
				);

				return;
			}
		}

		if (
			input.matches(
				SELECTORS.orderBumpTitleInput
			)
		) {
			handleOrderBumpTitle(
				input
			);

			return;
		}

		const orderBump =
			input.closest(
				SELECTORS.orderBumpItem
			);

		if (
			orderBump instanceof
				HTMLElement
		) {
			const orderBumpIdInput =
				getOrderBumpIdInput(
					orderBump
				);

			if (
				input ===
					orderBumpIdInput
			) {
				handleOrderBumpId(
					input
				);

				return;
			}
		}

		if (
			input.matches(
				SELECTORS.comboTitleInput
			)
		) {
			handleComboTitle(
				input
			);

			return;
		}

		const combo =
			input.closest(
				SELECTORS.comboItem
			);

		if (
			combo instanceof
				HTMLElement
		) {
			const comboIdInput =
				getComboIdInput(
					combo
				);

			if (
				input ===
					comboIdInput
			) {
				handleComboId(
					input
				);

				return;
			}
		}

		if (
			input.matches(
				[
					SELECTORS.comboProductId,
					SELECTORS.comboVariationId,
				].join(',')
			)
		) {
			handleComboProductIdentity(
				input
			);

			return;
		}

		if (
			input.matches(
				'[data-eilmo-rule-name]'
			)
		) {
			handleRuleName(
				input
			);

			return;
		}

		if (
			input.matches(
				'[data-eilmo-coupon-code]'
			)
		) {
			handleCouponCode(
				input
			);
		}
	}

	/**
	 * Handle select change.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(event) {
		if (
			!(
				event.target instanceof
					HTMLSelectElement
			)
		) {
			return;
		}

		if (
			event.target.matches(
				[
					'[data-eilmo-advance-type]',
					'[data-eilmo-discount-type]',
					'[data-eilmo-coupon-type]',
				].join(',')
			)
		) {
			updateValueField(
				event.target
			);
		}
	}


	/* ======================================================================
	 * Settings UX: presets, progressive disclosure and conditional controls
	 * ====================================================================== */

	const SETTINGS_UX = {
		advancedStorageKey: 'eilmoCfShowAdvancedSettings',
		advancedFields: [
			'eilmo_cf_settings[checkout_display][products][show_thumbnail]',
			'eilmo_cf_settings[checkout_display][products][title_link]',
			'eilmo_cf_settings[checkout_display][products][group_columns_desktop]',
			'eilmo_cf_settings[checkout_display][products][variation_columns_desktop]',
			'eilmo_cf_settings[checkout_display][products][group_image_ratio]',
			'eilmo_cf_settings[checkout_display][products][variation_badge_enabled]',
			'eilmo_cf_settings[checkout_display][products][select_label]',
			'eilmo_cf_settings[checkout_display][summary][title]',
			'eilmo_cf_settings[checkout_display][summary][selected_items_title]',
			'eilmo_cf_settings[checkout_display][summary][product_total_label]',
			'eilmo_cf_settings[checkout_display][summary][combo_discount_label]',
			'eilmo_cf_settings[checkout_display][summary][special_offer_label]',
			'eilmo_cf_settings[checkout_display][summary][automatic_discount_label]',
			'eilmo_cf_settings[checkout_display][summary][coupon_discount_label]',
			'eilmo_cf_settings[checkout_display][summary][delivery_charge_label]',
			'eilmo_cf_settings[checkout_display][summary][full_payment_discount_label]',
			'eilmo_cf_settings[checkout_display][summary][advance_payment_label]',
			'eilmo_cf_settings[checkout_display][summary][grand_total_label]',
			'eilmo_cf_settings[checkout_display][summary][pay_now_label]',
			'eilmo_cf_settings[checkout_display][summary][remaining_due_label]',
			'eilmo_cf_settings[checkout_display][summary][view_summary_label]',
			'eilmo_cf_settings[checkout_display][order_button][processing_label]',
			'eilmo_cf_settings[checkout_display][order_button][footer_enabled]',
		],
	};

	const PRESETS = {
		sticky_summary: {
			'eilmo_cf_settings[checkout_display][layout][summary_position]': 'right',
			'eilmo_cf_settings[checkout_display][layout][summary_sticky]': 'yes',
			'eilmo_cf_settings[checkout_display][layout][checkout_details_position]': 'main',
			'eilmo_cf_settings[checkout_display][order_button][placement]': 'below_payment',
			'eilmo_cf_settings[checkout_display][layout][whatsapp_placement]': 'below_payment',
		},
		sidebar_actions: {
			'eilmo_cf_settings[checkout_display][layout][summary_position]': 'right',
			'eilmo_cf_settings[checkout_display][layout][summary_sticky]': 'yes',
			'eilmo_cf_settings[checkout_display][layout][checkout_details_position]': 'main',
			'eilmo_cf_settings[checkout_display][order_button][placement]': 'below_summary',
			'eilmo_cf_settings[checkout_display][layout][whatsapp_placement]': 'below_summary',
		},
		full_sidebar: {
			'eilmo_cf_settings[checkout_display][layout][summary_position]': 'right',
			'eilmo_cf_settings[checkout_display][layout][summary_sticky]': 'yes',
			'eilmo_cf_settings[checkout_display][layout][checkout_details_position]': 'below_summary',
			'eilmo_cf_settings[checkout_display][order_button][placement]': 'below_payment',
			'eilmo_cf_settings[checkout_display][layout][whatsapp_placement]': 'below_payment',
		},
	};

	const PRESET_DEFAULT_BUTTON_LABEL = 'Use This Layout';
	const PRESET_ACTIVE_BUTTON_LABEL = 'Selected';

	function getNamedElements(name) {
		return Array.from(document.getElementsByName(name));
	}

	function getPrimaryNamedElement(name) {
		const elements = getNamedElements(name);

		return elements.find(function (element) {
			return element instanceof HTMLSelectElement ||
				(element instanceof HTMLInputElement && element.type !== 'hidden') ||
				element instanceof HTMLTextAreaElement;
		}) || elements[0] || null;
	}

	function getNamedValue(name) {
		const element = getPrimaryNamedElement(name);

		if (element instanceof HTMLInputElement && element.type === 'checkbox') {
			return element.checked ? element.value : 'no';
		}

		if (element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement) {
			return element.value;
		}

		return '';
	}

	function setNamedValue(name, value) {
		const elements = getNamedElements(name);

		elements.forEach(function (element) {
			if (element instanceof HTMLInputElement && element.type === 'checkbox') {
				element.checked = String(value) === element.value || String(value) === 'yes';
				return;
			}

			if (element instanceof HTMLSelectElement || (element instanceof HTMLInputElement && element.type !== 'hidden') || element instanceof HTMLTextAreaElement) {
				element.value = String(value);
			}
		});
	}

	function getSettingRow(name) {
		const element = getPrimaryNamedElement(name);
		return element instanceof HTMLElement ? element.closest('tr') : null;
	}

	function setConditionalRow(name, visible) {
		const row = getSettingRow(name);
		if (!(row instanceof HTMLElement)) {
			return;
		}
		row.classList.toggle('is-condition-hidden', !visible);
	}

	function markAdvancedRows() {
		if (!document.querySelector('[data-eilmo-easy-setup]')) {
			return;
		}

		SETTINGS_UX.advancedFields.forEach(function (name) {
			const row = getSettingRow(name);
			if (row instanceof HTMLElement) {
				row.classList.add('eilmo-cf-setting-row--advanced');
			}
		});
	}

	function advancedControlsAreOpen() {
		const form = document.querySelector('.eilmo-cf-settings-form');
		return form instanceof HTMLElement && form.classList.contains('is-advanced-open');
	}

	function setAdvancedControls(open) {
		const form = document.querySelector('.eilmo-cf-settings-form');
		const button = document.querySelector('[data-eilmo-toggle-advanced]');
		if (!(form instanceof HTMLElement)) {
			return;
		}

		form.classList.toggle('is-advanced-open', Boolean(open));

		if (button instanceof HTMLButtonElement) {
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
			button.textContent = open
				? (button.getAttribute('data-hide-label') || 'Hide Advanced Controls')
				: (button.getAttribute('data-show-label') || 'Show Advanced Controls');
		}

		try {
			window.localStorage.setItem(SETTINGS_UX.advancedStorageKey, open ? 'yes' : 'no');
		} catch (error) {
			// Local storage is a convenience only.
		}
	}

	function initializeAdvancedControls() {
		if (!document.querySelector('[data-eilmo-easy-setup]')) {
			return;
		}

		markAdvancedRows();

		let open = false;
		try {
			open = window.localStorage.getItem(SETTINGS_UX.advancedStorageKey) === 'yes';
		} catch (error) {
			open = false;
		}

		setAdvancedControls(open);
	}

	function syncSettingsUi() {
		const summaryPosition = getNamedValue('eilmo_cf_settings[checkout_display][layout][summary_position]');
		setConditionalRow('eilmo_cf_settings[checkout_display][layout][summary_sticky]', summaryPosition !== 'below');


		const showQuantity = getNamedValue('eilmo_cf_settings[checkout_display][products][show_quantity]');
		setConditionalRow(
			'eilmo_cf_settings[checkout_display][products][group_quantity_control]',
			showQuantity === 'yes'
		);

		const showSelectText = getNamedValue('eilmo_cf_settings[checkout_display][products][show_select_text]');
		setConditionalRow(
			'eilmo_cf_settings[checkout_display][products][select_label]',
			showSelectText === 'yes'
		);

		const paymentOptionLayout = getNamedValue('eilmo_cf_settings[advance_payment][display_layout]');
		if (paymentOptionLayout) {
			setConditionalRow('eilmo_cf_settings[advance_payment][columns_desktop]', paymentOptionLayout === 'grid');
		}

		const paymentMethodLayout = getNamedValue('eilmo_cf_settings[payment_methods][display_layout]');
		if (paymentMethodLayout) {
			setConditionalRow('eilmo_cf_settings[payment_methods][columns_desktop]', paymentMethodLayout === 'grid');
		}

	}

	function detectActivePreset() {
		const presetNames = Object.keys(PRESETS);

		for (let index = 0; index < presetNames.length; index += 1) {
			const presetName = presetNames[index];
			const preset = PRESETS[presetName];
			const matches = Object.keys(preset).every(function (name) {
				return String(getNamedValue(name)) === String(preset[name]);
			});

			if (matches) {
				return presetName;
			}
		}

		return 'custom';
	}

	function syncPresetSelection() {
		if (!document.querySelector('[data-eilmo-easy-setup]')) {
			return;
		}

		const activePreset = detectActivePreset();

		document.querySelectorAll('[data-eilmo-preset-card]').forEach(function (card) {
			if (!(card instanceof HTMLElement)) {
				return;
			}

			const presetName = card.getAttribute('data-eilmo-preset-card') || '';
			const active = presetName === activePreset;
			card.classList.toggle('is-selected', active);
			card.setAttribute('aria-current', active ? 'true' : 'false');

			const badge = card.querySelector('[data-eilmo-preset-active]');
			if (badge instanceof HTMLElement) {
				badge.hidden = !active;
			}

			const applyButton = card.querySelector('[data-eilmo-apply-preset]');
			if (applyButton instanceof HTMLButtonElement) {
				applyButton.disabled = active;
				applyButton.textContent = active ? PRESET_ACTIVE_BUTTON_LABEL : PRESET_DEFAULT_BUTTON_LABEL;
				applyButton.classList.toggle('button-primary', active);
			}
		});
	}

	function applyPreset(presetName) {
		const preset = PRESETS[presetName];
		if (!preset) {
			return;
		}

		Object.keys(preset).forEach(function (name) {
			setNamedValue(name, preset[name]);
		});

		syncSettingsUi();
		syncPresetSelection();

		const status = document.querySelector('[data-eilmo-preset-status]');
		if (status instanceof HTMLElement) {
			status.hidden = false;
			status.textContent = status.getAttribute('data-message') || 'Layout preset applied. Review the settings below, then click Save Settings.';
		}

		const layoutSection = document.querySelector('[data-eilmo-easy-setup] + .eilmo-cf-settings-section');
		if (layoutSection instanceof HTMLElement) {
			layoutSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function handleSettingsUxClick(event) {
		if (!(event.target instanceof Element)) {
			return;
		}

		const specialStyleChoice = event.target.closest(SELECTORS.specialStyleChoice);
		if (specialStyleChoice instanceof HTMLButtonElement) {
			event.preventDefault();
			const styleSelect = getEffectiveSpecialCardStyle();
			const style = specialStyleChoice.getAttribute('data-eilmo-special-style-choice') || '';
			if (styleSelect instanceof HTMLSelectElement && ['promo_banner', 'reward_tiles', 'compact_strip'].includes(style)) {
				styleSelect.value = style;
				syncSpecialDiscountStyleControls(true);
			}
			return;
		}

		const presetButton = event.target.closest('[data-eilmo-apply-preset]');
		if (presetButton instanceof HTMLButtonElement) {
			applyPreset(presetButton.getAttribute('data-eilmo-apply-preset') || '');
			return;
		}

		const customButton = event.target.closest('[data-eilmo-customize-preset]');
		if (customButton instanceof HTMLButtonElement) {
			setAdvancedControls(true);
			const layoutSection = document.querySelector('[data-eilmo-easy-setup] + .eilmo-cf-settings-section');
			if (layoutSection instanceof HTMLElement) {
				layoutSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			return;
		}

		const advancedButton = event.target.closest('[data-eilmo-toggle-advanced]');
		if (advancedButton instanceof HTMLButtonElement) {
			setAdvancedControls(!advancedControlsAreOpen());
		}
	}

	function handleSettingsUxChange(event) {
		if (!(event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement || event.target instanceof HTMLTextAreaElement)) {
			return;
		}

		if (!event.target.closest('.eilmo-cf-settings-form')) {
			return;
		}

		if (
			event.target.matches(SELECTORS.comboIconType) ||
			event.target.matches(SELECTORS.comboImageSource)
		) {
			const scope = event.target.closest(SELECTORS.comboItem) ||
				event.target.closest(SELECTORS.comboProductItem) ||
				document;
			syncComboMediaControls(scope);
		}

		syncComboStylePreview();
		syncSpecialStylePreview(
			event.target.matches(SELECTORS.specialCardStyle) && !event.target.getAttribute('name')
		);
		syncSpecialDiscountStyleControls(
			event.target.matches(SELECTORS.specialCardStyle) && Boolean(event.target.getAttribute('name'))
		);

		syncSettingsUi();
		syncPresetSelection();
	}

	/**
	 * Re-index immediately before settings save.
	 *
	 * @param {SubmitEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSubmit(event) {
		if (
			!(
				event.target instanceof
					HTMLFormElement
			) ||
			!event.target.matches(
				'.eilmo-cf-settings-form'
			)
		) {
			return;
		}

		reindexAll();

		syncPaymentDefaultOptions();
	}

	/**
	 * Reveal the first invalid field when it belongs to a collapsed card.
	 *
	 * Native validation otherwise blocks the POST while the merchant cannot see
	 * why a bottom Save button appeared to do nothing.
	 *
	 * @param {Event} event Invalid field event.
	 *
	 * @return {void}
	 */
	function revealInvalidSettingsField(event) {
		const field = event.target;

		if (
			!(field instanceof HTMLInputElement) &&
			!(field instanceof HTMLSelectElement) &&
			!(field instanceof HTMLTextAreaElement)
		) {
			return;
		}

		const form = field.closest('.eilmo-cf-settings-form');
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const accordion = field.closest(
			`${SELECTORS.orderBumpItem},${SELECTORS.comboItem}`
		);

		if (accordion instanceof HTMLElement) {
			const toggle = accordion.querySelector(
				'[data-eilmo-special-offer-toggle],[data-eilmo-combo-toggle]'
			);

			if (
				toggle instanceof HTMLElement &&
				'false' === toggle.getAttribute('aria-expanded')
			) {
				toggle.click();
			}
		}

		field.classList.add('eilmo-cf-settings-field--invalid');

		if ('yes' === form.dataset.eilmoInvalidRevealPending) {
			return;
		}

		form.dataset.eilmoInvalidRevealPending = 'yes';

		window.setTimeout(function () {
			field.scrollIntoView({ behavior: 'smooth', block: 'center' });
			field.focus({ preventScroll: true });
			delete form.dataset.eilmoInvalidRevealPending;
		}, 0);
	}

	/**
	 * Clear the validation highlight as soon as the field becomes valid.
	 *
	 * @param {Event} event Field input/change event.
	 *
	 * @return {void}
	 */
	function clearInvalidSettingsField(event) {
		const field = event.target;

		if (
			(field instanceof HTMLInputElement ||
				field instanceof HTMLSelectElement ||
				field instanceof HTMLTextAreaElement) &&
			field.checkValidity()
		) {
			field.classList.remove('eilmo-cf-settings-field--invalid');
		}
	}

	/**
	 * Keep the Checkout Style preview synchronized without a page reload.
	 *
	 * @return {void}
	 */
	function syncCheckoutStylePreview() {
		const section = document.querySelector(SELECTORS.checkoutStyle);
		const preview = document.querySelector(SELECTORS.stylePreview);

		if (!(section instanceof HTMLElement) || !(preview instanceof HTMLElement)) {
			return;
		}

		section.querySelectorAll('[data-eilmo-style-color]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-style-color') || '';
			const variable = STYLE_VARIABLES[key];

			if (variable) {
				preview.style.setProperty(variable, input.value);
			}

			const code = input.parentElement ? input.parentElement.querySelector('code') : null;
			if (code instanceof HTMLElement) {
				code.textContent = input.value;
			}
		});

		section.querySelectorAll('[data-eilmo-style-number]').forEach(function (input) {
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const key = input.getAttribute('data-eilmo-style-number') || '';
			const variables = {
				radius: '--eilmo-cf-radius',
				parent_radius: '--eilmo-cf-parent-radius',
				gap: '--eilmo-cf-gap',
				card_gap: '--eilmo-cf-card-gap',
			};

			if (variables[key]) {
				preview.style.setProperty(variables[key], `${input.value || 0}px`);
			}
		});

		const primary = section.querySelector('[data-eilmo-style-color="primary"]');
		const background = section.querySelector('[data-eilmo-style-color="background"]');
		const border = section.querySelector('[data-eilmo-style-color="border"]');

		if (primary instanceof HTMLInputElement) {
			const card = background instanceof HTMLInputElement ? background.value : '#ffffff';
			const line = border instanceof HTMLInputElement ? border.value : '#e5e7eb';
			preview.style.setProperty('--eilmo-cf-theme-primary', primary.value);
			preview.style.setProperty('--eilmo-cf-theme-primary-hover', `color-mix(in srgb, ${primary.value} 84%, #000000)`);
			preview.style.setProperty('--eilmo-cf-theme-primary-soft', `color-mix(in srgb, ${primary.value} 8%, ${card})`);
			preview.style.setProperty('--eilmo-cf-theme-strong-border', `color-mix(in srgb, ${line} 56%, ${primary.value} 44%)`);
			preview.style.setProperty('--eilmo-cf-theme-muted-surface', `color-mix(in srgb, ${card} 94%, ${primary.value} 6%)`);
		}
	}

	/**
	 * Multiple selection is valid only for exact Compact Grid cards.
	 * Keep the admin form honest before it reaches the server sanitizer.
	 *
	 * @return {void}
	 */
	function syncSingleProductVariationSelection() {
		const layout = document.querySelector(SELECTORS.singleVariationLayout);
		const selection = document.querySelector(SELECTORS.singleVariationSelection);

		if (!(layout instanceof HTMLSelectElement) || !(selection instanceof HTMLSelectElement)) {
			return;
		}

		const multipleOption = selection.querySelector('option[value="multiple"]');
		const compactGrid = layout.value === 'grid';

		if (multipleOption instanceof HTMLOptionElement) {
			multipleOption.disabled = !compactGrid;
		}

		if (!compactGrid && selection.value === 'multiple') {
			selection.value = 'single';
		}
	}

	/**
	 * Show only controls that apply to the selected variation layout.
	 *
	 * @return {void}
	 */
	function syncProductLayoutControls() {
		[
			{
				layout: SELECTORS.singleVariationLayout,
				selection: SELECTORS.singleVariationSelection,
				grid: '[data-eilmo-single-grid-only]',
				sections: '[data-eilmo-single-sections-only]',
				badge: '[data-eilmo-single-badge-settings]',
				badgeAttribute: '[data-eilmo-single-badge-attribute]',
				prefix: 'single_product',
			},
			{
				layout: SELECTORS.multipleVariationLayout,
				selection: SELECTORS.multipleVariationSelection,
				grid: '[data-eilmo-multiple-grid-only]',
				sections: '[data-eilmo-multiple-sections-only]',
				badge: '[data-eilmo-multiple-badge-settings]',
				badgeAttribute: '[data-eilmo-multiple-badge-attribute]',
				prefix: 'multiple_products',
			},
		].forEach(function (config) {
			const layout = document.querySelector(config.layout);

			if (!(layout instanceof HTMLSelectElement)) {
				return;
			}

			const compactGrid = layout.value === 'grid';
			document.querySelectorAll(config.grid).forEach(function (row) {
				row.hidden = !compactGrid;
			});
			document.querySelectorAll(config.sections).forEach(function (row) {
				row.hidden = compactGrid;
			});

			const selection = document.querySelector(config.selection);
			if (selection instanceof HTMLSelectElement) {
				const multipleOption = selection.querySelector('option[value="multiple"]');
				if (multipleOption instanceof HTMLOptionElement) {
					multipleOption.disabled = !compactGrid;
				}
				if (!compactGrid && selection.value === 'multiple') {
					selection.value = 'single';
				}
			}

			const badgeAttribute = document.querySelector(config.badgeAttribute);
			const badgeToggle = document.querySelector(
				`input[name="eilmo_cf_settings[${config.prefix}][show_variation_badges]"][type="checkbox"]`
			);
			if (badgeAttribute instanceof HTMLSelectElement) {
				badgeAttribute.hidden = compactGrid || (badgeToggle instanceof HTMLInputElement && !badgeToggle.checked);
			}
		});
	}

	/**
	 * Mark edited presets custom and update the live preview.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleCheckoutStyleChange(event) {
		if (!(event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement)) {
			return;
		}

		const section = event.target.closest(SELECTORS.checkoutStyle);
		if (!(section instanceof HTMLElement)) {
			return;
		}

		const preset = section.querySelector('[data-eilmo-style-preset]');

		if (
			event.target.matches('[data-eilmo-style-preset]') &&
			event.target instanceof HTMLSelectElement &&
			event.target.value === 'premium_purple'
		) {
			Object.keys(PREMIUM_PURPLE_STYLE).forEach(function (key) {
				const input = section.querySelector(`[data-eilmo-style-color="${key}"]`);

				if (input instanceof HTMLInputElement) {
					input.value = PREMIUM_PURPLE_STYLE[key];
				}
			});

		}

		if (
			preset instanceof HTMLSelectElement &&
			!event.target.matches('[data-eilmo-style-preset]')
		) {
			preset.value = 'custom';
		}

		syncCheckoutStylePreview();

		syncSingleProductVariationSelection();
		syncProductLayoutControls();
	}

	/**
	 * Initialize.
	 *
	 * @return {void}
	 */
	function initialize() {
		document.documentElement.setAttribute(
			'data-eilmo-cf-settings-js',
			'loading'
		);

		document
			.querySelectorAll(
				[
					'[data-eilmo-advance-type]',
					'[data-eilmo-discount-type]',
					'[data-eilmo-coupon-type]',
				].join(',')
			)
			.forEach(
				function (select) {
					if (
						select instanceof
							HTMLSelectElement
					) {
						updateValueField(
							select
						);
					}
				}
			);

		initializePaymentMethodIds();

		initializeOrderBumps();
		initializeWooProductSearch();
		syncSpecialDiscountStyleControls(false);

		initializeComboOffers();
		syncComboMediaControls(document);

		initializeProductGalleries();

		initializeAdvancedControls();

		syncCheckoutStylePreview();
		syncComboStylePreview();
		syncSpecialStylePreview();
		syncSingleProductVariationSelection();
		syncProductLayoutControls();

		syncSettingsUi();
		syncPresetSelection();

		syncPaymentDefaultOptions();

		document.documentElement.setAttribute(
			'data-eilmo-cf-settings-js',
			'ready'
		);
	}

	document.addEventListener(
		'click',
		handleSettingsUxClick
	);

	document.addEventListener(
		'change',
		handleSettingsUxChange
	);

	document.addEventListener(
		'click',
		handleClick,
		true
	);

	document.addEventListener(
		'input',
		handleInput
	);

	document.addEventListener(
		'input',
		handleCheckoutStyleChange
	);

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'change',
		handleCheckoutStyleChange
	);

	document.addEventListener(
		'change',
		function (event) {
			if (
				event.target instanceof Element &&
				(
					event.target.matches(SELECTORS.singleVariationLayout) ||
					event.target.matches(SELECTORS.multipleVariationLayout) ||
					event.target.matches('input[name$="[show_variation_badges]"]')
				)
			) {
				syncSingleProductVariationSelection();
				syncProductLayoutControls();
			}
		}
	);

	document.addEventListener(
		'submit',
		handleSubmit
	);

	document.addEventListener(
		'invalid',
		revealInvalidSettingsField,
		true
	);

	document.addEventListener(
		'input',
		clearInvalidSettingsField
	);

	document.addEventListener(
		'change',
		clearInvalidSettingsField
	);

	document.addEventListener(
		'dragstart',
		handleGalleryDragStart
	);

	document.addEventListener(
		'dragover',
		handleGalleryDragOver
	);

	document.addEventListener(
		'dragend',
		handleGalleryDragEnd
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

/**
 * Special Offer admin extensions.
 *
 * Kept as a small independent controller so legacy Order Bump repeater
 * behavior remains backward compatible.
 */
(function () {
	'use strict';

	/*
	 * The settings asset can be printed twice by third-party admin optimizers.
	 * Keep this controller strictly single-instance so delegated events are not
	 * registered more than once.
	 */
	if (window.eilmoCfSpecialDiscountAdminReady) {
		return;
	}
	window.eilmoCfSpecialDiscountAdminReady = true;

	const itemSelector = '[data-eilmo-order-bump]';

	function getPrefix(item) {
		const field = item.querySelector('input[name*="[order_bumps][offers]"]');
		if (!(field instanceof HTMLInputElement)) {
			return '';
		}
		const match = String(field.name || '').match(/eilmo_cf_settings\[order_bumps\]\[offers\]\[(\d+)\]/);
		return match ? `eilmo_cf_settings[order_bumps][offers][${match[1]}]` : '';
	}

	function defaultEndValue() {
		const date = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
		const pad = function (value) { return String(value).padStart(2, '0'); };
		return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
	}

	function setOpenState(item, open) {
		if (!(item instanceof HTMLElement)) {
			return;
		}

		const toggle = item.querySelector('[data-eilmo-special-offer-toggle]');
		const content = item.querySelector('[data-eilmo-special-offer-content]');
		const icon = item.querySelector('[data-eilmo-special-offer-toggle-icon]');

		if (!(toggle instanceof HTMLElement) || !(content instanceof HTMLElement)) {
			return;
		}

		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		content.hidden = !open;
		item.classList.toggle('is-open', open);

		if (icon instanceof HTMLElement) {
			icon.textContent = open ? '▼' : '▶';
		}
	}

	function initializeAccordion(item) {
		if (!(item instanceof HTMLElement) || 'yes' === item.dataset.eilmoSpecialAccordionReady) {
			return;
		}

		const toggle = item.querySelector('[data-eilmo-special-offer-toggle]');
		if (!(toggle instanceof HTMLElement)) {
			return;
		}

		setOpenState(item, 'true' === toggle.getAttribute('aria-expanded'));
		item.dataset.eilmoSpecialAccordionReady = 'yes';
	}

	function annotateConditionalFields(item) {
		if (!(item instanceof HTMLElement)) {
			return;
		}

		const markers = [
			['select[name$="[apply_behavior]"]', 'data-eilmo-special-apply-behavior-field'],
			['select[name$="[ineligible_action]"]', 'data-eilmo-special-ineligible-field'],
			['input[name$="[locked_text]"]', 'data-eilmo-special-locked-text-field'],
			['select[name$="[image_position]"]', 'data-eilmo-special-image-position-field'],
			['input[name$="[before_apply_text]"], input[name$="[button_text]"]', 'data-eilmo-special-before-apply-text-field'],
			['input[name$="[applied_text]"]', 'data-eilmo-special-applied-text-field']
		];

		markers.forEach(function (entry) {
			const control = item.querySelector(entry[0]);
			const wrapper = control ? control.closest('.eilmo-cf-admin-field') : null;
			if (wrapper instanceof HTMLElement) {
				wrapper.setAttribute(entry[1], '');
			}
		});

		const ineligible = item.querySelector('select[name$="[ineligible_action]"]');
		if (ineligible instanceof HTMLSelectElement) {
			ineligible.setAttribute('data-eilmo-special-ineligible-action', '');
		}

		const lockedText = item.querySelector('input[name$="[locked_text]"]');
		if (lockedText instanceof HTMLInputElement && !lockedText.value.trim()) {
			lockedText.value = 'Add {remaining_amount} more to unlock this reward.';
		}
	}

	function ensureFields(item) {
		if (!(item instanceof HTMLElement)) {
			return;
		}

		annotateConditionalFields(item);
		if (item.querySelector('[data-eilmo-special-offer-type]')) {
			return;
		}

		const prefix = getPrefix(item);
		const grid = item.querySelector('.eilmo-cf-admin-grid');
		if (!prefix || !(grid instanceof HTMLElement)) {
			return;
		}

		grid.insertAdjacentHTML('beforeend', `
			<div class="eilmo-cf-admin-field">
				<label>Reward Type</label>
				<select name="${prefix}[offer_type]" data-eilmo-special-offer-type>
					<option value="percentage_discount">Percentage Discount</option>
					<option value="fixed_discount">Fixed Amount Discount</option>
					<option value="free_delivery">Free Delivery</option>
					<option value="free_gift">Free Product / Gift</option>
				</select>
				<p class="description">The reward is applied only after the condition is satisfied.</p>
			</div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-style-field="promo_banner,reward_tiles"><label>Card Label</label><input type="text" name="${prefix}[label]" value="LIMITED TIME OFFER"></div>
			<div class="eilmo-cf-admin-field"><label>Start Date & Time (Optional)</label><input type="datetime-local" name="${prefix}[start_at]"></div>
			<div class="eilmo-cf-admin-field"><label>End Date & Time (Required)</label><input type="datetime-local" name="${prefix}[end_at]" value="${defaultEndValue()}" required><p class="description">Uses the WordPress site timezone.</p></div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-style-field="promo_banner,reward_tiles"><label>Countdown Timer</label><label class="eilmo-cf-switch"><input type="checkbox" name="${prefix}[timer_enabled]" value="yes" checked><span class="eilmo-cf-switch__slider"></span></label></div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-before-apply-text-field><label>Before Apply Text</label><input type="text" name="${prefix}[before_apply_text]" value="Add Reward"></div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-applied-text-field><label>Applied Text</label><input type="text" name="${prefix}[applied_text]" value="Added"></div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-product-field><label>Reward Display Title (Optional)</label><input type="text" name="${prefix}[reward_title]" value=""><p class="description">Used for Free Gift cards. Leave empty to show the selected product title.</p></div>
			<div class="eilmo-cf-admin-field"><label>Image Source</label><select name="${prefix}[image_source]" data-eilmo-special-image-source><option value="product">Product Featured Image</option><option value="custom">Custom Media Image</option><option value="hidden">No Image</option></select></div>
			<div class="eilmo-cf-admin-field" data-eilmo-special-image-position-field><label>Image Vertical Position</label><select name="${prefix}[image_position]"><option value="top">Top</option><option value="center" selected>Center</option><option value="bottom">Bottom</option></select><p class="description">Controls where the full-height transparent PNG sits inside the card.</p></div>
			<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-special-media-wrap hidden>
				<label>Custom Promotional Image</label>
				<div class="eilmo-cf-admin-single-media" data-eilmo-special-media-picker>
					<input type="hidden" name="${prefix}[image_media_id]" value="0" data-eilmo-special-media-id>
					<div class="eilmo-cf-admin-single-media__preview" data-eilmo-special-media-preview></div>
					<div class="eilmo-cf-admin-single-media__actions"><button type="button" class="button button-secondary" data-eilmo-select-special-media>Select Image</button><button type="button" class="button button-link-delete" data-eilmo-clear-special-media hidden>Remove</button></div>
				</div>
			</div>
		`);

		item.querySelectorAll('input[name$="[product_id]"], input[name$="[variation_id]"], input[name$="[quantity]"], input[name$="[reward_title]"]').forEach(function (field) {
			const wrapper = field.closest('.eilmo-cf-admin-field');
			if (wrapper instanceof HTMLElement) {
				wrapper.setAttribute('data-eilmo-special-product-field', '');
			}
		});
		annotateConditionalFields(item);

		syncItem(item);
	}

	/**
	 * Hide an inapplicable field group and remove its controls from native
	 * validation/submission. Values remain in the DOM and are restored when the
	 * merchant selects a condition or reward type that uses the group again.
	 *
	 * @param {HTMLElement|null} group Field wrapper.
	 * @param {boolean} active Whether the group applies.
	 *
	 * @return {void}
	 */
	function setFieldGroupActive(group, active) {
		if (!(group instanceof HTMLElement)) {
			return;
		}

		group.hidden = !active;

		group.querySelectorAll('input,select,textarea,button').forEach(function (control) {
			if (!('disabled' in control)) {
				return;
			}

			if (!active) {
				if (!control.disabled) {
					control.dataset.eilmoConditionalDisabled = 'yes';
					control.disabled = true;
				}
				return;
			}

			if ('yes' === control.dataset.eilmoConditionalDisabled) {
				control.disabled = false;
				delete control.dataset.eilmoConditionalDisabled;
			}
		});
	}

	function setPresentationFieldVisible(group, visible) {
		if (group instanceof HTMLElement) {
			group.hidden = !visible;
		}
	}

	function syncItem(item) {
		if (!(item instanceof HTMLElement)) {
			return;
		}

		const type = item.querySelector('[data-eilmo-special-offer-type]');
		const imageSource = item.querySelector('[data-eilmo-special-image-source]');
		const offerType = type instanceof HTMLSelectElement ? type.value : 'percentage_discount';
		const isDelivery = 'free_delivery' === offerType;
		const isCartReward = 'percentage_discount' === offerType || 'fixed_discount' === offerType;
		const hasRewardProduct = 'free_gift' === offerType;

		const scopeSelect = item.querySelector('[data-eilmo-special-rule-scope]');
		const scopeProducts = item.querySelector('[data-eilmo-special-scope-products]');
		setFieldGroupActive(scopeProducts, scopeSelect instanceof HTMLSelectElement && 'selected_products' === scopeSelect.value);
		item.querySelectorAll('[data-eilmo-special-free-delivery-field]').forEach(function (field) { setFieldGroupActive(field, isDelivery); });

		item.querySelectorAll('[data-eilmo-special-product-field]').forEach(function (field) {
			if (field instanceof HTMLElement) {
				setFieldGroupActive(field, hasRewardProduct);
			}
		});

		const pricingTypeField = item.querySelector('[data-eilmo-special-pricing-type-field]');
		const pricingValueField = item.querySelector('[data-eilmo-special-pricing-value-field]');
		const maximumDiscountField = item.querySelector('[data-eilmo-special-maximum-discount-field]');
		setFieldGroupActive(pricingTypeField, false);
		if (pricingValueField instanceof HTMLElement) {
			setFieldGroupActive(pricingValueField, isCartReward);
			const label = pricingValueField.querySelector('label');
			if (label instanceof HTMLElement) {
				label.textContent = 'percentage_discount' === offerType ? 'Discount Percentage' : 'Discount Amount';
			}
		}
		setFieldGroupActive(maximumDiscountField, isCartReward);

		const conditionSelect = item.querySelector('[data-eilmo-special-condition-type]');
		const conditionType = conditionSelect instanceof HTMLSelectElement ? conditionSelect.value : 'always';
		const conditionAmountLabel = item.querySelector('[data-eilmo-condition-amount-label]');
		if (conditionAmountLabel instanceof HTMLElement) {
			conditionAmountLabel.textContent = 'minimum_spend' === conditionType ? 'Minimum Spend' : 'Minimum Subtotal';
		}
		const alwaysWarning = item.querySelector('[data-eilmo-special-always-warning]');
		setPresentationFieldVisible(alwaysWarning, 'always' === conditionType);
		item.querySelectorAll('[data-eilmo-condition-amount]').forEach(function (field) { setFieldGroupActive(field, ['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(conditionType)); });
		item.querySelectorAll('[data-eilmo-condition-product]').forEach(function (field) { setFieldGroupActive(field, ['specific_product', 'product_exists'].includes(conditionType)); });
		item.querySelectorAll('[data-eilmo-condition-cart-quantity]').forEach(function (field) { setFieldGroupActive(field, ['cart_quantity', 'minimum_quantity', 'buy_x_quantity'].includes(conditionType)); });

		const ineligibleField = item.querySelector('[data-eilmo-special-ineligible-field]');
		const ineligibleSelect = item.querySelector('[data-eilmo-special-ineligible-action]');
		const hasEligibilityCondition = 'always' !== conditionType;
		setPresentationFieldVisible(ineligibleField, hasEligibilityCondition);

		const lockedTextField = item.querySelector('[data-eilmo-special-locked-text-field]');
		const showLockedText = hasEligibilityCondition && ineligibleSelect instanceof HTMLSelectElement && 'show_locked' === ineligibleSelect.value;
		setPresentationFieldVisible(lockedTextField, showLockedText);
		const lockedText = item.querySelector('input[name$="[locked_text]"]');
		if (lockedText instanceof HTMLInputElement) {
			const progressDefaults = [
				'Add {remaining_amount} more to unlock this reward.',
				'Add {remaining_quantity} more of product {required_product} to unlock this reward.',
				'Add {remaining_quantity} more product(s) to unlock this reward.'
			];
			if (!lockedText.value.trim() || progressDefaults.includes(lockedText.value.trim())) {
				if ('specific_product' === conditionType) {
					lockedText.value = progressDefaults[1];
				} else if ('cart_quantity' === conditionType) {
					lockedText.value = progressDefaults[2];
				} else {
					lockedText.value = progressDefaults[0];
				}
			}
		}

		const mediaWrap = item.querySelector('[data-eilmo-special-media-wrap]');
		setFieldGroupActive(
			mediaWrap,
			imageSource instanceof HTMLSelectElement && 'custom' === imageSource.value
		);
		const imagePositionField = item.querySelector('[data-eilmo-special-image-position-field]');
		const effectiveStyleSelect = Array.from(document.querySelectorAll('[data-eilmo-special-card-style]')).find(function (select) {
			return select instanceof HTMLSelectElement && Boolean(select.name);
		});
		const effectiveStyle = effectiveStyleSelect instanceof HTMLSelectElement ? effectiveStyleSelect.value : 'promo_banner';
		const iconPresetField = item.querySelector('[data-eilmo-special-icon-preset-field]');
		setPresentationFieldVisible(
			iconPresetField,
			imageSource instanceof HTMLSelectElement && 'preset' === imageSource.value
		);
		setPresentationFieldVisible(
			imagePositionField,
			imageSource instanceof HTMLSelectElement &&
				'hidden' !== imageSource.value &&
				'compact_strip' !== effectiveStyle
		);

		if (imageSource instanceof HTMLSelectElement) {
			const productImageOption = imageSource.querySelector('option[value="product"]');
			if (productImageOption instanceof HTMLOptionElement) {
				productImageOption.disabled = !hasRewardProduct;
			}
			if (!hasRewardProduct && 'product' === imageSource.value) {
				imageSource.value = 'preset';
				setFieldGroupActive(mediaWrap, false);
				setPresentationFieldVisible(iconPresetField, true);
			}
		}

		const quantity = item.querySelector('input[name$="[quantity]"]');
		const pricingType = item.querySelector('select[name$="[pricing_type]"]');
		const pricingValue = item.querySelector('input[name$="[pricing_value]"]');
		const pricingTypeHidden = item.querySelector('input[name$="[pricing_type]"]');
		if (pricingTypeHidden instanceof HTMLInputElement) {
			pricingTypeHidden.value = 'fixed_discount' === offerType ? 'fixed_discount' : ('percentage_discount' === offerType ? 'percentage_discount' : ('free_gift' === offerType ? 'fixed_price' : 'regular_price'));
		}
		const behavior = item.querySelector('[data-eilmo-special-apply-behavior]');
		const behaviorField = item.querySelector('[data-eilmo-special-apply-behavior-field]');
		setPresentationFieldVisible(behaviorField, hasRewardProduct);
		if (!hasRewardProduct && behavior instanceof HTMLSelectElement) {
			behavior.value = 'auto_apply';
		}

		setPresentationFieldVisible(
			item.querySelector('[data-eilmo-special-before-apply-text-field]'),
			true
		);
		setPresentationFieldVisible(
			item.querySelector('[data-eilmo-special-applied-text-field]'),
			true
		);
		if ('buy_one_get_one' === offerType) {
			if (quantity instanceof HTMLInputElement) { quantity.value = '2'; }
			if (pricingType instanceof HTMLSelectElement) { pricingType.value = 'percentage_discount'; }
			if (pricingValue instanceof HTMLInputElement) { pricingValue.value = '50'; }
		} else if ('free_gift' === offerType) {
			if (pricingType instanceof HTMLSelectElement) { pricingType.value = 'fixed_price'; }
			if (pricingValue instanceof HTMLInputElement) { pricingValue.value = '0'; }
		} else if ('percentage_discount' === offerType) {
			if (pricingType instanceof HTMLSelectElement) { pricingType.value = 'percentage_discount'; }
		} else if ('fixed_discount' === offerType) {
			if (pricingType instanceof HTMLSelectElement) { pricingType.value = 'fixed_discount'; }
		}

		/* Keep the collapsed rule card useful while the merchant edits it. */
		const summary = item.querySelector('.eilmo-cf-admin-rule-summary');
		if (summary instanceof HTMLElement) {
			const enabledControl = item.querySelector('input[name$="[enabled]"]');
			const enabled = !(enabledControl instanceof HTMLInputElement) || enabledControl.checked;
			const conditionLabel = conditionSelect instanceof HTMLSelectElement && conditionSelect.selectedOptions.length
				? conditionSelect.selectedOptions[0].textContent.trim()
				: 'Always';
			const rewardLabel = type instanceof HTMLSelectElement && type.selectedOptions.length
				? type.selectedOptions[0].textContent.trim()
				: 'Percentage Discount';
			summary.textContent = (enabled ? 'Enabled' : 'Disabled') + ' · ' + conditionLabel + ' → ' + rewardLabel;
		}
	}

	function initialize(root) {
		const scope = root && typeof root.querySelectorAll === 'function'
			? root
			: document;
		const items = Array.from(scope.querySelectorAll(itemSelector));

		if (scope instanceof Element && scope.matches(itemSelector)) {
			items.unshift(scope);
		}

		items.forEach(function (item) {
			if (item instanceof HTMLElement) {
				ensureFields(item);
				syncItem(item);
				initializeAccordion(item);
			}
		});
	}

	document.addEventListener('change', function (event) {
		if (!(event.target instanceof Element) || (!event.target.matches('[data-eilmo-special-offer-type]') && !event.target.matches('[data-eilmo-special-image-source]') && !event.target.matches('[data-eilmo-special-condition-type]') && !event.target.matches('[data-eilmo-special-ineligible-action]') && !event.target.matches('[data-eilmo-special-rule-scope]') && !event.target.matches('input[name$="[enabled]"]'))) {
			return;
		}
		const item = event.target.closest(itemSelector);
		if (item instanceof HTMLElement) {
			syncItem(item);
		}
	});

	document.addEventListener('click', function (event) {
		if (!(event.target instanceof Element)) {
			return;
		}

		const toggle = event.target.closest('[data-eilmo-special-offer-toggle]');
		if (toggle) {
			event.preventDefault();
			const item = toggle.closest(itemSelector);
			if (item instanceof HTMLElement) {
				setOpenState(item, 'true' !== toggle.getAttribute('aria-expanded'));
			}
			return;
		}

		if (event.target.closest('[data-eilmo-add-order-bump]')) {
			document.querySelectorAll(itemSelector).forEach(function (item) {
				if (item instanceof HTMLElement) {
					setOpenState(item, false);
				}
			});
			window.setTimeout(function () {
				const items = document.querySelectorAll(itemSelector);
				const newest = items.length ? items[items.length - 1] : null;
				if (newest instanceof HTMLElement) {
					initialize(newest);
					setOpenState(newest, true);
					if (window.jQuery) { window.jQuery(document.body).trigger('wc-enhanced-select-init'); }
				}
			}, 0);
			return;
		}

		const selectButton = event.target.closest('[data-eilmo-select-special-media]');
		if (selectButton) {
			event.preventDefault();
			const picker = selectButton.closest('[data-eilmo-special-media-picker]');
			if (!(picker instanceof HTMLElement) || !window.wp || !window.wp.media) {
				return;
			}
			const frame = window.wp.media({ title: 'Select Promotional Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				const input = picker.querySelector('[data-eilmo-special-media-id]');
				const preview = picker.querySelector('[data-eilmo-special-media-preview]');
				const clear = picker.querySelector('[data-eilmo-clear-special-media]');
				if (input instanceof HTMLInputElement) { input.value = String(attachment.id || 0); }
				if (preview instanceof HTMLElement) { preview.innerHTML = `<img src="${String(attachment.url || '')}" alt="">`; }
				if (clear instanceof HTMLElement) { clear.hidden = false; }
			});
			frame.open();
			return;
		}

		const clearButton = event.target.closest('[data-eilmo-clear-special-media]');
		if (clearButton) {
			event.preventDefault();
			const picker = clearButton.closest('[data-eilmo-special-media-picker]');
			if (!(picker instanceof HTMLElement)) { return; }
			const input = picker.querySelector('[data-eilmo-special-media-id]');
			const preview = picker.querySelector('[data-eilmo-special-media-preview]');
			if (input instanceof HTMLInputElement) { input.value = '0'; }
			if (preview instanceof HTMLElement) { preview.innerHTML = ''; }
			clearButton.hidden = true;
		}
	});

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
