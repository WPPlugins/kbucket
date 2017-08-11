jQuery.noConflict();

function confirmdelete(x) {
	var r = confirm("Are you sure to want to delete?");
	if (r == true) {

		jQuery.ajax({
			type: 'POST',
			dataType: 'json',
			url: '/wp-admin/admin-ajax.php',
			data: {
				'action': 'ajax_delsug',
				'delsug': x,
			},
			beforeSend:function(){
				jQuery('#country5').find('table[data-id='+x+']').addClass('kb_for_remove');
			},
			complete:function(){
				jQuery('#country5').find('table[data-id='+x+']').removeClass('kb_for_remove');
			},
			success: function(data){
				jQuery('#country5').find('table[data-id='+x+']').animate(
					{'opacity': 0},
					400, function() {
					jQuery('#country5').find('table[data-id='+x+']').remove();
				});
			},
		});

		//self.location = ajaxObj.adminUrl + "?get_rss=KBucket&delsug=" + x;
	}
}
function kbucketRowCallback(row, data, index)
{
	var link = '<a href="' + ajaxObj.kbucketUrl + '/kb/' + data.id_kbucket + '">' + data.title + '</a>',
		isSticky = '<input type="checkbox" name="post_id" id="checkbox-sticky-' + data.id_kbucket  + '" class="checkbox-sticky"' + (data.post_id !== null ? ' checked="checked"' : '') + '/>';
	//alert(link);
	jQuery("td", row).eq(1).html(link);
	jQuery("td", row).eq(5).html(isSticky);
	if (data.image_url !== '') var img = data.image_url;
	else var img = '';

	var image = '<span id="kb-image-' + data.id_kbucket + '"><img src="' + img + '" alt="" style="width:80px;height:80px"/></span>';
	var button = '<button data-attch-id="'+data.post_id+'" data-id="'+data.id_kbucket+'" style="position:absolute;left:0;right:0;top:0;bottom:0;margin:auto;height:30px;width:30px;padding:0;" class="upload_image_button button"><i class="fa fa-camera"></i></button>';
	image = '<div style="position:relative">' + image + button + '</div>';

	jQuery("td", row).eq(4).html(image);
}

function uploadFile(source,params){
	var file_frame;
	if ( file_frame ) {
		file_frame.open();
		return;
	}
	if(!params){
		var params = {
			'uploader_title':'Title',
			'uploader_button_text': 'Select'
		}
	}
	file_frame = wp.media.frames.file_frame = wp.media({
		title: params.uploader_title,
		button: {text: params.uploader_button_text},
		multiple: false  // Set to true to allow multiple files to be selected
	});




	file_frame.on('open',function(){
		interval = setInterval(function(){
			if(jQuery(file_frame.el).find('input[type=file]').length){
				clearInterval(interval);
				jQuery(file_frame.el).find('input[type=file]').removeAttr('multiple')
			}
		},500);
	});


	file_frame.on( 'select', function() {
		attachment = file_frame.state().get('selection').first().toJSON();

		var selection = file_frame.state().get('selection');
		var wp_kb_media_iniciator = jQuery(window.wp_kb_media_iniciator);

		if(wp_kb_media_iniciator.hasClass('upload_image_button')){
			var post_id = wp_kb_media_iniciator.data('attch-id') ? wp_kb_media_iniciator.data('attch-id') : '';
			selection.each(function(attachment) {
				jQuery.ajax({
					type: 'POST',
					dataType: 'json',
					url: '/wp-admin/admin-ajax.php',
					data: {
						'action': 'ajax_replace_post_kbucket',
						'attachment_id': attachment.id,
						'orig_attachment_id': post_id
					},
					success: function(response){
						if(response.error){
							if(response.error) alert(response.error);
						}else{
							if(wp_kb_media_iniciator.parents('.__inner').length){
								if(attachment.attributes.sizes.thumbnail) wp_kb_media_iniciator.parents('.__inner').find('.kb-thumbnail').css('background-image', 'url(' + attachment.attributes.sizes.thumbnail.url + ')');
								else wp_kb_media_iniciator.parents('.__inner').find('.kb-thumbnail').css('background-image', 'url(' + attachment.attributes.sizes.full.url + ')');
							}else{
								if(attachment.attributes.sizes.thumbnail) wp_kb_media_iniciator.parents('.post-content__inner').find('.kb-thumbnail').css('background-image', 'url(' + attachment.attributes.sizes.thumbnail.url + ')');
								else wp_kb_media_iniciator.parents('.post-content__inner').find('.kb-thumbnail').css('background-image', 'url(' + attachment.attributes.sizes.full.url + ')');
							}
						}
					}
				});
			});

			if(source.parent().find('img').length){
				source.parent().find('img').attr('src',attachment.url);
			}else{
				source.parent().append('<img src="'+attachment.url+'" alt="." style="width:80px;height:80px" />');
			}
			console.log(attachment);
			updateThumbImage(attachment.url,source.attr('data-id'),attachment.id);
		}else{
			if(source.parent().find('img').length){
				source.parent().find('img').attr('src',attachment.url);
			}else{
				source.parent().append('<img src="'+attachment.url+'" alt="." style="width:80px;height:80px" />');
			}
			updateThumbImage(attachment.url,source.attr('data-id'),attachment.id);
		}



	});
	file_frame.open();
	window.wp_kb_media_iniciator = source[0];
}

function uploadFileCategory(source,params){
	var file_frame;
	if ( file_frame ) {
		file_frame.open();
		return;
	}
	if(!params){
		var params = {
			'uploader_title':'Title',
			'uploader_button_text': 'Select'
		}
	}
	file_frame = wp.media.frames.file_frame = wp.media({
		title: params.uploader_title,
		button: {text: params.uploader_button_text},
		multiple: false  // Set to true to allow multiple files to be selected
	});
	file_frame.on( 'select', function() {
		attachment = file_frame.state().get('selection').first().toJSON();
		source.parent().append('<div style="background-image:url('+attachment.sizes.thumbnail.url+');width:70px;height:70px;background-repeat:no-repeat;background-size:cover;position:absolute;right:0;top:0;" />');
		if(attachment.sizes.full) source.parent().find('input[type=hidden]').val(attachment.sizes.full.url);
		//else source.parent().find('input[type=hidden]').val(attachment.sizes.full.url);
	});
	file_frame.open();
}

function updateThumbImage(url,id,attach_id){
	jQuery.ajax({
		url: ajaxurl,
		type: "POST",
		data: {
			action : "save_kbuket_image",
			url : url,
			id_kbucket : id,
			attach_id : attach_id
		},
		success: function (res) {

		}
	});
}

jQuery(document).ready(
	function () {

		jQuery('.upload_image_cat').on('click',function(event) {
			event.preventDefault();
			uploadFileCategory(jQuery(this));
		});


		jQuery("#form").validate();

		jQuery(".colorpickerField").ColorPicker({
			onSubmit: function (hsb, hex, rgb, el)
			{
				jQuery(el).val('#' + hex);
				jQuery(el).ColorPickerHide();
			},
			onBeforeShow:
				function () {
					jQuery(this).ColorPickerSetColor(this.value);
				}
		});

		if (jQuery('#kBucketTabs').length > 0) {
			var countries = new ddtabcontent("kBucketTabs");
			countries.setpersist(true);
			countries.setselectedClassTarget("link"); //"link" or "linkparent"
			countries.init();
		}

		jQuery("#category-dropdown:first-child").attr('selected', 'selected');
		jQuery("#subcategory-dropdown:first-child").attr('selected', 'selected');
		jQuery("#subcategory-dropdown").attr('disabled', 'disabled');

		jQuery("#category-dropdown").on("change", function(){
			changeRssCat();
			var categoryId = jQuery(this).val();
			if (categoryId == '') {
				jQuery('.rss_link').hide();
				return false;
			}
			jQuery("#subcategory-dropdown").load(ajaxurl, {"action" : "get_subcategories", "category_id" : categoryId},function(response){
				jQuery("#subcategory-dropdown").removeAttr('disabled');
				if(jQuery("#subcategory-dropdown").val() == '') jQuery('.rss_link').attr('data-cat',categoryId).hide();
				else{
					jQuery('.rss_link').attr('data-cat',categoryId);
					changeRssCat();
				}
			});
		});

		function changeRssCat(){
			var cat = (jQuery('.rss_link').attr('data-cat')) ? 'cat='+jQuery('.rss_link').attr('data-cat') : '';
			var subcat = (jQuery('.rss_link').attr('data-subcat')) ? '&subcat='+jQuery('.rss_link').attr('data-subcat') : '';
			jQuery('.rss_link').attr('href',jQuery('.rss_link').attr('data-link')+cat+subcat);
		}

		jQuery("#subcategory-dropdown").on("change", function(){
			changeRssCat();
			var categoryId = jQuery(this).val();
			if (categoryId == '') {
				jQuery('.rss_link').hide();
				return false;
			}

			jQuery("#kbuckets").html('<table cellpadding="0" cellspacing="0" border="0" class="display" id="kbuckets-list"></table>');

			var options =  {
				"dataType" : "json",
				"ajax": {
					"type" : "POST",
					"url" : ajaxurl,
					"data" : {action : "get_kbuckets", category_id : categoryId}
				},
				"columns":[
					{"mData":"name","sTitle":"Category"},
					{"mData":"title","sTitle":"Title"},
					{"mData":"author","sTitle":"Author"},
					{"mData":"add_date","sTitle":"Published at"},
					{"mData":"image_url","sTitle":"Image"},
					{"mData":"post_id","sTitle":"Sticky"}],
				"bDestroy":true,
				"bFilter" : false,
				"pageLength":20,
				//"bLengthChange": false,
				"createdRow":function(row, data, index) {
					kbucketRowCallback(row, data, index);
				},
				"initComplete": function () {
					jQuery('.rss_link').attr('data-subcat',categoryId).show().css('display','inline-block');

					changeRssCat();

					var  kbOn = [],
						kbOff = [];

					jQuery('.display.dataTable').find('tr td:nth-child(5)').each(function(index, el) {
						var id_kbucket = jQuery(this).next('td').find('input').attr('id').replace('checkbox-sticky-', '');
						//jQuery(this).css('position','relative').append('<button data-id="'+id_kbucket+'" style="position:absolute;left:0;right:0;top:0;bottom:0;margin:auto;height:30px;width:30px;padding:0;" class="upload_image_button button"><i class="fa fa-camera"></i></button>');
					});

					jQuery('body').on('click','.upload_image_button',function(event) {
						event.preventDefault();
						uploadFile(jQuery(this));
					});

					jQuery("#button-save-sticky").show();

					jQuery('.checkbox-sticky').on("click", function () {
						var id = jQuery(this).attr('id').replace('checkbox-sticky-', ''),
							state = jQuery(this).is(':checked');
						if (state == true) {
							valueIndex = jQuery.inArray( id, kbOff );
							if ( valueIndex !== '-1' ) {
								//alert(valueIndex );
								kbOff.splice(kbOff.indexOf(valueIndex), 1);
							}
							kbOn.push(id);
						} else {
							valueIndex = jQuery.inArray( id, kbOn );
							if ( valueIndex !== '-1') {
								kbOn.splice(kbOn.indexOf(valueIndex), 1);
							}
							kbOff.push(id);
						}
					});

					jQuery("#button-save-sticky").on("click", function () {

						jQuery("#messages").empty();

						if (kbOn.length == 0 && kbOff.length == 0) {
							return;
						}

						jQuery.ajax({
							url: ajaxurl,
							type: "POST",
							data: {action : "save_sticky", kb_on : kbOn, kb_off : kbOff},
							success: function (res) {
								kbOn = [];
								kbOff = [];
								stickySavedResponse(res);
								if (typeof res.images !== undefined) {
									jQuery.each(res.images, function(kbId, imgUrl){
										jQuery("#kb-image-" + kbId).html('<img src="' + imgUrl + '" alt="." style="width:80px;height:80px"/>');
									});
								}
							}
						});
					});
				}
			};

			var dataTable = jQuery('#kbuckets-list').DataTable(options);
		});
});

function stickySavedResponse(res)
{
	var msg = (res.status == 'ok' ? 'Changes has been saved successfully!' : 'There was an error.'),
		msgClass = (res.status == 'ok' ? 'updated-success' : 'updated-error');

	jQuery('#messages').html('<span class="' + msgClass + '">' + msg + '</span>');

}
