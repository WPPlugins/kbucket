<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<ul id="kBucketTabs" class="shadetabs">
	<li><a href="#" rel="country0" class="selected"><?php _e("Home",WPKB_TEXTDOMAIN) ?></a></li>
	<li><a href="#" rel="country1"><?php _e("Settings",WPKB_TEXTDOMAIN) ?></a></li>
	<li><a href="#" rel="country2"><?php _e("Upload Kbucket",WPKB_TEXTDOMAIN) ?></a></li>
	<li><a href="#" rel="country3"><?php _e("Set Social",WPKB_TEXTDOMAIN) ?></a></li>
	<li><a href="#" rel="country5"><?php _e("user Comments",WPKB_TEXTDOMAIN) ?></a></li>
	<li><a href="#" rel="country6"><?php _e("Published Links",WPKB_TEXTDOMAIN) ?></a></li>
</ul>

<div id="messages"></div>
<div class="kbucket-tabcontent">
	<div id="country0" class="tabcontent">

		<h1 style="margin-top:40px">Welcome to KBucket</h1>
		<p>This Home page has important links and information for using KBucket.</p>

		<h1 style="margin-top:10px">Getting Started</h1>
		<p>To get started you first need to register and get your API key from our site <a href="https://optimalaccess.com/register">here</a>.</p>
		<p>Once you register copy the API key from your profile page and enter it under the KBucket à Settings tab.</p>
		<p>Once you unlock your free version you will need to install Kauthor, our browser based curation tool.  </p>
		<p>To get started launch your Firefox browser and go <a href="https://optimalaccess.com/download">here</a> to Download and install the extension.</p>
		<p>See Important Links below for access to various resources regarding Kauthor.</p>
		<p>Our free version lets you post 12 post per channel. You can upgrade to a professional plan by visiting your Profile page <a href="https://optimalaccess.com/profile/">here</a>.</p>

		<h1 style="margin-top:10px">Important Links</h1>
		<p>As a Curator you need specific functionality. In a series of blog posts we have created a tutorial that lists the features and shows you how to use them. <a href="http://blog.optimalaccess.com/blog/topic/robins-criteria">Click here</a> to access these posts.</p>
		<p>We have 3 sets of Forums with important lessons and guidelines.  You can ask question and make your comments in any of these <a href="https://optimalaccess.com/forums/">forums</a>.</p>
		<p>You can access our standard training videos for Kauthor <a href="https://optimalaccess.com/products/">here</a>.</p>

	</div>
	<div id="country1" class="tabcontent">
		<form method="post" id="form" enctype="multipart/form-data">
			<?php $thepost = getKBSettings(); ?>
			<table>
				<tr>
					<th colspan="3"><?php _e("Page Settings",WPKB_TEXTDOMAIN) ?></th>
				</tr>

				<tr>
					<td class="kbucket-td-w30"><?php _e("API Key",WPKB_TEXTDOMAIN) ?></td>
					<td class="kbucket-td-w5">:</td>
					<td class="kbucket-td-w60">
						<input type="text" name="api_key" id="api_key" value="<?php echo (isset($thepost->api_key) ? $thepost->api_key: ''); ?>"/>
						<label for="api_key"></label>
						<input type="hidden" name="api_host" value="<?php echo kbucket_url_origin($_SERVER) ?>">
					</td>
				</tr>
				<tr>
					<td class="kbucket-td-w30"><?php _e("Author Tag Display",WPKB_TEXTDOMAIN) ?></td>
					<td class="kbucket-td-w5">:</td>
					<td class="kbucket-td-w60">

						<label for="atd-y">
							<input type="radio" name="atd" id="atd-y" value="1" <?php if ( 1 == $thepost->author_tag_display ) echo 'checked="checked"'; ?> />
							<span><?php _e("Yes",WPKB_TEXTDOMAIN) ?></span>
						</label>

						<label for="atd-n">
							<input type="radio" name="atd" id="atd-n" value="0" <?php if ( 0 == $thepost->author_tag_display ) echo 'checked="checked"'; ?> />
							<span><?php _e("No",WPKB_TEXTDOMAIN) ?></span>
						</label>
					</td>
				</tr>
				<tr>
					<td class="kbucket-td-w30"><?php _e("Page Title",WPKB_TEXTDOMAIN) ?></td>
					<td class="kbucket-td-w5">:</td>
					<td class="kbucket-td-w60">
						<input type="text" name="page_title" id="page_title" value="<?php echo(isset($thepost->page_title) ? $thepost->page_title : ''); ?>"/>
						<label for="page_title"><?php _e("If blank, empty title",WPKB_TEXTDOMAIN) ?></label>
					</td>
				</tr>
				<tr>
					<td class="kbucket-td-w30"><?php _e("Site Search",WPKB_TEXTDOMAIN) ?></td>
					<td class="kbucket-td-w5">:</td>
					<td class="kbucket-td-w60">

						<label for="site_search-1">
							<input type="radio" name="site_search" id="site_search-1" value="1" <?php if ( 1 == $thepost->site_search ) echo 'checked="checked"'; ?> />
							<span><?php _e("Yes",WPKB_TEXTDOMAIN) ?></span>
						</label>

						<label for="site_search-0">
							<input type="radio" name="site_search" id="site_search-0" value="0" <?php if ( 0 == $thepost->site_search ) echo 'checked="checked"'; ?> />
							<span><?php _e("No",WPKB_TEXTDOMAIN) ?></span>
						</label>
					</td>
				</tr>
				<tr>
					<td class="kbucket-td-w30"><?php _e("Sort By",WPKB_TEXTDOMAIN) ?></td>
					<td class="kbucket-td-w5">:</td>
					<td class="kbucket-td-w60">
						<select name="sortBy" id="sortBy">
							<option value=""><?php _e("Select",WPKB_TEXTDOMAIN) ?></option>
							<option	value="author" <?php if ( 'author' == $thepost->sort_by ) echo 'selected="selected"';?>><?php _e("Author",WPKB_TEXTDOMAIN) ?></option>
							<option value="title" <?php	if ( 'title' == $thepost->sort_by ) echo 'selected="selected"';?>><?php _e("Title",WPKB_TEXTDOMAIN) ?></option>
							<option value="add_date" <?php if ( 'add_date' == $thepost->sort_by ) echo 'selected="selected"' ?>><?php _e("Add Date",WPKB_TEXTDOMAIN) ?></option>
							<option value="pub_date" <?php if ( 'pub_date' == $thepost->sort_by ) echo 'selected="selected"' ?>><?php _e("Publish Date",WPKB_TEXTDOMAIN) ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td align="left" height="40"><?php _e("Sort Order",WPKB_TEXTDOMAIN) ?></td>
					<td>:</td>
					<td align="left">
						<select name="sortOrder" id="sortOrder">
							<option value=""><?php _e("Select",WPKB_TEXTDOMAIN) ?></option>
							<?php if ( isset( $thepost->sort_order ) ): ?>
								<option value="asc" <?php if ( 'asc' == $thepost->sort_order ) echo 'selected="selected"';?>><?php _e("Ascending",WPKB_TEXTDOMAIN) ?></option>
								<option value="desc" <?php if ( 'desc' == $thepost->sort_order ) echo 'selected="selected"';?>><?php _e("Descending",WPKB_TEXTDOMAIN) ?></option>
								<option value="desc" <?php if ( 'desc' == $thepost->sort_order ) echo 'selected="selected"';?>><?php _e("Newest",WPKB_TEXTDOMAIN) ?></option>
								<option value="asc" <?php if ( 'asc' == $thepost->sort_order ) echo 'selected="selected"';?>><?php _e("Oldest",WPKB_TEXTDOMAIN) ?></option>
							<?php else: ?>
								<option value="asc"><?php _e("Ascending",WPKB_TEXTDOMAIN) ?></option>
								<option value="desc"><?php _e("Descending",WPKB_TEXTDOMAIN) ?></option>
								<option value="desc"><?php _e("Newest",WPKB_TEXTDOMAIN) ?></option>
								<option value="asc"><?php _e("Oldest",WPKB_TEXTDOMAIN) ?></option>
							<?php endif ?>
						</select>
					</td>
				</tr>
				<tr>
					<td align="left" height="40"><?php _e("Number Of Listing per Page",WPKB_TEXTDOMAIN) ?></td>
					<td>:</td>
					<td align="left">
						<?php if ( isset( $thepost->no_listing_perpage ) ): ?>
							<input type="text" id="no_listing_page" name="no_listing_page" value="<?php	echo $thepost->no_listing_perpage;?>" class="number" />
						<?php else : ?>
							<input type="textbox" id="no_listing_page" name="no_listing_page" value="10" class="number"/>
						<?php endif; ?>
					</td>
				</tr>


				<tr>
					<td align="left" height="40"><?php _e("Html for K-Share popap",WPKB_TEXTDOMAIN) ?></td>
					<td>:</td>
					<td align="left">
						<?php if ( isset( $thepost->kb_share_popap ) ): ?>
							<textarea style="width: 100%;min-height: 90px;" id="kb_share_popap" name="kb_share_popap"><?php echo kbucket_utf8_urldecode($thepost->kb_share_popap);?></textarea>
						<?php else : ?>
							<textarea style="width: 100%;min-height: 90px;" id="kb_share_popap" name="kb_share_popap"></textarea>
						<?php endif; ?>
					</td>
				</tr>


				<tr>
					<td align="left" height="40"><?php _e("Use as ShortCode",WPKB_TEXTDOMAIN) ?></td>
					<td></td>
					<td align="left">
						<label for="site_vc-1">
							<input type="radio" name="site_vc" id="site_vc-1" value="1" <?php if ( 1 == (int)$thepost->vc ) echo 'checked="checked"'; ?> />
							<span><?php _e("Yes",WPKB_TEXTDOMAIN) ?></span>
						</label>
						<label for="site_vc-0">
							<input type="radio" name="site_vc" id="site_vc-0" value="0" <?php if ( 0 == (int)$thepost->vc ) echo 'checked="checked"'; ?> />
							<span><?php _e("No",WPKB_TEXTDOMAIN) ?></span>
						</label>

						<?php if((int)$thepost->vc): ?>
							<div class="kb_admin_error">
								[shortcode_kbucket_list] - Post list
								[shortcode_kbucket_tags] - Tags cloud
							</div>
						<?php endif; ?>
					</td>
				</tr>


				<tr>
					<td class="kbucket-text-left-h40" colspan="3">
						<?php if ( isset( $thepost->related_font_max ) ): ?>
							<input class="button button-primary" type="submit" name="submit" id="submit" value="<?php _e("Update Settings",WPKB_TEXTDOMAIN) ?>"/>
						<?php else : ?>
							<input class="button button-primary" type="submit" name="submit" id="submit" value="<?php _e("Update Settings",WPKB_TEXTDOMAIN) ?>"/>
						<?php endif; ?>
					</td>
				</tr>


			</table>
		</form>
	</div>

	<div id="country2" class="tabcontent">
		<style type="text/css">
			@font-face {
				font-family: "pixel_font";
				src: url("<?php echo WPKB_PLUGIN_URL; ?>/fonts/Gameplay.ttf"),
				url("<?php echo WPKB_PLUGIN_URL; ?>/fonts/Gameplay.ttf") format("truetype");
				font-style: normal;
				font-weight: normal;
			}
			.wrap_textarea{
				width: 100%;
				box-shadow: 0 0 6px rgba(0,0,0,0.4) inset;
				border: 1px solid #bababa;
				padding: 10px;
				background-color: #eaeaea;
				height: 195px;
				position: relative;
				margin: 0 !important;
				position: relative;
				margin: 20px 0 !important;
				overflow: hidden;
			}

			.wrap_textarea .textarea{
				overflow: auto;
				height: 100%;
				margin-right: -30px;
			}

			.wrap_textarea .textarea p{
				margin: 0;
				font-family: pixel_font,sans-serif;
				color: #888;
				font-size: 10px;
				letter-spacing: 1.1px;
				position: relative;
				padding-left: 55px;
			}
			.wrap_textarea .textarea p span.timer{
				position: absolute;
				left: 0;
				top: 0;
				width: 40px;
				height: 100%;
			}

			.wrap_textarea:before{
				content: "";
				left: 0;
				top: 0;
				display: inline-block;
				width: 100%;
				height: 100%;
				position: absolute;
				background-image: linear-gradient(rgba(255,255,255,0.4),rgba(0,0,0,0.1));
				z-index: 1;
			}
			@keyframes blink {
				0% {opacity: .2;}
				20% {opacity: 1;}
				100% {opacity: .2;}
			}
			.saving span {
				animation-name: blink;
				animation-duration: 1.4s;
				animation-iteration-count: infinite;
				animation-fill-mode: both;
				display: inline-block;
				padding: 0 2px;
				font-size: 15px;
				line-height: 5px;
			}
			.saving span:nth-child(2) {
				animation-delay: .2s;
			}
			.saving span:nth-child(3) {
				animation-delay: .4s;
			}
		</style>
		<p><?php _e("Upload KBuckets to Upload KBucket",WPKB_TEXTDOMAIN) ?></p>
		<form method="post" id="form_upload_xml" enctype="multipart/form-data">
			<div class="wrap_upload_xml_form" style="width:700px;height:200px; margin-top:10px;">

				<input type="file" size="24" name="upload_xml" id="upload_xml" accept="text/xml" />
				<input type="submit" class="button button-primary" name="submit" value="<?php _e("Upload XML",WPKB_TEXTDOMAIN) ?>"/>
			</div>
		</form>

		<script>
		var timer = new Date().getTime();

		function kbucket_timer(timer){
			var new_timer = new Date().getTime();
			return (((new_timer - timer) / 1000)/60).toString().substr(0, 5);
		}

		function inserted_text(target, text, updated, upd_class){
			if(updated){
				if(jQuery(target).find('p.kbv_updated.'+upd_class).length){
					jQuery(target).find('p.kbv_updated.'+upd_class).css('opacity','0').html(text);
					jQuery(target).find('p.kbv_updated.'+upd_class).animate({
						'opacity': '1'},
						200, function() {
						setTimeout(function(){
							var elem = document.getElementById('log_upload');
							elem.scrollTop = elem.scrollHeight;
						},600);
					});
				}else{
					jQuery(target).append('<p class="kbv_updated '+upd_class+'" style="opacity:0"><span class="timer">'+ kbucket_timer(timer) + ': </span>' +text+'</p>');
					jQuery(target).find('p.kbv_updated.'+upd_class).animate({
						'opacity': '1'},
						200, function() {
						setTimeout(function(){
							var elem = document.getElementById('log_upload');
							elem.scrollTop = elem.scrollHeight;
						},600);
					});
				}
			}else{
				jQuery(target).append('<p style="opacity:0"><span class="timer">'+ kbucket_timer(timer) + ': </span>' +text+'</p>');
				jQuery(target).find('p:last-child').animate({
					'opacity': '1'},
					200, function() {
					setTimeout(function(){
						var elem = document.getElementById('log_upload');
						elem.scrollTop = elem.scrollHeight;
					},600);
				});
			}
		}


		function updateAllImages(img_arr) {
			var imgCount = 0;

			var count_limit = 5;
			var count = Math.ceil(parseInt(img_arr.length) / count_limit);
			var all_img = img_arr.length;

			function getNextImage() {
				jQuery.ajax({
					type: 'POST',
					dataType: 'json',
					url: ajax_url,
					timeout: 2200000, // 20 min
					data: {
						'action': 'ajax_upload_img_kbucket_test',
						'images': img_arr.slice(imgCount * count_limit, (imgCount * count_limit) + count_limit),
					},
					async: true,
					success: function(response) {
						if (response.success && imgCount <= count) {
							++imgCount;


							var s_increment = parseInt(jQuery('#log_upload').find('.success_upload b').text());
							if(!jQuery('#log_upload').find('.success_upload b').length) s_increment = response.s_uploaded;
							else s_increment = s_increment + parseInt(response.s_uploaded);

							var e_increment = parseInt(jQuery('#log_upload').find('.error_upload b').text());
							if(!jQuery('#log_upload').find('.error_upload b').length) e_increment = response.e_uploaded;
							else e_increment = e_increment + parseInt(response.e_uploaded);

							var m_increment = parseInt(jQuery('#log_upload').find('.empty_upload b').text());
							if(!jQuery('#log_upload').find('.empty_upload b').length) m_increment = response.m_uploaded;
							else m_increment = m_increment + parseInt(response.m_uploaded);

							var all_ready = parseInt(s_increment) + parseInt(e_increment) + parseInt(m_increment);

							var upload_f_arr = img_arr.slice(imgCount * count_limit, (imgCount * count_limit) + count_limit);
							var upload_f_list = '';

							for(var i in upload_f_arr){
								upload_f_list = upload_f_list + upload_f_arr[i].name + '<br>';
							}

							inserted_text('#log_upload','Uploaded or exist files: <b>'+s_increment+'</b>', true, 'success_upload');
							inserted_text('#log_upload','Error with upload: <b>'+e_increment +'</b>', true, 'error_upload');
							inserted_text('#log_upload','Without image: <b>'+m_increment+'</b>', true, 'empty_upload');
							inserted_text('#log_upload','Total images: <b>'+all_ready+'</b>'+' of '+'<b>'+all_img+'</b>', true, 'all_upload');
							inserted_text('#log_upload','Progress: <b>'+Math.ceil((all_ready/all_img) * 100)+'%</b><strong class="saving"><span>.</span><span>.</span><span>.</span></strong>', true, 'c_upload');

							getNextImage();

							if(imgCount - 1 == count){
								inserted_text('#log_upload','All done!');
								jQuery('#form_upload_xml .button-primary').removeAttr('disabled');
								jQuery('#form_upload_xml input[type="file"]').show();

								jQuery('#form_upload_xml').after('<div class="debug_wrap"><a target="_blank" href="'+'<?=WPKB_PLUGIN_URL.'/'.DEBUG_FILENAME ?>'+'">'+'Link to debug order'+'</a></div>');
							}
						}

					},
					error: function(e){
						inserted_text('#log_upload','Error: '+e.statusText);
					}
				});
			}
			getNextImage();
		}

		ajax_url = '<?=admin_url('admin-ajax.php') ?>';

		jQuery('#form_upload_xml').on('submit', function(event) {
			event.preventDefault();
			if(!jQuery('.wrap_upload_xml_form').find('#log_upload').length) jQuery('.wrap_upload_xml_form').prepend('<div class="wrap_textarea"><div class="textarea" id="log_upload"></div></div>');
			else jQuery('#log_upload').html('');

			inserted_text('#log_upload','Begin upload<strong class="saving"><span>.</span><span>.</span><span>.</span></strong><br>');

			var uploadform = document.getElementById("form_upload_xml");

			var form_data = new FormData();
			var file = jQuery(uploadform).find("input[type=file]");
			var individual_file = file[0].files[0];
			form_data.append("file", individual_file);
			form_data.append("security", jQuery(uploadform).find("input[name=security]").val());
			form_data.append("action", "ajax_upload_kbucket");
			form_data.append("size", "");

			jQuery('#form_upload_xml .button-primary').attr('disabled','disabled');
			jQuery('#form_upload_xml input[type="file"]').hide();


			jQuery.ajax({
				url: ajax_url,
				type: "POST",
				data: form_data,
				contentType: false,
				cache: false,
				dataType: "json",
				processData: false,
				timeout: 2200000, // 20 min
				success: function(response) {
					jQuery('#log_upload').find('.saving').remove();
					if(response.error){
						inserted_text('#log_upload', response.error);
					}else{
						inserted_text('#log_upload', 'File Uploaded!<br>');
						inserted_text('#log_upload', 'Begin parsing '+response.file+'<strong class="saving"><span>.</span><span>.</span><span>.</span></strong><br>');

						jQuery.ajax({
							type: 'POST',
							dataType: 'json',
							url: ajax_url,
							timeout: 2200000, // 20 min
							data: {
								'action': 'ajax_parsing_kbucket',
								'file': response.success
							},
							success: function(response){
								jQuery('#log_upload').find('.saving').remove();

								inserted_text('#log_upload','Parsing complite!<br>');
								inserted_text('#log_upload',response.success);

								inserted_text('#log_upload','Begin upload images<strong class="saving"><span>.</span><span>.</span><span>.</span></strong><br>');

								var arr = Object.keys(response.kb_arr_image).map(function (key) {
									response.kb_arr_image[key].key = key;
									return response.kb_arr_image[key];
								});

								updateAllImages(arr);

							},
							error: function(e){
								inserted_text('#log_upload','Error: '+e.statusText);
							}
						});
					}


				},
				error: function(e){
					inserted_text('#log_upload','Error: '+e.statusText);
				}
			});
		});
		</script>
	</div>

	<div id="country3" class="tabcontent">
		<p><?php _e("Enter a description and an image for social sharing of your KBucket pages. Each channel can have a unique description and image.",WPKB_TEXTDOMAIN) ?></p>
		<div style="width:700px;height:auto; margin-top:10px; font-family:Arial, Helvetica, sans-serif">
			<form method="post" id="form" enctype="multipart/form-data">
				<table width="700" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<th width="180" class="kbucket-text-left" height="35"><?php _e("Categories",WPKB_TEXTDOMAIN) ?></th>
						<th width="250"><?php _e("Description",WPKB_TEXTDOMAIN) ?></th>
						<th width="250"><?php _e("Images",WPKB_TEXTDOMAIN) ?></th>
					</tr>
					<?php print_r( $this->get_categories_dropdown( 0 ) ); ?>
					<tr>
						<td colspan="3"><input type="submit" class="button button-primary" name="submit" value="<?php _e("Update",WPKB_TEXTDOMAIN) ?>"></td>
					</tr>
				</table>

				<table width="500" cellspacing="0" cellpadding="0" style="display:none;">
					<tr>
						<th colspan="3" height="40"><?php _e("Add New Categories",WPKB_TEXTDOMAIN) ?></th>
					</tr>
					<tr>
						<td class="kbucket-text-left-h40" width="45%"><?php _e("Parent Category",WPKB_TEXTDOMAIN) ?><br/>
							[<span style="color:red"><?php _e("Select Parent Category If Exists",WPKB_TEXTDOMAIN) ?></span>]
						</td>
						<td width="5%">:</td>
						<td class="kbucket-text-left" width="50%">
							<select name="parent_category">
								<option value=""><?php _e("Select Parent category",WPKB_TEXTDOMAIN) ?></option>
								<?php $categories = $this->wpdb->get_results( "SELECT id_cat,name,level FROM {$this->wpdb->prefix}kb_category" );
								foreach ( $categories as $c ) { ?>
									<option value="<?php echo esc_attr( $c->id_cat ) . '#' . esc_attr( $c->level ); ?>"><?php echo ucfirst( esc_html( $c->name ) ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td class="kbucket-text-left-h40"><?php _e("Category Name",WPKB_TEXTDOMAIN) ?></td>
						<td>:</td>
						<td class="kbucket-text-left"><input type="text" name="category" value=""/></td>
					</tr>
					<tr>
						<td colspan="3" height="40">
							<input type="submit" name="submit" class="button button-primary" value="<?php _e("Add Category",WPKB_TEXTDOMAIN) ?>"/>
						</td>
					</tr>
				</table>
			</form>
		</div>
	</div>

	<div id="country5" class="tabcontent">
		<p><?php _e("All comments entered through “Suggest Content” tab on your KBucket page will appear here. You can subscribe to this page and follow the comments in your Kauthor Firefox Extension.",WPKB_TEXTDOMAIN) ?></p>
		<div style="width:700px;margin-top:10px; font-family:Arial, Helvetica, sans-serif; font-size:12px;">
			<a href="<?php echo $_SERVER['REQUEST_URI'] . '&suggest_rss=y'; ?>" target="_blank"><img src="<?php echo WPKB_PLUGIN_URL; ?>/images/rss_icon.png"/></a>

				<?php

				$data = $this->wpdb->get_results(
				 "SELECT a.*,b.* ,DATE_FORMAT(a.`add_date`,'%d-%m-%Y' ) AS pubdate
				 FROM `" . $this->wpdb->prefix . 'kb_suggest` a
				 LEFT JOIN
				 (
					SELECT c.`id_cat` AS parid,
							b.`id_cat` AS subid,
							c.`name` AS parcat,
							b.`name` AS subcat,
							b.`description` AS subdes,
							c.`description` AS pades
					FROM `' . $this->wpdb->prefix . 'kb_category` b
					INNER JOIN `' . $this->wpdb->prefix . 'kb_category` c ON c.`id_cat` = b.`parent_cat`
						 ) b ON a.`id_cat` = b.`subid`'
				);

				foreach ( $data as $item ): ?>
				<table data-id="<?=$item->id_sug ?>">
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Suggested for:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td>
							<span class="generic_text_3">
								<span style="text-transform: uppercase;"><?php echo esc_html( $item->subcat ); ?></span>
							</span>
						</td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Date:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->add_date ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Page Title:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->tittle ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Page Tags:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->tags ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Page URL:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><a href="<?php echo esc_attr( $item->link ); ?>" target="_blank"><?php echo esc_html( $item->link ); ?></a></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Page Author:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->author ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Twitter Handle:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->twitter ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Facebook Page:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->facebook ); ?></td>
					</tr>
					<tr>
						<td class="kbucket-text-right"><strong><?php _e("Comment:",WPKB_TEXTDOMAIN) ?></strong></td>
						<td><?php echo esc_html( $item->description ); ?></td>
					</tr>
					<tr>
					<td colspan=2 style="border-bottom:2px solid pink; width:100%; min-width:700px; text-align:right;">
						<a href="javascript:void(0);" onclick="confirmdelete(<?php echo esc_js( $item->id_sug ); ?>)"><?php _e("Delete",WPKB_TEXTDOMAIN) ?></a>
					</td>
					</tr>
				</table>
				<?php endforeach ?>

		</div>
	</div><!-- /#country5 -->

	<div id="country6" class="tabcontent">
		<p><?php _e("Here you can add and change images to your links. You can also save the RSS feed for each channel and use “messaging apps” like Buffer and Socialoomph to schedule and distribute your curated links on social media. Visit our Forum for more info.",WPKB_TEXTDOMAIN) ?></p>
		<?php $categories = $this->get_subcategories( 0, '`id_cat`,`name`' );?>
		<?php $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2); ?>
		<a class="rss_link" href="#" data-link="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $uri_parts[0] . '?get_rss=y&'; ?>" target="_blank" style="display:none;vertical-align:middle;height:24px;">
			<img src="<?php echo WPKB_PLUGIN_URL; ?>/images/rss_icon.png">
		</a>
		<select name="category_id" id="category-dropdown">
			<option value=""><?php _e("Select Category",WPKB_TEXTDOMAIN) ?></option>
			<?php foreach ( $categories as $c ): ?>
				<option value="<?php echo esc_attr( $c['id_cat'] ); ?>"><?php echo esc_html( $c['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="subcategory_id" id="subcategory-dropdown">
			<option value=""><?php _e("Select Subcategory",WPKB_TEXTDOMAIN) ?></option>
		</select>

		<button id="button-save-sticky" class="button-secondary"><?php _e("Save changes",WPKB_TEXTDOMAIN) ?></button>
		<div id="kbuckets"></div>
	</div><!-- /#country6 -->
</div><!-- /.kbucket-tabcontent -->
