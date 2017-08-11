<?php
if ( !defined( 'ABSPATH' ) ) exit;

ob_start();
ini_set('memory_limit', '768M');
ini_set('allow_url_fopen', '1');
ini_set('max_execution_time', 1800); // 30 min

ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');

// Enable widget tags
include 'functions.php';
include 'inc/widget_tags.php';
require_once('inc/phpQuery/phpQuery.php');



// Define Kbucket textdomian
define( 'WPKB_TEXTDOMAIN', 'kbucket' );

// Define site URL
define( 'WPKB_SITE_URL', home_url() );

define('DEBUG_FILENAME', 'debug.500.html');

// Define Kbucket listing page URL
$kbucketPageID = kbucketPageID();
$post = get_post($kbucketPageID);
update_post_meta( $kbucketPageID, '_wp_page_template', 'template-kbucket.php' );
$slug = (isset($post->post_name)) ? $post->post_name : 'kbucket';
define( 'KBUCKET_URL', WPKB_SITE_URL . '/'.$slug );



define( 'WPKB_FILE',  str_replace('kb-init.php', 'KBucket.php', __FILE__));
// Define plugin path
define( 'WPKB_PATH',  plugin_dir_path( WPKB_FILE ) );

// Define plugin URL
define( 'WPKB_PLUGIN_URL', plugins_url() . '/kbucket' );


if(is_vc_activate()){
	// Include vc elements
	require_once( WPKB_PATH . '/vc-elements/vc-kbuckets.php' );
	require_once( WPKB_PATH . '/vc-elements/vc-widget-tags.php' );
}


add_filter( 'wpseo_canonical', '__return_false' );

// Set Kbucket install hook.
register_activation_hook( WPKB_FILE, 'register_activation_kbucket' );

// DEBUG
function write_to_file($name='test.txt',$text = ''){
	if(empty($text)) $text = ob_get_clean();
	file_put_contents(dirname(WPKB_FILE).'/'.$name, $text,FILE_APPEND);
}



/**
 * Run install scripts by plugin activation
 * Create Kbucket tables and insert default settings
 * Fired by register_activation_hook
 */
function register_activation_kbucket() {
	/** @var $wpdb object */
	global $wpdb;
	$charsetCollate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kbucket (
				id_kbucket varchar(100) NOT NULL,
				id_cat varchar(100) DEFAULT NULL,
				title text,
				description text NOT NULL,
				link text,
				author varchar(50) DEFAULT NULL,
				publisher varchar(255) DEFAULT NULL,
				author_alias varchar(50) DEFAULT NULL,
				facebook varchar(200) NOT NULL,
				twitter varchar(200) NOT NULL,
				pub_date varchar(100) DEFAULT NULL,
				add_date date DEFAULT NULL,
				status enum( '0','1' ) DEFAULT '1',
				image_url varchar(255) DEFAULT '',
				short_url varchar(255) DEFAULT '',
				post_id int(11) DEFAULT NULL,
				url_kbucket varchar(255),
			PRIMARY KEY  (id_kbucket),
			UNIQUE KEY id_kbucket_id_cat_index (id_kbucket,id_cat)
			) ENGINE=MyISAM $charsetCollate";
	$wpdb->query( $sql );



	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_files (
				id_file int(9) NOT NULL AUTO_INCREMENT,
				name varchar(100) DEFAULT NULL,
				comment text DEFAULT NULL,
				encoding text NOT NULL,
				publisher varchar(255) DEFAULT NULL,
				author varchar(50) DEFAULT NULL,
				keywords text DEFAULT NULL,
				file_added varchar(100) DEFAULT NULL,
				file_path varchar(255) DEFAULT NULL,
			PRIMARY KEY (id_file)
			) ENGINE=MyISAM $charsetCollate";
	$wpdb->query( $sql );



	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_category (
				id_cat varchar(100) NOT NULL,
				name varchar(50) DEFAULT NULL,
				description text,
				image text,
				parent_cat varchar(100) DEFAULT '0',
				level bigint(3) DEFAULT NULL,
				add_date date DEFAULT NULL,
				keywords varchar(255) DEFAULT NULL,
				status enum( '0','1' ) DEFAULT '1',
			PRIMARY KEY  (id_cat),
			UNIQUE KEY id_cat (id_cat,name,parent_cat)
			) ENGINE=MyISAM $charsetCollate";
	$wpdb->query( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}author_alias (
				author varchar(100) NOT NULL,
				alias varchar(100) DEFAULT NULL
			) ENGINE=MyISAM $charsetCollate";
	$wpdb->query( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_page_settings (
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

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_suggest (
				id_sug int(2) NOT NULL AUTO_INCREMENT,
				id_cat varchar(100) DEFAULT NULL,
				tittle varchar(50) DEFAULT NULL,
				description text NOT NULL,
				tags varchar(200) NOT NULL,
				add_date date DEFAULT NULL,
				author varchar(50) DEFAULT NULL,
				link text,
				twitter text,
				facebook text,
				status enum( '0','1' ) DEFAULT '1',
			PRIMARY KEY  (id_sug)
			) ENGINE=MyISAM $charsetCollate";
	$wpdb->query( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_tags (
				id_ktags int(3) NOT NULL AUTO_INCREMENT,
				id_kbucket varchar(100) DEFAULT NULL,
				id_tag int(3) DEFAULT NULL,
				status enum( '0','1' ) DEFAULT '1',
			PRIMARY KEY  (id_ktags),
			UNIQUE KEY id_kbucket_id_tag_index (id_kbucket,id_tag)
			) ENGINE=MyISAM  $charsetCollate;";
	$wpdb->query( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kb_tag_details (
				id_tag int(3) NOT NULL AUTO_INCREMENT,
				name varchar(50) DEFAULT NULL,
				status enum( '0','1' ) DEFAULT '1',
			PRIMARY KEY  (id_tag),
			UNIQUE KEY name_index (name)
			) ENGINE=MyISAM  $charsetCollate";
	$wpdb->query( $sql );

	$col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}kb_page_settings LIKE 'api_key'");
	if(!$col){
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings ADD COLUMN api_key varchar(255) DEFAULT NULL AFTER id_page");
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings ADD COLUMN api_trial varchar(2) DEFAULT NULL AFTER api_key");
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings ADD COLUMN kb_share_popap text NULL AFTER api_trial");
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings ADD COLUMN api_user_id varchar(255) NULL AFTER kb_share_popap");
	}else{
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings CHANGE COLUMN api_key api_key varchar(255) DEFAULT NULL AFTER id_page");
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings CHANGE COLUMN api_trial api_trial varchar(2) DEFAULT NULL AFTER api_key");
		$res = $wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings CHANGE COLUMN kb_share_popap kb_share_popap text NULL AFTER api_trial");
	}

	$wpdb->query('INSERT IGNORE	INTO '.$wpdb->prefix."kb_page_settings VALUES (1,'',1,'',0,'add_date','desc','9',50,50,50,'#F67B1F','#000','#F67B1F','#000','#F67B1F','#000','1','#1982D1','#F67B1F','#666666','#109CCB','1','',1,10,12,14,16,10,12,14,16,10,12,14,16,'o_3fanobfis0','R_d4112d6f69ad4bd8706925e11c892893','0')");


	$col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}kb_category LIKE 'keywords'");
	if(!$col){
		$wpdb->query("ALTER TABLE {$wpdb->prefix}kb_category ADD COLUMN keywords varchar(255) DEFAULT NULL AFTER add_date");
	}

	$col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}kb_page_settings LIKE 'vc'");
	if(!$col){
		$wpdb->query("ALTER TABLE {$wpdb->prefix}kb_page_settings ADD COLUMN vc enum( '0','1' ) DEFAULT '0' AFTER bitly_key");
	}

	$post = get_post(get_option('kb_setup_page_id','kbucket'));
	$kpage = (isset($post->post_name)) ? $post->post_name : 'kbucket';

	$kbucketPageExists = get_posts(
		array(
			'name' => $kpage,
			'post_type' => 'page',
		)
	);

	if(!$kbucketPageExists) {
		$kbucketPage = array(
			'post_name' => 'kbucket',
			'post_title' => 'Kbucket',
			'post_status' => 'publish',
			'post_type' => 'page',
		);

		$post_id = wp_insert_post( $kbucketPage );
		update_option('kb_setup_page_id',$post_id);
	}
	add_option( 'kbucket_data', 'Default', '', 'yes' );

}



// Add settings link on plugin page
add_filter( "plugin_action_links_".plugin_basename( WPKB_FILE ), function($links){
	$settings_link = '<a href="admin.php?page=KBucket">'.__("Settings",WPKB_TEXTDOMAIN).'</a>';
	array_unshift($links, $settings_link);
	return $links;
});



add_action('template_redirect','kb_remove_wpseo');
function kb_remove_wpseo(){
	if (!empty($_GET['share']) && class_exists('WPSEO_Frontend')) {
		global $wpseo_front;
		if(defined($wpseo_front)){
			remove_action('wp_head',array($wpseo_front,'head'),1);
		}
		else {
		  $wp_thing = WPSEO_Frontend::get_instance();
		  remove_action('wp_head',array($wp_thing,'head'),1);
		}
	}
}


/**
 * Set separate action hooks for admin and site
 * Fired by wp action hook: init
 */
// Start KBucket from init hook
add_action( 'init', 'action_set_kbucket' );
function action_set_kbucket(){
	/** @var $wp_rewrite object */
	global $wp_rewrite;

	$wp_rewrite->flush_rules();

	include_once "KBucket.php";

	//init_fatal_handler();

	// Get Kbucket instance
	$kbucket = KBucket::get_kbucket_instance();

	// Set AJAX action hook for share content box
	add_action( 'wp_ajax_nopriv_share_content', array( $kbucket, 'kbucket_action_ajax_share_content' ) );

	add_action('wp_ajax_nopriv_validate_suggest',array($kbucket,'kbucket_action_ajax_validate_suggest'));
	add_action('wp_ajax_validate_suggest',array($kbucket,'kbucket_action_ajax_validate_suggest'));

	// Set admin hooks for dashboard
	if ( is_admin() ) {
		add_action( 'admin_menu', array( $kbucket, 'kbucket_action_admin_menu' ),1 );
		add_action( 'admin_enqueue_scripts', array( $kbucket, 'kbucket_action_admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_save_sticky', array( $kbucket, 'kbucket_action_ajax_save_sticky' ) );
		add_action( 'wp_ajax_get_subcategories', array( $kbucket, 'kbucket_action_ajax_get_subcategories' ) );
		add_action( 'wp_ajax_get_kbuckets', array( $kbucket, 'kbucket_action_ajax_get_kbuckets' ) );
		add_action( 'wp_ajax_save_kbuket_image', array( $kbucket, 'kbucket_action_ajax_save_kbuket_image' ) );
		add_action( 'wp_ajax_share_content', array( $kbucket, 'kbucket_action_ajax_share_content' ) );
		add_filter( 'post_link', array( $kbucket, 'kbucket_filter_post_link' ), 10, 3 );

		add_action('wp_ajax_ajax_upload_kbucket',array($kbucket,'kbucket_ajax_upload_xml'));
		add_action('wp_ajax_ajax_parsing_kbucket',array($kbucket,'kbucket_ajax_parsing_xml'));
		add_action('wp_ajax_ajax_upload_img_kbucket',array($kbucket,'kbucket_ajax_upload_images'));
		add_action('wp_ajax_ajax_upload_img_kbucket_test',array($kbucket,'kbucket_ajax_upload_images_test'));

		add_action('wp_ajax_ajax_delsug',array($kbucket,'kb_ajax_delsug'));


		// Support colmn for upload page
		add_filter( 'manage_media_columns', array( $kbucket, 'kb_media_columns_orig_path') );
		add_action( 'manage_media_custom_column', array( $kbucket, 'kb_media_custom_column_orig_path'), 10, 2 );
		add_action( 'manage_media_custom_column', array( $kbucket, 'kb_media_custom_column_orig_size'), 10, 2 );
		add_action( 'admin_print_styles-upload.php', array( $kbucket, 'kb_orig_path_column_orig_path') );

		//add_action('wp_ajax_ajax_replace_post_kbucket', array( $kbucket, 'kb_replace_post_image_kbucket' ));

		// If sticky post has been updated, we can update kbucket too. Not in use in this version
		//add_action( 'edit_post', array( $kbucket, 'action_update_kbucket_from_sticky' ), 10, 3 );
		return true;
	}

	// Init Kbucket when wp object is initialized
	add_action( 'wp', 'action_init_kbucket' );

	// Set action for custom search query
	//add_action( 'pre_get_posts', array( $kbucket, 'customize_search_query' ) );

	return true;
}

/**
 * Kbucket initialization
 * Set action hooks,filters and templates
 * Fired by wp action hook: wp
 * @return bool
 */
function action_init_kbucket() {

	// Return Kbucket instance
	$kbucket = KBucket::get_kbucket_instance();

	//add_action('wp_head', array( $kbucket, 'addShareMetaKbucket' ), 0);

	// Set callback method for <head> content
	add_filter( 'wp_head', array( $kbucket, 'filter_wp_head' ), 1000 );

	if(!is_vc_activate()){
		// Set callback method for post content
		add_filter( 'the_content', array( $kbucket, 'filter_the_content' ) );
	}

	// Set callback method for footer content
	add_filter( 'wp_footer', array( $kbucket, 'filter_wp_footer' ) );

	// Evil behaviour. Remove canonical Meta tag from <head> to avoid problems with share scripts and other plugins
	remove_action( 'wp_head', 'rel_canonical' );

	// Set post link callback to point posts to Kbucket listing page with share window
	add_filter( 'post_link', array( $kbucket, 'kbucket_filter_post_link' ), 10, 2 );



	// Set page title filter
	add_filter( 'wp_title', array( $kbucket, 'filter_the_title' ), 100, 3 );
	add_filter( 'document_title_parts', array( $kbucket, 'filter_the_title_alt' ), 100 );

	// Set shortcode for "Suggest Page" Button
	add_shortcode( 'suggest-page', array( $kbucket, 'shortcode_render_suggest_page' ) );

	// Write settings and page data to Kbucket Page object
	$kbucket->init_kbucket_section();

	if ( $kbucket->isKbucketPage ) {

		// Detect mobile devices and set the property
		$kbucket->is_mobile();

		add_filter( 'jetpack_enable_opengraph', '__return_false', 99 );
	}

	// Set custom template
	add_action( 'page_template', array( $kbucket, 'action_set_page_template' ) );

	// Initialize scripts
	add_action( 'wp_enqueue_scripts', array( $kbucket, 'action_init_scripts' ) );

	// On remove image
	add_action( 'delete_attachment', array( $kbucket, 'kb_on_remove_attach') );



	return true;
}


add_action( 'wp_enqueue_scripts', 'kbucket_masonry_scripts_add' );
function kbucket_masonry_scripts_add(){
	wp_enqueue_script('kb_imagesloaded', WPKB_PLUGIN_URL . '/js/masonry/imagesloaded.pkgd.min.js', 'jquery', ( WP_DEBUG ) ? time() : null, true);
	wp_enqueue_script('kb_sp_masonry', WPKB_PLUGIN_URL . '/js/masonry/masonry.pkgd.min.js', 'jquery', ( WP_DEBUG ) ? time() : null, true);
}


// register_shutdown_function( "fatal_handler" );

// function fatal_handler() {
// 	$errfile = "unknown file";
// 	$errstr  = "shutdown";
// 	$errno   = E_CORE_ERROR;
// 	$errline = 0;

// 	init_fatal_handler();

// 	$error = error_get_last();

// 	if( $error !== NULL) {
// 		$errno   = $error["type"];
// 		$errfile = $error["file"];
// 		$errline = $error["line"];
// 		$errstr  = $error["message"];

// 		write_to_file(DEBUG_FILENAME,format_error( $errno, $errstr, $errfile, $errline ));
// 	}
// }

// function init_fatal_handler(){
// 	if(!file_exists(dirname(__FILE__).'/'.DEBUG_FILENAME)){
// 		write_to_file(DEBUG_FILENAME,"
// 		<style>
// 			.debug_table{
// 				border: 1px solid #bbb;
// 				margin: 30px auto;
// 				width: 100%;
// 				max-width: 940px;
// 				overflow:auto;
// 			}
// 			.debug_table table{
// 				width:100%;
// 				table-layout: fixed;
// 				border-collapse: collapse;
// 			}
// 			.debug_table table thead tr th:nth-of-type(1){
// 				width:10%;
// 			}
// 			.debug_table table thead tr th:nth-of-type(2){
// 				width:90%;
// 			}
// 			.debug_table table thead tr th + th,
// 			.debug_table table tbody tr th + td{
// 				border-left: 1px solid #bbb;
// 			}
// 			.debug_table table tbody tr th,
// 			.debug_table table tbody tr td,
// 			.debug_table table thead tr th{
// 				padding: 10px 15px;
// 			}
// 			.debug_table table thead tr th{
// 				background-color: #d5d5d5;
// 			}
// 			.debug_table table tbody tr th{
// 				width:10%;
// 				text-align:left;
// 			}
// 			.debug_table table tbody tr td{
// 				width: 90%;
// 				word-wrap: break-word;
// 				white-space: pre-wrap;
// 			}
// 			.debug_table table tbody tr:nth-child(even) {background: #d5d5d5}
// 			.debug_table table tbody tr:nth-child(odd) {background: #fff}
// 		</style>");
// 	}
// }



// function format_error( $errno, $errstr, $errfile, $errline ) {
// 	$trace = print_r( debug_backtrace( false ), true );

// 	$content = "
// 	<div class='debug_table'>
// 		<table>
// 			<thead><th>Item</th><th>Description</th></thead>
// 			<tbody>
// 				<tr>
// 					<th>Error:</th>
// 					<td>$errstr</td>
// 				</tr>
// 				<tr>
// 					<th>Err No.:</th>
// 					<td>$errno</td>
// 				</tr>
// 				<tr>
// 					<th>File:</th>
// 					<td>$errfile</td>
// 				</tr>
// 				<tr>
// 					<th>Line:</th>
// 					<td>$errline</td>
// 				</tr>
// 				<tr>
// 					<th>Trace:</th>
// 					<td><pre>$trace</pre></td>
// 				</tr>
// 			</tbody>
// 		</table>
// 	</div>";

// 	return $content;
// }

