<?php
namespace EilmoCheckout\Payment\Gateways;

use EilmoCheckout\Payment\Services\PaymentProof;

defined( 'ABSPATH' ) || exit;

abstract class AbstractMobileGateway extends \WC_Payment_Gateway {
	protected $eilmo_brand = '';
	public function __construct() {
		$this->has_fields = true; $this->supports = array( 'products' );
		$this->method_title = $this->eilmo_brand;
		$this->method_description = sprintf( __( 'Accept %s payments with transaction ID and optional payment proof.', 'eilmo-checkout-flow' ), $this->eilmo_brand );
		$this->init_form_fields(); $this->init_settings();
		$this->enabled = $this->get_option( 'enabled', 'yes' );
		$this->title = $this->get_option( 'title', $this->eilmo_brand );
		$this->description = $this->get_option( 'description', '' );

		/*
		 * WooCommerce expects the gateway icon property to contain only a URL.
		 * Older Eilmo custom-method data could contain rendered HTML in icon_url,
		 * which becomes a broken <img src> on native checkout. Reject malformed
		 * values and fall back to the bundled brand asset.
		 */
		$defaults = GatewaySettings::defaults( $this->id );
		$icon_url = self::sanitize_gateway_icon_url( GatewaySettings::usable_icon_url( $this->id, (string) $this->get_option( 'icon_url', '' ) ) );
		if ( '' === $icon_url ) {
			$icon_url = self::sanitize_gateway_icon_url( (string) ( $defaults['icon_url'] ?? '' ) );
		}
		$this->icon = $icon_url;
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	public function init_form_fields() {
		$d = GatewaySettings::defaults( $this->id );
		$this->form_fields = array(
			'enabled'=>array('title'=>__('Enable/Disable','eilmo-checkout-flow'),'type'=>'checkbox','label'=>sprintf(__('Enable Eilmo %s','eilmo-checkout-flow'),$this->eilmo_brand),'default'=>'yes'),
			'title'=>array('title'=>__('Title','eilmo-checkout-flow'),'type'=>'text','default'=>$d['title']),
			'description'=>array('title'=>__('Description','eilmo-checkout-flow'),'type'=>'textarea','default'=>$d['description']),
			'account_label'=>array('title'=>__('Account label','eilmo-checkout-flow'),'type'=>'text','default'=>$d['account_label']),
			'account_number'=>array('title'=>__('Account number','eilmo-checkout-flow'),'type'=>'text','default'=>''),
			'instructions'=>array('title'=>__('Payment instructions','eilmo-checkout-flow'),'type'=>'textarea','default'=>$d['instructions']),
			'transaction_id_required'=>array('title'=>__('Transaction ID','eilmo-checkout-flow'),'type'=>'checkbox','label'=>__('Require a transaction ID unless a valid screenshot is uploaded','eilmo-checkout-flow'),'default'=>'yes'),
			'transaction_id_label'=>array('title'=>__('Transaction ID label','eilmo-checkout-flow'),'type'=>'text','default'=>$d['transaction_id_label']),
			'transaction_id_placeholder'=>array('title'=>__('Transaction ID placeholder','eilmo-checkout-flow'),'type'=>'text','default'=>$d['transaction_id_placeholder']),
			'payment_proof_enabled'=>array('title'=>__('Payment screenshot','eilmo-checkout-flow'),'type'=>'checkbox','label'=>__('Allow payment screenshot upload','eilmo-checkout-flow'),'default'=>'yes'),
			'payment_proof_label'=>array('title'=>__('Screenshot label','eilmo-checkout-flow'),'type'=>'text','default'=>$d['payment_proof_label']),
			'payment_proof_help'=>array('title'=>__('Screenshot help text','eilmo-checkout-flow'),'type'=>'textarea','default'=>$d['payment_proof_help']),
			'payment_proof_max_mb'=>array('title'=>__('Maximum screenshot size (MB)','eilmo-checkout-flow'),'type'=>'number','default'=>5,'custom_attributes'=>array('min'=>1,'max'=>10,'step'=>1)),
			'icon_url'=>array('title'=>__('Icon URL','eilmo-checkout-flow'),'type'=>'text','default'=>$d['icon_url'],'description'=>__('Leave the bundled logo or paste a custom image URL.','eilmo-checkout-flow')),
		);
	}
	/** Return a safe plain image URL for WooCommerce gateway icon output. */
	private static function sanitize_gateway_icon_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || false !== strpos( $value, '<' ) || false !== strpos( $value, '>' ) ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		return esc_url( $value );
	}

	public function get_eilmo_payment_config(): array { $s=GatewaySettings::get($this->id); $s['gateway_id']=$this->id; $s['method_key']=GatewaySettings::method_key($this->id); return $s; }
	public function payment_fields() {
		$config = $this->get_eilmo_payment_config();
		$method_key = GatewaySettings::method_key( $this->id );
		$account_label = sanitize_text_field( (string) ( $config['account_label'] ?? '' ) );
		$account_number = sanitize_text_field( (string) ( $config['account_number'] ?? '' ) );
		$instructions = trim( (string) ( $config['instructions'] ?? '' ) );
		$transaction_label = sanitize_text_field( (string) ( $config['transaction_id_label'] ?? __( 'Transaction ID', 'eilmo-checkout-flow' ) ) );
		$transaction_placeholder = sanitize_text_field( (string) ( $config['transaction_id_placeholder'] ?? '' ) );
		$proof_enabled = 'yes' === (string) ( $config['payment_proof_enabled'] ?? 'no' );
		$proof_label = sanitize_text_field( (string) ( $config['payment_proof_label'] ?? __( 'Payment Screenshot', 'eilmo-checkout-flow' ) ) );
		$proof_help = sanitize_text_field( (string) ( $config['payment_proof_help'] ?? '' ) );
		$max_mb = max( 1, min( 10, absint( $config['payment_proof_max_mb'] ?? 5 ) ) );
		?>
		<div class="eilmo-cf-wc-manual-payment" data-eilmo-wc-manual-payment="<?php echo esc_attr( $method_key ); ?>">
			<?php if ( '' !== $account_number ) : ?>
				<div class="eilmo-cf-wc-manual-payment__account">
					<span class="eilmo-cf-wc-manual-payment__account-label"><?php echo esc_html( $account_label ); ?></span>
					<strong class="eilmo-cf-wc-manual-payment__account-value"><?php echo esc_html( $account_number ); ?></strong>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $instructions ) : ?>
				<div class="eilmo-cf-wc-manual-payment__instructions"><?php echo wp_kses_post( wpautop( $instructions ) ); ?></div>
			<?php endif; ?>

			<p class="form-row form-row-wide eilmo-cf-wc-manual-payment__transaction">
				<label for="<?php echo esc_attr( $this->id ); ?>_transaction_id"><?php echo esc_html( $transaction_label ); ?></label>
				<input type="text" class="input-text" id="<?php echo esc_attr( $this->id ); ?>_transaction_id" name="<?php echo esc_attr( $this->id ); ?>_transaction_id" placeholder="<?php echo esc_attr( $transaction_placeholder ); ?>">
			</p>

			<?php if ( $proof_enabled ) : ?>
				<div class="eilmo-cf-wc-proof eilmo-cf-wc-manual-payment__proof" data-eilmo-wc-proof data-method-key="<?php echo esc_attr( $method_key ); ?>" data-proof-max-mb="<?php echo esc_attr( (string) $max_mb ); ?>">
					<label class="eilmo-cf-wc-manual-payment__proof-label"><?php echo esc_html( $proof_label ); ?></label>
					<input class="eilmo-cf-wc-manual-payment__proof-file" type="file" accept="image/jpeg,image/png,image/webp" data-eilmo-wc-proof-file>
					<input type="hidden" name="<?php echo esc_attr( $this->id ); ?>_proof_token" value="" data-eilmo-wc-proof-token>
					<?php if ( '' !== $proof_help ) : ?>
						<small class="eilmo-cf-wc-manual-payment__proof-help" data-eilmo-wc-proof-status><?php echo esc_html( $proof_help ); ?></small>
					<?php else : ?>
						<small class="eilmo-cf-wc-manual-payment__proof-help" data-eilmo-wc-proof-status></small>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function validate_fields() {
		$c=$this->get_eilmo_payment_config(); $key=GatewaySettings::method_key($this->id);
		$tx=$this->posted_transaction_id($key); $proof=$this->posted_proof_token($key);
		$valid=$proof!==''&&PaymentProof::is_valid_token($proof,$key); $tr='yes'===(string)($c['transaction_id_required']??'yes'); $pe='yes'===(string)($c['payment_proof_enabled']??'no');
		if($pe&&$tx===''&&!$valid){wc_add_notice($tr?__('Please enter the transaction ID or upload the payment screenshot.','eilmo-checkout-flow'):__('Please upload the payment screenshot.','eilmo-checkout-flow'),'error');return false;}
		if($tr&&!$pe&&$tx===''){wc_add_notice(__('Please enter the transaction ID.','eilmo-checkout-flow'),'error');return false;} return true;
	}
	/** Process a manual mobile payment without trusting frontend status. */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return array( 'result' => 'failure' );
		}

		$key   = GatewaySettings::method_key( $this->id );
		$tx    = $this->posted_transaction_id( $key );
		$proof = $this->posted_proof_token( $key );

		if ( '' !== $tx ) {
			$order->update_meta_data( '_eilmo_cf_payment_transaction_id', $tx );
		}

		$order->update_meta_data( '_eilmo_cf_payment_method_key', $key );
		$order->update_meta_data( '_eilmo_cf_payment_gateway_id', $this->id );
		$order->update_meta_data( '_eilmo_cf_payment_source', 'eilmo_gateway' );
		$order->update_meta_data( '_eilmo_cf_payment_status', 'awaiting_verification' );

		if ( '' === (string) $order->get_meta( '_eilmo_cf_created_via', true ) ) {
			$total = max( 0.0, (float) $order->get_total() );
			$order->update_meta_data( '_eilmo_cf_created_via', 'eilmo-checkout-flow' );
			$order->update_meta_data( '_eilmo_cf_payment_type', 'full' );
			$order->update_meta_data( '_eilmo_cf_grand_total', wc_format_decimal( $total ) );
			$order->update_meta_data( '_eilmo_cf_pay_now', wc_format_decimal( $total ) );
			$order->update_meta_data( '_eilmo_cf_remaining_due', wc_format_decimal( 0 ) );
			$order->update_meta_data( '_eilmo_cf_advance_amount', wc_format_decimal( 0 ) );
		}

		$proof_attached = true;
		if ( '' !== $proof ) {
			$proof_attached =
				PaymentProof::is_attached_to_order( $order, $key ) ||
				PaymentProof::assign_to_order( $order, $proof, $key );
		}

		$order->update_status(
			'on-hold',
			sprintf(
				__( 'Awaiting %s payment verification.', 'eilmo-checkout-flow' ),
				$this->eilmo_brand
			)
		);

		/*
		 * The order already exists at this point. If a proof file cannot be
		 * attached, keep checkout successful to avoid accidental duplicate
		 * orders, but surface an explicit operational error for the merchant.
		 */
		if ( ! $proof_attached ) {
			$order->update_meta_data( '_eilmo_cf_payment_status', 'payment_error' );
			$order->add_order_note(
				__( 'Payment screenshot could not be attached. Verify the transaction manually before approving payment.', 'eilmo-checkout-flow' )
			);
			do_action( 'eilmo_cf/payment_proof_attachment_failed', $order->get_id(), $key, $order );
		} else {
			$order->update_meta_data( '_eilmo_cf_payment_status', 'awaiting_verification' );
		}

		$order->save();

		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/** Read a transaction ID from direct-gateway, native bridge or Eilmo-renderer fields. */
	private function posted_transaction_id( string $method_key ): string {
		$value = sanitize_text_field( wp_unslash( (string) ( $_POST[ $this->id . '_transaction_id' ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $value ) {
			return $value;
		}
		$value = sanitize_text_field( wp_unslash( (string) ( $_POST['eilmo_cf_native_transaction_id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $value ) {
			return $value;
		}
		$prefix = 'eilmo_payment_transaction_';
		$suffix = '_' . sanitize_key( $method_key );
		foreach ( $_POST as $posted_key => $posted_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! is_scalar( $posted_value ) ) {
				continue;
			}
			$posted_key = (string) $posted_key;
			if ( 0 === strpos( $posted_key, $prefix ) && substr( $posted_key, -strlen( $suffix ) ) === $suffix ) {
				return sanitize_text_field( wp_unslash( (string) $posted_value ) );
			}
		}
		return '';
	}

	/** Read a proof token from direct-gateway, native bridge or Eilmo-renderer fields. */
	private function posted_proof_token( string $method_key ): string {
		$value = sanitize_text_field( wp_unslash( (string) ( $_POST[ $this->id . '_proof_token' ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $value ) {
			return $value;
		}
		$value = sanitize_text_field( wp_unslash( (string) ( $_POST['eilmo_cf_native_proof_token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $value ) {
			return $value;
		}
		$prefix = 'eilmo_payment_proof_';
		$suffix = '_' . sanitize_key( $method_key );
		foreach ( $_POST as $posted_key => $posted_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! is_scalar( $posted_value ) ) {
				continue;
			}
			$posted_key = (string) $posted_key;
			if ( 0 === strpos( $posted_key, $prefix ) && substr( $posted_key, -strlen( $suffix ) ) === $suffix ) {
				return sanitize_text_field( wp_unslash( (string) $posted_value ) );
			}
		}
		return '';
	}

}
