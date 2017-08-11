<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<li id="kb-item-<?php echo esc_attr( $m->kbucketId );?>" class="kb-item kb-col-4">

	<div class="grid-sizer"></div>
	<?php

	$imageUrl = isset($kbucket->image_url) && $kbucket->image_url !== '' ? $kbucket->image_url : false;
	$image_exist = pathinfo($imageUrl);


	?>



	<div class="kb-item-inner-wrap" itemscope itemtype="http://schema.org/Article">
		<meta itemscope itemprop="mainEntityOfPage" itemType="https://schema.org/WebPage" itemid="<?php echo esc_attr( $m->link ); ?>"/>

		<div class="__img-wrap" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
			<?php if(!empty($image_exist['filename']) && !empty($image_exist['basename'])): ?>
				<div class="image_wrap" style="background-image:url('<?php echo $imageUrl ?>')"></div>
				<meta itemprop="url" content="<?php echo $imageUrl ?>"/>
				<meta itemprop="width" content="500"/>
				<meta itemprop="height" content="500"/>
			<?php else: ?>
				<div class="image_wrap no_image"></div>
				<meta itemprop="url" content="http://placehold.it/150x150"/>
				<meta itemprop="width" content="500"/>
				<meta itemprop="height" content="500"/>
			<?php endif; ?>
		</div>


		<div class="body_wrap">
			<div class="meta_tags">
				<span class="kb-item-author">
					<span class="kb-item-author-name" title="<?php echo esc_html( $m->publisher ); ?>" itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
						<?php _e("Publisher:",WPKB_TEXTDOMAIN) ?> <span itemprop="name"><?php echo esc_html( $m->publisher ); ?></span>
						<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject"><img itemprop="url" src="/" style="display:none;"/></span>
					</span><br>
					<span class="kb-item-author-name" title="<?php echo esc_html( $m->author ); ?>" itemprop="author" itemscope itemtype="https://schema.org/Person">
						<?php _e("Author:",WPKB_TEXTDOMAIN) ?> <span itemprop="name"><?php echo esc_html( $m->author ); ?></span>
					</span>
				</span>
				<span class="kb-item-date" itemprop="dateModified">
					<?php echo date( "Y-m-d",strtotime($m->add_date) ); ?>
				</span>
				<meta itemprop="datePublished" content="<?php echo date( "Y-m-d",strtotime($m->add_date) ); ?>"/>
			</div>

			<h3 itemprop="headline">
				<?php $title = html_entity_decode(esc_html( $m->title )); ?>
				<a rel="nofollow,noindex" href="<?php echo esc_attr( $m->link ); ?>" class="kb-link" target="_blank" style="color:<?php echo esc_attr( $this->kbPage->settings->header_color );?>;"><?=(strlen($title) >=107 ? substr( $title, 0, 107 ).'...' : $title); ?></a>
			</h3>
			<p class="body_text" itemprop="description">

				<?php echo html_entity_decode(substr( kbucket_strip_cdata($description), 0, 100 )); ?>

				<?php if($share): ?>
					<a style="color:<?php echo esc_attr( $this->kbPage->settings->tag_color );?>;" id="kb-share-item-<?php echo esc_attr( $m->kbucketId );?>" class="kb-share-item kb-more-button" title="<?php echo esc_attr( $m->title ); ?>">
						<img src="<?php echo WPKB_PLUGIN_URL . '/images/kshare.png'; ?>" alt="<?php _e("Share Button",WPKB_TEXTDOMAIN) ?>"/>
					</a>
				<?php endif ?>
			</p>
			<p class="blue" style="margin-bottom:2px;">
				<span style="color:<?php echo esc_attr( $this->kbPage->settings->tag_color ); ?>;"><?php _e("Tags:",WPKB_TEXTDOMAIN) ?></span>
				<span itemprop="keywords"><?php $this->render_kbucket_tags( get_permalink(), $m->tags );?></span>
			</p>

			<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating" style="display: none !important;">
				<span itemprop="ratingValue">5</span>
				<span itemprop="reviewCount">0</span>
			</div>


			<div id="fb-root"></div>

		</div>

		<div class="kb-footer">
			<div class="addthis_toolbox addthis_default_style">
				<table class="extended_addthis_share">
					<tr>
						<td colspan="4">
							<label>Url:</label>
							<input type="text" class="form-control url_share_addthis" value="<?php echo $url ?>">
						</td>
					</tr>
				</table>
			</div>
		</div>


	</div><!-- /.kb-item-inner-wrap -->
</li>
