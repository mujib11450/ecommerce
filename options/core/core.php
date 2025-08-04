<?php

/**
 * Controls attached to core sections
 *
 * @package vamtam/jolie
 */


return array(
	array(
		'label'     => esc_html__( 'Header Logo Type', 'jolie' ),
		'id'        => 'header-logo-type',
		'type'      => 'switch',
		'transport' => 'postMessage',
		'section'   => 'title_tagline',
		'choices'   => array(
			'image'      => esc_html__( 'Image', 'jolie' ),
			'site-title' => esc_html__( 'Site Title', 'jolie' ),
		),
		'priority' => 8,
	),

	array(
		'label'     => esc_html__( 'Single Product Image Zoom', 'jolie' ),
		'id'        => 'wc-product-gallery-zoom',
		'type'      => 'switch',
		'transport' => 'postMessage',
		'section'   => 'woocommerce_product_images',
		'choices'   => array(
			'enabled'  => esc_html__( 'Enabled', 'jolie' ),
			'disabled' => esc_html__( 'Disabled', 'jolie' ),
		),
		// 'active_callback' => 'vamtam_extra_features',
	),
);


