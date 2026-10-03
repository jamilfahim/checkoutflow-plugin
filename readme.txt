=== Checkout Flow for WooCommerce ===
Tags: woocommerce, checkout, elementor, direct checkout, whatsapp
Requires at least: 6.5
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.2.4.25
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular WooCommerce checkout flow builder with Elementor and shortcode support.

== Description ==

Checkout Flow for WooCommerce keeps WooCommerce authoritative for products, stock, prices, orders and payment gateways while Elementor controls the customer-facing presentation.

Primary workflow:

* Build product marketing cards with Elementor.
* Use Checkout Flow – Product Button for Simple products or exact variations.
* Use Checkout Flow – Variation Selector for visual variable-product choices.
* Use Checkout Flow – Combo Button for reusable Combo Offers.
* Use Checkout Flow for customer information, delivery, payment, order summary and order submission.
* Special Discounts apply automatically and appear only as real benefits in totals/summary.

The checkout can optionally show one compact Primary Product / Package section. Up to the configured number of variations are shown directly; larger sets use the current package plus a Change modal. Elementor selections and the checkout package selector stay synchronized.

Legacy saved Single Product / Multiple Products settings and shortcode attributes remain supported internally for backward compatibility, but the duplicated Products admin screen is no longer exposed.

Other capabilities include delivery methods, coupons, Special Discounts, Quick Checkout, Default WooCommerce checkout integration, courier integrations, Meta Tracking, WhatsApp orders, abandoned checkout recovery, security controls, HPOS support and Cart/Checkout Block compatibility.

== Installation ==

1. Upload the plugin ZIP from Plugins > Add New > Upload Plugin.
2. Activate Checkout Flow for WooCommerce.
3. Open Checkout Flow > Checkout Settings > General and enable only the optional modules you need.
4. Configure Checkout Settings, Offers & Discounts and Integrations.
5. Build the landing-page product presentation in Elementor with Checkout Flow widgets.

== Elementor Widgets ==

* Checkout Flow — optional Primary Product plus customer, delivery, payment, summary and order action.
* Checkout Flow – Product Button — adds/removes a Simple product or exact variation.
* Checkout Flow – Variation Selector — visual Grid Cards or Compact Buttons with optional image, title, badge, price, regular price, saving and stock state.
* Checkout Flow – Combo Button — adds/removes a saved Combo Offer using the existing Combo engine.

== Shortcodes ==

A lightweight shortcode remains available for non-Elementor and existing pages:

`[eilmo_checkout product_id="123"]`

Historical attributes such as `product_mode`, `product_ids`, `variation_layout` and `attribute_styles` remain accepted so existing saved pages do not break, but new sites should use Elementor widgets for product presentation.

== Data and Privacy ==

Plugin settings and operational records are preserved when the plugin is deactivated or deleted by default.

Administrators can explicitly enable Checkout Settings > General > Data Retention > Remove Plugin Data on Uninstall. When enabled, deleting the plugin removes Eilmo settings, caches, scheduled tasks and plugin-owned tables. WooCommerce orders and their historical order metadata remain preserved.

When enabled and configured, the plugin can communicate with the following external services:

* The configured Eilmo License Manager for license activation, validation and Help Center content. Requests can include the license key, installation identifier, site URL and plugin version.
* Meta Pixel and Meta Conversions API for merchant-authorized analytics and marketing events. These features are disabled by default and should be enabled only after the site has collected any consent required by its privacy policy and applicable law.
* Steadfast and Pathao courier APIs for merchant-requested delivery, consignment and delivery-history operations. Requests contain only the order, customer and delivery data required for the selected courier action.

The site owner is responsible for reviewing the terms and privacy policy of every configured service, documenting those processors in the site's privacy notice, and connecting the available consent filters to the site's consent-management platform before enabling marketing tracking.

== Help Content ==

The active License Manager server supplies Documentation and Video Tutorials. Help content is cached for 24 hours and the latest successful response remains available during a temporary server outage. Plugin releases installed from WordPress.org are updated by WordPress core.

== Changelog ==

= 2.2.4.25 =
* Default new Meta Tracking configurations to Advanced Purchase with Completed as the trigger status. Explicitly saved strategies and statuses remain unchanged.

= 2.2.4.24 =
* Prevent duplicate Meta PageView and ViewContent events when the checkout bundle and standalone tracker are both available on a page.
* Keep Advanced Purchase tracking server-side and tied to the selected WooCommerce order status.

= 2.2.4.23 =
* Clarify Orders payment summaries: unverified payments show the amount to verify, COD shows the amount to collect on delivery, and zero-value lines are hidden.
* Identify paid advances separately from the remaining balance due after the advance.

= 2.2.4.22 =
* Show COD orders with an outstanding balance as Pay on Delivery, including existing orders in the Orders table.
* Count confirmed payments rather than outstanding COD balances in Dashboard payment totals.
* Read today's abandoned checkout count from recorded checkout activity and fix overlapping labels in narrow Orders rows.

= 2.2.4.21 =
* Give Order Management the same settings card background, border, and spacing as other tabs.
* Add Today, Last 7 Days, This Month, and Last Month dashboard order filters, with current-month context beside the selected period.
* Keep abandoned checkouts and attention counts explicitly scoped to today while order metrics are filtered.

= 2.2.4.20 =
* Arrange enabled WooCommerce Orders details in responsive, two-level rows so staff can verify and update orders without horizontal scrolling.
* Keep sorting and bulk selection available above the rows, and expand long product lists in place.
* Default the Meta detail to hidden while retaining an individual on/off switch for every detail.

= 2.2.4.19 =
* Save plain payment method titles for native and campaign checkout orders and display clean titles for existing orders.
* Keep wide WooCommerce Orders tables inside their own horizontal scroller so the WordPress admin navigation stays in place.

= 2.2.4.18 =
* Record order cooldown and rate-limit counts for native Classic and Checkout Block orders even when Eilmo payment metadata is present.
* Keep Campaign and Quick Checkout orders from being counted twice by WooCommerce compatibility hooks.
* Show the dashboard-configured checkout failure message for unexpected security errors across custom and native checkout flows.

= 2.2.4.16 =
* Shorten English payment-option card copy while leaving every Bangla string unchanged.
* Cash on Delivery now uses “Pay with cash upon delivery.”, Advance uses the dynamic “Pay {pay_now} in advance.”, and Full Payment uses “Pay the full amount in advance.”
* Migrate only exact historical English defaults so existing merchant-customized payment text is preserved.
* Keep Campaign Checkout and native/default checkout payment-option copy consistent.

= 2.2.4.15 =
* Make checkout language handling explicit: known plugin UI copy switches automatically between English and Bangla while unknown merchant/customer content is preserved.
* Expand Bangla catalog coverage for the marked checkout UI, including summary labels, delivery/payment empty states, payment descriptions, badges, security text and renderer-generated system messages.
* Add optional Bangla name and description fields to every Delivery Method. Bangla checkout uses those values when present and safely falls back to the original English/custom value when blank.
* Migrate the historical Inside Dhaka / Outside Dhaka labels to their known Bangla equivalents without attempting to translate arbitrary custom delivery names.

= 2.2.4.14 =
* Decouple Quick Checkout from Checkout Settings -> Global Checkout Style. The default Quick Checkout source now always uses the canonical plugin purple palette instead of a merchant's custom campaign/form palette.
* Migrate legacy Quick Checkout `source=global` to the new independent `source=default` without changing saved Quick Checkout custom colours.
* Keep Quick Checkout colour custom properties in the private `--eilmo-qc-theme-*` namespace while shared checkout components are bridged only inside the modal scope.
* Rename the Quick Checkout style-source UI to `Plugin Default Theme` so the setting matches its actual independent behavior.

= 2.2.4.13 =
* Isolate Quick Checkout colours behind a dedicated `--eilmo-qc-theme-*` namespace and bridge shared checkout components locally inside the modal.
* Prevent generic `--eilmo-cf-theme-*` values from another checkout surface, Elementor wrapper or stale global declaration from repainting Quick Checkout.
* Repair the exact historical green default even when an older release accidentally saved it with `preset=custom`; genuine merchant Custom themes remain unchanged.
* Keep Order Now on the same private Quick Checkout theme tokens while preserving WhatsApp brand green.

= 2.2.4.12 =
* Fix Quick Checkout inheriting the historical green palette when Global Checkout Style is still saved as the Default Theme/Premium Purple preset.
* Make the named Default Theme authoritative at runtime and migrate stale schema-48 style data to the canonical purple palette without changing explicit Custom themes.
* Normalize the Checkout Settings Style screen with the same runtime theme so admin and frontend no longer disagree.
* Keep the existing namespaced `--eilmo-cf-theme-*` CSS variables; the issue was stale saved theme data, not a CSS custom-property collision.

= 2.2.4.11 =
* Fix Quick Checkout shared theme variables being attached too late for SummaryRenderer.
* Enqueue Quick Checkout/shared checkout styles and saved theme tokens during wp_enqueue_scripts.
* Add canonical Eilmo purple fallback tokens to the Quick Checkout root so shared components remain styled even if dynamic tokens are unavailable.


= 2.2.4.10 =
* Quick Checkout stability pass: removed the global reference-layout attributes that were unintentionally changing the modal grid and selected-product presentation.
* Kept Place Order inside SummaryRenderer's native action slot, directly below totals.
* Restored the proven Quick Checkout main/summary alignment so the Summary starts level with the selected product instead of leaving an empty top-right gap.
* Hardened Order button theme fallbacks and disabled-state styling so the action stays visibly branded and readable.

= 2.2.4.9 =
* Aligned Quick Checkout with the shared checkout layout contract so the canonical Summary styling could be reused across checkout surfaces.
* Moved the Quick Checkout Place Order action into SummaryRenderer's native summary action slot.

= 2.2.4.8 =
* Unified the untouched default checkout palette around Eilmo's canonical purple theme across Campaign Checkout, Default Checkout, Quick Checkout and checkout-facing Elementor defaults.
* Added safe migrations for the accidental green Premium Purple defaults, the historical native WooCommerce-purple defaults, and the legacy blue Quick Checkout Order Now button without overwriting merchant-customized colours.
* Kept branded integration colours such as WhatsApp green unchanged.

= 2.2.4.7 =
* Quick Checkout now uses the same shared CheckoutStyle design tokens as Ad Campaign, Elementor and Block Editor checkout surfaces.
* Added Quick Checkout Appearance settings with Global (recommended) and optional Custom style sources.
* Normalized Quick Checkout section order to Customer → Delivery → Payment → Summary.
* Removed the legacy hard-coded Quick Checkout shell colours and duplicate component styling.
* Quick Checkout header, selected product, modal shell and final action now inherit the shared theme, spacing and radius system.

= 2.2.4.6 =
* Simplified the operational Dashboard to today metrics, attention items, integration health and quick actions.
* Moved optional feature switches to Checkout Settings > General so Dashboard is no longer a second settings screen.
* Added a short dashboard metrics cache to reduce repeated order hydration on busy stores.
* Added Help Center request backoff and a fetch lock so remote documentation outages do not slow every admin page load.
* Hardened manual payment proof attachment failures and surfaces an explicit payment error instead of silently losing evidence.
* Removed verified unused legacy checkout CSS/JS from the production package.
* Avoided loading the global Cart Drawer/Checkout bundle on the order-received page, where the order is already complete.

= 2.0.7 =
* Added the official Checkout Flow brand mark to the WordPress admin menu and landing-page identity.
* Added optimized logo and favicon assets without changing checkout, order, security or integration behavior.

= 2.0.6 =
* Prevented mixed bundle and fallback scripts from creating separate live phone-verification state stores.
* Prevented duplicate checkout listeners from racing into an error followed by an existing-order success redirect.
* Kept the server-signed phone decision and configured duplicate-order policy authoritative during checkout.

= 2.0.5 =
* Removed the private update checker and custom update-channel controls for WordPress.org policy compatibility.
* Preserved license activation, Help Center content and all checkout, order, courier, tracking and security features.

= 2.0.4 =
* Corrected final Plugin Check directive placement for prepared SQL and schema migrations.
* Updated WordPress compatibility metadata and kept the changelog within parser limits.

= 2.0.3 =
* Resolved second-pass Plugin Check internationalization, output-escaping, nonce-analysis and database findings without changing checkout behavior.
* Added precise inline exceptions only for verified WooCommerce compatibility, exception-message and custom-table query cases.

= 2.0.2 =
* Hardened configurable remote requests with WordPress SSRF-safe HTTP APIs and safe manifest URL validation.
* Reworked custom-table queries to use WordPress identifier placeholders and narrowed unavoidable PHPCS exceptions.
* Added a deterministic production frontend bundle with individual-script fallback and removed stale asset manifest entries.
* Added external-service privacy guidance, regenerated the translation template and cleaned release-only artifacts.

= 2.0.1 =
* Stabilized the 2.0 checkout, security, courier, tracking and license-management release package.
* Improved WooCommerce checkout compatibility and administrative security tooling.

= 2.0.0 =
* Introduced the modular v2 checkout architecture, security activity logging and expanded integration framework.
* Added database-schema upgrades for checkout security and abandoned-checkout workflows.

= 1.10.0 =
* Added remotely managed Documentation and Video Tutorials with 24-hour caching and a saved fallback.
* Added WordPress-native one-click updates from the configured License Manager server.
* Added protected license-aware package downloads and native plugin information/changelog support.
* Added Dashboard quick-access resource cards for License, Documentation and Video Tutorials.


Release history is maintained in the development repository and is not bundled in the production package.
