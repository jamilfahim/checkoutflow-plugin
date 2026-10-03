<?php
/**
 * Checkout Flow Elementor widget.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Widgets;

use EilmoCheckout\Elementor\Controls\DesignControls;
use EilmoCheckout\Elementor\Elementor as ElementorIntegration;
use EilmoCheckout\Rendering\CheckoutRenderer;
use EilmoCheckout\Admin\CheckoutStyle;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use EilmoCheckout\Core\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout Flow Elementor widget.
 *
 * Business rules and presentation remain managed by Checkout Flow plugin settings.
 * Elementor assigns checkout-instance content only, plus optional design styling.
 */
final class CheckoutFlowWidget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {

		return 'eilmo-checkout-flow';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __(
			'Checkout Flow',
			'eilmo-checkout-flow'
		);
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {

		return 'eicon-cart';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {

		return array(
			ElementorIntegration::get_category(),
		);
	}

	/**
	 * Widget keywords.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {

		return array(
			'checkout',
			'woocommerce',
			'order',
			'cart',
			'payment',
			'eilmo',
		);
	}

    /**
     * Get widget style dependencies.
     *
     * This ensures the normal Checkout Flow frontend
     * stylesheet is also loaded inside Elementor Preview.
     *
     * @return array<int, string>
     */
    public function get_style_depends(): array {

        Assets::register_frontend();

        return wp_style_is( Assets::FRONTEND_STYLE, 'registered' )
            ? array( Assets::FRONTEND_STYLE )
            : array();
    }

    /**
     * Get widget script dependencies.
     *
     * The same Checkout Flow frontend scripts used by
     * shortcode rendering are reused by Elementor.
     *
     * @return array<int, string>
     */
    public function get_script_depends(): array {

        return Assets::get_frontend_script_handles();
    }

	/**
	 * Avoid an unnecessary Elementor inner wrapper.
	 *
	 * CheckoutRenderer already owns the checkout wrapper.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {

		return false;
	}

	/**
	 * Register widget controls.
	 *
	 * Plugin business configuration is intentionally not duplicated here.
	 * Only checkout-instance assignment plus visual Design controls belong
	 * to the Elementor widget.
	 *
	 * @return void
	 */
	protected function register_controls(): void {

		$this->register_checkout_source_controls();
		$this->register_delivery_payment_controls();

		if (
			class_exists(
				DesignControls::class
			) &&
			method_exists(
				DesignControls::class,
				'register'
			)
		) {
			DesignControls::register(
				$this
			);
		}
	}

	/**
	 * Register the minimum checkout-instance controls.
	 *
	 * These are not duplicate business settings. They only decide which
	 * products / reusable offers belong to this individual widget instance.
	 *
	 * @return void
	 */
	private function register_checkout_source_controls(): void {

		$this->start_controls_section(
			'eilmo_cf_checkout_source',
			array(
				'label' => __( 'Checkout Content', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'eilmo_cf_settings_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => __( 'Keep checkout focused on buying. Design product cards in Elementor with Product Button, Variation Selector and Combo Button widgets. The checkout can still show a compact package selector for the primary product.', 'eilmo-checkout-flow' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control( 'checkout_layout', array(
			'label' => __( 'Checkout Layout', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => '',
			'options' => array( '' => __( 'Inherit Global', 'eilmo-checkout-flow' ), 'inline' => __( 'Full Inline', 'eilmo-checkout-flow' ), 'right_sticky' => __( 'Right-side Sticky', 'eilmo-checkout-flow' ) ),
		) );

		$this->add_control( 'checkout_language', array(
			'label' => __( 'Checkout Language', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => '',
			'options' => array( '' => __( 'Inherit Global', 'eilmo-checkout-flow' ), 'en' => 'English', 'bn' => 'বাংলা' ),
		) );

		$this->add_control( 'checkout_mode', array(
			'label' => __( 'Checkout Mode', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => 'single',
			'options' => array(
				'single' => __( 'Single Product', 'eilmo-checkout-flow' ),
				'multiple' => __( 'Multiple Products', 'eilmo-checkout-flow' ),
			),
			'description' => __( 'Single Product keeps one primary product selected. Multiple Products starts empty and accepts products from Elementor Product Button / Variation Selector widgets.', 'eilmo-checkout-flow' ),
		) );

		$this->add_control( 'product_id', array(
			'label' => __( 'Primary Product', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT2,
			'options' => $this->get_product_options(),
			'multiple' => false,
			'label_block' => true,
			'condition' => array( 'checkout_mode' => 'single' ),
			'description' => __( 'Required for Single Product checkout. Product/Variation buttons for this same product synchronize with the package section instead of adding extra rows.', 'eilmo-checkout-flow' ),
		) );

		$this->add_control( 'show_package_selection', array(
			'label' => __( 'Show Product / Package Section', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SWITCHER,
			'label_on' => __( 'Show', 'eilmo-checkout-flow' ),
			'label_off' => __( 'Hide', 'eilmo-checkout-flow' ),
			'return_value' => 'yes',
			'default' => 'yes',
			'condition' => array( 'checkout_mode' => 'single' ),
		) );

		foreach ( array(
			'show_package_title' => __( 'Show Package Title', 'eilmo-checkout-flow' ),
			'show_package_helper' => __( 'Show Text Below Package Title', 'eilmo-checkout-flow' ),
			'show_selected_quantity' => __( 'Show Selected & Quantity Section', 'eilmo-checkout-flow' ),
			'show_descriptions' => __( 'Show Descriptions', 'eilmo-checkout-flow' ),
		) as $control => $label ) {
			$this->add_control( $control, array(
				'label' => $label,
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'eilmo-checkout-flow' ),
				'label_off' => __( 'Hide', 'eilmo-checkout-flow' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => array( 'checkout_mode' => 'single', 'show_package_selection' => 'yes' ),
			) );
		}

		$this->add_control( 'max_visible_variations', array(
			'label' => __( 'Visible Variations Before “Change”', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::NUMBER,
			'default' => 4,
			'min' => 1,
			'max' => 12,
			'step' => 1,
			'condition' => array( 'checkout_mode' => 'single', 'show_package_selection' => 'yes' ),
			'description' => __( 'Up to this number are shown directly. Larger variation sets use a compact selected card with a Change modal.', 'eilmo-checkout-flow' ),
		) );


		$this->end_controls_section();
	}

	/** Presentation-only overrides for this checkout instance. */
	private function register_delivery_payment_controls(): void {
		$this->start_controls_section( 'eilmo_cf_delivery_payment_layout', array(
			'label' => __( 'Delivery & Payment Layout', 'eilmo-checkout-flow' ),
			'tab' => Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'show_delivery_section', array(
			'label' => __( 'Show Delivery Section', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SWITCHER,
			'label_on' => __( 'Show', 'eilmo-checkout-flow' ),
			'label_off' => __( 'Hide', 'eilmo-checkout-flow' ),
			'return_value' => 'yes',
			'default' => 'yes',
			'description' => __( 'The default delivery method and charge still apply when hidden.', 'eilmo-checkout-flow' ),
		) );
		$groups = array(
			'delivery' => __( 'Delivery Cards', 'eilmo-checkout-flow' ),
			'payment_options' => __( 'Payment Options', 'eilmo-checkout-flow' ),
			'payment_methods' => __( 'Payment Methods', 'eilmo-checkout-flow' ),
		);
		foreach ( $groups as $group => $title ) {
			$this->add_control( $group . '_layout_heading', array(
				'label' => $title,
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			) );
			if ( 'payment_methods' === $group ) {
				$this->add_control( 'payment_methods_arrangement', array(
					'label' => __( 'Arrangement', 'eilmo-checkout-flow' ),
					'type' => Controls_Manager::SELECT,
					'default' => '',
					'options' => array( '' => __( 'Use Plugin Settings', 'eilmo-checkout-flow' ), 'list' => __( 'List', 'eilmo-checkout-flow' ), 'grid' => __( 'Grid', 'eilmo-checkout-flow' ) ),
				) );
			}
			foreach ( array( 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ) as $device => $maximum ) {
				$options = array( '' => __( 'Use Plugin Settings', 'eilmo-checkout-flow' ) );
				for ( $columns = 1; $columns <= $maximum; $columns++ ) {
					$options[ (string) $columns ] = (string) $columns;
				}
				$this->add_control( $group . '_columns_' . $device, array(
					'label' => sprintf( __( '%s Columns', 'eilmo-checkout-flow' ), ucfirst( $device ) ),
					'type' => Controls_Manager::SELECT,
					'default' => '',
					'options' => $options,
					'condition' => 'payment_methods' === $group ? array( 'payment_methods_arrangement!' => 'list' ) : array(),
				) );
			}
		}
		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 *
	 * @return void
	 */
	protected function render(): void {

		$elementor_settings =
			$this->get_settings_for_display();

		$settings =
			$this->build_checkout_settings(
				is_array(
					$elementor_settings
				)
					? $elementor_settings
					: array()
			);

		/**
		 * Filters CheckoutRenderer settings generated by
		 * the Elementor Checkout Flow widget.
		 *
		 * @param array<string, mixed> $settings           Checkout settings.
		 * @param CheckoutFlowWidget   $widget             Widget instance.
		 * @param array<string, mixed> $elementor_settings Elementor settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/elementor/checkout_settings',
				$settings,
				$this,
				$elementor_settings
			);

		if ( ! is_array( $settings ) ) {
			return;
		}

		$renderer =
			new CheckoutRenderer();

		$html =
			$renderer->render(
				$settings
			);

		if ( '' === $html ) {

			$this->render_editor_placeholder();

			return;
		}

		/*
		 * Elementor live preview changes Style controls client-side without
		 * re-running the PHP renderer. Keep the saved Global Checkout Style on
		 * a parent wrapper and let Elementor-generated selectors override the
		 * checkout root. This avoids stale inline variables blocking live preview.
		 */
		$global_theme_variables = CheckoutStyle::get_css_variables();

		echo '<div class="eilmo-cf-elementor-theme-scope" style="' . esc_attr( $global_theme_variables ) . '">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CheckoutRenderer escapes its own output.
		echo $html;
		echo '</div>';
	}

	/**
	 * Build settings consumed by CheckoutRenderer.
	 *
	 * @param array<string, mixed> $elementor_settings Elementor settings.
	 *
	 * @return array<string, mixed>
	 */
	private function build_checkout_settings(
		array $elementor_settings
	): array {

		$multiple_product_ids = $this->normalize_numeric_ids(
			$elementor_settings['product_ids'] ?? array()
		);
		$single_product_id = absint( $elementor_settings['product_id'] ?? 0 );

		/*
		 * New Elementor widgets expose an explicit, simple checkout mode.
		 * Existing saved widgets created before 2.2.1 keep their historical
		 * product_mode value so upgrades do not silently rewrite old pages.
		 */
		$raw_widget_settings = $this->get_data( 'settings' );
		$raw_widget_settings = is_array( $raw_widget_settings ) ? $raw_widget_settings : array();
		$has_new_mode = array_key_exists( 'checkout_mode', $raw_widget_settings );
		$requested_mode = sanitize_key( (string) ( $elementor_settings['checkout_mode'] ?? 'single' ) );
		$legacy_product_mode = sanitize_key( (string) ( $raw_widget_settings['product_mode'] ?? '' ) );

		if ( $has_new_mode ) {
			$product_mode = 'multiple' === $requested_mode ? 'multiple' : 'single';
		} elseif ( in_array( $legacy_product_mode, array( 'single', 'multiple' ), true ) ) {
			$product_mode = $legacy_product_mode;
		} else {
			$product_mode = $single_product_id > 0 ? 'single' : 'multiple';
		}

		if ( 'single' === $product_mode ) {
			$product_ids = $single_product_id > 0 ? array( $single_product_id ) : array();
		} else {
			/* New Multiple Products starts empty. Old saved widgets may keep
			 * assigned products for backward compatibility. */
			$product_ids = $has_new_mode ? array() : $multiple_product_ids;
		}

		$gallery_ids = array();


		/* Combo Offers are external Elementor triggers; Special Discounts apply automatically. */


		/* Single Product replaces/synchronizes one primary product. Multiple
		 * Products builds a selected collection from Elementor triggers. */
		$selection = 'multiple' === $product_mode ? 'multiple' : 'single';

		$product_layout = 'cards';
		$product_group_title = '';

		$show_package_selection = 'yes' === (string) ( $elementor_settings['show_package_selection'] ?? 'yes' ) ? 'yes' : 'no';
		$max_visible_variations = max( 1, min( 12, absint( $elementor_settings['max_visible_variations'] ?? 4 ) ) );
		$show_package_title = 'yes' === (string) ( $elementor_settings['show_package_title'] ?? 'yes' ) ? 'yes' : 'no';
		$show_package_helper = 'yes' === (string) ( $elementor_settings['show_package_helper'] ?? 'yes' ) ? 'yes' : 'no';
		$show_selected_quantity = 'yes' === (string) ( $elementor_settings['show_selected_quantity'] ?? 'yes' ) ? 'yes' : 'no';
		$show_descriptions = 'yes' === (string) ( $elementor_settings['show_descriptions'] ?? 'yes' ) ? 'yes' : 'no';

		$single_product_overrides = array(
			'visibility'                   => 'hidden',
			'show_variations'              => $show_package_selection,
			'variation_layout'             => 'grid',
			'variation_selection'          => 'single',
			'summary_auto_add'             => 'yes',
			'show_selected_items'          => 'no',
			'selected_items_show_quantity' => 'no',
			'show_summary_items'           => 'yes',
			'summary_items_show_quantity'  => 'no',
			'grid_show_price'              => 'yes',
			'grid_show_regular_price'      => 'no',
			'grid_show_savings'            => 'no',
			/* Consumed directly by SingleProductRenderer after legacy sanitization. */
			'show_checkout_selector'       => $show_package_selection,
			'max_visible_variations'       => $max_visible_variations,
			'show_package_title'           => $show_package_title,
			'show_package_helper'          => $show_package_helper,
			'show_selected_quantity'       => $show_selected_quantity,
			'show_description'             => $show_descriptions,
			'show_variation_descriptions'  => $show_descriptions,
		);

		if ( 'multiple' === $product_mode ) {
			$single_product_overrides = array();
		}

		$render_settings = array(
			/*
			 * --------------------------------------
			 * Products
			 * --------------------------------------
			 */
			'product_id' =>
				! empty(
					$product_ids
				)
					? $product_ids[0]
					: 0,

			'product_ids' =>
				$product_ids,

			'product_mode' =>
				$product_mode,

			'selection' =>
				$selection,

			'product_layout' =>
				$product_layout,

			'single_product' =>
				$single_product_overrides,

			'external_products' => 'yes',

			'show_package_selection' =>
				$show_package_selection,

			'max_visible_variations' =>
				$max_visible_variations,

			'product_group_title' =>
				$product_group_title,

			'group_gallery_id' => '',

			'gallery_ids' =>
				$gallery_ids,

			/*
			 * Combo Offers are intentionally never rendered as checkout cards.
			 * CheckoutRenderer emits only a hidden, signed state adapter so the
			 * standalone Elementor Combo Button can drive the existing engine.
			 */
			'show_combo_offers' => 'no',
			'combo_offer_ids' => array(),
			'combo_selection_mode' => 'multiple',
			'combo_title' => '',
			'combo_show_description' => 'no',
			'selected_combo_offer_ids' => array(),

			/* All enabled Special Discount rules are automatic and presentation-free. */
			'show_special_offers' => 'yes',
			'special_offer_ids' => array(),
			'special_card_style' => '',
			'special_columns_desktop' => 0,

			/* Internal compatibility aliases. */
			'show_order_bumps' => 'yes',
			'order_bump_ids' => array(),
		);

        $render_settings['checkout_layout'] = $elementor_settings['checkout_layout'] ?? '';
		$render_settings['checkout_language'] = $elementor_settings['checkout_language'] ?? '';
		$render_settings['eilmo_widget_layout'] = array(
			'show_delivery' => 'yes' === (string) ( $elementor_settings['show_delivery_section'] ?? 'yes' ) ? 'yes' : 'no',
			'payment_methods_arrangement' => sanitize_key( (string) ( $elementor_settings['payment_methods_arrangement'] ?? '' ) ),
		);
		foreach ( array( 'delivery', 'payment_options', 'payment_methods' ) as $group ) {
			foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
				$key = $group . '_columns_' . $device;
				$value = absint( $elementor_settings[ $key ] ?? 0 );
				if ( $value > 0 ) {
					$render_settings['eilmo_widget_layout'][ $key ] = $value;
				}
			}
		}

		/*
		 * Elementor owns the widget-level presentation. The renderer therefore
		 * emits layout variables only; theme variables are inherited from the
		 * wrapper printed in render(). Elementor's generated CSS can then update
		 * the checkout root instantly while the editor color picker is moving.
		 */
		$render_settings['checkout_style_inherit_theme'] = 'yes';

		return $render_settings;
	}


	/**
	 * Get published WooCommerce products for the Primary Product selector.
	 *
	 * This helper is intentionally kept on the Checkout widget because the
	 * simplified Elementor-first UI still exposes one optional primary product.
	 *
	 * @return array<int, string>
	 */
	private function get_product_options(): array {

		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$options = array();
		$products = wc_get_products(
			array(
				'limit'  => -1,
				'status' => 'publish',
			)
		);

		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! method_exists( $product, 'get_name' ) ) {
				continue;
			}

			$product_id = absint( $product->get_id() );
			if ( $product_id <= 0 ) {
				continue;
			}

			$options[ $product_id ] = sanitize_text_field( (string) $product->get_name() );
		}

		return $options;
	}


	/**
	 * Normalize numeric IDs.
	 *
	 * Supports:
	 *
	 * 123
	 *
	 * 123,456,789
	 *
	 * @param mixed $value Value.
	 *
	 * @return array<int, int>
	 */
	private function normalize_numeric_ids(
		$value
	): array {

		$values =
			$this->normalize_list(
				$value
			);

		$ids =
			array();

		foreach ( $values as $item ) {

			$id =
				absint(
					$item
				);

			if ( $id <= 0 ) {
				continue;
			}

			$ids[] =
				$id;
		}

		return array_values(
			array_unique(
				$ids
			)
		);
	}

	/**
	 * Normalize comma / whitespace separated list.
	 *
	 * @param mixed $value Value.
	 *
	 * @return array<int, string>
	 */
	private function normalize_list(
		$value
	): array {

		if ( is_string( $value ) ) {

			$value =
				preg_split(
					'/[\s,]+/',
					$value
				);
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$values =
			array();

		foreach ( $value as $item ) {

			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$item =
				trim(
					(string) $item
				);

			if ( '' === $item ) {
				continue;
			}

			$values[] =
				$item;
		}

		return $values;
	}

	/**
	 * Render an editor-only placeholder when CheckoutRenderer
	 * has no product context to render.
	 *
	 * @return void
	 */
	private function render_editor_placeholder(): void {

		if (
			! $this->is_elementor_editor()
		) {
			return;
		}
		?>

		<div class="elementor-alert elementor-alert-info">

			<?php
			echo esc_html__(
				'Checkout Flow: choose Single Product or Multiple Products mode. Single Product requires a Primary Product; Multiple Products starts empty and is filled by Elementor Product Button / Variation Selector widgets.',
				'eilmo-checkout-flow'
			);
			?>

		</div>

		<?php
	}

	/**
	 * Determine whether Elementor editor is active.
	 *
	 * @return bool
	 */
	private function is_elementor_editor(): bool {

		if (
			! class_exists(
				'\Elementor\Plugin'
			)
		) {
			return false;
		}

		try {

			$plugin =
				\Elementor\Plugin::$instance;

			return (
				is_object(
					$plugin
				) &&
				isset(
					$plugin->editor
				) &&
				is_object(
					$plugin->editor
				) &&
				method_exists(
					$plugin->editor,
					'is_edit_mode'
				) &&
				$plugin->editor->is_edit_mode()
			);

		} catch ( \Throwable $throwable ) {

			return false;
		}
	}
}
