<?php
if ( !defined( 'ABSPATH' ) ) exit;

if(class_exists('WPBakeryShortCode')){

	class VCExtendAddonKBauthorTags {
		function __construct() {
			// We safely integrate with VC with this hook
			add_action( 'init', array( $this, 'KBTagsintegrateWithVC' ) );

			// Use this when creating a shortcode addon
			add_shortcode( 'kb_tag_sidebar', array( $this, 'renderKBTags' ) );
		}

		public function KBTagsintegrateWithVC() {
			// Check if Visual Composer is installed
			if ( ! defined( 'WPB_VC_VERSION' ) ) {
				// Display notice that Visual Compser is required
				add_action('admin_notices', array( $this, 'showVcVersionNotice' ));
				return;
			}

			/*
			Add your Visual Composer logic here.
			Lets call vc_map function to "register" our custom shortcode within Visual Composer interface.

			More info: http://kb.wpbakery.com/index.php?title=Vc_map
			*/
			vc_map( array(
				"name" => __("Sidebar of Kbucket tags", WPKB_TEXTDOMAIN),
				"description" => __("List of category, author, related tags", WPKB_TEXTDOMAIN),
				"base" => "kb_tag_sidebar",
				"class" => "",
				"controls" => "full",
				'icon' => WPKB_PLUGIN_URL.'/images/vc-icon.png',
				'category' => __('Optimal Access', WPKB_TEXTDOMAIN),
				"params" => array(
				    array(
						"type" => "textfield",
						"holder" => "div",
						"class" => "",
						"heading" => __("Content before", WPKB_TEXTDOMAIN),
						"param_name" => "before_widget",
					),
					array(
						"type" => "textfield",
						"holder" => "div",
						"class" => "",
						"heading" => __("Content after", WPKB_TEXTDOMAIN),
						"param_name" => "after_widget",
					),
				)
			) );
		}

		/*
		Shortcode logic how it should be rendered
		*/
		public function renderKBTags( $atts, $content = null ) {
			extract( shortcode_atts( array(
				'before_widget' => '',
				'after_widget' => '',
			), $atts ) );


			$action = KBucket::get_kbucket_instance();

			if(!$action->is_kbucket_page() && !is_vc_activate()) return false;

			$output = "";

			if(!empty($atts['before_widget'])) $output .= $atts['before_widget'];

			$output .= '<div id="kb-tags-wrap-1" class="">';
				// Render category tags
				$categoryTags = $action->get_category_tags();


				if ( ! empty( $categoryTags ) ) {
					$output .= '<h3>Category Tags</h3>';

					$output .= $action->render_tags_cloud_alt(
						$categoryTags,
						'm',
						'main',
						array( 'value' => $action->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
						array( 'mtag' => 'name' )
					);
				}

				// Render related tags
				if ( ! empty( $action->kbPage->relatedTags ) ) {
					$output .= '<h3>Related Tags</h3>';

					$output .= $action->render_tags_cloud_alt(
						$action->kbPage->relatedTags,
						'r',
						'related',
						array( 'value' => $action->kbPage->relatedTagName, 'dbKey' => 'name', 'title' => 'name' ),
						array( 'mtag' => array( 'raw' => $action->kbPage->activeTagName ), 'rtag' => 'name' )
					);
				}

				// Render Author tags
				if ( $action->kbPage->settings->author_tag_display && isset( $action->kbPage->authorTagName ) ) {
					$authorTags = $action->get_author_tags();

					$output .= '<h3>Author Tags</h3>';
					$output .= $action->render_tags_cloud_alt(
						$authorTags,
						'a',
						'author',
						array( 'value' => $action->kbPage->authorTagName, 'dbKey' => 'author', 'title' => 'author' ),
						array( 'atag' => 'author' )
					);
				}

				// Render Publisher tags
				$publisherTags = $action->get_publisher_tags();
				if(isset($publisherTags[0]->publisher)) $output .= '<h3>Publisher Tags</h3>';


				$output .= $action->render_tags_cloud_alt(
					$publisherTags,
					'a',
					'publisher',
					array( 'value' => $action->kbPage->publisherTagName, 'dbKey' => 'publisher', 'title' => 'publisher' ),
					array( 'ptag' => 'publisher' )
				);

			$output .= '</div>';

			if(!empty($atts['after_widget'])) $output .= $atts['after_widget'];

			return $output;
		}

		/*
		Show notice if your plugin is activated but Visual Composer is not
		*/
		public function showVcVersionNotice() {
			$plugin_data = get_plugin_data(__FILE__);
			echo '
			<div class="updated"><p>'.sprintf(__('<strong>%s</strong> requires <strong><a href="http://bit.ly/vcomposer" target="_blank">Visual Composer</a></strong> plugin to be installed and activated on your site.', 'vc_extend'), $plugin_data['Name']).'</p></div>';
		}
	}
	// Finally initialize code
	new VCExtendAddonKBauthorTags();
}


function kb_shortcode_kbucket_tags($attrs){
	$args = shortcode_atts(array(
		'before_widget' => '',
		'after_widget' => '',
	),$attrs,'shortcode_kbucket_tags');

	$action = KBucket::get_kbucket_instance();

	if(!$action->is_kbucket_page() && !is_vc_activate()) return false;

	$output = "";

	if(!empty($args['before_widget'])) $output .= $args['before_widget'];

	$output .= '<div id="kb-tags-wrap-1" class="">';
		// Render category tags
		$categoryTags = $action->get_category_tags();


		if ( ! empty( $categoryTags ) ) {
			$output .= '<h3>Category Tags</h3>';

			$output .= $action->render_tags_cloud_alt(
				$categoryTags,
				'm',
				'main',
				array( 'value' => $action->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
				array( 'mtag' => 'name' )
			);
		}

		// Render related tags
		if ( ! empty( $action->kbPage->relatedTags ) ) {
			$output .= '<h3>Related Tags</h3>';

			$output .= $action->render_tags_cloud_alt(
				$action->kbPage->relatedTags,
				'r',
				'related',
				array( 'value' => $action->kbPage->relatedTagName, 'dbKey' => 'name', 'title' => 'name' ),
				array( 'mtag' => array( 'raw' => $action->kbPage->activeTagName ), 'rtag' => 'name' )
			);
		}

		// Render Author tags
		if ( $action->kbPage->settings->author_tag_display && isset( $action->kbPage->authorTagName ) ) {
			$authorTags = $action->get_author_tags();

			$output .= '<h3>Author Tags</h3>';
			$output .= $action->render_tags_cloud_alt(
				$authorTags,
				'a',
				'author',
				array( 'value' => $action->kbPage->authorTagName, 'dbKey' => 'author', 'title' => 'author' ),
				array( 'atag' => 'author' )
			);
		}

		// Render Publisher tags
		$publisherTags = $action->get_publisher_tags();
		if(isset($publisherTags[0]->publisher)) $output .= '<h3>Publisher Tags</h3>';


		$output .= $action->render_tags_cloud_alt(
			$publisherTags,
			'a',
			'publisher',
			array( 'value' => $action->kbPage->publisherTagName, 'dbKey' => 'publisher', 'title' => 'publisher' ),
			array( 'ptag' => 'publisher' )
		);

	$output .= '</div>';

	if(!empty($args['after_widget'])) $output .= $args['after_widget'];

	return $output;
}
add_shortcode( 'shortcode_kbucket_tags', 'kb_shortcode_kbucket_tags' );
