<?php
if ( !defined( 'ABSPATH' ) ) exit;

function kbucket_url_origin( $s, $use_forwarded_host = false ){
	$ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
	$sp       = strtolower( $s['SERVER_PROTOCOL'] );
	$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
	$port     = $s['SERVER_PORT'];
	$port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
	$host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
	$host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
	return $protocol . '://' . $host;
}

function kbucket_utf8_urldecode($str) {
	return html_entity_decode(htmlspecialchars_decode(stripslashes(urldecode($str))), null, 'UTF-8');
}

// Hide template select in kbucket admin page
function kbucket_hide_template_add_custom_box(){
	global $post;
	$kb_page_id = kbucketPageID();
	if($kb_page_id && $post->ID == (int)$kb_page_id){
		add_meta_box(
			'pageparentdiv',
			__('Page Attributes'),
			'kbucket_hide_template_attributes_meta_box',
			'page',
			'side'
		);
	}
}
add_action('add_meta_boxes', 'kbucket_hide_template_add_custom_box');


function kbucket_hide_template_attributes_meta_box($post) {

	$post_type_object = get_post_type_object($post->post_type);
	if ( $post_type_object->hierarchical ) {
		$dropdown_args = array(
			'post_type'        => $post->post_type,
			'exclude_tree'     => $post->ID,
			'selected'         => $post->post_parent,
			'name'             => 'parent_id',
			'show_option_none' => __('(no parent)'),
			'sort_column'      => 'menu_order, post_title',
			'echo'             => 0,
		);

		$dropdown_args = apply_filters( 'page_attributes_dropdown_pages_args', $dropdown_args, $post );
		$pages = wp_dropdown_pages( $dropdown_args );
		if ( ! empty($pages) ) { ?>
			<p><strong><?php _e('Parent') ?></strong></p>
			<label class="screen-reader-text" for="parent_id"><?php _e('Parent') ?></label>
			<?php echo $pages; ?>
			<?php
		} // end empty pages check
	} // end hierarchical check.
	if ( 'page' == $post->post_type && 0 != count( get_page_templates() ) ) {
		$template = !empty($post->page_template) ? $post->page_template : false;
	} ?>
	<p><strong><?php _e('Order') ?></strong></p>
	<p><label class="screen-reader-text" for="menu_order"><?php _e('Order') ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr($post->menu_order) ?>" /></p>
	<p><?php if ( 'page' == $post->post_type ) _e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
	<?php
}



/**
 * @param $wp_rewrite
 */
function kbucket_rewrite_rules( $wp_rewrite ) {
	$kb_page_id = kbucketPageID();
	$post = get_post($kb_page_id);
	$slug = ($post && isset($post->post_name)) ? $post->post_name : 'kbucket';

	$kbucketRules = array(
		"^$slug/(.*)" => "index.php?pagename=$slug",
	);

	$wp_rewrite->rules = $kbucketRules + $wp_rewrite->rules;
}
// Add custom rewrite rules for SEO links
add_action( 'generate_rewrite_rules', 'kbucket_rewrite_rules' );



function kbucket_custom_excerpt_length() {
	return 2000;
}
add_filter( 'excerpt_length', 'kbucket_custom_excerpt_length', 999 );


function kbucket_strip_cdata($string){
	$matches = $matches1 =  array();

	preg_match_all('/&lt\;!\[CDATA\[(.*?)\]\]\&gt\;&gt\;/is', $string, $matches);
	if(isset($matches[0][0]) && isset($matches[1][0])) $new_string = str_replace($matches[0][0], $matches[1][0], $string);

	if(isset($new_string)){
		preg_match_all('/<!\[cdata\[(.*?)\]\]>/is', $new_string, $matches1);
		if(isset($matches1[0][0]) && isset($matches1[1][0])) $new_string = str_replace($matches1[0][0], $matches1[1][0], $new_string);
		return str_replace(']]>', '', $new_string);
	}else return $string;

}

function kbucketPageID(){
	$kpost = get_post(get_option('kb_setup_page_id','kbucket'));
	if(!isset($kpost->post_name)) $kpost = get_page_by_path('kbucket', OBJECT, 'page');
	if(!is_object($kpost)) return false;
	else return $kpost->ID;
}


// Add status label for kabucket page in admin list pages
add_filter( 'display_post_states','kbucket_post_state');
function kbucket_post_state( $states ) {
	global $post;
	$kpageID = kbucketPageID();

	if(!$kpageID) return $states;
	if ( $post->ID == $kpageID ) {
		$states[] = '<span class="custom_state kbucket">'.__('Kbucket page',WPKB_TEXTDOMAIN).'</span>';
	}
	return $states;
}



function getKBSettings(){
	global $wpdb;
	$val = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}kb_page_settings'");
	if(!$val){
		$charsetCollate = $wpdb->get_charset_collate();
		$sql = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . "kb_page_settings (
				id_page int(3) NOT NULL AUTO_INCREMENT,
				api_key varchar(255) DEFAULT NULL,
				api_trial varchar(2) DEFAULT NULL,
				kb_share_popap text NOT NULL,
				api_user_id varchar(255) DEFAULT NULL,
				sort_by varchar(10) DEFAULT NULL,
				sort_order varchar(10) DEFAULT NULL,
				no_listing_perpage int(4) DEFAULT NULL,
				main_no_tags int(4) DEFAULT NULL,
				author_no_tags int(4) DEFAULT NULL,
				related_no_tags int(4) DEFAULT NULL,
				main_tag_color1 varchar(10) DEFAULT NULL,
				main_tag_color2 varchar(10) DEFAULT NULL,
				author_tag_color1 varchar(10) DEFAULT NULL,
				author_tag_color2 varchar(10) DEFAULT NULL,
				related_tag_color1 varchar(10) DEFAULT NULL,
				related_tag_color2 varchar(10) DEFAULT NULL,
				author_tag_display enum( '0','1' ) DEFAULT '1',
				header_color varchar(10) DEFAULT NULL,
				author_color varchar(10) DEFAULT NULL,
				content_color varchar(10) DEFAULT NULL,
				tag_color varchar(10) DEFAULT NULL,
				status enum( '0','1' ) DEFAULT '1',
				page_title varchar(50) DEFAULT NULL,
				site_search int(1) DEFAULT NULL,
				m1 int(4) DEFAULT NULL,
				m2 int(4) DEFAULT NULL,
				m3 int(4) DEFAULT NULL,
				m4 int(4) DEFAULT NULL,
				a1 int(4) DEFAULT NULL,
				a2 int(4) DEFAULT NULL,
				a3 int(4) DEFAULT NULL,
				a4 int(4) DEFAULT NULL,
				r1 int(4) DEFAULT NULL,
				r2 int(4) DEFAULT NULL,
				r3 int(4) DEFAULT NULL,
				r4 int(4) DEFAULT NULL,
				bitly_username varchar(255) NOT NULL,
				bitly_key varchar(255) NOT NULL,
				vc enum( '0','1' ) DEFAULT '0',
			PRIMARY KEY  (id_page)
			) ENGINE=MyISAM $charsetCollate";
		$wpdb->query( $sql );
		$wpdb->query('INSERT IGNORE	INTO '.$wpdb->prefix."kb_page_settings VALUES (1,'',1,'',0,'add_date','desc','9',50,50,50,'#F67B1F','#000','#F67B1F','#000','#F67B1F','#000','1','#1982D1','#F67B1F','#666666','#109CCB','1','',1,10,12,14,16,10,12,14,16,10,12,14,16,'o_3fanobfis0','R_d4112d6f69ad4bd8706925e11c892893','0')");
	}else{
		$sql_sel = 'SELECT * FROM ' . $wpdb->prefix . "kb_page_settings	WHERE id_page='1'";
		return $wpdb->get_row( $sql_sel );
	}
}


/**
 * Remove plugin data if plugin has been deleted
 * Fired by register_deactivation_hook
 */
function register_deactivation_kbucket() {
	if ( ! current_user_can( 'activate_plugins' ) ) return;
	global $wpdb;

	$dropTables = array(
		$wpdb->prefix . 'kbucket',
		$wpdb->prefix . 'kb_category',
		$wpdb->prefix . 'kb_suggest',
		$wpdb->prefix . 'kb_tags',
		$wpdb->prefix . 'kb_tag_details',
		$wpdb->prefix . 'kb_page_settings',
	);

	$dropTablesStr = '' . implode( ',', $dropTables );
	$wpdb->query( "DROP TABLE $dropTablesStr" );
}


function is_vc_activate(){
	$settings = getKBSettings();
	if(!empty($settings->vc)){
		if((int)$settings->vc) return true;
		else return false;
	}else return false;
}