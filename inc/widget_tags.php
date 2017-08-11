<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class KBauthorTags extends WP_Widget {
	function __construct() {
		parent::__construct(false, $name = __('Kbucket tags'),array('description' => __('Show tags from kbucket plugin')));
	}
	function widget($args, $instance) {
		extract( $args );
		$action = KBucket::get_kbucket_instance();
		if(!$action->is_kbucket_page()) return false;


		echo($args['before_widget']);
		echo($args['before_title'].$instance['title'].$args['after_title']);?>

		<div id="kb-tags-wrap-1" class="">
			<?php
			// Render category tags
			$categoryTags = $action->get_category_tags();


			if ( ! empty( $categoryTags ) ) { ?>
				<h3>Category Tags</h3>
				<?php

				$action->render_tags_cloud(
					$categoryTags,
					'm',
					'main',
					array( 'value' => $action->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
					array( 'mtag' => 'name' )
				);
			}

			// Render related tags
			if ( ! empty( $action->kbPage->relatedTags ) ) { ?>
				<h3>Related Tags</h3>
				<?php
				$action->render_tags_cloud(
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

				//if($authorTags[0]->author){
					echo '<h3>Author Tags</h3>';
					$action->render_tags_cloud(
						$authorTags,
						'a',
						'author',
						array( 'value' => $action->kbPage->authorTagName, 'dbKey' => 'author', 'title' => 'author' ),
						array( 'atag' => 'author' )
					);
				//}



			}

			// Render Publisher tags
			$publisherTags = $action->get_publisher_tags();
			if(isset($publisherTags[0]->publisher)) echo '<h3>Publisher Tags</h3>';


			$action->render_tags_cloud(
				$publisherTags,
				'a',
				'publisher',
				array( 'value' => $action->kbPage->publisherTagName, 'dbKey' => 'publisher', 'title' => 'publisher' ),
				array( 'ptag' => 'publisher' )
			);
			?>
		</div>

		<?php
		echo($args['after_widget']);
	}

	function update($new_instance, $old_instance) {
		if(!isset($new_instance[ 'only_kb_page' ])) $new_instance[ 'only_kb_page' ] = false;
		return $new_instance;
	}

	function form($instance) {
		$title = '';
		$count_elm = true;
		if (isset( $instance['title'])) $title = esc_attr($instance['title']);
		else $title = 'Your Title';
		$only_kb_page = (isset( $instance['only_kb_page']) && $instance['only_kb_page']) ? true : false; ?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:',WPKB_TEXTDOMAIN); ?>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</label>
		</p>
		<!-- <p>
			<input class="widefat" id="<?php echo $this->get_field_id('only_kb_page'); ?>" name="<?php echo $this->get_field_name('only_kb_page'); ?>" type="checkbox" <?php if($only_kb_page) echo 'checked="checked"' ?> />
			<label for="<?php echo $this->get_field_id('only_kb_page'); ?>"><?php _e('Show only kbucket page',WPKB_TEXTDOMAIN); ?></label>
		</p> -->
		<?php
	}
}
function wd_KBauthorTags() {register_widget('KBauthorTags');}
add_action('widgets_init', 'wd_KBauthorTags');
