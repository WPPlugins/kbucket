<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php $kbucket = KBucket::get_kbucket_instance(); ?>
<div class="suggest_container">
	<div><label id="showmsg" style="color:red; width:100%; font-size:14px; text-align:center;">&nbsp;</label></div>
	<div id="kb-suggest-box">
		<form action="" method="post" id="suggform">
			<p><label><?php _e("Category",WPKB_TEXTDOMAIN) ?></label><span><?php $kbucket->render_categories_dropdown();?></span></p>
			<div class="">
				<div class="">
					<p><label for="stitle"><?php _e("Page Tittle",WPKB_TEXTDOMAIN) ?> <span class="red">*</span> :</label><span><input type="text" name="stitle"  id="stitle" value="" /></span></p>
					<p><label for="stags"><?php _e("Tags",WPKB_TEXTDOMAIN) ?> <span class="red">*</span> : </label><span><input type="text" name="stags" id="stags" value="" /></span></p>
					<p><label for="surl"><?php _e("Page URL",WPKB_TEXTDOMAIN) ?> <span class="red">*</span> :</label><span><input type="text" name="surl" id="surl" value="" /></span></p>
				</div>
				<div class="">
					<p><label for="sauthor"><?php _e("Author",WPKB_TEXTDOMAIN) ?> <span class="red">*</span> :</label><span><input type="text" name="sauthor" id="sauthor" value="" /></span></p>
					<p><label for="stwitter"><?php _e("Twitter Information page of author:",WPKB_TEXTDOMAIN) ?></label><span><input type="text" id="stwitter" name="stwitter" value="" /></span></p>
					<p><label for="sfacebook"><?php _e("Facebook information page of author:",WPKB_TEXTDOMAIN) ?></label><span><input type="text" id="sfacebook" name="sfacebook" value="" /></span></p>
				</div>
			</div>

			<p><label for="sdesc"><?php _e("Comment",WPKB_TEXTDOMAIN) ?> <span class="red">*</span> : </label><span><textarea name="sdesc" id="sdesc"></textarea></span></p>
			<p><span><button type="button" name="ssugg" value="<?php _e("Add New Suggest",WPKB_TEXTDOMAIN) ?>" onclick="validateSug()"><?php _e("Add New Suggest",WPKB_TEXTDOMAIN) ?></button></span></p>
		</form>
	</div>
</div>
