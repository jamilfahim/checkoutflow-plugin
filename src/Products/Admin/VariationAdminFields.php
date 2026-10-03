<?php
/**
 * Variation presentation fields.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Products\Services\DefaultVariationResolver;
use EilmoCheckout\Products\Services\VariationBadgeService;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Eilmo default-selection and custom-badge fields to Woo variations.
 */
final class VariationAdminFields implements RegistrableInterface {

	/**
	 * Badge service.
	 *
	 * @var VariationBadgeService
	 */
	private $badge_service;

	/**
	 * Constructor.
	 *
	 * @param VariationBadgeService|null $badge_service Badge service.
	 */
	public function __construct(
		?VariationBadgeService $badge_service = null
	) {

		$this->badge_service = $badge_service ?? new VariationBadgeService();
	}

	/**
	 * Register WooCommerce variation editor hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'woocommerce_product_after_variable_attributes',
			array( $this, 'render_fields' ),
			20,
			3
		);

		add_action(
			'woocommerce_admin_process_product_object',
			array( $this, 'save_default_variation' )
		);

		add_action(
			'woocommerce_admin_process_variation_object',
			array( $this, 'save_badge_fields' ),
			20,
			2
		);
	}

	/**
	 * Render fields inside one variation editor row.
	 *
	 * @param int                   $loop           Variation index.
	 * @param array<string, mixed>  $variation_data Variation data.
	 * @param \WP_Post              $variation      Variation post.
	 *
	 * @return void
	 */
	public function render_fields(
		int $loop,
		array $variation_data,
		\WP_Post $variation
	): void {

		$variation_product = wc_get_product( $variation->ID );

		if ( ! $variation_product instanceof \WC_Product_Variation ) {
			return;
		}

		$parent = wc_get_product( $variation_product->get_parent_id() );
		$default_variation_id = $parent instanceof \WC_Product_Variable
			? absint( $parent->get_meta( DefaultVariationResolver::META_KEY, true ) )
			: 0;
		$badge = $this->badge_service->get_badge( $variation_product );
		?>
		<div class="eilmo-cf-variation-admin-fields form-row form-row-full">
			<h4><?php esc_html_e( 'Eilmo Checkout Presentation', 'eilmo-checkout-flow' ); ?></h4>

			<?php if ( 0 === $loop ) : ?>
				<label class="eilmo-cf-variation-admin-fields__default">
					<input type="radio" name="_eilmo_cf_default_variation_id" value="0" <?php checked( 0, $default_variation_id ); ?>>
					<?php esc_html_e( 'Use WooCommerce default attributes', 'eilmo-checkout-flow' ); ?>
				</label>
			<?php endif; ?>

			<label class="eilmo-cf-variation-admin-fields__default">
				<input type="radio" name="_eilmo_cf_default_variation_id" value="<?php echo esc_attr( (string) $variation->ID ); ?>" <?php checked( $variation->ID, $default_variation_id ); ?>>
				<?php esc_html_e( 'Use this as the Eilmo default variation', 'eilmo-checkout-flow' ); ?>
			</label>

			<p class="form-row form-row-first">
				<label>
					<input type="checkbox" name="variable_eilmo_cf_badge_enabled[<?php echo esc_attr( (string) $loop ); ?>]" value="yes" <?php checked( 'yes', $badge['enabled'] ); ?>>
					<?php esc_html_e( 'Show custom badge', 'eilmo-checkout-flow' ); ?>
				</label>
			</p>

			<p class="form-row form-row-last">
				<label for="variable_eilmo_cf_badge_type_<?php echo esc_attr( (string) $loop ); ?>"><?php esc_html_e( 'Badge Style', 'eilmo-checkout-flow' ); ?></label>
				<select id="variable_eilmo_cf_badge_type_<?php echo esc_attr( (string) $loop ); ?>" name="variable_eilmo_cf_badge_type[<?php echo esc_attr( (string) $loop ); ?>]">
					<option value="primary" <?php selected( 'primary', $badge['type'] ); ?>><?php esc_html_e( 'Primary', 'eilmo-checkout-flow' ); ?></option>
					<option value="success" <?php selected( 'success', $badge['type'] ); ?>><?php esc_html_e( 'Success', 'eilmo-checkout-flow' ); ?></option>
					<option value="warning" <?php selected( 'warning', $badge['type'] ); ?>><?php esc_html_e( 'Warning', 'eilmo-checkout-flow' ); ?></option>
				</select>
			</p>

			<p class="form-row form-row-full">
				<label for="variable_eilmo_cf_badge_text_<?php echo esc_attr( (string) $loop ); ?>"><?php esc_html_e( 'Badge Text', 'eilmo-checkout-flow' ); ?></label>
				<input type="text" maxlength="80" id="variable_eilmo_cf_badge_text_<?php echo esc_attr( (string) $loop ); ?>" name="variable_eilmo_cf_badge_text[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $badge['text'] ); ?>" placeholder="<?php esc_attr_e( 'Most Popular', 'eilmo-checkout-flow' ); ?>">
			</p>
		</div>
		<?php
	}

	/**
	 * Save the one Eilmo default variation on the parent product.
	 *
	 * @param \WC_Product $product Product being saved.
	 *
	 * @return void
	 */
	public function save_default_variation( \WC_Product $product ): void {

		if (
			! $product instanceof \WC_Product_Variable ||
			! current_user_can( 'edit_product', $product->get_id() ) ||
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			! isset( $_POST['_eilmo_cf_default_variation_id'] )
		) {
			return;
		}

		$variation_id = absint(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			wp_unslash( $_POST['_eilmo_cf_default_variation_id'] )
		);

		$variation = $variation_id > 0
			? wc_get_product( $variation_id )
			: null;

		if (
			$variation instanceof \WC_Product_Variation &&
			$variation->get_parent_id() === $product->get_id()
		) {
			$product->update_meta_data(
				DefaultVariationResolver::META_KEY,
				$variation_id
			);
			return;
		}

		$product->delete_meta_data( DefaultVariationResolver::META_KEY );
	}

	/**
	 * Save custom badge fields on one variation.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 * @param int                   $index     Variation index.
	 *
	 * @return void
	 */
	public function save_badge_fields(
		\WC_Product_Variation $variation,
		int $index
	): void {

		if ( ! current_user_can( 'edit_product', $variation->get_parent_id() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$enabled = isset( $_POST['variable_eilmo_cf_badge_enabled'][ $index ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			? sanitize_key( wp_unslash( $_POST['variable_eilmo_cf_badge_enabled'][ $index ] ) )
			: 'no';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$text = isset( $_POST['variable_eilmo_cf_badge_text'][ $index ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			? wp_unslash( $_POST['variable_eilmo_cf_badge_text'][ $index ] )
			: '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$type = isset( $_POST['variable_eilmo_cf_badge_type'][ $index ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			? wp_unslash( $_POST['variable_eilmo_cf_badge_type'][ $index ] )
			: 'primary';

		$this->badge_service->save_badge(
			$variation,
			$enabled,
			$text,
			$type
		);
	}
}
