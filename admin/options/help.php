<?php
return array(
	'name' => esc_html__( 'Help', 'jolie' ),
	'auto' => true,
	'config' => array(

		array(
			'name' => esc_html__( 'Help', 'jolie' ),
			'type' => 'title',
			'desc' => '',
		),

		array(
			'name' => esc_html__( 'Help', 'jolie' ),
			'type' => 'start',
			'nosave' => true,
		),
//----
		array(
			'type' => 'docs',
		),

			array(
				'type' => 'end',
			),
	),
);
