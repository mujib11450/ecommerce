<?php

/**
 * WooCommerce-related functions and filters
 *
 * @package vamtam/caridad
 */

if ( vamtam_has_woocommerce() || apply_filters( 'vamtam_force_dropdown_cart', false ) ) {
	/**
	 * Retrieve page ids - used for myaccount, edit_address, shop, cart, checkout, pay, view_order, terms. returns -1 if no page is found.
	 *
	 * @param string $page Page slug.
	 * @return int
	 */
	function vamtam_wc_get_page_id( $page ) {
		if ( 'pay' === $page || 'thanks' === $page ) {
			wc_deprecated_argument( __FUNCTION__, '2.1', 'The "pay" and "thanks" pages are no-longer used - an endpoint is added to the checkout instead. To get a valid link use the WC_Order::get_checkout_payment_url() or WC_Order::get_checkout_order_received_url() methods instead.' );

			$page = 'checkout';
		}
		if ( 'change_password' === $page || 'edit_address' === $page || 'lost_password' === $page ) {
			wc_deprecated_argument( __FUNCTION__, '2.1', 'The "change_password", "edit_address" and "lost_password" pages are no-longer used - an endpoint is added to the my-account instead. To get a valid link use the wc_customer_edit_account_url() function instead.' );

			$page = 'myaccount';
		}

		$page = apply_filters( 'woocommerce_get_' . $page . '_page_id', get_option( 'woocommerce_' . $page . '_page_id' ) );

		return $page ? absint( $page ) : -1;
	}

	/**
	 * Retrieve page permalink.
	 *
	 * @param string      $page page slug.
	 * @param string|bool $fallback Fallback URL if page is not set. Defaults to home URL. @since 3.4.0.
	 * @return string
	 */
	function vamtam_wc_get_page_permalink( $page, $fallback = null ) {
		$page_id   = vamtam_wc_get_page_id( $page );
		$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';

		if ( ! $permalink ) {
			$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
		}

		return apply_filters( 'woocommerce_get_' . $page . '_page_permalink', $permalink );
	}

	function vamtam_wc_get_cart_url() {
		return apply_filters( 'woocommerce_get_cart_url', vamtam_wc_get_page_permalink( 'cart' ) );
	}

	function vamtam_woocommerce_cart_dropdown() {
		get_template_part( 'templates/cart-dropdown' );
	}
	add_action( 'vamtam_header_cart', 'vamtam_woocommerce_cart_dropdown' );

	if ( ! vamtam_has_woocommerce() ) {
		// shim for the cart fragments script
		function vamtam_wc_cart_fragments_shim() {
			wp_localize_script( 'vamtam-all', 'wc_cart_fragments_params', [
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'wc_ajax_url'     => esc_url_raw( apply_filters( 'woocommerce_ajax_get_endpoint', add_query_arg( 'wc-ajax', '%%endpoint%%', remove_query_arg( array( 'remove_item', 'add-to-cart', 'added-to-cart', 'order_again', '_wpnonce' ), home_url( '/', 'relative' ) ) ), '%%endpoint%%' ) ),
				'cart_hash_key'   => apply_filters( 'woocommerce_cart_hash_key', 'wc_cart_hash_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) ),
				'fragment_name'   => apply_filters( 'woocommerce_cart_fragment_name', 'wc_fragments_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) ),
				'request_timeout' => 5000,
				'jspath'          => plugins_url( 'woocommerce/assets/js/frontend/cart-fragments.min.js' ),
				'csspath'         => plugins_url( 'woocommerce/assets/css/woocommerce.css' ),
			] );
		}
		add_action( 'wp_enqueue_scripts', 'vamtam_wc_cart_fragments_shim', 9999 );
	}
}

// TODO: Turn this into a class for better readability.

// Only use our pagination for fallback.
if ( ! vamtam_extra_features() ) {
	// We may want to add a separate master toggle for this.
	if ( ! function_exists( 'woocommerce_pagination' ) ) {
		// replace the default pagination with ours
		function woocommerce_pagination() {
			$query = null;

			$base = esc_url_raw( add_query_arg( 'product-page', '%#%', false ) );
			$format = '?product-page=%#%';

			if ( ! wc_get_loop_prop( 'is_shortcode' ) ) {
				$format = '';
				$base   = esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) );
			}

			if ( isset( $GLOBALS['woocommerce_loop'] ) ) {
				$query = (object)[
					'max_num_pages' => wc_get_loop_prop( 'total_pages' ),
					'query_vars'    => [
						'paged' => wc_get_loop_prop( 'current_page' )
					],
				];
			}

			echo VamtamTemplates::pagination_list( $query, $format, $base ); // xss ok
		}
	}
}

if ( vamtam_has_woocommerce() ) {
	// we have woocommerce

	remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
	add_action( 'woocommerce_checkout_terms_and_conditions', 'vamtam_wc_terms_and_conditions_page_content', 30 );

	function vamtam_wc_terms_and_conditions_page_content() {
		$terms_page_id = wc_terms_and_conditions_page_id();

		if ( ! $terms_page_id ) {
			return;
		}

		$page = get_post( $terms_page_id );

		$content = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $page->post_content );

		if ( $page && 'publish' === $page->post_status && $page->post_content && ! has_shortcode( $page->post_content, 'woocommerce_checkout' ) ) {
			echo '<div class="woocommerce-terms-and-conditions" style="display: none; max-height: 200px; overflow: auto;">' . wc_format_content( wp_kses_post( $content ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	add_filter( 'wcml_load_multi_currency_in_ajax', '__return_true', 1000 );

	// Include WC theme funcionality based on site settings.
	function vamtam_check_include_wc_hooks() {
		if ( vamtam_should_include_wc_theme_mods_for_page() ) {
			vamtam_wc_hooks();
		}
	}

	if ( is_admin() ) {
		// Editor.
		add_action( 'init', 'vamtam_check_include_wc_hooks', 10 );
	} else {
		// Frontend.
		add_action( 'wp', 'vamtam_check_include_wc_hooks', 10 );
	}

	function vamtam_should_include_wc_theme_mods_for_page() {
		// Default is to include all theme wc mods.
		$include = true;

		// We disable wc theme mods, for specific pages, only when specified by user (site-settings).
		if ( VamtamElementorBridge::is_elementor_active() && vamtam_extra_features() ) {
			// Is this a WC page we have master toggle for?
			if ( is_cart() || is_checkout() || is_account_page() ) {
				$wc_page = '';
				if ( is_cart() ) {
					$wc_page = 'cart';
				} else if ( is_checkout() ) {
					$wc_page = 'checkout';
				} else if ( is_account_page() ) {
					$wc_page = 'account';
				}

				// Is the relevant wc mod active (site settings)?
				$wc_mod_active = \Vamtam_Elementor_Utils::is_wc_mod_active( "{$wc_page}-page-theme-mod" );
				if ( ! $wc_mod_active ) {
					// If not, don't include our WC funcionality.
					$include = false;
				}
			}
		}

		return $include;
	}

	function vamtam_wc_content_product_hooks() {
		// Rating is rendered last inside product content.
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_before_shop_loop_item_title' ) ) {
			//WC products filtering
			function vamtam_woocommerce_before_shop_loop_item_title( $content ) {
				?>
					<?php do_action( 'vamtam_product_content_before_open' ); ?>
					<div class="vamtam-product-content">
					<?php do_action( 'vamtam_product_content_after_open' ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_before_shop_loop_item_title', 'vamtam_woocommerce_before_shop_loop_item_title', 100 );

		if ( ! function_exists( 'vamtam_woocommerce_after_shop_loop_item_title' ) ) {
			//WC products filtering
			function vamtam_woocommerce_after_shop_loop_item_title( $content ) {
				?>
					<?php do_action( 'vamtam_product_content_before_close' ); ?>
					</div>
					<?php do_action( 'vamtam_product_content_after_close' ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_after_shop_loop_item_title', 'vamtam_woocommerce_after_shop_loop_item_title', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_loop_add_to_cart_link' ) ) {
			function vamtam_woocommerce_loop_add_to_cart_link( $content, $product, $args ) {
				// Hidden price.
				if ( vamtam_theme_supports( 'woocommerce-products--hide-price' ) ) {
					$hide_product_price = isset( $GLOBALS['vamtam_wc_products_hide_price'] ) && ! empty( $GLOBALS['vamtam_wc_products_hide_price'] );

					// If price is hidden, render the Read More btn.
					if ( $hide_product_price ) {
						$content = sprintf(
							'<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
							esc_url( $product->get_permalink() ),
							esc_attr( isset( $args['quantity'] ) ? $args['quantity'] : 1 ),
							esc_attr( 'button ' . 'product_type_' . $product->get_type() ),
							isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
							esc_html__( 'Read More', 'jolie' )
						);
					}
				}

				// Add add_to_cart btn wrapper (products loop)
				$adc = '<div class="vamtam-add-to-cart-wrap">'
							. $content .
						'</div>';

				return apply_filters( 'vamtam_woocommerce_loop_add_to_cart_link', $adc );
			}
		}
		add_filter( 'woocommerce_loop_add_to_cart_link', 'vamtam_woocommerce_loop_add_to_cart_link', 10, 3 );

		if ( ! function_exists( 'vamtam_woocommerce_loop_add_to_cart_args' ) ) {
			function vamtam_woocommerce_loop_add_to_cart_args( $args, $product ) {
				if ( $product->get_type() === 'external' ) {
					$args['attributes']['target'] = '_blank';
				}

				return $args;
			}
		}

		add_filter( 'woocommerce_loop_add_to_cart_args', 'vamtam_woocommerce_loop_add_to_cart_args', 10, 2 );

		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );

		// Display title (in vamtam-product-content).
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		add_action( 'vamtam_product_content_after_open', 'woocommerce_template_loop_product_title', 10 );

		// Price after title.
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_action( 'vamtam_product_content_before_close', 'woocommerce_template_loop_price', 102 );

		// Add to cart after product-content.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		add_action( 'vamtam_product_content_after_close', 'woocommerce_template_loop_add_to_cart', 10 );

		// This fixes an issue with where previously removed wc actions will be added again
		// due to Elementor re-registering WC's hooks at a later stage [search: register_wc_hooks in elementor-pro]
		// for products-related widgets. It only affects the editor.
		if ( is_admin() ) {
			if ( ! function_exists( 'vamtam_woocommerce_before_shop_loop_item' ) ) {
				function vamtam_woocommerce_before_shop_loop_item() {
					remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
					remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
					remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
				}
			}
			add_action( 'woocommerce_before_shop_loop_item', 'vamtam_woocommerce_before_shop_loop_item' );
		}

		if ( ! function_exists( 'vamtam_close_outer_product_link' ) ) {
			// Fixes an issue with invalid markup caused by links inside vamtam-product-content interfering with the outer standard WC product link.
			function vamtam_close_outer_product_link() {
				if ( did_action( 'woocommerce_before_shop_loop_item' ) && has_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open' ) ) {
					// If the link was opened we close it here to avoid invalid html.

					// Close the link.
					woocommerce_template_loop_product_link_close();

					// We closed it ourshelves.
					remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
					do_action( 'vamtam_closed_outer_product_link' );
				}
			}
		}
		add_action( 'vamtam_product_content_before_open', 'vamtam_close_outer_product_link', 10 );
		if ( ! function_exists( 'vamtam_open_product_link_in_product_content' ) ) {
			function vamtam_open_product_link_in_product_content() {
				if ( did_action( 'vamtam_closed_outer_product_link' ) ) {
					// Open the link.
					woocommerce_template_loop_product_link_open();
					do_action( 'vamtam_opened_product_link_in_product_content' );
				}
			}
		}
		add_action( 'vamtam_product_content_after_open', 'vamtam_open_product_link_in_product_content', -10 );
		if ( ! function_exists( 'vamtam_close_product_link_in_product_content' ) ) {
			function vamtam_close_product_link_in_product_content() {
				if ( did_action( 'vamtam_opened_product_link_in_product_content' ) ) {
					// Close the link.
					woocommerce_template_loop_product_link_close();
					do_action( 'vamtam_closed_product_link_in_product_content' );
				}
			}
		}
		add_action( 'vamtam_product_content_before_close', 'vamtam_close_product_link_in_product_content', 100 );
	}

	function vamtam_wc_check_include_content_product_cat_hooks( $widget_name ) {
		if ( \Vamtam_Elementor_Utils::is_widget_mod_active( $widget_name ) ) {
			vamtam_wc_content_product_cat_hooks();
		}
	}

	function vamtam_wc_content_product_cat_hooks() {
		if ( ! function_exists( 'vamtam_woocommerce_template_loop_category_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_template_loop_category_title( $term ) {
				$class_description = get_term_meta( $term->term_id, 'class_description', true );
				?>
					<?php if ( ! empty( $class_description ) ) : ?>
						<p class="vamtam-product-cat-class-description">
							<?php echo esc_html( $class_description ); ?>
						</p>
					<?php endif; ?>
				<?php
			}
		}
		add_action( 'woocommerce_shop_loop_subcategory_title', 'vamtam_woocommerce_template_loop_category_title', 100 );

		if ( ! function_exists( 'vamtam_woocommerce_before_subcategory_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_before_subcategory_title( $term ) {
				$class_level    = get_term_meta( $term->term_id, 'class_level', true );
				$class_category = get_term_meta( $term->term_id, 'class_category', true );
				?>
					<div class="vamtam-product-cat-thumb-wrap">
						<?php do_action( 'vamtam_cat_thumb_content', $term ); ?>
					</div>

					<?php if ( ! empty( $class_level ) || ! empty( $class_category ) ) : ?>
						<span class="vamtam-product-cat-info">
							<?php if ( ! empty( $class_level ) ) : ?>
								<span class="vamtam-product-cat-class-level"><?php echo esc_html( $class_level ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $class_category ) ) : ?>
								<span class="vamtam-product-cat-class-category"><?php echo esc_html( $class_category ); ?></span>
							<?php endif; ?>
						</span>
					<?php endif; ?>

					<div class="vamtam-product-cat-content">
					<?php do_action( 'vamtam_cat_content', $term ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_before_subcategory_title', 'vamtam_woocommerce_before_subcategory_title', 100 );

		// Adding thumb in container so the hover anim can work properly.
		remove_action( 'woocommerce_before_subcategory_title', 'woocommerce_subcategory_thumbnail', 10 );
		add_action( 'vamtam_cat_thumb_content', 'woocommerce_subcategory_thumbnail', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_after_subcategory_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_after_subcategory_title( $term ) {
				?>
				<div class="vamtam-product-cat-icon elementor-icon">
					<?php \Elementor\Icons_Manager::render_icon( [
						'value' => 'vamtamtheme- vamtam-theme-arrow-right' ,
						'library' => 'theme-icons'
					], [ 'aria-hidden' => 'true' ] ); ?>
				</div>
				<?php
				?>
				<?php do_action( 'vamtam_cat_content_before_close' ); ?>
				</div>
				<?php
			}
		}
		add_action( 'woocommerce_after_subcategory_title', 'vamtam_woocommerce_after_subcategory_title', 100 );

		if ( ! function_exists( 'vamtam_subcategory_archive_thumbnail_size' ) ) {
			// Return full-sized images for categories.
			function vamtam_subcategory_archive_thumbnail_size() {
				return 'woocommerce_single';
			}
		}
		add_filter( 'subcategory_archive_thumbnail_size', 'vamtam_subcategory_archive_thumbnail_size' );

	}

	function vamtam_wc_general_hooks() {
		// remove the WooCommerce breadcrumbs
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20,0 );

		// remove the WooCommerve sidebars
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

		function vamtam_woocommerce_body_class( $class ) {
			if ( is_cart() || is_checkout() || is_account_page() ) {
				$class[] = 'woocommerce';
			}

			return $class;
		}
		add_action( 'body_class', 'vamtam_woocommerce_body_class' );

		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
		add_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display' );

		add_filter( 'woocommerce_product_description_heading', '__return_false' );
		add_filter( 'woocommerce_show_page_title', '__return_false' );

		// Cart quantity override.
		function vamtam_woocommerce_cart_item_quantity( $content, $cart_item_key, $cart_item ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				// Elementor's filter has different args order.
				if ( ! isset( $cart_item['data'] ) && isset( $cart_item_key['data'] ) ) {
					$temp          = $cart_item_key;
					$cart_item_key = $cart_item;
					$cart_item     = $temp;
				}
			}
			$_product  = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$only_one_allowed  = $_product->is_sold_individually();

			// Attrs needed in cart (for variant quantities) but not menu cart.
			$select_cart_attrs = '';
			if ( ! $only_one_allowed && is_cart() ) {
				$select_cart_attrs = 'name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="' . esc_attr( $cart_item['quantity'] ) . '" title="' . esc_attr__( 'Qty', 'jolie' ) . '" min="0" max="' . esc_attr( $_product->get_max_purchase_quantity() ) . '"';
			}

			$max_product_quantity = $_product->get_stock_quantity();
			if ( ! isset( $max_product_quantity ) ) {
				if ( $_product->get_max_purchase_quantity() === -1 ) {
					// For product that don't adhere to stock_quantity, provide a default max-quantity.
					// This will be used for the number of options inside the quantity <select>.
					$max_product_quantity = apply_filters( 'vamtam_cart_item_max_quantity', 10 );
				} else {
					$max_product_quantity = $_product->get_max_purchase_quantity();
				}
			}

			// Inject select for quantity.
			$select = '<div class="vamtam-quantity"' . ( $only_one_allowed ? ' disabled ' : '' ) . '>';

			if ( vamtam_extra_features() ) {
				$select .= '<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 320 512\' focusable=\'false\' aria-hidden=\'true\'><path fill="currentColor" d="M143 352.3L7 216.3c-9.4-9.4-9.4-24.6 0-33.9l22.6-22.6c9.4-9.4 24.6-9.4 33.9 0l96.4 96.4 96.4-96.4c9.4-9.4 24.6-9.4 33.9 0l22.6 22.6c9.4 9.4 9.4 24.6 0 33.9l-136 136c-9.2 9.4-24.4 9.4-33.8 0z"/></svg>
						<select ' . ( $only_one_allowed ? 'disabled' : $select_cart_attrs ) . ' data-product_id="' . esc_attr( $cart_item['product_id'] ) . '" data-cart_item_key="' . esc_attr( $cart_item_key ) . '">';

				for ( $quantity = 1; $quantity <= ( $only_one_allowed ? 1 : $max_product_quantity ); $quantity++ ) {
					$select .= '<option ' . selected( $cart_item['quantity'], $quantity, false ) . "value='$quantity'>$quantity</option>";
					if ( $quantity >= $max_product_quantity ) {
						break;
					}
				}

				if ( $cart_item['quantity'] > $max_product_quantity ) {
					$select .= '<option selected value=' . $cart_item['quantity'] . '>' . $cart_item['quantity'] . '</option>';
				}

				$select .= '</select></div>';
			} else {
				$select = woocommerce_quantity_input(
					array(
						'input_name'   => "cart[{$cart_item_key}][qty]",
						'input_value'  => $cart_item['quantity'],
						'max_value'    => $_product->get_max_purchase_quantity(),
						'min_value'    => '0',
						'product_name' => $_product->get_name(),
					),
					$_product,
					false
				);
			}

			if ( vamtam_extra_features() ) {
			$content = preg_replace( '/<span class="quantity">(\d+)/', '<span class="quantity">' .$select, $content );
			// Remove the "x" symbol.
			$content = str_replace( ' &times; ', '', $content );
			} else {
				$content = $select;
			}

			return $content;
		}

		add_filter( 'woocommerce_cart_item_quantity', 'vamtam_woocommerce_cart_item_quantity', 10, 3 );

		function vamtam_woocommerce_cart_item_remove_link( $content, $cart_item_key ) {
			// Inject our close icon.
			$content = str_replace(
				'</a>',
				'<i class="vamtam-remove-product"><svg class="vamtam-close vamtam-trash" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" version="1.1"><path xmlns="http://www.w3.org/2000/svg" fill="currentColor" d="M432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16zM53.2 467a48 48 0 0 0 47.9 45h245.8a48 48 0 0 0 47.9-45L416 128H32z"></path></svg></i></a>',
				$content );

			return $content;
		}
		add_filter( 'woocommerce_cart_item_remove_link', 'vamtam_woocommerce_cart_item_remove_link', 10, 2 );

		// Emtpy cart content.
		function vamtam_wc_empty_cart_message() {
			?>
				<div class="vamtam-empty-cart">
					<div class="thumbnail">
						<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 32 32">
							<path d="M10.336 9.344v-2.72c0.192-3.584 2.176-5.568 5.696-5.568 3.488 0 5.504 1.984 5.632 5.632v2.656h2.336c1.472 0 2.656 1.184 2.656 2.656v16c0 1.472-1.184 2.656-2.656 2.656h-16c-1.472 0-2.656-1.184-2.656-2.656v-16c0-1.472 1.184-2.656 2.656-2.656h2.336zM12.32 9.344h7.36v-2.624c-0.128-2.56-1.216-3.68-3.648-3.68s-3.552 1.12-3.712 3.648v2.656zM10.336 11.328h-2.336c-0.384 0-0.672 0.288-0.672 0.672v16c0 0.384 0.288 0.672 0.672 0.672h16c0.384 0 0.672-0.288 0.672-0.672v-16c0-0.384-0.288-0.672-0.672-0.672h-2.336v3.2c0 0.544-0.448 0.992-0.992 0.992s-0.992-0.448-0.992-0.992v-3.2h-7.36v3.2c0 0.544-0.448 0.992-0.992 0.992s-0.992-0.448-0.992-0.992v-3.2z" />
						</svg>
					</div>
					<div class="empty-cart-notice">
						<h5><?php esc_html_e( 'Your cart is currently empty.', 'jolie' ); ?></h5>
					</div>
				</div>
			<?php
		}
		add_action( 'woocommerce_cart_is_empty', 'vamtam_wc_empty_cart_message', 10 );

		// WC Form Fields filtering (works in conjuction with vamtam_woocommerce_form_field())
		function vamtam_woocommerce_form_field_args( $args, $key, $value ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				if ( $args['type'] === 'select' || $args['type'] === 'country' || $args['type'] === 'state' ) {
					$args['input_class'][] = 'elementor-field-textual';
					$args['class'][] = 'elementor-field-group';
				}
			}
			return $args;
		}
		add_filter( 'woocommerce_form_field_args', 'vamtam_woocommerce_form_field_args', 10, 3 );

		// WC Form Fields filtering (works in conjuction with vamtam_woocommerce_form_field_args())
		function vamtam_woocommerce_form_field( $field, $key, $args, $value ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				if ( $args['type'] === 'select' || $args['type'] === 'country' || $args['type'] === 'state' ) {
					$field = str_replace( 'woocommerce-input-wrapper', 'woocommerce-input-wrapper elementor-select-wrapper', $field );
				}
			}
			return $field;
		}
		add_filter( 'woocommerce_form_field', 'vamtam_woocommerce_form_field', 10, 4 );

		// WC product category custom fields.
		function vamtam_product_cat_add_form_fields( $term ) {
			$class_level       = isset( $term->term_id ) ? get_term_meta( $term->term_id, 'class_level', true ) : '';
			$class_category    = isset( $term->term_id ) ? get_term_meta( $term->term_id, 'class_category', true ) : '';
			$class_description = isset( $term->term_id ) ? get_term_meta( $term->term_id, 'class_description', true ) : '';
			?>
				<?php if ( current_filter() === 'product_cat_edit_form_fields') : ?>
					<tr class="form-field">
						<th valign="top" scope="row">
							<label for="term_fields[class_level]"><?php echo apply_filters( 'vamtam_product_cat_class_level_label', esc_html__( 'Class Level', 'jolie' ) ); ?></label>
						</th>
						<td>
							<input type="text" size="40" value="<?php echo esc_attr( $class_level ); ?>" id="term_fields[class_level]" name="term_fields[class_level]"><br/>
							<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_level_description', esc_html__( 'e.g. Beginner, Advanced etc. ', 'jolie' ) ); ?></span>
						</td>
					</tr>
					<tr class="form-field">
						<th valign="top" scope="row">
							<label for="term_fields[class_category]"><?php echo apply_filters( 'vamtam_product_cat_class_category_label', esc_html__( 'Class Category', 'jolie' ) ); ?></label>
						</th>
						<td>
							<input type="text" size="40" value="<?php echo esc_attr( $class_category ); ?>" id="term_fields[class_category]" name="term_fields[class_category]"><br/>
							<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_category_description', esc_html__( 'e.g. A1, B2 etc.', 'jolie' ) ); ?></span>
						</td>
					</tr>
					<tr class="form-field">
						<th valign="top" scope="row">
							<label for="term_fields[class_description]"><?php echo apply_filters( 'vamtam_product_cat_class_description_label', esc_html__( 'Class Description', 'jolie' ) ); ?></label>
						</th>
						<td>
							<textarea class="large-text" cols="50" rows="5" id="term_fields[class_description]" name="term_fields[class_description]"><?php echo esc_textarea( $class_description ); ?></textarea><br/>
							<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_description_description', esc_html__( 'Add a description for this class.', 'jolie' ) ); ?></span>
						</td>
					</tr>
				<?php elseif ( current_filter() === 'product_cat_add_form_fields' ) : ?>
					<div class="form-field">
						<label for="term_fields[class_level]"><?php echo apply_filters( 'vamtam_product_cat_class_level_label', esc_html__( 'Class Level', 'jolie' ) ); ?></label>
						<input type="text" size="40" value="<?php echo esc_attr( $class_level ); ?>" id="term_fields[class_level]" name="term_fields[class_level]">
						<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_level_description', esc_html__( 'e.g. Beginner, Advanced etc. ', 'jolie' ) ); ?></span>
					</div>
					<div class="form-field">
						<label for="term_fields[class_category]"><?php echo apply_filters( 'vamtam_product_cat_class_category_label', esc_html__( 'Class Category', 'jolie' ) ); ?></label>
						<input type="text" size="40" value="<?php echo esc_attr( $class_category ); ?>" id="term_fields[class_category]" name="term_fields[class_category]">
						<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_description_description', esc_html__( 'e.g. A1, B2 etc.', 'jolie' ) ); ?></span>
					</div>
					<div class="form-field">
						<label for="term_fields[class_description]"><?php echo apply_filters( 'vamtam_product_cat_class_description_label', esc_html__( 'Class Description', 'jolie' ) ); ?></label>
						<textarea class="large-text" cols="50" rows="5" id="term_fields[class_description]" name="term_fields[class_description]"><?php echo esc_textarea( $class_description ); ?></textarea>
						<span class="description"><?php echo apply_filters( 'vamtam_product_cat_class_description_description', esc_html__( 'Add a description for this class.', 'jolie' ) ); ?></span>
					</div>
				<?php endif; ?>
			<?php
		}
		add_action('product_cat_add_form_fields', 'vamtam_product_cat_add_form_fields', 10, 2);
		add_action('product_cat_edit_form_fields', 'vamtam_product_cat_add_form_fields', 10, 2);

		// WC product category custom fields (save).
		function vamtam_save_product_cat_fields( $term_id ) {
			if ( ! isset( $_POST['term_fields'] ) ) {
				return;
			}

			foreach ( $_POST['term_fields'] as $key => $value ) {
				update_term_meta( $term_id, $key, sanitize_text_field( $value ) );
			}
		}
		add_action('edited_product_cat', 'vamtam_save_product_cat_fields', 10, 2);
		add_action('create_product_cat', 'vamtam_save_product_cat_fields', 10, 2);
	}

	function vamtam_wc_hooks() {
		// General purpose. (Always enabled).
		vamtam_wc_general_hooks();

		if ( vamtam_extra_features() ) {
			if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-loops-common-mods' ) ) {
				vamtam_wc_content_product_hooks();
				vamtam_wc_content_product_cat_hooks();
			}
		} else {
			// Fallback.
			vamtam_wc_content_product_hooks();
			vamtam_wc_content_product_cat_hooks();
		}
	}

	function vamtam_wc_cart_ajax_hooks() {
		// Remove product in the cart using ajax
		function vamtam_ajax_product_remove() {
			if ( ! is_cart() ) {
				// It's menu cart.
				return;
			}

			if ( vamtam_should_include_wc_theme_mods_for_page() ) {
				return;
			}

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if( $cart_item['product_id'] == $_POST['product_id'] && $cart_item_key == $_POST['cart_item_key'] ) {
					WC()->cart->remove_cart_item( $cart_item_key );
				}
			}

			WC()->cart->calculate_totals();
			WC()->cart->maybe_set_cart_cookies();

			// Fragments and mini cart are returned
			WC_AJAX::get_refreshed_fragments();
		}
		// Ajax hooks for product remove from cart.
		add_action( 'wp_ajax_product_remove', 'vamtam_ajax_product_remove' );
		add_action( 'wp_ajax_nopriv_product_remove', 'vamtam_ajax_product_remove' );

		// Update product quantity from cart.
		function vamtam_update_item_from_cart() {
			if ( ! is_cart() ) {
				// It's menu cart.
				return;
			}

			if ( vamtam_should_include_wc_theme_mods_for_page() ) {
				return;
			}

			$quantity = (int) $_POST['product_quantity'];

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if( $cart_item['product_id'] == $_POST['product_id'] && $cart_item_key == $_POST['cart_item_key'] ) {
					WC()->cart->set_quantity( $cart_item_key, $quantity );
				}
			}

			WC()->cart->calculate_totals();
			WC()->cart->maybe_set_cart_cookies();

			// Fragments and mini cart are returned
			WC_AJAX::get_refreshed_fragments();
		}
		// Ajax hooks for updating product quantity from cart.
		add_action('wp_ajax_update_item_from_cart', 'vamtam_update_item_from_cart');
		add_action('wp_ajax_nopriv_update_item_from_cart', 'vamtam_update_item_from_cart');
	}
	// Ajax hooks must be included early.
	if ( vamtam_extra_features() ) {
		vamtam_wc_cart_ajax_hooks();
	}

	function vamtam_wc_single_product_ajax_hooks() {
		// Handles ajax add to cart calls.
		function woocommerce_ajax_add_to_cart() {
			new Vamtam_WC_Ajax_Add_To_Cart_Handler();
		}
		// Ajax hooks for add to cart on single products.
		add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart' );
		add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart' );


		class Vamtam_WC_Ajax_Add_To_Cart_Handler {
			protected static $product_id;
			protected static $quantity;

			public function __construct() {
				self::$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
				self::$quantity   = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );
				self::handle();
			}

			private static function handle() {
				$product_id        = self::$product_id;
				$quantity          = self::$quantity;
				$variation_id      = absint( $_POST['variation_id'] );
				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
				$product_status    = get_post_status( $product_id );
				$is_valid          = $passed_validation && 'publish' === $product_status;
				$product_added     = false;

				// Don't manually add Bookables as they are already added by WC Bokkings.
				if ( $_POST['is_wc_booking'] && function_exists( 'is_wc_booking_product' ) ) {
					$product       = wc_get_product( $product_id );
					$product_added = is_wc_booking_product( $product );
					if ( $product_added ) {
						$is_valid = true;
					}
				}

				if ( ! $product_added ) {
					if ( isset( $_POST['is_grouped'] ) ) {
						// Grouped products.
						$product_added = self::handle_grouped_products();
					} elseif ( isset( $_POST['is_variable'] ) ) {
						// Variable products
						$product_added = self::handle_variable_products();
					} else {
						// Simple products
						// Add product to cart.
				if ( $is_valid ) {
					$product_added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
				}
					}
				}

				if ( $is_valid && $product_added ) {
					do_action( 'woocommerce_ajax_added_to_cart', $product_id );

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						wc_add_to_cart_message( array( $product_id => $quantity ), true );

						// User has enabled redirect to cart on successful addition.
						if ( 0 === wc_notice_count( 'error' ) ) {
							$data = array(
								'redirect_to_cart' => true,
							);

							// Clear notices so they don't show up after redirect.
							wc_clear_notices();

							echo wp_send_json( $data );

							wp_die();
						}
					} else {
						// Adding the notice in the response so it can be outputted right away (woocommerce.js).
						add_filter( 'woocommerce_add_to_cart_fragments', [ __CLASS__, 'vamtam_woocommerce_add_to_cart_fragments' ] );
					}

					// Clear noticed so they don't show up on refresh.
					wc_clear_notices();

					WC_AJAX::get_refreshed_fragments();
				} else {
					$data = array(
						'error' => true,
						'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
						'notice' => '<div class="' . esc_attr( 'woocommerce-message error' ) . '" role="alert"><span class="vamtam-wc-msg">' . wc_kses_notice( end( wc_get_notices( 'error' ) )['notice'] ) . '</span><span class="vamtam-close-notice-btn" /></div>',
					);

					// Clear noticed so they don't show up on refresh.
					wc_clear_notices();

					echo wp_send_json( $data );
				}

				wp_die();
			}

			public static function handle_grouped_products() {
				$items             = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? $_POST['products'] : [];
				$added_to_cart     = [];
				$was_added_to_cart = false;

				if ( ! empty( $items ) ) {
					$quantity_set = false;

					foreach ( $items as $item => $quantity ) {
						if ( $quantity <= 0 ) {
							continue;
						}
						$quantity_set = true;

						// Add to cart validation.
						$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $item, $quantity );

						// Suppress total recalculation until finished.
						remove_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );

						if ( $passed_validation && false !== WC()->cart->add_to_cart( $item, $quantity ) ) {
							$was_added_to_cart      = true;
							$added_to_cart[ $item ] = $quantity;
						}

						add_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );
					}

					if ( ! $was_added_to_cart && ! $quantity_set ) {
						wc_add_notice( __( 'Please choose the quantity of items you wish to add to your cart.', 'jolie' ), 'error' );
					} elseif ( $was_added_to_cart ) {
						WC()->cart->calculate_totals();
						return true;
					}
				}

				return false;
			}

			public static function handle_variable_products() {
				$product_id   = self::$product_id;
				$variation_id = empty( $_REQUEST['variation_id'] ) ? '' : absint( wp_unslash( $_REQUEST['variation_id'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$quantity     = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_REQUEST['quantity'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$variations   = array();

				$product      = wc_get_product( $product_id );

				foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( 'attribute_' !== substr( $key, 0, 10 ) ) {
						continue;
					}

					$variations[ sanitize_title( wp_unslash( $key ) ) ] = wp_unslash( $value );
				}

				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

				if ( ! $passed_validation ) {
					return false;
				}

				// Prevent parent variable product from being added to cart.
				if ( empty( $variation_id ) && $product && $product->is_type( 'variable' ) ) {
					/* translators: 1: product link, 2: product name */
					wc_add_notice( sprintf( __( 'Please choose product options by visiting <a href="%1$s" title="%2$s">%2$s</a>.', 'jolie' ), esc_url( get_permalink( $product_id ) ), esc_html( $product->get_name() ) ), 'error' );

					return false;
				}

				return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
			}

			public static function vamtam_woocommerce_add_to_cart_fragments( $fragments ) {
				remove_filter( 'woocommerce_add_to_cart_fragments', [ __CLASS__, 'vamtam_woocommerce_add_to_cart_fragments' ] );
				$fragments['notice'] = '<div class="' . esc_attr( 'woocommerce-message' ) . '" role="alert"><span class="vamtam-wc-msg">' . wc_add_to_cart_message( array( self::$product_id => self::$quantity ), true, true ) . '</span><span class="vamtam-close-notice-btn" /></div>';
				return $fragments;
			}
		}
	}
	// Ajax hooks must be included early.
	if ( vamtam_extra_features() ) {
		vamtam_wc_single_product_ajax_hooks();
	}
}
