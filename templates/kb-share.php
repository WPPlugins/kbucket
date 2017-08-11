<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php $imageUrl = $kbucket->image_url !== '' ? $kbucket->image_url : false; ?>
<div class="kb-wrapper share_modal_wrapper">

	<div class="kb-item">
		<?php if ( ! empty( $shareData['imageUrl'] ) ): ?>
			<img src="<?php echo esc_attr( $shareData['imageUrl'] ); ?>" class="share-image" /><br/>
		<?php endif; ?>

		<div class="meta_tags">
			<span class="kb-item-author">
				<?php _e("Publisher:",WPKB_TEXTDOMAIN) ?> <?php echo esc_html( $kbucket->publisher ); ?><br>
				<?php _e("Author:",WPKB_TEXTDOMAIN) ?> <?php echo esc_html( $kbucket->author ); ?></span>
			</span>
			<span class="kb-item-date"><?php echo date("Y-m-d",strtotime( $kbucket->pub_date )); ?></span>
		</div>

		<h3><a href="<?php echo esc_attr( $kbucket->link ); ?>" target="_blank"><?php echo html_entity_decode( esc_html($shareData['title']) );?></a></h3>

		<p class="body_text">
			<?php echo html_entity_decode(wp_kses(
				kbucket_strip_cdata($shareData['description']),
				array(
					'a' => array(
						'href' => array(),
						'title' => array()
					),
					'br' => array(),
				)
			));?>
		</p>
		<p class="blue" style=" margin-bottom:2px;">
			<span>Tags: <?php echo esc_html( $kbucket->tags ); ?></span>
			<?php $this->render_kbucket_tags( $url, $kbucket->tags );?>
		</p>

		<?php
		$description = strip_tags(html_entity_decode(kbucket_strip_cdata($shareData['description'])));
		$description = strlen($description) >= 56 ? substr($description, 0, 56).'...' : $description;
		?>

		<div class="kb-footer-modal">
			<div class="addthis_toolbox_custom addthis_default_style" addthis:url="<?php echo $url.'/?share=true' ?>" addthis:title="<?php echo trim($shareData['title']) ?>" addthis:description="<?php echo $description ?>">
				<div class="wrap_addthis_share">
					<div style="width:33.333%;">
						<div class="fb-share-button" data-action="like" data-show-faces="true" data-href="<?php echo $url.'/?share=true' ?>" data-layout="button_count"></div>
					</div>
					<div style="width:33.333%;">
						<a class="addthis_button_tweet"></a>
					</div>
					<div style="width:33.333%;">
						<a class="addthis_button_google_plusone" g:plusone:annotation="bubble" g:plusone:size="medium"></a>
					</div>
				</div>

				<div class="extended_addthis_share">
					<div style='width:25%'><a class="addthis_button_linkedin_counter" li:counter="none"></a></div>
					<div style='width:25%'><a class="addthis_button_pinterest_pinit"></a></div>
					<div style='width:25%'><a class="addthis_button_stumbleupon_badge" su:badge:style="3" su:badge:url="<?php echo $url ?>" style="background-image: url('<?php echo WPKB_PLUGIN_URL; ?>/images/stumble.png')"></a></div>
					<div style='width:25%'><a class="addthis_button_email"><img src="<?php echo WPKB_PLUGIN_URL; ?>/images/email-share.png" alt="mail"></a></div>
				</div>
			</div>
			<div id="fb-root"></div>

			<style>
				.fb_iframe_widget span,
				iframe.fb_iframe_widget_lift,
				.fb_iframe_widget iframe {
				    width:95px !important;
				    height:20px !important;
				    position:relative;
				}
			</style>

			<script>
				jQuery(".url_share_addthis").focus(function() { jQuery(this).select(); } );
				<?php if($kbucket->twitter): ?>
					window.addthis_share.passthrough = {twitter: {via: '<?=str_replace('@', '', $kbucket->twitter);?>'}};
				<?php endif ?>
				addthis.toolbox('.addthis_toolbox_custom');
			</script>
		</div>
	</div>

	<?php $kb_share = getKBSettings(); ?>
	<?php if(isset($kb_share->kb_share_popap)): ?>
		<div class="ext_widget_sect">
			<?php echo kbucket_utf8_urldecode($kb_share->kb_share_popap); ?>
		</div>
	<?php endif; ?>
</div>
