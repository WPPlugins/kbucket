<?php
if ( !defined( 'ABSPATH' ) ) exit;
/*
Description: Show KBuckets in page
*/

// Element Class
if(class_exists('WPBakeryShortCode')){
	class vcShowKbuckets extends WPBakeryShortCode {

		// Element Init
		function __construct() {
			add_action( 'init', array( $this, 'vc_kb_list_mapping' ), 100 );
			add_shortcode( 'vc_infobox', array( $this, 'vc_kb_list_html' ), 100 );
		}

		// Element Mapping
		public function vc_kb_list_mapping() {

			// Stop all if VC is not enabled
			if ( !defined( 'WPB_VC_VERSION' ) ) {
					return;
			}

			// Map the block with vc_map()
			vc_map(
				array(
					'name' => __('List of KBuckets', WPKB_TEXTDOMAIN),
					'base' => 'vc_infobox',
					'description' => __('Kbuckets list with categories', WPKB_TEXTDOMAIN),
					'category' => __('Optimal Access', WPKB_TEXTDOMAIN),
					'icon' => WPKB_PLUGIN_URL.'/images/vc-icon.png',
					'params' => array(

						// array(
						// 	'type' => 'textfield',
						// 	'holder' => 'h3',
						// 	'class' => 'title-class',
						// 	'heading' => __( 'Title', 'text-domain' ),
						// 	'param_name' => 'title',
						// 	'value' => __( 'Default value', 'text-domain' ),
						// 	'description' => __( 'Box Title', 'text-domain' ),
						// 	'admin_label' => false,
						// 	'weight' => 0,
						// 	'group' => 'Custom Group',
						// ),

						// array(
						// 	'type' => 'textarea',
						// 	'holder' => 'div',
						// 	'class' => 'text-class',
						// 	'heading' => __( 'Text', 'text-domain' ),
						// 	'param_name' => 'text',
						// 	'value' => __( 'Default value', 'text-domain' ),
						// 	'description' => __( 'Box Text', 'text-domain' ),
						// 	'admin_label' => false,
						// 	'weight' => 0,
						// 	'group' => 'Custom Group',
						// )
					)
				)
			);

		}


		// Element HTML
		public function vc_kb_list_html( $atts ) {
			// Params extraction
			// extract(
			// 	shortcode_atts(
			// 		array(
			// 			'title'   => '',
			// 			'text' => '',
			// 		),
			// 		$atts
			// 	)
			// );

			$html = '';
			// Fill $html var with data
			$html .= '<div class="vc-kb-wrap">';
				$kbucket = KBucket::get_kbucket_instance();
				$html .= $kbucket->filter_the_content_alt();
			$html .= '</div>';

			return $html;

		}

	} // End Element Class
	// Element Class Init
	new vcShowKbuckets();
}



function kb_shortcode_kbucket_list($attrs){
	$args = shortcode_atts(array(
		'arg' => 0,
	),$attrs,'shortcode_kbucket_list');

	$html = '';
	// Fill $html var with data
	$html .= '<div class="vc-kb-wrap">';
		$kbucket = KBucket::get_kbucket_instance();
		$html .= $kbucket->filter_the_content_alt();
	$html .= '</div>';

	return $html;
}
add_shortcode( 'shortcode_kbucket_list', 'kb_shortcode_kbucket_list' );
