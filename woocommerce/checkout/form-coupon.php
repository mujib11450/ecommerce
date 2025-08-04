<?php
/**
 * Checkout coupon form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-coupon.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

// WC Template include, based on site settings (when Elementor is active).
if ( ! vamtam_should_include_wc_theme_mods_for_page() ) {
	// Fallback.
	include WC()->plugin_path() . '/templates/checkout/form-coupon.php';
	return;
}

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
	return;
}

?>
<div class="woocommerce-coupon-form">
	<div class="woocommerce-form-coupon-toggle">
		<?php wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'jolie' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'jolie' ) . '</a>' ), 'notice' ); ?>
	</div>

	<form class="checkout_coupon woocommerce-form-coupon" method="post" style="display:none">
		<div class="vamtam-checkout-coupon-header">
			<p class="header"><?php esc_html_e( 'If you have a coupon code, please apply it below.', 'jolie' ); ?></p>

			<p class="form-row form-row-first">
				<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
				<input type="text" name="coupon_code" class="input-text" placeholder="<?php esc_attr_e( 'Coupon code', 'jolie' ); ?>" id="coupon_code" value="" />
			</p>
		</div>

		<p class="form-row form-row-last">
			<button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'jolie' ); ?>">
				<?php esc_html_e( 'Apply coupon', 'jolie' ); ?>
			</button>
		</p>

		<div class="clear"></div>
	</form>
</div>
