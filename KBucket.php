<?php
/*
Plugin Name: KBucket
Plugin URI: https://optimalaccess.com/download/
Description: KBucket Curated Pages
Version: 2.5.2
Author: Optimal Access Inc.
Author URI: https://optimalaccess.com/
License: KBucket
*/
//
if ( !defined( 'ABSPATH' ) ) exit;

ini_set('memory_limit', '768M');
ini_set('allow_url_fopen', '1');
ini_set('max_execution_time', 1800); // 30 min

ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');


/**
 * Class KBucket
 */
class KBucket{
	public $template = '';
	public $isKbucketPage = false;

	/** @var $wpdb object */
	public $wpdb;

	public static $instance = null;

	/** @var $kbPage object */
	public $kbPage;

	/** @var $kbucket object */
	private $kbucket = false;

	/** @var $category object */
	private $category = false;

	private $isMobile = false;
	private $kbucketCurrentUrl = '';
	private $metaOgUrl = '';
	private $addthisId = 'ra-54aacc3842e62476'; //@TODO avoid harcoding - should be built according to settings

	private $disqusConfig = array(
		'shortname' => '',
		'title' => 'Kbuckets',
		'url' => KBUCKET_URL,
		'id' => 0,
	);

	// List of available share buttons in Addthis toolbox
	private $addthisShareButtons = array(
		'facebook' => '',
		'twitter' => '',
		'digg' => '',
		'email' => '',
		'pinterest_share' => '',
		'stumbleupon' => '',
		'linkedin' => '',
		'google_plusone' => array( 'g:plusone:count' => 'false', 'g:plusone:size' => 'medium' ),
		'more' => 'compact'
	);


	/**
	 * Class Constructor
	 */
	public function __construct(){
		global $wpdb;

		// Encapsulate wpdb object to avoid global variables
		$this->wpdb = $wpdb;

		// Kbucket data object
		$this->kbPage = new stdClass();


		$settings = getKBSettings();
		// Read Plugin settings from database
		$this->kbPage->settings = $this->wpdb->get_row( 'SELECT * FROM `' . $this->wpdb->prefix . 'kb_page_settings`' );

		// Set custom template property for section
		// @TODO - templates are not needed anymore. Should be changed to ajax actions
		if ( ! empty( $_GET['kbt'] ) ) {
			$template = esc_attr( $_GET['kbt'] );
			$this->template = substr( $template, 0, 10 ) . '.php';
		}

		// Set Disqus shortname if "Disqus Comment System" plugin detected
		if ( defined( 'DISQUS_DOMAIN' ) ) {
			$this->disqusConfig['shortname'] = strtolower( get_option( 'disqus_forum_url' ) );
		}

		$this->kbucket_rss_action_hook();
		$this->kbucket_rss_category_action_hook();
		$this->kbucket_rss_suggest_action_hook();


		return true;
	}


	public function kb_ajax_delsug(){
		$delsug = (int)$_POST['delsug'];

		$sqldel = $this->wpdb->prepare("DELETE FROM {$this->wpdb->prefix}kb_suggest WHERE id_sug=%s", $delsug);
		$this->wpdb->query( $sqldel );
	}


	public function kbucket_ajax_upload_xml(){
		$apikey = $this->getAPIKey();
		if(!$apikey){
			die(json_encode(array(
				'error' => 'You need to upgrade your API key! Please, go to http://optimalaccess.com/register/'
			)));
		}


		if ($_FILES['file']['error']) {
			die(json_encode(array(
				'error' => 'Cannot upload the file',
				'extend' => $_FILES['error']
			)));;
		}

		$sourcePath = $_FILES['file']['tmp_name'];
		$targetPath = WPKB_PATH.'uploads/'.str_replace(' ', '_', $_FILES['file']['name']);
		if(!file_exists(WPKB_PATH.'uploads/')) mkdir(WPKB_PATH.'uploads/',0755,true);
		move_uploaded_file($sourcePath,$targetPath) ;
		die(json_encode(array(
			'success' => WPKB_PLUGIN_URL.'/uploads/'.str_replace(' ', '_', $_FILES['file']['name']),
			'file' => str_replace(' ', '_', $_FILES['file']['name'])
		)));
	}


	public function kbucket_get_file_param(){
		$sql = "SELECT * FROM {$this->wpdb->prefix}kb_files LIMIT 0,1";
		return $this->wpdb->get_results($sql, ARRAY_A);
	}

	public function kbucket_ajax_parsing_xml(){
		ob_start();

		$uploadFile = $_POST['file'];

		//libxml_use_internal_errors( true );

		if(file_exists(dirname(__FILE__).'/'.DEBUG_FILENAME)){
			@unlink(dirname(__FILE__).'/'.DEBUG_FILENAME);
		}

		$kbucketPageID = kbucketPageID();
		$post = get_post($kbucketPageID);
		$kbslug = (isset($post->post_name)) ? $post->post_name : 'kbucket';

		$categories = $this->getCatByName();

		$truncateTables = array(
			$this->wpdb->prefix . 'kb_category',
			$this->wpdb->prefix . 'kbucket',
			$this->wpdb->prefix . 'kb_tags',
			$this->wpdb->prefix . 'kb_tag_details',
			$this->wpdb->prefix . 'author_alias',
			$this->wpdb->prefix . 'kb_files'
		);

		foreach ( $truncateTables as $table ) {
			$this->wpdb->query( "TRUNCATE `$table`" );
		}

		$pagesUrls = array();

		$kbucketsSql = 'INSERT IGNORE INTO `'.$this->wpdb->prefix.'kbucket`	(`id_kbucket`,`id_cat`,`title`,`description`,`link`,`author`,`publisher`,`author_alias`,`twitter`,`pub_date`,`add_date`,`image_url`,`url_kbucket`) VALUES ';

		$kbucketsPlaceholders = array();
		$kbucketsValues = array();

		$categoriesSql = 'INSERT IGNORE INTO `'.$this->wpdb->prefix.'kb_category`(`id_cat`,`name`,`parent_cat`,`level`,`add_date`,`keywords`) VALUES ';
		$categoriesPlaceholders = array();
		$categoriesValues = array();

		$tagsDetailsSql = 'INSERT IGNORE INTO `'.$this->wpdb->prefix.'kb_tag_details`(`name`) VALUES ';
		$tagsDetailsPlaceholders = array();
		$tagsDetailsValues = array();
		$tags = array();



		$XDOMContent = @file_get_contents($uploadFile);
		$XDOMContent = phpQuery::newDocumentHTML($XDOMContent);

		//$XDOMContent = file_get_html($uploadFile);


		if(!$XDOMContent) die(json_encode(array('error'=>'You upload file over 6Mb')));


		$fileArr = $XDOMContent->find('file');
		foreach ($fileArr as $fileKey => $file) {
			$library = pq($file);

			$attrFile_Name = $library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="about"]')->html();
			if(empty($attrFile_Name)) $attrFile_Name = '';

			$commentFileNode = $library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="comment"]')->html();
			if(empty($commentFileNode)) $commentFileNode = '';

			$attrFile_UID = $library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="encoding"]')->html();
			if(empty($attrFile_UID)) $attrFile_UID = '';

			$attrFile_publisher = trim($library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="publisher"]')->html());
			if(empty($attrFile_publisher)) $attrFile_publisher = '';

			$attrFile_author = trim($library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="author"]')->html());
			if(empty($attrFile_author)) $attrFile_author = '';

			$attrFile_tooltip = $library->find('div[itemtype="http://schema.org/WebPage"]>span[itemprop="keywords"]')->html();
			if(empty($attrFile_tooltip)) $attrFile_tooltip = '';
		}
		$file_added = time();
		$kbucket_filesSQL = "
			INSERT IGNORE
			INTO {$this->wpdb->prefix}kb_files(
				name,
				comment,
				encoding,
				publisher,
				author,
				keywords,
				file_added,
				file_path
			) VALUES (
				'{$attrFile_Name}',
				'{$commentFileNode}',
				'{$attrFile_UID}',
				'{$attrFile_publisher}',
				'{$attrFile_author}',
				'{$attrFile_tooltip}',
				'{$file_added}',
				'{$uploadFile}'
			)";
		$this->wpdb->query($kbucket_filesSQL);


		$bookArr = $fileArr->find('group');



		// Iterate all book elements one by one
		foreach ( $bookArr as $bookKey => $book ) {
			$qbook = pq($book);
			$categoriesPlaceholders[] = '(%s,%s,%s,%d,%s,%s)';


			$attrBook_Name = $qbook->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="about"]')->html();
			$attrBook_UID = $qbook->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="encoding"]')->html();
			$attrBookKeywords = $qbook->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="keywords"]')->html();

			if(!isset($categories[$attrBook_Name])){
				$categoriesValues[] = $attrBook_UID;
				$categoriesValues[] = $attrBook_Name;
				$categoriesValues[] = '';
				$categoriesValues[] = 1;
				$categoriesValues[] = date( 'Y' ) . '-' . date( 'm' ) . '-' . date( 'd' );
				$categoriesValues[] = $attrBookKeywords;
			}

			$parentCategoryId = wp_create_category( $attrBook_Name );


			$chapterArr = $qbook->find('page');



			foreach ( $chapterArr as $chapterKey => $chapter ) {
				$qchapter = pq($chapter);
				$count = 0;

				$categoriesPlaceholders[] = '(%s,%s,%s,%d,%s,%s)';

				$attrChapter_Name = $qchapter->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="about"]')->html();
				$attrChapter_UID = $qchapter->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="encoding"]')->html();
				$attrChapterKeywords = $qchapter->find('&>div[itemtype="http://schema.org/WebPageElement"]>span[itemprop="keywords"]')->html();

				// Add subcategories values
				if(!isset($categories[$attrBook_Name])){
					$categoriesValues[] = $attrChapter_UID;
					$categoriesValues[] = $attrChapter_Name;
					$categoriesValues[] = $attrBook_UID;
					$categoriesValues[] = 2;
					$categoriesValues[] = date( 'Y' ) . '-' . date( 'm' ) . '-' . date( 'd' );
					$categoriesValues[] = $attrChapterKeywords;
				}

				wp_create_category( $attrChapter_Name, $parentCategoryId );

				$pageArr = $qchapter->find('div[itemtype="http://schema.org/Article"]');

				foreach ( $pageArr as $pageKey => $page ) {
					$qpage = pq($page);

					$attrPage_tooltip = trim($qpage->find('span[itemprop="keywords"]')->html());
					$attrPage_Name = $qpage->find('h2[itemprop="headline"]')->html();
					$attrPage_UID = strtoupper(md5($attrPage_Name));

					$tagArr = explode( ',', $attrPage_tooltip );

					// Iterate tags
					foreach ( $tagArr as $tag ) {

						$tag = strtolower(trim($tag));

						$tagsDetailsPlaceholders[] = '(%s)';
						$tagsDetailsValues[] = $tag;

						if (!isset($tags[$tag])){
							$tags[$tag] = array();
						}

						$tags[$tag][] = $attrPage_UID;
					}

					unset($tagArr);

					$count++;
					$trial = $this->isTrialKey();
					if($trial && $count >= 11) continue;


					$attrPage_author = trim($qpage->find('span[itemprop="name"]')->html());
					$attrPage_publisher = trim($qpage->find('meta[itemprop="name"]')->attr('content'));
					$attrPage_pub_date = $qpage->find('meta[itemprop="datePublished"]')->attr('content');
					$commentPageNode = $qpage->find('span[itemprop="description"]')->html();
					$urlPageNode = $qpage->find('meta[itemprop="mainEntityOfPage"]')->attr('itemid');
					$attrTwitterName = trim($qpage->find('meta[name="twitter:site"]')->attr('content'));

					$attrImageUrl = $qpage->find('meta[itemprop="url"]')->attr('content');


					$pagesUrls[] = array(
						'url' => $urlPageNode,
						'name' => $attrPage_Name,
					);

					$kbucketsPlaceholders [] = '(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)';

					$kbucketsValues[] = $attrPage_UID;
					$kbucketsValues[] = $attrChapter_UID;
					$kbucketsValues[] = $attrPage_Name;
					$kbucketsValues[] = $commentPageNode;
					$kbucketsValues[] = $urlPageNode;
					$kbucketsValues[] = $attrPage_author;
					$kbucketsValues[] = $attrPage_publisher;
					$kbucketsValues[] = '';
					$kbucketsValues[] = $attrTwitterName; // TWITTER
					$kbucketsValues[] = date("Y-m-d H:i:s", strtotime($attrPage_pub_date));
					$kbucketsValues[] = date( 'Y' ) . '-' . date( 'm' ) . '-' . date( 'd' );
					$kbucketsValues[] = '';

					$kb_arr_image[$attrPage_UID]['image'] = $attrImageUrl;
					$kb_arr_image[$attrPage_UID]['name'] = $attrPage_Name;

					$kbucketUrl = $kbslug.'/'.sanitize_title_with_dashes( $attrBook_Name ).'/'.sanitize_title_with_dashes($attrChapter_Name).'/kb/'.$attrPage_UID;

					$kbucketsValues[] = $kbucketUrl;


				}
				unset($pageArr);
			}
			unset($chapterArr);
		} //end book

		unset($bookArr);

		$resMsg = '';

		$categoriesSql .= implode( ',', $categoriesPlaceholders );
		$categoriesSql = $this->wpdb->prepare( $categoriesSql, $categoriesValues );


		if ( $categoriesSql ) {
			$resMsg .= 'Inserted Categories: '.$this->wpdb->query( $categoriesSql ).'<br/>';
		}


		$kbucketsSql .= implode( ',', $kbucketsPlaceholders );
		$kbucketsSql = $this->wpdb->prepare( $kbucketsSql, $kbucketsValues );

		if ( $kbucketsSql ) {
			$resMsg .= 'Inserted Kbuckets: '.$this->wpdb->query( $kbucketsSql ).'<br/>';
		}

		$tagsDetailsSql .= implode( ',', $tagsDetailsPlaceholders );
		$tagsDetailsSql = $this->wpdb->prepare( $tagsDetailsSql, $tagsDetailsValues );
		if ( $tagsDetailsSql ) {
			$resMsg .= 'Inserted Tags Details: ' . $this->wpdb->query( $tagsDetailsSql ) . '<br/>';

			$tagsSql = 'INSERT IGNORE INTO `'.$this->wpdb->prefix.'kb_tags`	(`id_kbucket`,`id_tag`) VALUES';

			$tagsPlaceholders = array();
			$tagsValues = array();

			$savedTagsDetails = $this->wpdb->get_results('SELECT `id_tag`,`name` FROM `' . $this->wpdb->prefix . 'kb_tag_details`');

			// Add values for category tags
			foreach ( $savedTagsDetails as $tag ) {
				if ( isset( $tags[ $tag->name ] ) ) {
					foreach ( $tags[ $tag->name ] as $kbucketId ) {
						$tagsPlaceholders[] = '(%s,%d)';
						$tagsValues[] = $kbucketId;
						$tagsValues[] = $tag->id_tag;
					}
				}
			}

			if ( ! empty( $tagsValues ) ) {

				$tagsSql .= implode( ',', $tagsPlaceholders );
				$tagsSql = $this->wpdb->prepare( $tagsSql, $tagsValues );
				if ( $tagsSql ) {
					$resMsg .= 'Inserted Tags: ' . $this->wpdb->query( $tagsSql ) . '<br/>';
				}
			}
		}

		// NEW CATEGORIES
		$new_categories = $this->getCatByName(true);
		foreach ($new_categories as $cat_name => $new_category) {
			if(isset($categories[$cat_name])){
				$cat_data = $categories[$cat_name];
				$sql = "UPDATE {$this->wpdb->prefix}kb_category SET description=%s,image=%s WHERE name=%s";
				$sql = $this->wpdb->prepare($sql,$cat_data['description'],$cat_data['image'],$cat_name);
				$result = $this->wpdb->query($sql);
			}
		}

		$resMsg .= 'KBuckets uploaded successfully!<br/>';
		$trial = $this->isTrialKey();
		if($trial) $resMsg .= 'You have entered the trial key. Your kbuckets limit is 10 in every category<br/>';

		$output = '';


		//file_put_contents(dirname(__FILE__).'/kb_images.php', '<?php $kb_arr_image = ' . var_export($kb_arr_image,true) . ';');

		die(json_encode(array(
			'debug' => $output,
			'success' => $resMsg,
			'kb_arr_image' => $kb_arr_image,
			'cnt' => count($kb_arr_image)
		)));

	}


	public function kb_replace_post_image_kbucket(){
		$orig_attachment_id = esc_attr($_POST['orig_attachment_id']);
		$attachment_id = (int)$_POST['attachment_id'];

		if(!empty($orig_attachment_id) && !empty($attachment_id)) update_post_meta( $orig_attachment_id, 'kb_custom_photo', $attachment_id );

		// $post_sql = $this->wpdb->prepare("SELECT post_id FROM {$this->wpdb->prefix}kbucket WHERE id_kbucket=%s", $post_id);

		// $post = $this->wpdb->get_results($post_sql, ARRAY_A);

		// if(empty($post)) return;

		// $sql = $this->wpdb->prepare("UPDATE {$this->wpdb->prefix}kbucket SET post_id=%d WHERE id_kbucket=%s", $attachment_id, $post_id);
		// $this->wpdb->query($sql);
	}


	/**
	 * Returns the size of a file without downloading it, or -1 if the file
	 * size could not be determined.
	 *
	 * @param $url - The location of the remote file to download. Cannot
	 * be null or empty.
	 *
	 * @return The size of the file referenced by $url, or -1 if the size
	 * could not be determined.
	 */
	public function kb_curl_get_file_size( $url ) {
		// Assume failure.
		$result = -1;

		$curl = curl_init( $url );

		// Issue a HEAD request and follow any redirects.
		curl_setopt( $curl, CURLOPT_NOBODY, true );
		curl_setopt( $curl, CURLOPT_HEADER, true );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
		//curl_setopt( $curl, CURLOPT_USERAGENT, get_user_agent_string() );

		$data = curl_exec( $curl );
		curl_close( $curl );

		if( $data ) {
			$content_length = "unknown";
			$status = "unknown";

			if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
				$status = (int)$matches[1];
			}

			if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
				$content_length = (int)$matches[1];
			}

			// http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
			if( $status == 200 || ($status > 300 && $status <= 308) ) {
				$result = $content_length;
			}
		}
		return $result;
	}

	/**
	* Filter the Media list table columns to add a File Size column.
	*
	* @param array $posts_columns Existing array of columns displayed in the Media list table.
	* @return array Amended array of columns to be displayed in the Media list table.
	*/
	public function kb_media_columns_orig_path( $posts_columns ) {
		$posts_columns['orig_path'] = __( 'Original Path', WPKB_TEXTDOMAIN );
		$posts_columns['orig_size'] = __( 'Original Size', WPKB_TEXTDOMAIN );

		return $posts_columns;
	}

	/**
	 * Display File Size custom column in the Media list table.
	 *
	 * @param string $column_name Name of the custom column.
	 * @param int    $post_id Current Attachment ID.
	 */
	function kb_media_custom_column_orig_path( $column_name, $post_id ) {
		if ( 'orig_path' !== $column_name) {
			return;
		}
		//$bytes = filesize( get_attached_file( $post_id ) );
		//echo size_format( $bytes, 2 );
		$orig_path = get_post_meta($post_id, 'wp_attachment_orig_url', true);
		echo '<a href="'.$orig_path.'" title="'.$orig_path.'">'.basename($orig_path).'</a>';

	}

	function kb_media_custom_column_orig_size( $column_name, $post_id ) {
		if ('orig_size' !== $column_name) {
			return;
		}

		$orig_size = get_post_meta($post_id, 'wp_attachment_size', true);
		echo size_format( $orig_size, 2 );
	}

	/**
	 * Adjust File Size column on Media Library page in WP admin
	 */
	function kb_orig_path_column_orig_path() {
		echo
		'<style>
			.fixed .column-orig_path,
			.fixed .column-orig_size {
				width: 10%;
			}
		</style>';
	}



	public function kbucket_action_ajax_save_kbuket_image(){
		$image_url = esc_sql( $_POST['url'] );
		$id_kbucket = esc_sql( $_POST['id_kbucket'] );
		$attach_id = esc_sql( $_POST['attach_id'] );

		$sql = "UPDATE {$this->wpdb->prefix}kbucket SET image_url=%s WHERE id_kbucket=%s";
		$query = $this->wpdb->prepare( $sql, $image_url, $id_kbucket );
		$this->wpdb->query( $query );

		// $sql = "UPDATE {$this->wpdb->prefix}kbucket SET post_id=%s WHERE id_kbucket=%s";
		// $query = $this->wpdb->prepare( $sql, $attach_id, $id_kbucket );
		// $this->wpdb->query( $query );

		update_post_meta( $attach_id, 'kb_custom_photo', $id_kbucket );

	}


	public function kbucket_ajax_upload_images_test(){
		if(empty($kb_arr_image)) $kb_arr_image = $_POST['images'];

		$inc = $alt_inc = 0;
		$files = $empty = $ex_files = array();


		foreach ($kb_arr_image as $post_arr) {
			$image = !empty($post_arr['image']) ? $post_arr['image'] : '';
			$name = !empty($post_arr['name']) ? $post_arr['name'] : '';
			$uid = $post_arr['key'];

			$res = $this->kbucket_update_image($uid, $image, $name);
			if($res){
				$files[$uid] = $image;
				$inc++;
			}elseif(empty($image)) $empty[$uid] = $name;
			else $ex_files[$uid] = $image;

			$alt_inc++;
		}

		die(json_encode(array(
			'cnt' => count($kb_arr_image),
			'success' => true,
			's_uploaded' => $inc,
			'e_uploaded' => count($ex_files),
			'm_uploaded' => count($empty),
		)));


	}



	public function kbucket_ajax_upload_images(){
		if(empty($kb_arr_image)) $kb_arr_image = $_POST['images'];

		$inc = $alt_inc = 0;
		$files = $empty = $ex_files = array();

		foreach ($kb_arr_image as $uid => $post_arr) {
			$image = !empty($post_arr['image']) ? $post_arr['image'] : '';
			$name = !empty($post_arr['name']) ? $post_arr['name'] : '';



			$res = $this->kbucket_update_image($uid, $image, $name);
			if($res){
				$files[$uid] = $image;
				$inc++;
			}elseif(empty($image)) $empty[$uid] = $name;
			else $ex_files[$uid] = $image;

			$alt_inc++;
		}

		die(json_encode(array(
			'cnt' => count($kb_arr_image),
			'success' => '',
			'uploaded' => $inc.' from '.$alt_inc,
			'files' => $files,
			'err_files' => $ex_files,
			'empty' => $empty
		)));
	}

	function kbucket_update_image($UID, $image_url,$attrName){
		if(!empty($image_url)){
			$attrImageInnerID = $this->addFeaturedImg($image_url,$attrName);
			if($attrImageInnerID) $attrImageInnerUrl = wp_get_attachment_url($attrImageInnerID);
			else $attrImageInnerUrl = '';

			if(!empty($attrImageInnerUrl)){
				$sql = $this->wpdb->prepare( "SELECT post_id FROM {$this->wpdb->prefix}postmeta WHERE meta_key=%s AND meta_value=%s", 'kb_custom_photo', $UID );
				$post_id = $this->wpdb->get_results($sql, ARRAY_A);

				if(empty($post_id[0]['post_id'])){
					$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET image_url=%s WHERE id_kbucket=%s", $attrImageInnerUrl,$UID );
					$res = $this->wpdb->query($sql);
					if(!empty($attrImageInnerID)){
						$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET post_id=%s WHERE id_kbucket=%s", $attrImageInnerID,$UID );
						$res = $this->wpdb->query($sql);
					}
				}else{
					$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET image_url=%s WHERE id_kbucket=%s", wp_get_attachment_url($post_id[0]['post_id']), $UID );
					$this->wpdb->query($sql);

					$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET post_id=%s WHERE id_kbucket=%s", $post_id[0]['post_id'], $UID );
					$this->wpdb->query($sql);
				}
				return true;
			}else{
				$sql = $this->wpdb->prepare( "SELECT post_id FROM {$this->wpdb->prefix}postmeta WHERE meta_key=%s AND meta_value=%s", 'kb_custom_photo', $UID );
				$post_id = $this->wpdb->get_results($sql, ARRAY_A);

				if(!empty($post_id[0]['post_id'])){
					$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET image_url=%s WHERE id_kbucket=%s", wp_get_attachment_url($post_id[0]['post_id']), $UID );
					$this->wpdb->query($sql);

					$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET post_id=%s WHERE id_kbucket=%s", $post_id[0]['post_id'], $UID );
					$this->wpdb->query($sql);
					return true;
				}else{
					return false;
				}
			}
		}else{
			$sql = $this->wpdb->prepare( "SELECT post_id FROM {$this->wpdb->prefix}postmeta WHERE meta_key=%s AND meta_value=%s", 'kb_custom_photo', $UID );
			$post_id = $this->wpdb->get_results($sql, ARRAY_A);

			if(!empty($post_id[0]['post_id'])){
				$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET image_url=%s WHERE id_kbucket=%s", wp_get_attachment_url($post_id[0]['post_id']), $UID );
				$this->wpdb->query($sql);

				$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET post_id=%s WHERE id_kbucket=%s", $post_id[0]['post_id'], $UID );
				$this->wpdb->query($sql);
				return true;
			}else{
				return false;
			}
		}
	}


	public function kb_on_remove_attach($attach_id){
		$attrImageUrl = wp_get_attachment_url($attach_id);
		if(!empty($attrImageUrl)){
			$sql = $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}kbucket SET image_url='' WHERE image_url=%s", $attrImageUrl );
			$res = $this->wpdb->query($sql);
		}

		delete_post_meta($attach_id, 'wp_attachment_url');
		delete_post_meta($attach_id, 'wp_attachment_size');
		delete_post_meta($attach_id, 'kb_custom_photo');
		delete_post_meta($attach_id, 'wp_attachment_orig_url');
	}


	public function kb_get_filetype_by_curl($url){

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$results = explode("\n", trim(curl_exec($ch)));
		foreach($results as $line) {
			if (strtok($line, ':') == 'Content-Type') {
				$parts = explode(":", $line);
				switch (trim($parts[1])) {
					case 'image/jpeg':
						return 'jpg';
						break;
					case 'image/png':
						return 'png';
						break;
					case 'image/gif':
						return 'gif';
						break;
					default:
						return false;
						break;
				}
			}
		}
	}


	/**
	 * Get an attachment ID given a URL.
	 *
	 * @param string $url
	 *
	 * @return int Attachment ID on success, 0 on failure
	 */
	public function kb_get_attachment_id( $url, $size = 0, $uid = 0 ) {
		$attachment_id = 0;

		global $wpdb;

		$url = html_entity_decode($url);

		if(!$size){
			$sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='wp_attachment_orig_url' AND meta_value=%s", $url);
		}else{
			$sql = $wpdb->prepare("
				SELECT post_id FROM {$wpdb->prefix}postmeta a
				LEFT JOIN {$wpdb->prefix}postmeta b on b.meta_id  = a.meta_id
				WHERE a.meta_key=%s AND a.meta_value=%s AND b.meta_key=%s AND b.meta_value=%s",
				'wp_attachment_orig_url', $url, 'wp_attachment_size', (int)$size);
		}

		$query = $wpdb->get_results($sql);

		if(count($query)){
			 return $query[0]->post_id;
		}else return false;

	}

	/**
	 * Set thumbnail image to the post
	 * @param integer $post_id: Current the post
	 * @param string $image_url: Uri of image
	 * @return void
	 * @author Max Pilegi <3972349@gmail.com>
	 * @version 2.1 (Correct work if has the image in destination)
	 */
	public function addFeaturedImg($image_url, $title, $uid = 0){
		global $wpdb;

		$image_url = trim($image_url);
		$title = trim($title);

		if(empty($image_url)) return false;

		// Check image on begin //
		if(preg_match('/^\/\//i', $image_url)) $image_url = 'http:'.$image_url;

		// Check image on exists
		$attach_id = (int)$this->kb_get_attachment_id($image_url);
		if($attach_id) return $attach_id;

		$image_name = basename($image_url);

		if(empty($image_name)) return;



		// Check extension for remote image
		if(!function_exists('exif_imagetype')){
			$extension = $this->kb_get_filetype_by_curl($image_url);
		}else{
			switch (@exif_imagetype($image_url)) {
				case 1:
					$extension = 'gif';
					break;
				case 2:
					$extension = 'jpg';
					break;
				case 3:
					$extension = 'png';
					break;

				default:
					$extension = '';
					break;
			}
		}


		if(empty($extension)) return false;

		$uid = $uid ? $uid : get_current_user_id();

		// Prepare for new image

		// Uniq name to image
		$ecr_title = md5($title) . '_'. $uid .'.'.$extension;

		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['path'] . '/' . $ecr_title;

		// Check image for exists
		$file_size = $this->kb_curl_get_file_size($image_url);


		// Copy image to destination
		if(copy( $image_url, $file_path )){
			$file_url = $upload_dir['url'] . '/' . $ecr_title;

			sleep(1);

			$user_id = $uid;

			$wp_filetype = wp_check_filetype($file_path, null );
			$attachment = array(
				'guid'				=> $file_url,
				'post_mime_type'	=> $wp_filetype['type'],
				'post_title'		=> preg_replace( '/\.[^.]+$/', '', $image_name ),
				'post_content'		=> '',
				'post_status'		=> 'inherit',
				'post_author'		=> $user_id
			);
			$attach_id = wp_insert_attachment( $attachment, $file_path, 0 );
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
			$res = wp_update_attachment_metadata( $attach_id, $attach_data );

			if(!empty($file_path)) update_post_meta($attach_id, 'wp_attachment_url', html_entity_decode($file_path));
			if(!empty($image_url)) update_post_meta($attach_id, 'wp_attachment_orig_url', html_entity_decode($image_url));
			if(!empty($file_path)) update_post_meta($attach_id, 'wp_attachment_size', filesize($file_path));

			return $attach_id;

		}else{
			return false;
		}
	}



	public function addShareMetaKbucket(){
		if(isset($_GET['share'])){
			$img_url = !empty($_GET['img_url']) ? $_GET['img_url'] : '';
			$title = !empty($_GET['img_url']) ? $_GET['title'] : '';
			$descr = !empty($_GET['descr']) ? $_GET['img_url'] : '';
			$this->render_meta_tags($img_url,$title,$descr);
		}
	}

	function get_current_page_url() {
		global $wp;
		return add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );
	}

	public function kbucket_rss_action_hook(){
		if(isset($_REQUEST['c']) && isset($_REQUEST['get_rss'])){
			$cid = esc_attr($_REQUEST['c']);

			global $wp;
			$current_url = home_url(add_query_arg(array(),$wp->request));

			$pid = $this->wpdb->get_var("SELECT ID FROM {$this->wpdb->posts} WHERE post_content LIKE '%[KBucket-Page]%' AND post_status='publish'");

			$url = get_permalink($pid).'?scategory='.$cid;

			$catinfo = $this->wpdb->get_row("SELECT name,description,add_date FROM {$this->wpdb->prefix}kb_category WHERE id_cat = '$cid' AND status='1'");

			$data = $this->wpdb->get_results("
				SELECT a.id_kbucket,id_cat,title,description,link,author,add_date,tags,pub_date,twitter
				FROM {$this->wpdb->prefix}kbucket a
				LEFT JOIN(
					SELECT a.id_kbucket,GROUP_CONCAT(name) as tags
					FROM {$this->wpdb->prefix}kbucket a
					Left Join {$this->wpdb->prefix}kb_tags b on b.id_kbucket  = a.id_kbucket
					Left Join {$this->wpdb->prefix}kb_tag_details c on c.id_tag = b.id_tag
					where a.id_cat = '$cid'
					group by a.id_kbucket
				) b on a.id_kbucket = b.id_kbucket
				WHERE a.id_cat = '$cid'
				LIMIT 200");
			header("Content-type: application/rss+xml");
			$result = '<?xml version="1.0" encoding="iso-8859-1"?>'."\n";
			$result .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
			$result .= '<channel>'."\n";
				$result .= '<title>'.(isset($catinfo->name) ? $catinfo->name : '').'</title>'."\n";
				$result .= '<link>'.$url.'</link>'."\n";
				$result .= '<description>'.(isset($catinfo->description) ? htmlspecialchars($catinfo->description) : '').'</description>'."\n";
				$date = isset($catinfo->add_date) ? strtotime($catinfo->add_date) : '';

				$result .= '<pubDate>'.date('r',$date).'</pubDate>'."\n";
				foreach ($data as $item) {
					$result .= '<item>'."\n";
						$result .= '<guid isPermaLink="false">'.$item->link.'</guid>';
						$result .= '<title><![CDATA['.$item->title.']]></title>'."\n";
						$result .= '<pubDate><![CDATA['.$item->pub_date.']]></pubDate>'."\n";
						$result .= '<author><![CDATA['.$item->author.']]></author>'."\n";
						$result .= '<link><![CDATA['.$item->link.']]></link>'."\n";
						$result .= '<description><![CDATA['.htmlspecialchars($item->description).'<br>Tags: '.$item->tags.']]></description>'."\n";
						$result .= '<category><![CDATA['.$item->tags.']]></category>'."\n";
					$result .= '</item>'."\n";
				}
			$result .= '</channel>'."\n";
			$result .= '</rss>';
			echo $result;
			exit();
		}
	}

	public function kbucket_rss_category_action_hook(){
		if(isset($_REQUEST['cat']) && isset($_REQUEST['subcat'])){
			$cid = $_REQUEST['cat'];
			$scid = (isset($_REQUEST['subcat'])) ? $_REQUEST['subcat'] : false;

			$pid = $this->wpdb->get_var("SELECT ID FROM {$this->wpdb->posts} WHERE post_content LIKE '%[KBucket-Page]%' AND post_status='publish'");

			$url = get_permalink($pid).'?scategory='.$cid;

			$catinfo = $this->wpdb->get_row("SELECT name,description,add_date FROM {$this->wpdb->prefix}kb_category WHERE id_cat = '$cid' AND status='1'");
			$scatinfo = $this->wpdb->get_row("SELECT name,description,add_date FROM {$this->wpdb->prefix}kb_category WHERE id_cat = '$scid' AND status='1'");

			$data = $this->wpdb->get_results("SELECT a.id_kbucket,id_cat,title,description,link,author,add_date,tags,pub_date
				FROM {$this->wpdb->prefix}kbucket a left join(
					SELECT a.id_kbucket,GROUP_CONCAT(name) as tags
					FROM {$this->wpdb->prefix}kbucket a
					Left Join {$this->wpdb->prefix}kb_tags b on b.id_kbucket  = a.id_kbucket
					Left Join {$this->wpdb->prefix}kb_tag_details c on c.id_tag = b.id_tag
					where a.id_cat = '$cid'
					group by a.id_kbucket
				) b on a.id_kbucket = b.id_kbucket
				where a.id_cat = '$scid' LIMIT 200");
			header("Content-type: application/rss+xml");
			$result = '<?xml version="1.0" encoding="iso-8859-1"?>';
			$result .= '<rss version="2.0">';
			$result .= '<channel>';
			$result .= '<title>Kbucket - (Category: '.(!empty($catinfo->name) ? $catinfo->name.($scid ? ', Subcategory: '.$scatinfo->name : '') : '').')</title>';
			$result .= '<link>'.$url.'</link>';
			$result .= '<description>'.(!empty($catinfo->description) ? $catinfo->description : '').'</description>';
			$result .= '<pubDate>'.(!empty($catinfo) ? $catinfo->add_date : date('r', time())).'</pubDate>';
			foreach ($data as $item) {
				$result .= '<item>';
				$result .= '<title><![CDATA['.$item->title.']]></title>';
				$result .= '<pubDate><![CDATA['.$item->pub_date.']]></pubDate>';
				$result .= '<author><![CDATA['.$item->author.']]></author>';
				$result .= '<link><![CDATA['.KBUCKET_URL.'/kb/'.$item->id_kbucket.']]></link>';
				$result .= '<description><![CDATA['.$item->description.'<br>Tags: '.$item->tags.']]></description>';
				$result .= '<category><![CDATA['.$item->tags.']]></category>';
				$result .= '</item>';
			}
			$result .= '</channel>';
			$result .= '</rss>';
			echo $result;
			exit();
		}
	}

	private function RFC2822($date, $time = '00:00'){
		list($d, $m, $y) = explode('-', $date);
		list($h, $i) = explode(':', $time);
		return date('r', mktime($h,$i,0,$m,$d,$y));
	}

	public function kbucket_rss_suggest_action_hook(){
		if(isset($_REQUEST['suggest_rss'])){
			$pid = $this->wpdb->get_var("SELECT ID FROM {$this->wpdb->posts} WHERE post_content LIKE '%[KBucket-Page]%' AND post_status = 'publish' ");
			$url = get_permalink($pid);
			$data = $this->wpdb->get_results("SELECT a.*,b.* ,DATE_FORMAT(a.add_date,'%d-%m-%Y') as pubdate
				FROM {$this->wpdb->prefix}kb_suggest a
				left join(
					SELECT c.id_cat as parid, b.id_cat as subid,c.name as parcat,b.name as  subcat,b.description as subdes, c.description as pades
					FROM {$this->wpdb->prefix}kb_category b
					inner join {$this->wpdb->prefix}kb_category c on c.id_cat = b.parent_cat
				) b on a.id_cat = b.subid

			");

			header("Content-type: application/rss+xml");
			$result = '<?xml version="1.0" encoding="iso-8859-1"?>';
			$result .= '<rss version="2.0">';
			$result .= '<channel>';
			$result .= '<title><![CDATA[Suggested Page URLs]]></title>';
			$result .= '<link><![CDATA['.$url.']]></link>';
			$result .= '<pubDate>'.$this->RFC2822(date('d-m-Y')).'</pubDate>';
			foreach($data  as $item) {
				$result .= '<item>';
				$result .= '<title><![CDATA['.$item->tittle.']]></title>';
				$result .= '<author><![CDATA['.$item->author.']]></author>';
				$result .= '<pubDate><![CDATA['.$item->add_date.']]></pubDate>';
				$result .= '<link><![CDATA['.$item->link.']]></link>';
				$result .= '<description><![CDATA['.$item->description.']]></description>';
				$result .= '<category><![CDATA['.$item->tags.']]></category>';
				$result .= '<twitter_handle><![CDATA['.$item->twitter.']]></twitter_handle>';
				$result .= '<facebook_page><![CDATA['.$item->facebook.']]></facebook_page>';
				$result .= '</item>';
			}
			$result .= '</channel>';
			$result .= '</rss>';
			echo $result;
			exit();
		}
	}


	/*****************************
	 * AJAX actions
	 *****************************
	 */

	public function kbucket_action_ajax_validate_suggest(){
		$sid_cat = esc_attr( $_POST['sid_cat'] );
		$stitle = esc_attr( $_POST['stitle'] );
		$sdesc = esc_attr( $_POST['sdesc'] );
		$stags = esc_attr( $_POST['stags'] );
		$sauthor = esc_attr( $_POST['sauthor'] );
		$surl = esc_url( $_POST['surl'] );
		$stwitter = esc_attr( $_POST['stwitter'] );
		$sfacebook = esc_attr( $_POST['sfacebook'] );

		$sql = $this->wpdb->prepare("INSERT INTO {$this->wpdb->prefix}kb_suggest
			VALUE(
				%s,
				%s,
				%s,
				%s,
				%s,
				current_date,
				%s,
				%s,
				%s,
				%s,
				'0'
			)",
			"",
			$sid_cat,
			$stitle,
			$sdesc,
			$stags,
			$sauthor,
			$surl,
			$stwitter,
			$sfacebook
		);
		$this->wpdb->query($sql);
	}

	/**
	 * Render dropdown with subcategories by category id
	 * Requires POST param category_id
	 * Fired by wp ajax hook: wp_ajax_get_subcategories
	 */
	public function kbucket_action_ajax_get_subcategories()
	{
		if ( empty( $_POST['category_id'] ) ) {
			return;
		}

		$sql = 'SELECT `id_cat`, `name`
				FROM `' . $this->wpdb->prefix . 'kb_category`
				WHERE `parent_cat`=%s';

		$categoryId = sanitize_text_field( $_POST['category_id'] );

		$query = $this->wpdb->prepare( $sql, $categoryId );

		$subcategories = $this->wpdb->get_results( $query );

		$response = '<option value="">Select Subcategory</option>';
		foreach ( $subcategories as $s ) {
			$response .= '<option value="' . esc_attr( $s->id_cat ) . '">' . esc_html( $s->name ) . '</option>';
		}

		echo $response;
		exit;
	}

	/**
	 * Render DataTables compatible JSON object containing Kbuckets by category id
	 * Requires POST param category_id
	 * Fired by wp ajax hook: wp_ajax_get_kbuckets
	 */
	public function kbucket_action_ajax_get_kbuckets(){
		if(empty($_POST['category_id'])) return;

		$offset = (isset($_POST['offset'])) ? (int)$_POST['offset'] : 0;

		header( 'Content-Type: application/json' );

		$sql = "SELECT c.name,k.id_kbucket,k.title,k.url_kbucket,k.post_id AS link,k.author,DATE_FORMAT(STR_TO_DATE(k.pub_date, '%%Y-%%m-%%d %%H:%%i:%%s'), '%%Y-%%m-%%d') as add_date, k.image_url, k.post_id
				FROM {$this->wpdb->prefix}kbucket k
				LEFT JOIN {$this->wpdb->prefix}kb_category c ON k.id_cat=c.id_cat
				WHERE c.id_cat=%s
				ORDER BY add_date DESC";

		$sql_l = "SELECT c.name,k.id_kbucket,k.title,k.url_kbucket,k.post_id AS link,k.author,DATE_FORMAT(STR_TO_DATE(k.pub_date, '%%Y-%%m-%%d %%H:%%i:%%s'), '%%Y-%%m-%%d') as add_date, k.image_url, k.post_id
				FROM {$this->wpdb->prefix}kbucket k
				LEFT JOIN {$this->wpdb->prefix}kb_category c ON k.id_cat=c.id_cat
				WHERE c.id_cat=%s
				ORDER BY add_date DESC";

		$categoryId = esc_sql( $_POST['category_id'] );

		$query = $this->wpdb->prepare( $sql, $categoryId );
		$query_l = $this->wpdb->prepare( $sql_l, $categoryId );

		$kbuckets = $this->wpdb->get_results( $query );
		$kbuckets_l = count($this->wpdb->get_results( $query_l ));

		echo json_encode( array( 'data' => $kbuckets, 'limit' => $kbuckets_l ) );
		exit;
	}



	/**
	 * Render share window content
	 * Requires POST param kb-share
	 * Fired by wp ajax hook: wp_ajax_nopriv_share_content
	 */
	public function kbucket_action_ajax_share_content(){
		if ( empty( $_GET['kb-share'] ) ) {
			return false;
		}

		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

		$kid = esc_attr( $_GET['kb-share'] );

		$kbucket = $this->get_kbucket_by_id( $kid );

		if ( ! $kbucket ) {
			return false;
		}

		$imageUrl = $kbucket->image_url !== '' ? $kbucket->image_url : false;

		if ( empty( $kbucket->post_id ) ) {
			$shareData = $this->get_kbucket_share_data( $kbucket );
		} else {

			$post = get_post( $kbucket->post_id );

			// Get sharing data
			$shareData = array(
				'url' => $kbucket->url_kbucket,
				'imageUrl' => $kbucket->image_url,
				'title' => $kbucket->title,
				'description' => $kbucket->description,
			);
		}

		$url = WPKB_SITE_URL . '/' . $shareData['url'];
		include WPKB_PATH.'templates/kb-share.php';
		exit;
	}

	/**
	 * Insert Sticky Kbuckets as WP posts and return JSON result response
	 * Requires one of POST params kb_on | kb_off
	 * Fired by wp ajax hook: wp_ajax_save_sticky
	 */
	public function kbucket_action_ajax_save_sticky()
	{
		if (
			( empty( $_POST['kb_on'] ) || ! is_array( $_POST['kb_on'] ) )
			&& ( empty( $_POST['kb_off'] ) || ! is_array( $_POST['kb_off'] ) )
		) {
			return;
		}

		header( 'Content-Type: application/json' );

		// Posts to write
		if ( ! empty( $_POST['kb_on'] ) ) {

			$args = array( 'hide_empty' => 0 );

			$wpCategories = array();
			$wpc = get_categories( $args );
			foreach ( $wpc as $cat ) {
				$wpCategories[ $cat->name ] = $cat->cat_ID;
			}

			$where = "a.`id_kbucket` IN ( '" . implode( "','", $_POST['kb_on'] ) . "' )";

			$kbuckets = $this->get_kbuckets( $where );

			$savedImages = array();

			foreach ( $kbuckets as $kbucket ) {

				if ( $kbucket->post_id > 0 || is_sticky( $kbucket->post_id ) ) {
					continue;
				}

				$content = $kbucket->description;

				$postData = array(
					'post_content' => $content,
					'post_title' => $kbucket->title,
					'post_status' => 'publish',
					'post_author' => $kbucket->author,
					'post_date' => $kbucket->add_date,
					'post_date_gmt' => $kbucket->add_date,
					'comment_status' => 'closed',
					'post_category' => array( $wpCategories[ $kbucket->categoryName ] )
				);

				/** @var $postId object|int */
				$postId = wp_insert_post( $postData, true );

				// Wpdb error object returned if there was an error
				if ( is_object( $postId ) ) {
					echo json_encode( array( 'status' => $postId->last_error ) );
					exit;
				}

				stick_post( $postId );

				add_post_meta( $postId, 'article_url', $kbucket->link, true );
				add_post_meta( $postId, '_url_kbucket', $kbucket->url_kbucket, true );
				add_post_meta( $postId, '_id_kbucket', $kbucket->id_kbucket, true );

				$updateArr = array( 'post_id' => $postId );
				$updatePlaceholders = array( '%d' );

				// Get image url from site og:image meta tag
				//$imageUrl = $this->scrape_og_image( $kbucket->link );

				if ( $imageUrl !== '' ) {

					$attachment = $this->save_post_image( $imageUrl, $postId, $kbucket->title );

					if ( empty( $attachment['error'] ) && ! empty( $attachment['url'] ) ) {

						$imageFile = $attachment['file'];

						$filetype = wp_check_filetype( basename( $imageFile ), null );

						$postData = array(
							'post_mime_type' => $filetype['type'],
							'post_title' => $kbucket->title,
							'post_content' => '',
							'post_status' => 'inherit',
						);

						$attachmentId = wp_insert_attachment( $postData, $imageFile, $postId );

						if ( ! function_exists( 'wp_generate_attachment_data' ) ) {
							require_once( ABSPATH . 'wp-admin' . '/includes/image.php' );
						}

						$attachmentData = wp_generate_attachment_metadata( $attachmentId, $imageFile );
						if ( wp_update_attachment_metadata( $attachmentId,  $attachmentData ) ) {
							add_post_meta( $postId, '_thumbnail_id', $attachmentId, true );
						}

						$savedImages[ $kbucket->id_kbucket ] = $imageUrl;

						$updateArr['image_url'] = $imageUrl;
						$updatePlaceholders[] = '%s';
					}
				}
				$this->wpdb->update(
					$this->wpdb->prefix . 'kbucket',
					$updateArr,
					array( 'id_kbucket' => $kbucket->id_kbucket ),
					$updatePlaceholders,
					'%s'
				);
			}
		}

		// Posts to delete
		if ( ! empty( $_POST['kb_off'] ) ) {

			$where = "a.`id_kbucket` IN ( '" . implode( "','", $_POST['kb_off'] ) . "' )";

			$kbuckets = $this->get_kbuckets( $where );

			foreach ( $kbuckets as $kbucket ) {

				if ( ! $kbucket->post_id > 0 ) {
					continue;
				}

				$args = array(
					'post_type' => 'attachment',
					'post_status' => 'any',
					'posts_per_page' => -1,
					'post_parent' => $kbucket->post_id,
				);
				$attachments = new WP_Query( $args );
				$attachment_ids = array();
				if ( $attachments->have_posts() ) {
					while ( $attachments->have_posts() ) {
						$attachments->the_post();
						$attachment_ids[] = get_the_id();
					}
				}

				wp_reset_postdata();

				if ( ! empty( $attachment_ids ) ) {
					$delete_attachments_query = $this->wpdb->prepare(
						"DELETE FROM %1$s WHERE %1$s.ID IN (%2$s)",
						$this->wpdb->posts,
						join( ',', $attachment_ids )
					);
					$this->wpdb->query( $delete_attachments_query );
				}

				if(false === wp_delete_post($kbucket->post_id, true )) continue;
			}

			$sql = $this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}kbucket SET post_id=NULL WHERE id_kbucket IN (%s)",
				implode("','",esc_sql($_POST['kb_off']))
			);
			$this->wpdb->query($sql);
		}

		if ( $this->wpdb->last_error !== '' ) {
			echo json_encode( array( 'status' => $this->wpdb->last_error ) );
			exit;
		}

		$response = array( 'status' => 'ok' );

		if ( ! empty( $savedImages ) ) {
			$response['images'] = $savedImages;
		}

		echo json_encode( $response );
		exit;
	}



	/*****************************
	 * Actions fired by Hooks
	 *****************************
	 */

	/**
	 * Set frontend styles and scripts
	 * Fired by wp action hook: wp_enqueue_scripts
	 */
	public function action_init_scripts(){
		// Invoke jQuery mobile for mobile version
		if ( $this->isKbucketPage && $this->isMobile ) {

			global $wp_styles;

			// Reset theme css
			$wp_styles = array();

			global $wp_scripts;

			// Reset all theme scripts
			$wp_scripts = array();

			wp_enqueue_style( 'jquery-mobile-css', WPKB_PLUGIN_URL .'/css/jquery.mobile-1.4.5.min.css' );
			wp_enqueue_style( 'jquery-mobile-structure-css', WPKB_PLUGIN_URL .'/css/jquery.mobile.structure-1.4.5.min.css'	);
			wp_enqueue_style( 'jquery-mobile-theme-css', WPKB_PLUGIN_URL .'/css/jquery.mobile.theme-1.4.5.min.css'	);
			wp_enqueue_style( 'kbucket-mobile-css', WPKB_PLUGIN_URL .'/css/style-mobile.css' );

			wp_enqueue_script( 'jquery-mobile-js', WPKB_PLUGIN_URL . '/js/jquery.mobile-1.4.5.js', array( 'jquery' ));

			return;
		}

		wp_enqueue_style( 'kbucket-facebox-css', WPKB_PLUGIN_URL . '/css/facebox.css' );
		wp_enqueue_style( 'kbucket-css', WPKB_PLUGIN_URL .'/css/kbucket-style.css', array( 'kbucket-facebox-css' ) );

		// Register Facebox modal window
		wp_register_script('facebox-js', WPKB_PLUGIN_URL . '/js/facebox.js', array( 'jquery' ),'',true);
		wp_enqueue_script('addthis-kb-footer', '//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-xxxxxxxxxxxxxxxxx',array( 'jquery' ),( WP_DEBUG ) ? time() : null,true);


		// Register Kbucket scripts
		wp_register_script( 'kbucket-js',WPKB_PLUGIN_URL . '/js/kbucket.js', array( 'jquery', 'facebox-js', 'jquery-ui-core', 'jquery-ui-draggable', 'jquery-ui-droppable' ),( WP_DEBUG ) ? time() : null,true);
		wp_enqueue_script( 'facebox-js' );
		wp_enqueue_script( 'kbucket-js' );
	}

	/**
	 * Set admin styles and scripts
	 * Fired by wp action hook: admin_enqueue_scripts
	 */
	public function kbucket_action_admin_enqueue_scripts(){
		wp_enqueue_media();
		wp_enqueue_style( 'tabcontent-css', WPKB_PLUGIN_URL .'/css/tabcontent.css', array(), '1.0', 'all' );
		wp_enqueue_style( 'colorpicker-css', WPKB_PLUGIN_URL .'/css/colorpicker.css', array(), '1.0', 'all' );
		wp_enqueue_style( 'datatables-css', WPKB_PLUGIN_URL .'/css/jquery.dataTables.min.css' );
		wp_enqueue_style( 'datatables-css', WPKB_PLUGIN_URL .'/css/jquery.dataTables_themeroller.css' );
		wp_enqueue_style( 'kbucket-facebox-css', WPKB_PLUGIN_URL . '/css/facebox.css' );
		wp_enqueue_style( 'kbucket-css', WPKB_PLUGIN_URL .'/css/kbucket-style.css', array( 'kbucket-facebox-css' ) );
		wp_enqueue_style( 'fa', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', NULL, NULL, 'all' );
		wp_enqueue_style( 'admin-kbucket-style', WPKB_PLUGIN_URL . '/css/admin-kbucket-style.css', NULL, NULL, 'all' );

		wp_enqueue_script( 'jquery-validate-js', WPKB_PLUGIN_URL . '/js/jquery.validate.min.js' );
		wp_enqueue_script( 'tabcontent-js', WPKB_PLUGIN_URL .'/js/tabcontent.js' );
		wp_enqueue_script( 'datatables-js', WPKB_PLUGIN_URL .'/js/jquery.dataTables.min.js' );
		wp_enqueue_script( 'colorpicker-js', WPKB_PLUGIN_URL . '/js/colorpicker.js' );
		wp_enqueue_script( 'admin-js', WPKB_PLUGIN_URL . '/js/admin.js' );

		wp_localize_script(
			'admin-js',
			'ajaxObj',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'adminUrl' => admin_url() . 'admin.php',
				'pluginUrl' => WPKB_PLUGIN_URL,
				'kbucketUrl' => KBUCKET_URL,
			)
		);
	}

	/**
	 * Add Kbucket section page in dashboard
	 * Fired by wp action hook: admin_menu
	 */
	public function kbucket_action_admin_menu() {
		add_menu_page( 'Page Title', 'KBucket', 'administrator', 'KBucket', array( $this, 'render_dashboard' ) );
	}

	/**
	 * Set custom template for subsections or mobile
	 * Fired by wp action hook: page_template
	 * @param $template
	 * @return string
	 */
	public function action_set_page_template( $template )
	{
		if ( $this->template !== '' ) {
			return WPKB_PATH . 'templates/' . $this->template;
		}

		return $template;
	}

	/**
	 * @param $postId
	 * @param $post
	 */
	public function action_update_kbucket_from_sticky( $postId, $post )
	{
		$kbucketId = get_post_meta( $postId, '_id_kbucket', true );

		if ( $kbucketId ) {
			$res = $this->wpdb->update(
			 $this->wpdb->prefix . 'kbucket',
			 array(
				 'title' => $post->post_title,
				 'description' => $post->post_content,
			 ),
			 array( 'id_kbucket' => $kbucketId ),
			 '%s',
			 '%s'
			);

		}
	}

	/**
	 * @param $query - passed by reference,therefore modified directly
	 */
	public function customize_search_query($query)
	{
		//dump($query);
	}

	/*****************************
	 * Content Filtering functions
	 *****************************
	 */

	/**
	 * Change default page title to current Kbucket category name
	 * Fired by wp_title filter hook
	 * @return mixed
	 */

	public function filter_the_title($title, $sep, $seplocation) {
		$title = explode(' - ', $title);

		$new_title = array();

		if(count($title)){
			$first = reset($title);
			$end = end($title);
		}else{
			$first = $end = '';
		}
		$new_title[] = $first;

		$cat_subcat = $this->get_cat_subcat_array();



		// Paginate
		if(!empty($_GET['pagin'])){
			$page = sprintf(__("Page of %s", WPKB_TEXTDOMAIN), (int)$_GET['pagin']);
		}else{
			$page = '';
		}

		// Sort by category
		if(!empty($_GET['mtag'])){
			$mtag = sprintf(__("Sort by Category: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['mtag']));
		}else{
			$mtag = '';
		}

		// Sort by Related tags
		if(!empty($_GET['rtag'])){
			$rtag = sprintf(__("Sort by Related tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['rtag']));
		}else{
			$rtag = '';
		}

		// Sort by Publisher tags
		if(!empty($_GET['ptag'])){
			$ptag = sprintf(__("Sort by Publisher tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['ptag']));
		}else{
			$ptag = '';
		}

		// Sort by Author tags
		if(!empty($_GET['atag'])){
			$atag = sprintf(__("Sort by Author tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['atag']));
		}else{
			$atag = '';
		}

		// Sort by Author, Title or date
		if(!empty($_GET['sort'])){
			if($_GET['sort'] == 'author'){
				$sort = sprintf(__("Sort by Author: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}elseif($_GET['sort'] == 'add_date'){
				$sort = sprintf(__("Sort by Date: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}elseif($_GET['sort'] == 'title'){
				$sort = sprintf(__("Sort by Title: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}else{
				$sort = sprintf(__("Sort by Undefined: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}
		}else{
			$sort = '';
		}

		// Search page
		if(!empty($_GET['search'])){
			$search = __("Search in Kbuckets", WPKB_TEXTDOMAIN);
		}else{
			$search = '';
		}



		if(!empty($cat_subcat['cat']->name)) $new_title[] = $cat_subcat['cat']->name;
		if(!empty($cat_subcat['subcat']->name)) $new_title[] = $cat_subcat['subcat']->name;
		if(!empty($page)) $new_title[] = $page;
		if(!empty($mtag)) $new_title[] = $mtag;
		if(!empty($rtag)) $new_title[] = $rtag;
		if(!empty($ptag)) $new_title[] = $ptag;
		if(!empty($atag)) $new_title[] = $atag;
		if(!empty($sort)) $new_title[] = $sort;
		if(!empty($search)) $new_title[] = $search;


		$new_title[] = $end;

		$new_title = implode(' - ', $new_title);
		return $new_title;
	}

	public function filter_the_title_alt($title) {
		$cat_subcat = $this->get_cat_subcat_array();

		// Paginate
		if(!empty($_GET['pagin'])){
			$page = sprintf(__("Page of %s", WPKB_TEXTDOMAIN), (int)$_GET['pagin']);
		}else{
			$page = '';
		}

		// Sort by category
		if(!empty($_GET['mtag'])){
			$mtag = sprintf(__("Sort by Category: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['mtag']));
		}else{
			$mtag = '';
		}

		// Sort by Related tags
		if(!empty($_GET['rtag'])){
			$rtag = sprintf(__("Sort by Related tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['rtag']));
		}else{
			$rtag = '';
		}

		// Sort by Publisher tags
		if(!empty($_GET['ptag'])){
			$ptag = sprintf(__("Sort by Publisher tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['ptag']));
		}else{
			$ptag = '';
		}

		// Sort by Author tags
		if(!empty($_GET['atag'])){
			$atag = sprintf(__("Sort by Author tag: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['atag']));
		}else{
			$atag = '';
		}

		// Sort by Author, Title or date
		if(!empty($_GET['sort'])){
			if($_GET['sort'] == 'author'){
				$sort = sprintf(__("Sort by Author: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}elseif($_GET['sort'] == 'add_date'){
				$sort = sprintf(__("Sort by Date: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}elseif($_GET['sort'] == 'title'){
				$sort = sprintf(__("Sort by Title: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}else{
				$sort = sprintf(__("Sort by Undefined: %s", WPKB_TEXTDOMAIN), sanitize_text_field($_GET['sort']));
			}
		}else{
			$sort = '';
		}

		// Search page
		if(!empty($_GET['search'])){
			$search = __("Search in Kbuckets", WPKB_TEXTDOMAIN);
		}else{
			$search = '';
		}


		$new_title = array();
		if(count($title)){
			$first = reset($title);
			$end = end($title);
		}else{
			$first = $end = '';
		}

		$new_title[] = $first;


		if(!empty($cat_subcat['cat']->name)) $new_title[] = $cat_subcat['cat']->name;
		if(!empty($cat_subcat['subcat']->name)) $new_title[] = $cat_subcat['subcat']->name;
		if(!empty($page)) $new_title[] = $page;
		if(!empty($mtag)) $new_title[] = $mtag;
		if(!empty($rtag)) $new_title[] = $rtag;
		if(!empty($ptag)) $new_title[] = $ptag;
		if(!empty($atag)) $new_title[] = $atag;
		if(!empty($sort)) $new_title[] = $sort;
		if(!empty($search)) $new_title[] = $search;


		$new_title[] = $end;
		return $new_title;
	}

	/**
	 * Replace post link URL with Kbuckets listings page URL with share window
	 * Fired by wp filter hook: post_link
	 * @param $url
	 * @param $post
	 * @return string
	 */
	public function kbucket_filter_post_link( $url, $post )
	{
		if ( is_sticky( $post->ID ) && is_home() ) {

			//$kbucketUrl = get_post_meta( $post->ID, '_url_kbucket', true );


			//return $kbucketUrl !== '' ? WPKB_SITE_URL . '/'  . $kbucketUrl : $url;


			$articleUrl = get_post_meta( $post->ID, 'article_url', true );

			return $articleUrl ? $articleUrl : $url;
		}

		return $url;
	}

	/**
	 * Render head content
	 * Fired by wp_head filter hook
	 * @param $content string
	 */
	public function filter_wp_head( $content ) {

		// If Kbucket id is set,it's a link to share box.
		if ( $this->kbucket ) {
			$shareData = $this->get_kbucket_share_data( $this->kbucket );

			$this->metaOgUrl = WPKB_SITE_URL . '/' . $shareData['url'] . '/';

			 // Set the metatags for Facebook Open Graph and Linkedin callback request
			$this->render_meta_tags(
				$shareData['imageUrl'],
				$shareData['title'],
				$shareData['description'],
				$shareData['kbucket']->twitter
			);

		} elseif ( isset( $this->category->name ) ) {
			$imageUrl = !empty( $this->category->image ) ? $this->category->image : '';

			$this->metaOgUrl = $this->category->url; ?>
			<!-- Generated by Kbucket -->
			<?php
			// Set the metatags for Facebook Open Graph and Linkedin callback request
			$this->render_meta_tags( $imageUrl, $this->category->name, $this->category->description ); ?>
			<!-- /Generated by Kbucket -->
			<?php
		}

		$content .= '<script type="text/javascript">';
			if( 1 == $this->kbPage->settings->site_search ){
				$content .= "var kbSearch = '',";
				$content .= "permalink = '".esc_js(get_permalink())."',";
				$content .= "searchRes = '".(!empty( $_REQUEST['srch'] ) ? esc_js( $_REQUEST['srch'] ) : '')."';";
			}
		$content .= '</script>';
		return $content;
	}

	/**
	 * Render KBucket listings page content
	 * Fired by wp filter hook: the_content
	 */
	public function filter_the_content( $content = '' ) {

		ob_start();

		$localize = array(
			'kbucketUrl' => WPKB_PLUGIN_URL,
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		if ( $this->isMobile ) {
			if ( $this->kbucket ) {
				$this->render_kbucket_page_mobile();
				$kbContent = ob_get_contents();
				ob_end_clean();
				return $kbContent;
			}

			$this->render_head_mobile();
			$this->render_content_mobile();
			$kbContent = ob_get_contents();
			ob_end_clean();
			return $kbContent;
		}


		// If it's not a Kbucket listing page
		if ( ! $this->isKbucketPage ) {
			wp_localize_script( 'kbucket-js', 'kbObj', $localize );
			$kbContent = ob_get_contents();
			ob_end_clean();
			return $kbContent . $content;
		}

		$localize['categoryName'] = isset($this->category->name) ? $this->category->name : 0;
		$localize['addthisId'] = $this->addthisId;

		if ( $this->kbucket ) {
			$localize['shareId'] = $this->kbucket->id_kbucket;
		}

		wp_localize_script( 'kbucket-js', 'kbObj', $localize );

		$settings = $this->kbPage->settings;?>
		<div id="kb-wrapper">
			<?php

			$sortBy = ! empty( $_REQUEST['sort'] ) ? esc_html( $_REQUEST['sort'] ) : esc_html( $settings->sort_by );
			$categoryId = isset($this->category->id_cat) ? $this->category->id_cat : 0;

			if ( $settings->page_title != '' ):?>
				<h2><b><?php echo esc_html( $settings->page_title ); ?></b></h2>
			<?php endif;
			$this->render_header_content( $this->category );

			if ( 1 == $settings->site_search && isset( $_REQUEST['srch'] ) ):

				$this->render_search_results( $this->category );

				$kbContent = ob_get_contents();

				ob_end_clean();

				return $kbContent . $content . '</div>';

			endif;
			?>
			<a href="<?php echo KBUCKET_URL; ?>?kbt=suggest" id="kb-suggest" class="kb-suggest" title="suggest content">
				<img src="<?php echo WPKB_PLUGIN_URL; ?>/images/suggest_content.png" alt="Suggest Content">
			</a>

			<div class="wrap_sort">
				<div class="kb-sort">
					<a href="<?php echo KBUCKET_URL . '/?get_rss=y&c=' . $categoryId; ?>" id="kb-rss" target="_blank">RSS</a>
					<div class="inner_sort">
						<span>Sort by:</span>
						<a href="<?php echo esc_attr( $this->get_current_url( 'author' ) ); ?>" <?php if($sortBy == 'author') echo 'class="active"';?>>Publisher</a>
						<a href="<?php echo esc_attr( $this->get_current_url( 'add_date' ) ); ?>" <?php if($sortBy == 'add_date') echo 'class="active"';?>>Date</a>
						<a href="<?php echo esc_attr( $this->get_current_url( 'title' ) ); ?>" <?php if($sortBy == 'title') echo 'class="active"';?>>Title</a>
					</div>
				</div>
			</div>
			<div class="kb-row">
				<div id="kb-items-1" class="kb-col-12">

					<ul id="kb-items-list">
						<?php if ( count( $this->kbPage->buckets ) > 0 ): ?>
							<?php $this->render_kbuckets_list( $this->kbPage->buckets ); ?>
						<?php else:?>
							<div class="kb-list-item"><b>No result found. Please refine your search</b></div>
						<?php endif; ?>
					</ul>

					<div class="wrap_pagination">
						<span id="kb-pages-count"><?php	echo 'Page ' . (int) $this->kbPage->currentPageI . ' of ' . (int) $this->kbPage->pagesCount; ?></span>
						<div id="kb-pagination"><?php $this->render_pagination_links(); ?></div>
					</div>

				</div>

				<?php if(isset($Kbucket_wd)): ?>
					<div id="kb-tags-wrap-1" class="kb-col-4">
						<?php

						// Render category tags
						$categoryTags = $this->get_category_tags();
						if ( ! empty( $categoryTags ) ) {
							$this->render_tags_cloud(
								$categoryTags,
								'm',
								'main',
								array( 'value' => $this->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
								array( 'mtag' => 'name' )
							);
						}

						// Render related tags
						if ( ! empty( $this->kbPage->relatedTags ) ) { ?>
							<h3>Related Tags</h3>
							<?php
							$this->render_tags_cloud(
								$this->kbPage->relatedTags,
								'r',
								'related',
								array( 'value' => $this->kbPage->relatedTagName, 'dbKey' => 'name', 'title' => 'name' ),
								array( 'mtag' => array( 'raw' => $this->kbPage->activeTagName ), 'rtag' => 'name' )
							);
						}

						// Render Author tags
						if ( $this->kbPage->settings->author_tag_display && isset( $this->kbPage->authorTagName ) && $this->get_author_tags() ) { ?>
							<h3>Author Tags</h3>
							<?php
							$authorTags = $this->get_author_tags();

							$this->render_tags_cloud(
								$authorTags,
								'a',
								'author',
								array( 'value' => $this->kbPage->authorTagName, 'dbKey' => 'author', 'title' => 'author' ),
								array( 'atag' => 'author' )
							);
						} ?>
					</div>
				<?php endif; ?>
			</div><!-- /.kb-row -->
		</div><!-- /#kb-wrapper -->
		<?php $this->render_category_disqus(); ?>
		<noscript><?php _e("Please enable JavaScript to view the") ?> <a href="https://disqus.com/?ref_noscript"><?php _e("comments powered by Disqus.") ?></a></noscript>
		<?php
		$kbContent = ob_get_contents();
		ob_end_clean();

		return $kbContent . $content;
	}


	/**
	 * Render KBucket listings page content
	 * Fired by wp filter hook: the_content
	 */
	public function filter_the_content_alt( $content = '' ) {

		ob_start();

		$localize = array(
			'kbucketUrl' => WPKB_PLUGIN_URL,
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		if ( $this->isMobile ) {
			if ( $this->kbucket ) {
				$this->render_kbucket_page_mobile();
				$kbContent = ob_get_contents();
				ob_end_clean();
				return $kbContent;
			}

			$this->render_head_mobile();
			$this->render_content_mobile();
			$kbContent = ob_get_contents();
			ob_end_clean();
			return $kbContent;
		}

		$localize['categoryName'] = isset($this->category->name) ? $this->category->name : 0;
		$localize['addthisId'] = $this->addthisId;

		if ( $this->kbucket ) {
			$localize['shareId'] = $this->kbucket->id_kbucket;
		}

		wp_localize_script( 'kbucket-js', 'kbObj', $localize );

		$settings = $this->kbPage->settings;?>
		<div id="kb-wrapper">
			<?php

			$sortBy = ! empty( $_REQUEST['sort'] ) ? esc_html( $_REQUEST['sort'] ) : esc_html( $settings->sort_by );
			$categoryId = isset($this->category->id_cat) ? $this->category->id_cat : 0;

			if ( $settings->page_title != '' ) echo '<h2><b>'.esc_html( $settings->page_title ).'</b></h2>';

			$this->render_header_content_alt( $this->category );

			if ( 1 == $settings->site_search && isset( $_REQUEST['srch'] ) ){
				$this->render_search_results( $this->category );
				$kbContent = ob_get_contents();
				ob_end_clean();
				return $kbContent . $content . '</div>';
			}
			?>
			<a href="<?php //echo KBUCKET_URL; ?>?kbt=suggest" id="kb-suggest" class="kb-suggest" title="suggest content">
				<img src="<?php echo WPKB_PLUGIN_URL; ?>/images/suggest_content.png" alt="Suggest Content">
			</a>

			<div class="wrap_sort">
				<div class="kb-sort">
					<a href="<?php echo get_permalink() . '?get_rss=y&c=' . $categoryId; ?>" id="kb-rss" target="_blank">RSS</a>
					<div class="inner_sort">
						<span>Sort by:</span>
						<a href="<?php echo add_query_arg(array('sort'=>'author','order'=>'asc'), get_permalink()); ?>" <?php if($sortBy == 'author') echo 'class="active"';?>>Publisher</a>
						<a href="<?php echo add_query_arg(array('sort'=>'add_date','order'=>'asc'), get_permalink()); ?>" <?php if($sortBy == 'add_date') echo 'class="active"';?>>Date</a>
						<a href="<?php echo add_query_arg(array('sort'=>'title','order'=>'asc'), get_permalink()); ?>" <?php if($sortBy == 'title') echo 'class="active"';?>>Title</a>
					</div>
				</div>
			</div>
			<div class="kb-row">
				<div id="kb-items-1" class="kb-col-12">

					<ul id="kb-items-list">
						<?php if ( count( $this->kbPage->buckets ) > 0 ): ?>
							<?php $this->render_kbuckets_list_alt( $this->kbPage->buckets ); ?>
						<?php else:?>
							<div class="kb-list-item"><b>No result found. Please refine your search</b></div>
						<?php endif; ?>
					</ul>

					<div class="wrap_pagination">
						<span id="kb-pages-count"><?php	echo 'Page ' . (int) $this->kbPage->currentPageI . ' of ' . (int) $this->kbPage->pagesCount; ?></span>
						<div id="kb-pagination"><?php $this->render_pagination_links_alt(); ?></div>
					</div>

				</div>

				<?php if(isset($Kbucket_wd)): ?>
					<div id="kb-tags-wrap-1" class="kb-col-4">
						<?php

						// Render category tags
						$categoryTags = $this->get_category_tags();
						if ( ! empty( $categoryTags ) ) {
							$this->render_tags_cloud(
								$categoryTags,
								'm',
								'main',
								array( 'value' => $this->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
								array( 'mtag' => 'name' )
							);
						}

						// Render related tags
						if ( ! empty( $this->kbPage->relatedTags ) ) { ?>
							<h3>Related Tags</h3>
							<?php
							$this->render_tags_cloud(
								$this->kbPage->relatedTags,
								'r',
								'related',
								array( 'value' => $this->kbPage->relatedTagName, 'dbKey' => 'name', 'title' => 'name' ),
								array( 'mtag' => array( 'raw' => $this->kbPage->activeTagName ), 'rtag' => 'name' )
							);
						}

						// Render Author tags
						if ( $this->kbPage->settings->author_tag_display && isset( $this->kbPage->authorTagName ) && $this->get_author_tags() ) { ?>
							<h3>Author Tags</h3>
							<?php
							$authorTags = $this->get_author_tags();

							$this->render_tags_cloud(
								$authorTags,
								'a',
								'author',
								array( 'value' => $this->kbPage->authorTagName, 'dbKey' => 'author', 'title' => 'author' ),
								array( 'atag' => 'author' )
							);
						} ?>
					</div>
				<?php endif; ?>
			</div><!-- /.kb-row -->
		</div><!-- /#kb-wrapper -->
		<?php $this->render_category_disqus(); ?>
		<noscript><?php _e("Please enable JavaScript to view the") ?> <a href="https://disqus.com/?ref_noscript"><?php _e("comments powered by Disqus.") ?></a></noscript>
		<?php
		$kbContent = ob_get_contents();
		ob_end_clean();

		return $kbContent . $content;
	}


	/**
	 * Render Footer content
	 * Fired by wp_footer filter hook
	 * @param $content
	 * @return string
	 */
	public function filter_wp_footer( $content ) { ?>
		<div class="addthis_toolbox"></div>
		<?php if ( $this->isMobile ) return $content;
		$this->render_addthis_config();
		return $content;
	}

	/*****************************
	 * Content rendering functions
	 *****************************
	 */

	/**
	 * Render Head content for mobile version
	 * Fired by wp_head filter hook
	 */
	private function render_head_mobile(){
		$page = $this->kbPage;
		?>
		<div id="kb-tags" class="sidebar" data-role="panel" data-position="left" data-display="overlay" data-dismissible="false">
			<div class="ui-corner-all custom-corners">
				<a href="#kb-list" data-rel="close" class="ui-btn">Close</a>

				<div class="ui-bar ui-bar-a"><h3>Tags</h3></div>

				<div class="ui-body ui-body-a">
					<?php
					$categoryTags = $this->get_category_tags();
					if ( ! empty( $categoryTags ) ) {
						$this->render_tags_cloud(
						 $categoryTags,
						 'm',
						 'main',
						 array( 'value' => $this->kbPage->activeTagName, 'dbKey' => 'name', 'title' => 'name' ),
						 array( 'mtag' => 'name' )
						);
					}
					?>
				</div>
				<a href="#kb-list" data-rel="close" class="ui-btn">Close</a>
			</div>
		</div>

		<header data-role="header">
			<div id="nav-icon2">
				<span></span>
				<span></span>
				<span></span>
				<span></span>
				<span></span>
				<span></span>
			</div>
		</header>

		<div class="menu">
			<ul>
				<li><a href="<?php echo WPKB_SITE_URL; ?>"><?php _e("Home") ?></a></li>

				<?php $subcategories = array(); ?>
				<?php foreach ( $page->menu['categories'] as $c ): ?>
					<li>
						<a href="<?php echo esc_html( $c->url ); ?>" data-ajax="false"><?php echo esc_html( $c->name ); ?></a>

						<?php if(is_array($c->subcategories)): ?>
							<ul>
								<?php foreach ( $c->subcategories as $subcategoryId ): ?>
									<li>
										<?php $subcategory = $page->menu['subcategories'][$subcategoryId]; ?>
										<?php echo $this->category->id_cat == $subcategory->id_cat ? esc_html( $subcategory->name ) : '<a href="' . esc_attr( $subcategory->url ).'"  rel="external" data-ajax="false">'. esc_html( $subcategory->name ) . '</a>'; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

					</li>
				<?php endforeach; ?>

				<li><a href="#kb-tags">Tags Cloud</a></li>
			</ul>
		</div>

		<script type="text/javascript">
			jQuery('#nav-icon2').click(function(){
				jQuery(this).toggleClass('open');
				jQuery(".menu").slideToggle("slow");
			});
			jQuery( ".menu" ).hide();
		</script>
		<?php
	}

	private function render_kbucket_page_mobile(){
		// Get sharing data
		$shareData = $this->get_kbucket_share_data( $this->kbucket );

		$url = WPKB_SITE_URL . '/' . $shareData['url'];	?>
		<div role="main" class="ui-content">
			<div class="ui-grid-solo">
				<?php if ( ! empty( $shareData['imageUrl'] ) ): ?>
					<img src="<?php	echo esc_attr( $shareData['imageUrl'] );?>" class="share-image" style="max-width:300px;max-height:300px"/>
				<?php endif; ?>
				<h2><a href="<?php echo esc_attr( $this->kbucket->link ); ?>" rel="external" data-ajax="false"><?php echo esc_html( $shareData['title'] ); ?></a></h2>
				<span class="kb-item-date"><?php echo esc_html(  $shareData['kbucket']->add_date ); ?> by </span>
				<span style="color:<?php echo esc_attr( $this->kbPage->settings->author_color ); ?> !important;"><?php echo esc_html( $shareData['kbucket']->author ); ?></span>
				<p>
					<span style="color:<?php echo esc_attr( $this->kbPage->settings->content_color );?> !important;">
						<?php
						echo wp_kses(
							$this->kbucket->description,
							array(
								'a' => array('href' => array(),'title' => array()),
								'br' => array(),
							)
						);?>
					</span>
				</p>
			</div>
		</div>
		<?php $this->render_footer_links();
	}

	public function get_image_kbucket_by_ID($kb_id){
		if(empty($kb_id)) return false;
		$query = $this->wpdb->prepare('SELECT `image_url` FROM `' . $this->wpdb->prefix . 'kbucket` WHERE `id_kbucket`=%s', $kb_id);
		$image = $this->wpdb->get_col( $query );
		if(isset($image[0])) return $image[0];
		else return false;
	}

	/**
	 * Render page content for mobile version
	 * Fired by the_content filter hook
	 * @return string
	 */
	public function render_content_mobile(){
		$murl = $this->get_current_url( 'mtag' ); ?>

		<div role="main" id="kb-items-list" class="ui-content">
			<ul data-role="listview" data-inset="true" data-theme="a" data-divider-theme="a" class="ui-corner-all">
				<?php foreach ( $this->kbPage->buckets as $m ): ?>
					<li class="ui-corner-all kb-list-item">
						<p><?php echo esc_html( $m->add_date ); ?></p>
						<?php $image_url = trim($this->get_image_kbucket_by_ID($m->kbucketId)); ?>
						<div class="wrap_image <?php if(!$image_url) echo 'no_image'; ?>">
							<div class="kbucket_img" style="background-image:url(<?php echo $image_url ?>)"></div>
						</div>
						<h2><a href="<?php echo esc_attr( $m->link ); ?>"><?php echo html_entity_decode(esc_html( $m->title )); ?></a></h2>
						<p><strong><?php echo esc_html( $m->author ); ?></strong></p>
						<p class="desc"><?php echo(html_entity_decode($m->description)) ?></p>

						<p class="kb-wrap">
							<?php if($this->kbucket){
								echo wp_kses(
								 $this->kbucket->description,
								 array(
									'a' => array(
										'href' => array(),
										'title' => array()
									),
									'br' => array(),
								 )
								);
							}?>
						</p>
						<div data-role="controlgroup" data-type="horizontal" class="ui-mini kb-tag-links">
							<?php $this->render_kbucket_tags( $murl, $m->tags, ' ui-btn' ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php $this->render_category_disqus();?>
		</div>
		<?php $this->render_footer_mobile();?>
		<script type="text/javascript">
			jQuery(document).ready(function ($kbj) {
				$kbj('#kb-main-menu a').attr('data-ajax', 'false');
				if (typeof DISQUS !== 'undefined') {
					DISQUS.reset({
						reload: true,
						config: function () {
							this.page.identifier = disqus_identifier;
							this.page.url = disqus_url;
						}
					});
				}
			});
		</script><?php
	}

	/**
	 * Render Footer content for mobile version
	 * Fired by wp_footer filter hook
	 * @return string
	 */
	public function render_footer_mobile(){
		?>
		<div id="kb-footer" data-role="footer" style="overflow:hidden;">
			<h4 style="text-align:center;"><?php
				echo 'Page: ' . (int) $this->kbPage->currentPageI . ' of ' . (int) $this->kbPage->pagesCount; ?></h4>
			<div data-role="navbar">
				<ul>
					<?php $this->render_pagination_links(); ?>
				</ul>
			</div>
		</div>
		<?php
		$this->render_footer_links();
	}

	public function render_footer_links(){ ?>
		<div class="ui-bar-a">
			<p>
				<a href="<?php echo WPKB_SITE_URL; ?>" rel="external" data-ajax="false">View Full Site</a> |
				<a href="<?php echo KBUCKET_URL; ?>" rel="external" data-ajax="false">Kbuckets</a>
			</p>
			<p>
				<a href="<?php echo KBUCKET_URL; ?>/?mobile=n" rel="external" data-ajax="false">Switch to full version</a>
			</p>
		</div>
		<?php $this->render_addthis_config();
	}

	private function render_category_disqus()
	{
		// Ignore Disqus if there is no shortname set (comment system plugin not installed?)
		if ( $this->disqusConfig['shortname'] == '' ) {
			return false;
		}?>
		<div id="disqus_thread"></div>
		<?php
		$this->render_disqus_config(
			$this->disqusConfig['shortname'],
			$this->category->name,
			$this->category->url,
			$this->category->id_cat
		);
	}

	/**
	 * Render Meta tags for share scripts
	 * @param $imageUrl
	 * @param $title
	 * @param $description
	 */
	public function render_meta_tags( $imageUrl, $title, $description, $via = null ){

		$imageUrl = esc_url( $imageUrl );
		$title = trim(esc_attr( $title ));
		$url = esc_url( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$description = html_entity_decode(strip_tags(esc_html($description)));
		if(strlen($description) >= 107){
			$description = substr($description, 0, 107).'...';
		}
		?>
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Expires" content="0" />
		<?php /*<meta property="og:site_name" content="<?php echo esc_attr( $site ); ?>" /> */?>
		<meta property="og:url" content="<?php echo $url; ?>">
		<meta property="og:type" content="website" />
		<meta property="og:title" content="<?php echo $title; ?>"/>
		<meta name="bitly-verification" content="2809eb754836"/>
		<?php if ( ! empty( $imageUrl ) ): ?>
			<link rel="image_src" href="<?php echo $imageUrl; ?>" />
			<meta property="og:image" content="<?php echo $imageUrl; ?>" />

			<meta name="twitter:card" content="photo" />
			<meta name="twitter:title" content="<?php echo $title; ?>" />
			<meta name="twitter:image" content="<?php echo $imageUrl; ?>" />
			<meta name="twitter:image:src" content="<?php echo $imageUrl; ?>" />


		<?php endif;
		if ( ! empty( $description ) ): ?>
			<meta name="description" content="<?php echo $description; ?>" />
			<meta property="og:description" content="<?php echo $description; ?>" />
			<meta name="twitter:description" content="<?php echo $description; ?>" /><?php
		endif;
	}



	/**
	 * Render Addthis Share toolbox
	 * @param array $data
	 */
	private function render_share_toolbox( $data = array() )
	{
		$lastChar = substr( $data['url'], -1 );
		// Stupid hack to get deal with Facebook share (URL needs to be ended with slash to prevent 301 redirect)
		if ( $lastChar !== '/' ) {
			$data['url'] .= '/';
		}
		?>
		<div class="addthis_toolbox addthis_default_style addthis_"<?php
		if ( ! empty( $data['url'] ) ) {
			echo ' data-url="' . esc_attr( $data['url'] ) . '" addthis:url="' . esc_attr( $data['url'] ) . '"';
		}
		if ( ! empty( $data['title'] ) ) {
			echo ' data-title=" ' . esc_attr( $data['title'] ) . '" addthis:title=" ' . esc_attr( $data['title'] ) . '"';
		}
		if ( ! empty( $data['image'] ) ) {
			echo ' addthis:image="' . esc_attr( $data['image'] ) . '"';
		}
		if ( ! empty( $data['description'] ) ) {
			echo ' addthis:description="' . esc_attr( $data['description'] ) . '"';
		}?>>
			<?php
			foreach ( $this->addthisShareButtons as $service => $values ): ?>
				<a class="addthis_button_<?php echo esc_attr( $service ); ?>"<?php
				if ( is_array( $values ) ) {
					foreach ( $values as $key => $val ) {
						echo ' ' . esc_attr( $key ). '="' . esc_attr( $val ) . '"';
					}
				}
				?>></a>
			<?php endforeach ; ?>
		</div>
	<?php
	}

	/**
	 * Render Kbuckets list
	 * @param $kbuckets
	 * @param bool $share
	 */
	private function render_kbuckets_list( $kbuckets, $share = true ){
		foreach ( $kbuckets as $i => $m ){
			$description = wp_kses(
				$m->description,
				array(
					'a' => array(
						'href' => array(),
						'title' => array()
					),
					'br' => array(),
				)
			);

			$kbucket = $this->get_kbucket_by_id( $m->kbucketId );
			if ( empty( $kbucket->post_id ) ) {
				$shareData = $this->get_kbucket_share_data( $kbucket );
			} else {
				$post = get_post( $kbucket->post_id );
				// Get sharing data
				$shareData = array(
					'url' => $kbucket->url_kbucket,
					'imageUrl' => $kbucket->image_url,
					'title' => $kbucket->title,
					'description' => $kbucket->description,
				);
			}

			$url = WPKB_SITE_URL . '/' . $shareData['url'];

			include WPKB_PATH.'templates/kb-item.php';
		}
		?>
		<script type="text/javascript">var addthis_config = {"data_track_addressbar":true};</script>

		<script>
			jQuery(".url_share_addthis").focus(function() { jQuery(this).select(); } );

			jQuery('.morebutton_kbucket').off("click").on('click',function(event) {
				event.preventDefault();
				jQuery(this).parents('.kb-footer').addClass('clearHeight');
				var current_table = jQuery(this).parents('.kb-footer').find('.extended_addthis_share');
				if(!current_table.hasClass('selector')){
					current_table.show().stop().animate({
						opacity: 1,
					},250, function() {
						jQuery(this).addClass('selector');

						initMasonryGrid();
					});
				}else{
					current_table.stop().animate({
						opacity: 0,
					},250, function() {
						jQuery(this).removeClass('selector').hide();
						initMasonryGrid();
					});
				}
			});


		</script>
		<?php
	}


	private function render_kbuckets_list_alt( $kbuckets, $share = true ){
		foreach ( $kbuckets as $i => $m ){
			$description = wp_kses(
				$m->description,
				array(
					'a' => array(
						'href' => array(),
						'title' => array()
					),
					'br' => array(),
				)
			);

			$kbucket = $this->get_kbucket_by_id( $m->kbucketId );
			if ( empty( $kbucket->post_id ) ) {
				$shareData = $this->get_kbucket_share_data( $kbucket );
			} else {
				$post = get_post( $kbucket->post_id );
				// Get sharing data
				$shareData = array(
					'url' => $kbucket->url_kbucket,
					'imageUrl' => $kbucket->image_url,
					'title' => $kbucket->title,
					'description' => $kbucket->description,
				);
			}

			$url = add_query_arg('route', $shareData['url'], get_permalink());

			include WPKB_PATH.'templates/kb-item-alt.php';
		}
		?>
		<script type="text/javascript">var addthis_config = {"data_track_addressbar":true};</script>

		<script>
			jQuery(".url_share_addthis").focus(function() { jQuery(this).select(); } );

			jQuery('.morebutton_kbucket').off("click").on('click',function(event) {
				event.preventDefault();
				jQuery(this).parents('.kb-footer').addClass('clearHeight');
				var current_table = jQuery(this).parents('.kb-footer').find('.extended_addthis_share');
				if(!current_table.hasClass('selector')){
					current_table.show().stop().animate({
						opacity: 1,
					},250, function() {
						jQuery(this).addClass('selector');

						initMasonryGrid();
					});
				}else{
					current_table.stop().animate({
						opacity: 0,
					},250, function() {
						jQuery(this).removeClass('selector').hide();
						initMasonryGrid();
					});
				}
			});


		</script>
		<?php
	}

	/**
	 * Render header content
	 */
	public function render_header_content() {
		$file_param = $this->kbucket_get_file_param();
		$searchtxt = isset( $_REQUEST['srch'] ) ? $_REQUEST['srch'] : ''; ?>
		<div id="kb-header" itemscope itemtype="http://schema.org/WebPage">

			<meta itemprop="about" content="<?=$file_param[0]['name']; ?>" />
			<meta itemprop="comment" content="<?=$file_param[0]['comment']; ?>" />
			<meta itemprop="encoding" content="<?=$file_param[0]['encoding']; ?>" />
			<meta itemprop="publisher" content="<?=$file_param[0]['publisher']; ?>" />
			<meta itemprop="author" content="<?=$file_param[0]['author']; ?>" />
			<meta itemprop="keywords" content="<?=$file_param[0]['keywords']; ?>" />

			<div id="kb-menu" itemscope itemtype="http://schema.org/WebPageElement">
				<div class="kb-right-cont">
					<div id="kb-search">
						<?php
						if ( 1 == $this->kbPage->settings->site_search ): ?>
							<form method="POST" action="<?=add_query_arg('search', 'true'); ?>">
								<input type="text" name="srch" value="<?php
								echo esc_attr( $searchtxt ); ?>" id="kb-search-input" placeholder="Search the kbucket" />
								<button type="submit" value="Search">Search</button>
							</form>
						<?php endif;?>
					</div>
				</div>
				<ul>
					<?php
					$subcategories = array();
					foreach ( $this->kbPage->menu['categories'] as $c ) {
						$class = $this->category->id_cat == $c->id_cat || ( isset( $this->category->parent_cat ) && $this->category->parent_cat == $c->id_cat )	? ' class="kb-menu-active"'	: ''; ?>
						<li <?php echo $class ?>>

							<a href="<?php echo esc_attr( $c->url ) ?>" <?php echo $class ?>><?php echo esc_html( $c->name ); ?></a>
							<?php if($this->category->id_cat == $c->id_cat || ( isset( $this->category->parent_cat ) && $this->category->parent_cat == $c->id_cat )): ?>
								<meta itemprop="about" content="<?=$c->name ?>" />
								<meta itemprop="comment" content="<?=$c->description ?>" />
								<meta itemprop="encoding" content="<?=$c->id_cat ?>" />
								<meta itemprop="keywords" content="" />
							<?php endif; ?>
						</li>
						<?php
						if ( $this->category->id_cat == $c->id_cat ) {
							$subcategories = $c->subcategories;
						}
					} ?>
				</ul>
			</div>
			<div id="kb-submenu" itemscope itemtype="http://schema.org/WebPageElement">


				<ul>
					<?php if(isset( $this->category->parent_cat ) ) $subcategories = $this->kbPage->menu['categories'][ $this->category->parent_cat ]->subcategories;
					foreach ( $subcategories as $subcategoryId ):?>
						<?php $subcategory = $this->kbPage->menu['subcategories'][ $subcategoryId ]; ?>
						<?php $class = $this->category->id_cat == $subcategoryId ? ' class="kb-menu-active"' : ''; ?>
						<li <?php echo $class ?>>
							<?php if($this->category->id_cat == $subcategoryId): ?>
								<meta itemprop="about" content="<?=$subcategory->name ?>" />
								<meta itemprop="comment" content="<?=$subcategory->description ?>" />
								<meta itemprop="encoding" content="<?=$subcategoryId ?>" />
								<meta itemprop="keywords" content="" />
							<?php endif; ?>
							<a href="<?php echo esc_attr($subcategory->url); ?>"<?php echo $class; ?>><?php echo esc_html($subcategory->name); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div><?php
	}


	/**
	 * Render header content
	 */
	public function render_header_content_alt() {
		$file_param = $this->kbucket_get_file_param();
		$searchtxt = isset( $_REQUEST['srch'] ) ? $_REQUEST['srch'] : ''; ?>
		<div id="kb-header" itemscope itemtype="http://schema.org/WebPage">
			<meta itemprop="about" content="<?=$file_param[0]['name']; ?>" />
			<meta itemprop="comment" content="<?=$file_param[0]['comment']; ?>" />
			<meta itemprop="encoding" content="<?=$file_param[0]['encoding']; ?>" />
			<meta itemprop="publisher" content="<?=$file_param[0]['publisher']; ?>" />
			<meta itemprop="author" content="<?=$file_param[0]['author']; ?>" />
			<meta itemprop="keywords" content="<?=$file_param[0]['keywords']; ?>" />

			<div id="kb-menu" itemscope itemtype="http://schema.org/WebPageElement">
				<div class="kb-right-cont">
					<div id="kb-search">
						<?php if ( 1 == $this->kbPage->settings->site_search ): ?>
							<form method="POST" action="<?=add_query_arg('search', 'true'); ?>">
								<input type="text" name="srch" value="<?php	echo esc_attr( $searchtxt ); ?>" id="kb-search-input" placeholder="Search the kbucket" />
								<button type="submit" value="Search">Search</button>
							</form>
						<?php endif;?>
					</div>
				</div>
				<ul>
					<?php
					$subcategories = array();
					foreach ( $this->kbPage->menu['categories'] as $c ) {
						$class = $this->category->id_cat == $c->id_cat || ( isset( $this->category->parent_cat ) && $this->category->parent_cat == $c->id_cat )	? ' class="kb-menu-active"'	: ''; ?>
						<li <?php echo $class ?>>
							<a href="<?php echo add_query_arg( 'route',$c->route, get_permalink() ) ?>" <?php echo $class ?>><?php echo esc_html( $c->name ); ?></a>
							<?php if($this->category->id_cat == $c->id_cat || ( isset( $this->category->parent_cat ) && $this->category->parent_cat == $c->id_cat )): ?>
								<meta itemprop="about" content="<?=$c->name ?>" />
								<meta itemprop="comment" content="<?=$c->description ?>" />
								<meta itemprop="encoding" content="<?=$c->id_cat ?>" />
								<meta itemprop="keywords" content="<?=$c->keywords ?>" />
							<?php endif; ?>
						</li>
						<?php
						if ( $this->category->id_cat == $c->id_cat ) {
							$subcategories = $c->subcategories;
						}
					} ?>
				</ul>

			</div>
			<div id="kb-submenu" itemscope itemtype="http://schema.org/WebPageElement">
				<ul>
					<?php if(isset( $this->category->parent_cat ) ) $subcategories = $this->kbPage->menu['categories'][ $this->category->parent_cat ]->subcategories;
					foreach ( $subcategories as $subcategoryId ):?>
						<?php $subcategory = $this->kbPage->menu['subcategories'][ $subcategoryId ]; ?>
						<?php $class = $this->category->id_cat == $subcategoryId ? ' class="kb-menu-active"' : ''; ?>
						<li <?php echo $class ?>>
							<?php if($this->category->id_cat == $subcategoryId): ?>
								<meta itemprop="about" content="<?=$subcategory->name ?>" />
								<meta itemprop="comment" content="<?=$subcategory->description ?>" />
								<meta itemprop="encoding" content="<?=$subcategoryId ?>" />
								<meta itemprop="keywords" content="<?=$subcategory->keywords ?>" />
							<?php endif; ?>
							<a href="<?php echo add_query_arg('route',$subcategory->route, get_permalink()); ?>"<?php echo $class; ?>><?php echo esc_html($subcategory->name); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div><?php
	}


	/**
	 * Render Javascript section with Addthis configuration variables
	 */
	private function render_addthis_config(){ ?>
		<script type="text/javascript">
			addthis_share = {
				url_transforms : {
					shorten: {
						twitter: 'bitly'
					}
					<?php if ( ! empty( $_GET ) ): ?>,
						add: {
							<?php
							foreach ( $_GET as $key => $val ) {
								echo esc_js( $key ) . ":'" . esc_js( $val ) . "',";
							} ?>
						}
					<?php endif; ?>
				},
				shorteners : {
					bitly : {}
				}
			};
		</script>
		<?php
	}

	/**
	 * Render search content
	 */
	private function render_search_results() {
		include WPKB_PATH.'templates/search.php';
		return true;
	}

	/**
	 * Render pagination
	 */
	private function render_pagination_links()
	{
		$start = $this->kbPage->currentPageI - 3 > 0 ? $this->kbPage->currentPageI - 3 : 1;
		$end = $this->kbPage->currentPageI + 3 < $this->kbPage->pagesCount ? $this->kbPage->currentPageI + 3 : $this->kbPage->pagesCount;
		$purl = $this->get_current_url( 'pagin' );

		for ( $i = $start; $i <= $end; $i ++ ) {
			$mobileAttr = '';
			if ( $this->isMobile ) {
				echo '<li>';
				$mobileAttr = ' rel="external" data-ajax="false"';
			}
			echo $this->kbPage->currentPageI == $i ? '<a href="#ratop"' . $mobileAttr . ' class="' . ( $this->isMobile ? 'ui-btn-active' : 'selected' ) . '"><span>' . $i . '</span></a>' : '<a href="' . $purl . '&amp;pagin=' . $i . '"' . $mobileAttr . '><span>'. $i . '</span></a>';
			if ( $this->isMobile ) {
				echo '</li>';
			}
		}
	}

	/**
	 * Render pagination
	 */
	private function render_pagination_links_alt(){
		$start = $this->kbPage->currentPageI - 3 > 0 ? $this->kbPage->currentPageI - 3 : 1;
		$end = $this->kbPage->currentPageI + 3 < $this->kbPage->pagesCount ? $this->kbPage->currentPageI + 3 : $this->kbPage->pagesCount;
		$purl = $this->get_current_url( 'pagin' );

		for ( $i = $start; $i <= $end; $i ++ ) {
			$mobileAttr = '';
			if ( $this->isMobile ) {
				echo '<li>';
				$mobileAttr = ' rel="external" data-ajax="false"';
			}
			echo $this->kbPage->currentPageI == $i ? '<a href="#ratop"' . $mobileAttr . ' class="' . ( $this->isMobile ? 'ui-btn-active' : 'selected' ) . '"><span>' . $i . '</span></a>' : '<a href="' . add_query_arg('pagin', $i ) . '"' . $mobileAttr . '><span>'. $i . '</span></a>';
			if ( $this->isMobile ) echo '</li>';
		}
	}

	/**
	 * Render tags cloud
	 * @param $tagsArr
	 * @param $sfont
	 * @param $stag
	 * @param $activeTag
	 * @param $linkParams
	 */
	public function render_tags_cloud( $tagsArr, $sfont, $stag, $activeTag, $linkParams )
	{
		if ( empty( $tagsArr ) ) {
			return;
		} ?>
		<div class="kb-tags" >
			<?php
			foreach ( $tagsArr as $i => $tag ):

				if ( 'related' == $stag && $tag->name == $this->kbPage->activeTagName ) {
					continue;
				}
				if(isset($tag->name) && empty($tag->name)) continue;

				$fontSize = $this->kbPage->settings->{$sfont .'4'};
				$tagsCount = (int) $tag->mcnt;
				if ( $tagsCount <= 3 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '1'};
				} else if ( $tagsCount > 3 and $tagsCount <= 7 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '2'};
				} else if ( $tagsCount > 7 and $tagsCount <= 15 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '3'};
				}

				if(isset($tag->name)){
					$custom_value = $tag->name;
				}elseif(isset($tag->author)){
					$custom_value = $tag->author;
				}elseif(isset($tag->publisher)){
					$custom_value = $tag->publisher;
				}

				$linkStyle = isset($custom_value) && $activeTag['value'] == $custom_value ? 'color:#fff!important;font-size:'.$fontSize.'px;background-color:#5269ac' :'font-size:'.$fontSize.'px;';

				$linkUrl = $this->category->url . '?';

				$params = array();
				foreach ( $linkParams as $param => $val ) {
					$val = is_array( $val ) ? $val['raw'] : urlencode( $tag->{$val} );
					$params[] = "$param=" . urlencode( $val );
					if(!$val) continue 2;
				}
				$linkUrl .= implode( '&amp;', $params );
				?>
				<a href="<?php echo esc_attr( $linkUrl ); ?>" style="<?php echo esc_attr( $linkStyle ); ?>"<?php if ( $this->isMobile ) {echo ' class="ui-btn" data-ajax="false"';} ?>><?php echo esc_html( $custom_value ); ?></a>
			<?php
			endforeach;?>
		</div>
	<?php
	}


	public function render_tags_cloud_alt( $tagsArr, $sfont, $stag, $activeTag, $linkParams ){
		if ( empty( $tagsArr ) ) return;
		$output = '';

		$output .= '<div class="kb-tags">';
			foreach ( $tagsArr as $i => $tag ){

				if ( 'related' == $stag && $tag->name == $this->kbPage->activeTagName ) continue;

				if(isset($tag->name) && empty($tag->name)) continue;

				$fontSize = $this->kbPage->settings->{$sfont .'4'};
				$tagsCount = (int) $tag->mcnt;
				if ( $tagsCount <= 3 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '1'};
				} else if ( $tagsCount > 3 and $tagsCount <= 7 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '2'};
				} else if ( $tagsCount > 7 and $tagsCount <= 15 ) {
					$fontSize = $this->kbPage->settings->{$sfont . '3'};
				}

				if(isset($tag->name)){
					$custom_value = $tag->name;
				}elseif(isset($tag->author)){
					$custom_value = $tag->author;
				}elseif(isset($tag->publisher)){
					$custom_value = $tag->publisher;
				}

				$linkStyle = isset($custom_value) && $activeTag['value'] == $custom_value ? 'color:#fff!important;font-size:'.$fontSize.'px;background-color:#5269ac' :'font-size:'.$fontSize.'px;';

				$linkUrl = get_permalink() . '?';

				$params = array();
				foreach ( $linkParams as $param => $val ) {
					$val = is_array( $val ) ? $val['raw'] : urlencode( $tag->{$val} );
					$params[] = "$param=" . urlencode( $val );
					if(!$val) continue 2;
				}
				$linkUrl .= implode( '&amp;', $params );

				$output .= '<a href="'.esc_attr( $linkUrl ).'" style="'.esc_attr( $linkStyle ).'"'.( $this->isMobile ? ' class="ui-btn" data-ajax="false"' : '').'>'.esc_html( $custom_value ).'</a>';

			}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render tag links for Kbucket
	 * @param $baseUrl
	 * @param $tagsStr
	 * @param string $class
	 */
	public function render_kbucket_tags( $baseUrl, $tagsStr, $class = '' ){
		$tagsArr = explode( '~', $tagsStr );
		$tagsStr = '';
		$attr = $this->isMobile ? ' data-ajax="false"' : '';
		foreach ( $tagsArr as $tag ) {
			$tag = explode( '|', $tag );
			if ( ! empty( $tag[1] ) ) {
				$tagsStr .= '<a class="kb-tag-link' . $class . '" style="color:'.esc_attr( $this->kbPage->settings->tag_color ) . ' !important;" href="'. add_query_arg( 'mtag', urlencode( $tag[1] ), $baseUrl ).'"'.$attr.'>'.esc_html( $tag[1] ).'</a>';

				if ( ! $this->isMobile ) {
					$tagsStr .= ',';
				}

				$tagsStr .= ' ';
			}
		}
		$tagsStr = trim( $tagsStr );
		if ( $tagsStr !== '' ) {
			echo substr( $tagsStr, 0, -1 );
		}
	}


	public function get_cat_subcat_array(){
		$url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SERVER['REQUEST_URI'];
		$catRes = array();
		preg_match('/\/kc\/([A-F0-9]+)\//', $url,$matches);
		if(isset($matches[1])){
			if(!empty($this->kbPage->menu['categories'][ $matches[1] ])){
				foreach ( $this->kbPage->menu['categories'] as $subcatKey => $subcategory ){
					foreach ($subcategory->subcategories as $subcat) {
						if($subcat == $matches[1]){

							$subcatRes = $subcat;
						}
					}
					if($subcategory->id_cat == $matches[1]) $catRes = $subcategory;
				}
			}else{
				if(!empty($this->kbPage->menu['categories'])){
					foreach ( $this->kbPage->menu['categories'] as $catKey => $catValue ){
						foreach ($catValue->subcategories as $subcat) {
							if($subcat == $matches[1]){
								$catRes = $catValue;
							}

						}
					}
				}
			}

		}else{
			if(!empty($this->kbPage->menu['categories'])) $catRes = reset($this->kbPage->menu['categories']);
		}

		return array(
			'subcat' => $this->category,
			'cat' => $catRes
		);
	}

	public function get_current_category(){
		$url = $_SERVER['HTTP_REFERER'];
		preg_match('/\/kc\/([A-F0-9]+)\//', $url,$matches);
		if(isset($matches[1])){
			if(!count($this->kbPage->menu['categories'][ $matches[1] ])){
				foreach ( $this->kbPage->menu['categories'] as $subcatKey => $subcategory ){
					foreach ($subcategory->subcategories as $subcat) {
						if($subcat == $matches[1]) return $subcatKey;
					}
				}
			}else{
				return $matches[1];
			}

		}else{
			$subcat = reset($this->kbPage->menu['categories']);
			return $subcat->id_cat;//$subcat->subcategories[0];
		}
	}

	/**
	 * Render Categories dropdown
	 */
	public function render_categories_dropdown(){
		if ( isset( $this->category->parent_cat ) ) {
			$subcategories = $this->kbPage->menu['categories'][ $this->get_current_category() ]->subcategories;
		}

		?>
		<select name="sid_cat" id="sid_cat">
			<?php foreach ( $subcategories as $subcategoryId ): ?>
				<?php $subcategory = $this->kbPage->menu['subcategories'][ $subcategoryId ]; ?>
				<?php $class = (isset($_COOKIE['current_category']) && !empty($_COOKIE['current_category']) && $_COOKIE['current_category'] == $subcategoryId) ? 'selected="selected"' : ''; ?>
				<option <?php echo $class ?> style="padding-left:20px;" value="<?php echo esc_attr( $subcategoryId );?>"><?php echo esc_html( $subcategory->name ); ?></option>
			<?php endforeach; ?>
		</select><?php
	}

	/**
	 * Render suggest window content
	 * Fired by custom shortcode hook: suggest-page
	 */
	public function shortcode_render_suggest_page() {
		$page = $this->get_page_data();
		$this->render_header_content( $page );

		$err = '';
		if ( isset( $_POST['ssugg'] ) ) {

			$requredParams = array(
				'stitle',
				'sdesc',
				'stags',
				'surl',
				'sauthor',
			);

			foreach ( $requredParams as $param ) {
				if ( empty( $_POST[ $param ] ) ) {
					//$err = 'Failed, Please fill all required fields...';
					break;
				}
			}

			if ( $err = '' ) {

				$sid_cat = esc_attr($_POST['sid_cat']);
				$stitle = esc_attr($_POST['stitle']);
				$sdesc = esc_attr($_POST['sdesc']);
				$stags = esc_attr($_POST['stags']);
				$sauthor = esc_attr($_POST['sauthor']);
				$surl = esc_url($_POST['surl']);
				$stwitter = esc_attr($_POST['stwitter']);
				$sfacebook = esc_attr($_POST['sfacebook']);

				$sql = $this->wpdb->prepare(
				 'INSERT IGNORE INTO `' . $this->wpdb->prefix . "kb_suggest`
				  SET `id_sug`='',
					`id_cat`=%s,
					`tittle`=%s,
					`description`=%s,
					`tags`=%s,
					`add_date`=CURRENT_DATE,
					`author`=%s,
					`link`=%s,
					`twitter`=%s,
					`facebook`=%s,
					`status`='0'",
				 array(
					$sid_cat,
					$stitle,
					$sdesc,
					$stags,
					$sauthor,
					$surl,
					$stwitter,
					$sfacebook,
				 )
				);

				$err = $this->wpdb->query( $sql ) ? 'Suggest link has been added successfully...' : 'Inserted Failed...';
			}
		} ?>
		<div class="container">
			<div class="con_suggest">
				<h1>Suggested page URL's </h1>
				<?php if ( $err != '' ): ?>
					<p><label>&nbsp;</label><span style="font-weight:bold; color:red;"><?=$err;	?></span></p>
				<?php endif; ?>
				<form action="" method="post">
					<p>
						<label>Category</label><span><?php $this->render_categories_dropdown(); ?></span>
					</p>
					<p>
						<label for="stitle">Page Tittle* :</label>
						<span><input type="text" name="stitle" id="stitle" value="<?php	echo @$_REQUEST['stitle']; ?>"/></span>
					</p>
					<p>
						<label for="sdesc">Description* : </label>
						<span><input type="text" name="sdesc" id="sdesc" value="<?php echo @$_REQUEST['sdesc']; ?>"/></span>
					</p>
					<p>
						<label for="stags">Tags* : </label>
						<span><input type="text" name="stags" id="stags" value="<?php echo @$_REQUEST['stags']; ?>"/></span>
					</p>
					<p>
						<label for="surl">Page URL* :</label>
						<span><input type="text" name="surl" id="surl" value="<?php	echo @$_REQUEST['surl']; ?>"/></span>
					</p>
					<p>
						<label for="sauthor">Author* :</label>
						<span><input type="text" name="sauthor" id="sauthor" value="<?php echo @$_REQUEST['sauthor']; ?>"/></span>
					</p>
					<p>
						<label for="stwitter">Twitter Information page of author:</label>
						<span><input type="text" name="stwitter" id="stwitter" value="<?php	echo @$_REQUEST['stwitter']; ?>"/></span>
					</p>
					<p>
						<label for="sfacebook">Facebook information page of author:</label>
						<span><input type="text" name="sfacebook" id="sfacebook" value="<?php echo @$_REQUEST['sfacebook']; ?>"/></span>
					</p>
					<p>
						<label for="ssugg">&nbsp;</label>
						<span><input type="submit" name="ssugg" id="ssugg" value="Add New Suggest"/></span>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	public function render_disqus_config( $shortname, $title, $url, $id = 1) {
		?>
		<script type="text/javascript">
			var disqus_shortname = '<?php echo esc_js( $shortname ); ?>',
				disqus_title = '<?php echo esc_js( $title ); ?>',
				disqus_url = '<?php echo esc_js( $url ); ?>',
				disqus_identifier = '<?php echo esc_js( $shortname ); ?>-<?php echo esc_js( $id ); ?>';
		</script>
		<?php if ($this->isMobile): ?>
			<script type="text/javascript">
				(function() {
					var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
					dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
					(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
				})();
			</script>
			<noscript>Please enable JavaScript to view the <a href="//disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
			<a href="//disqus.com" class="dsq-brlink">blog comments powered by <span class="logo-disqus">Disqus</span></a>
		<?php return; endif;

		wp_enqueue_script( 'disqus_embed', '//' . $shortname . '.disqus.com/embed.js' );
	}

	/**
	 * @param $m
	 * @return array|bool
	 */
	private function get_kbucket_share_data( $m ){
		// Exit if kbucket record was not found
		if ( empty( $m ) ) {
			return false;
		}

		$share = array( 'kbucket' => $m );

		$share['url'] = $m->url_kbucket;
		$share['imageUrl'] = $m->image_url;
		$share['title'] = $m->title;
		$share['description'] = strip_tags( $m->description );

		return $share;
	}


	/**
	 * Get categories or subcategories depending from $id_parent argument
	 * Result can be filtered with columns returned
	 * @param int $id_parent
	 * @param bool $columns
	 * @return mixed
	 */
	private function get_subcategories( $id_parent = 0, $columns = false, $setsocial=false ) {

		if ( ! $columns ) {
			$columns = '`id_cat`,`name`,`level`,`image`,`description`';
		}

		if($id_parent == '0'){
			if($setsocial) $sql = "SELECT $columns	FROM {$this->wpdb->prefix}kb_category WHERE parent_cat=''";
			else $sql = "SELECT $columns	FROM {$this->wpdb->prefix}kb_category WHERE parent_cat='' AND id_cat IN	(SELECT parent_cat FROM {$this->wpdb->prefix}kbucket a INNER JOIN {$this->wpdb->prefix}kb_category b ON a.id_cat=b.id_cat WHERE level=2	GROUP BY parent_cat)";
			$sql = "SELECT $columns	FROM {$this->wpdb->prefix}kb_category WHERE parent_cat=''";
		}else{
			$id_parent = esc_sql( $id_parent );
			if($setsocial) $sql = "SELECT $columns FROM {$this->wpdb->prefix}kb_category WHERE parent_cat='$id_parent'";
			else $sql = "SELECT $columns FROM {$this->wpdb->prefix}kb_category WHERE parent_cat='$id_parent' AND id_cat IN(SELECT id_cat FROM {$this->wpdb->prefix}kbucket GROUP BY id_cat)";
		}

		$res = $this->wpdb->get_results( $sql, ARRAY_A );

		return $res;
	}

	/**
	 * @param bool $categoryId
	 * @return bool
	 */
	private function get_category_by_id( $categoryId = false ){
		return isset( $this->kbPage->menu['categories'][ $categoryId ] ) ? $this->kbPage->menu['categories'][ $categoryId ]	: false;
	}


	/**
	 * Get subcategory data
	 * @param bool $categoryId
	 * @return bool
	 */
	private function get_subcategory_by_id( $categoryId = false ){
		if(isset( $this->kbPage->menu['subcategories'][ $categoryId ] )){
			return $this->kbPage->menu['subcategories'][ $categoryId ];
		}else{
			return false;
		}
	}

	/**
	 * @param bool $categoryId
	 * @return bool
	 */
	private function get_first_subcategory( $categoryId = false ){

		$subcategory = false;
		$category = false;

		if ( $categoryId && ! empty( $this->kbPage->menu['categories'][ $categoryId ] ) ) {
			$category = $this->kbPage->menu['categories'][ $categoryId ];
		} elseif ( ! empty( $this->kbPage->menu['categories'] ) ) {
			reset( $this->kbPage->menu['categories'] );
			$categoryIndex = key( $this->kbPage->menu['categories'] );
			$category = $this->kbPage->menu['categories'][ $categoryIndex ];
		}

		if ( ! empty( $category->subcategories ) ) {
			reset( $category->subcategories );
			$sIndex = key( $category->subcategories );
			$subcategoryId = $category->subcategories[ $sIndex ];
			if ( ! empty( $subcategoryId ) ) {
				$subcategory = $this->kbPage->menu['subcategories'][ $subcategoryId ];
			}
		}

		return $subcategory;
	}

	/**
	 * Get the class object instance
	 */
	public static function get_kbucket_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get Kbuckets records by where criteria
	 * @param $where
	 * @return mixed -numerically indexed array of row objects.
	 */
	private function get_kbuckets( $where ){
		$sql = "
				SELECT a.id_kbucket,
				a.id_cat,
				a.title,
				a.description,
				a.link,
				a.author,
				a.twitter,
				a.publisher,
				a.add_date,
				a.pub_date,
				tags,
				a.image_url,
				a.short_url,
				a.post_id,
				c.name AS categoryName,
				a.url_kbucket
			FROM {$this->wpdb->prefix}kbucket a
			LEFT JOIN
			(
				SELECT a.id_kbucket,GROUP_CONCAT(name) as tags
				FROM {$this->wpdb->prefix}kbucket a
				LEFT JOIN {$this->wpdb->prefix}kb_tags b ON b.id_kbucket  = a.id_kbucket
				LEFT JOIN {$this->wpdb->prefix}kb_tag_details c ON c.id_tag = b.id_tag
				WHERE $where
			) b ON a.id_kbucket = b.id_kbucket
			LEFT JOIN {$this->wpdb->prefix}kb_category c ON a.id_cat=c.id_cat
			WHERE $where";
		return $this->wpdb->get_results( $sql );
	}

	/**
	 * Get Kbucket record by Kbucket id
	 * @param $kid
	 * @return mixed - row object or false on failure
	 */
	public function get_kbucket_by_id( $kid ){
		$kid = esc_sql( $kid );
		$kbucket = $this->get_kbuckets( "a.`id_kbucket`='$kid'" );
		return ! empty( $kbucket[0] ) ? $kbucket[0] : false;
	}

	/**
	 * Get tags by current category id
	 * @return array - numerically indexed array of row objects.
	 */
	public function get_category_tags(){
		$sql = 'SELECT t.`id_tag`,
						td.`name`,
						k.`title`,
						COUNT(t.`id_tag`) AS mcnt
				FROM `' . $this->wpdb->prefix . 'kbucket` k
				JOIN `' . $this->wpdb->prefix . 'kb_tags` t ON k.`id_kbucket`=t.`id_kbucket`
				JOIN `' . $this->wpdb->prefix . 'kb_tag_details` td ON td.`id_tag`=t.`id_tag`
				WHERE k.`id_cat`=%s AND td.name IS NOT NULL
				GROUP BY td.name
				ORDER BY td.name';

		if(!empty($this->category->id_cat)){
			$values = array( $this->category->id_cat );
			$query = $this->wpdb->prepare( $sql, $values );
			return $this->wpdb->get_results( $query );
		}else return;


	}

	/**
	 * Get Author tags by current category id
	 * @return array - numerically indexed array of row objects.
	 */
	public function get_author_tags(){
		$sql = 'SELECT k.`author`,
						(
							SELECT COUNT(*)
							FROM `' . $this->wpdb->prefix . "kb_tags`
							WHERE `status` ='1' AND id_kbucket=k.id_kbucket
						) AS mcnt
				FROM `" . $this->wpdb->prefix . 'kbucket` k
				WHERE k.`id_cat`=%s
				GROUP BY `author`
				ORDER BY `author`
				LIMIT 0 , ' . (int) $this->kbPage->settings->author_no_tags;
		if(!empty($this->category->id_cat)){
			$values = array( $this->category->id_cat );
			$query = $this->wpdb->prepare( $sql, $values );
			return $this->wpdb->get_results( $query );
		}else return;
	}


	/**
	 * Get Publisher tags by current category id
	 * @return array - numerically indexed array of row objects.
	 */
	public function get_publisher_tags()
	{
		$sql = "SELECT DISTINCT k.publisher,
						(
							SELECT COUNT(*)
							FROM {$this->wpdb->prefix}kb_tags
							WHERE status=1 AND id_kbucket=k.id_kbucket
						) AS mcnt
				FROM {$this->wpdb->prefix}kbucket k
				WHERE k.id_cat=%s
				ORDER BY 'publisher'
				LIMIT 0 , %d";

		if(!empty($this->category->id_cat) && !empty($this->kbPage->settings->author_no_tags)){
			$values = array( $this->category->id_cat, (int) $this->kbPage->settings->author_no_tags );
			$query = $this->wpdb->prepare( $sql, $values );
			return $this->wpdb->get_results( $query );
		}else return;


	}

	/**
	 * Get related tags by active tag name and current category id
	 * @param $activeTagName
	 */
	private function set_related_tags( $activeTagName )
	{
		$sql = 'SELECT t.`id_tag`, d.`name`, k.`title`,k.`id_kbucket`,
				COUNT(t.`id_tag`) AS mcnt
				FROM `' . $this->wpdb->prefix . 'kb_tag_details` d
				JOIN `' . $this->wpdb->prefix . 'kb_tags` t ON t.`id_tag`=d.`id_tag`
				JOIN `' . $this->wpdb->prefix . 'kbucket` k ON k.`id_kbucket`=t.`id_kbucket`
				WHERE k.`id_cat`=%s AND k.`id_kbucket` IN (
					SELECT t.`id_kbucket` FROM `' . $this->wpdb->prefix . 'kb_tag_details` d
					JOIN `' . $this->wpdb->prefix . 'kb_tags` t ON t.id_tag=d.id_tag
					WHERE d.`name`=%s
				)
				GROUP BY d.`name`
				ORDER BY d.`name`
				LIMIT 0,'. (int) $this->kbPage->settings->related_no_tags;

		$query = $this->wpdb->prepare( $sql, array( $this->category->id_cat, $activeTagName ) );
		$this->kbPage->relatedTags = $this->wpdb->get_results( $query );
	}

	/**
	* Create Kbuckets listings page URL by multiple params
	* @param $sort
	* @param $tag
	* @return string
	*/
	private function get_current_url( $sort, $tag = false ) {

		$url = ! empty( $this->category ) ? $this->category->url : KBUCKET_URL . '/';

		$params = array();

		$url .= '?';

		if ( isset( $_REQUEST['atag'] ) ) {$params[] = 'atag=' . $_REQUEST['atag'];}
		if ( isset( $_REQUEST['ptag'] ) ) {$params[] = 'ptag=' . $_REQUEST['ptag'];}

		if ( 'mtag' == $sort ) {
			if ($tag) {
				return $url . 'mtag=' . $tag;
			}

			if ( isset( $_REQUEST['sort'] ) ) {
				$params[] = 'sort=' . $_REQUEST['sort'] . '&amp;order=' . $_REQUEST['order'];
			}

			return $url . implode( '&amp;', $params );
		}

		if ( isset( $_REQUEST['mtag'] ) ) {
			$params[] = 'mtag=' . $_REQUEST['mtag'];
		}

		if ( isset( $_REQUEST['rtag'] ) ) {
			$params[] = 'rtag=' . $_REQUEST['rtag'];
		}

		if ( 'pagin' == $sort ) {
			if ( isset( $_REQUEST['sort'] ) ) {
				$params[] = 'sort=' . $_REQUEST['sort'] . '&amp;order=' . $_REQUEST['order'];
			}
		} else if ( isset( $_REQUEST['sort'] ) ) {
			if ( $sort == $_REQUEST['sort'] ) {
				if ( 'asc' == $_REQUEST['order'] ) {
					$params[] = 'sort=' . $sort . '&amp;order=desc';
				} else {
					$params[] = 'sort=' . $sort . '&amp;order=asc';
				}
			} else {
				$params[] = 'sort=' . $sort . '&amp;order=asc';
			}
		} else {
			$params[] = 'sort=' . $sort . '&amp;order=asc';
		}

		return $url . implode( '&amp;', $params );
	}

	/**
	 * Access Kbucket listing page data
	 * @return stdClass
	 */
	public function get_page_data(){
		return $this->kbPage;
	}

	/**
	 * Set mobile layout template if mobile device detected or cookie option has been set
	 */
	public function is_mobile(){
		if ( wp_is_mobile() ) {
			$this->isMobile = true;
		} elseif ( isset( $_COOKIE['kb-mobile'] ) && 'y' == $_COOKIE['kb-mobile'] ) {
			$this->isMobile = true;
		}

		if ( isset( $_GET['mobile'] ) ) {
			$switch = $_GET['mobile'];
			setcookie( 'kb-mobile', $switch );
			if ( $switch = 'n' ) {
				$this->isMobile = false;
			}
		}

		if ( ( isset( $_GET['mobile'] ) && 'y' == $_GET['mobile'] ) ) {
			$this->isMobile = true;
		}

		if ( $this->isMobile ) {

			$this->template = 'mobile.php';
		}
	}

	/**
	 * Set Kbucket listings page object
	 */
	public function init_kbucket_section(){
		//define( 'WPKB_PAGE_URL', get_page_link() );

		$this->isKbucketPage = $this->is_kbucket_page();

		if ( $this->isKbucketPage || is_vc_activate() ) {

			//Set menu data
			$sql = "
				SELECT
					c.id_cat AS categoryId,
					c.name AS categoryName,
					c.image AS categoryImage,
					c.description AS categoryDescription,
					c.keywords AS categoryKeywords,
					s.id_cat AS subcategoryId,
					s.name AS subcategoryName,
					s.image AS subcategoryImage,
					s.description AS subcategoryDescription,
					s.keywords AS subcategoryKeywords,
					s.parent_cat
				FROM {$this->wpdb->prefix}kb_category c
				LEFT JOIN {$this->wpdb->prefix}kb_category s ON s.parent_cat=c.id_cat
				WHERE c.parent_cat=''";

			$res = $this->wpdb->get_results( $sql );

			$menu = array( 'categories' => array(), 'subcategories' => array() );

			foreach ( $res as $cat ) {
				if ( ! isset( $menu['categories'][ $cat->categoryId ] ) ) {
					$category = new stdClass();
					$category->id_cat = $cat->categoryId;
					if(!is_vc_activate()) setcookie('current_cat', $category->id_cat, 0, '/');
					$category->url = KBUCKET_URL . '/' . sanitize_title_with_dashes( $cat->categoryName ) . '/kc/' . $cat->categoryId . '/';
					$category->route = sanitize_title_with_dashes( $cat->categoryName ) . '/kc/' . $cat->categoryId . '/';
					$category->name = $cat->categoryName;
					$category->image = $cat->categoryImage;
					$category->description = $cat->categoryDescription;
					$category->keywords = !empty($cat->categoryKeywords) ? $cat->categoryKeywords : '';
					$category->subcategories = array();
					$menu['categories'][ $cat->categoryId ] = $category;
				}

				$subcategory = new stdClass();
				$subcategory->id_cat = $cat->subcategoryId;
				$subcategory->url = KBUCKET_URL.'/'.sanitize_title_with_dashes($cat->categoryName).'/'.sanitize_title_with_dashes($cat->subcategoryName).'/kc/'.$cat->subcategoryId.'/';
				$subcategory->route = sanitize_title_with_dashes($cat->categoryName).'/'.sanitize_title_with_dashes($cat->subcategoryName).'/kc/'.$cat->subcategoryId.'/';
				$subcategory->name = $cat->subcategoryName;
				$subcategory->image = $cat->subcategoryImage;
				$subcategory->description = $cat->subcategoryDescription;
				$subcategory->parent_cat = $cat->parent_cat;
				$subcategory->keywords = $cat->subcategoryKeywords;

				$menu['categories'][ $cat->categoryId ]->subcategories[] = $cat->subcategoryId;
				$menu['subcategories'][ $cat->subcategoryId ] = $subcategory;
			}

			$this->kbPage->menu = $menu;

			global $wp;

			if(!is_vc_activate()){
				$requestArr = explode( '/', $wp->request );
			}else{
				if(!empty($_GET['route'])){
					$requestArr = explode( '/', $_GET['route']);
					if(empty(end( $requestArr ))){
						$end = key( array_slice( $requestArr, -1, 1, TRUE ) );
						unset($requestArr[$end]);
					}else{
						$requestArr = array();
					}
				}

			}

			if(!empty($requestArr)){
				// Kbucket id or category is probably passed at the end of URL
				$keyId = array_pop( $requestArr );

				// Two  possible slugs:
				// kb - kbucket
				// kc - category
				$keySlug = array_pop( $requestArr );

				$categoryId = false;

				// If kb identifier exists,set Kbucket property
				if ( 'kb' == $keySlug ) {
					$this->kbucket = $this->get_kbucket_by_id( $keyId );
					if(isset($this->kbucket->id_cat)) $categoryId = $this->kbucket->id_cat;
				}

				// If kc identifier exists, assign Category id
				elseif ( 'kc' == $keySlug ) {
					$categoryId = $keyId;
				}
			}else{
				$categoryId = false;
			}



			// Read category data
			$this->category = $categoryId ? $this->get_category_by_id( $categoryId ) : false;

			if ( $categoryId && ! $this->category ) {
				$this->category = $this->get_subcategory_by_id( $categoryId );
			}

			$this->kbPage->buckets = array();
			$this->kbPage->pagesCount = 0;
			$this->kbPage->currentPageI = 1;

			if ( ! $this->category ) {
				// Assign first category from existing
				$this->category = $this->get_first_subcategory();
			}

			if ( isset( $this->category->subcategories ) ) {
				$this->category = $this->get_first_subcategory( $categoryId );
			}

			$this->kbPage->relatedTags = array();
			$this->kbPage->activeTagName = '';
			$this->kbPage->relatedTagName = '';
			$this->kbPage->authorTagName = '';
			$this->kbPage->publisherTagName = '';

			// Select by tag name if has been passed
			if ( ! empty( $_REQUEST['mtag'] ) ) {
				$this->kbPage->activeTagName = urldecode( substr( $_REQUEST['mtag'], 0 , 80 ) );
				$this->set_related_tags( $this->kbPage->activeTagName );
			}

			$values = array();

			//Build query to select Kbuckets
			$sql = "
				SELECT id_kbucket AS kbucketId,
					id_cat,
					title,
					description,
					link,
					author,
					publisher,
					pub_date AS add_date,
					(
						SELECT GROUP_CONCAT(CONCAT(t.`id_tag`, '|', td.`name`) SEPARATOR '~' )
						FROM {$this->wpdb->prefix}kb_tags t
						JOIN {$this->wpdb->prefix}kb_tag_details td	ON td.id_tag=t.id_tag
						WHERE t.id_kbucket = kbucketId
					) AS tags
				FROM {$this->wpdb->prefix}kbucket WHERE ";

			$values[] = (isset($this->category->id_cat) ? $this->category->id_cat : 0);

			$where = "status='1' AND id_cat=%s ";

			// Select by author name if has been passed
			if ( ! empty( $_REQUEST['atag'] ) ) {
				$this->kbPage->authorTagName  = urldecode( substr( $_REQUEST['atag'], 0, 60 ) );
				$where .= ' AND (`author_alias`=%s OR `author`=%s) ';
				$values[] = $this->kbPage->authorTagName;
				$values[] = $this->kbPage->authorTagName;
			}

			// Select by publisher name if has been passed
			if ( ! empty( $_REQUEST['ptag'] ) ) {
				$this->kbPage->publisherTagName  = urldecode( substr( $_REQUEST['ptag'], 0, 60 ) );
				$where .= ' AND (`author_alias`=%s OR `publisher`=%s) ';
				$values[] = $this->kbPage->publisherTagName;
				$values[] = $this->kbPage->publisherTagName;
			}



			if ( $this->kbPage->activeTagName !== '' ) {
				$tagIds[] = $this->kbPage->activeTagName;
				$where .= 'AND `id_kbucket`
						IN (
							SELECT t.`id_kbucket` FROM `' . $this->wpdb->prefix . 'kb_tags` t
							JOIN `' . $this->wpdb->prefix . 'kb_tag_details` td
								ON td.`id_tag`=t.`id_tag`
							WHERE td.`name`=%s)';

				$values[] = $this->kbPage->activeTagName;

				// Select by active and related tag name if both has been passed
				if ( ! empty( $_REQUEST['rtag'] ) ) {

					$this->kbPage->relatedTagName = urldecode( substr( $_REQUEST['rtag'], 0 , 80 ) );

					$where .= ' AND `id_kbucket`
						IN (
							SELECT t.`id_kbucket` FROM `' . $this->wpdb->prefix . 'kb_tags` t
							JOIN `' . $this->wpdb->prefix . 'kb_tag_details` td
								ON td.`id_tag`=t.`id_tag`
							WHERE td.`name`=%s
							)';
					$values[] = $this->kbPage->relatedTagName;
				}
			}

			$sql .= $where;

			$countSql = "SELECT COUNT(*) FROM {$this->wpdb->prefix}kbucket WHERE {$where}";
			$countQuery = $this->wpdb->prepare( $countSql, $values );
			$kbucketsCount = $this->wpdb->get_var( $countQuery );

			$sortCols = array( 'author', 'add_date', 'title' );

			if ( ! empty( $_REQUEST['sort'] ) && in_array( $_REQUEST['sort'], $sortCols ) ) {
				$sql .= " ORDER BY {$_REQUEST['sort']}";
				if ( isset( $_REQUEST['order'] ) && ( 'asc' == $_REQUEST['order'] || 'desc' == $_REQUEST['order'] ) ) {
					$sql .= " {$_REQUEST['order']}";
				}
			} else {
				$sql .= " ORDER BY {$this->kbPage->settings->sort_by} {$this->kbPage->settings->sort_order}";
			}

			$pagin = isset( $_REQUEST['pagin'] ) ? (int) $_REQUEST['pagin'] : 1;

			$start = ( $pagin - 1 ) * $this->kbPage->settings->no_listing_perpage;

			$sql .= " LIMIT $start," . $this->kbPage->settings->no_listing_perpage;

			$query = $this->wpdb->prepare( $sql, $values );

			$this->kbPage->buckets = $this->wpdb->get_results( $query );

			$this->kbPage->pagesCount = ceil( $kbucketsCount / $this->kbPage->settings->no_listing_perpage );

			$this->kbPage->currentPageI = $pagin;
		}

		$this->kbucketCurrentUrl = $this->get_current_url( 'mtag' );
	}

	/**
	 * Build query string for search query
	 * @param $phrase
	 * @param $cols
	 * @param $operands
	 * @return array
	 */
	private function create_search_condition( $phrase, $cols, $operands )
	{
		$words = explode( ' ', $phrase );
		$conditionsArr = array();
		$values = array();
		$likeConditions = array();

		foreach ( $cols as $col ) {
			foreach ( $words as $word ) {
				if ( $word == '' ) {
					continue;
				}
				foreach ( $operands as $operand ) {
					$conditionsArr[] = "`$col`$operand" . ( $operand == 'LIKE' ? " '%%%s%%'" : '%s' );
					$values[] = $word;
				}
			}
			$likeConditions = implode( ' OR ', $conditionsArr );
		}

		return array( 'conditions' => $likeConditions, 'values' => $values );
	}

	/**
	 * Get result notification content for dashboard
	 * @param $result
	 * @return string
	 */
	private function dashboard_get_result_message( $result )
	{
		$msg = '';

		if ( is_string( $result ) ) {
			$result = array( 'error' => array( $result ) );
		}

		foreach ( $result as $type => $messages ) {
			foreach ( $messages as $message ) {
				$msg .= '
				<div class="updated updated-' . $type . '">
					<p><strong>' . $message . '</strong></p>
				</div>';
			}
		}

		return $msg;
	}


	/**
	 * Scrape Kbucket image URL from external site
	 * Trying to get og:image content value from meta tag
	 * @param $url
	 * @return bool|string
	 */
	public function scrape_og_image( $url )
	{
		$content = $this->curl_get_result( $url );

		libxml_use_internal_errors( true );

		$dom = new DOMDocument();

		if ( $content == '' || false == @$dom->loadHTML( $content ) ) {
			return false;
		}

		$xpath = new DOMXPath( $dom );



		$expr = '//meta[@property="og:image"]/@content';

		// Trying to scrape first image from article containing description
		$nodeList = $xpath->query( $expr );

		$node = is_object( $nodeList ) && is_object( $nodeList->item( 0 ) )
			? $nodeList->item( 0 )->nodeValue
			: false;

		return $node;
	}

	/**
	 * Save Kbucket image by URL as WP Post attachment
	 * @param $imageUrl
	 * @return array|bool
	 */
	private function save_post_image( $imageUrl )
	{
		if ( ! class_exists( 'WP_Http' ) ) {
			include_once( ABSPATH . WPINC. '/class-http.php' ); }

		$httpObj = new WP_Http();

		$photo = $httpObj->request( $imageUrl );
		if ( is_object( $photo ) || $photo['response']['code'] != 200 ) {
			return false;
		}

		global $user_login;

		$attachment = wp_upload_bits(
		 $user_login . '.jpg',
		 null,
		 $photo['body'],
		 date( 'Y-m', strtotime( $photo['headers']['last-modified'] ) )
		);

		return $attachment;
	}


	function getCatByName($empty=false){
		global $wpdb;
		$sql = "SELECT name,description,image,parent_cat FROM {$wpdb->prefix}kb_category";
		$result = $wpdb->get_results($sql);
		$new_res = array();
		foreach ($result as $key => $cat) {
			if(!$cat->description && !$empty) continue; // Pass empty description
			if(!$cat->parent_cat) continue; // Pass parent category
			$new_res[$cat->name]['description'] = $cat->description;
			$new_res[$cat->name]['image'] = $cat->image;
		}
		return $new_res;
	}


	function getAPIKey(){
		return (isset($this->kbPage->settings->api_key) ? $this->kbPage->settings->api_key : false);
	}

	function isTrialKey(){
		global $wpdb;
		$key = $this->getAPIKey();

		$col = $this->wpdb->query("SHOW COLUMNS FROM {$this->wpdb->prefix}kb_page_settings LIKE 'api_trial'");
		if(!$col){
			$this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD api_key varchar(255) DEFAULT NULL AFTER 'id_page'");
			$this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD api_trial varchar(2) DEFAULT NULL AFTER 'api_key'");
			$this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD kb_share_popap TEXT DEFAULT NULL AFTER 'api_trial'");
		}

		$sql = $wpdb->prepare("SELECT api_trial FROM {$this->wpdb->prefix}kb_page_settings WHERE api_key=%s",$key);
		$result = $wpdb->get_results($sql);
		if(isset($result[0]->api_trial)){
			if($result[0]->api_trial == '1') return true;
			else return false;
		}else return false;
	}



	/**
	 * Dashboard functionality for Kbucket management
	 */
	public function render_dashboard() {

		$amsg = '';

		$data = get_plugin_data(__FILE__);
		echo '<h1>Kbucket <span style="font-size: 10px;font-family: Arial,sans-serif;font-weight: normal;">v'.$data['Version'].'</span></h1>';

		if ( isset( $_REQUEST['submit'] ) ) {
			$submit = $_REQUEST['submit'];

			switch ( $submit ) {

				// Update tag
				case 'Update Tag':
					$this->wpdb->update(
						$this->wpdb->prefix . 'kbucket',
						array( 'author_alias' => $_REQUEST['tag_name'] ),
						array( 'author' => $_REQUEST['author'] )
					);
					$amsg = 'Auther alias name has been successfully updated';
					break;

				// Delete Author
				case 'Delete Author':
					$this->wpdb->delete(
						$this->wpdb->prefix . 'kbucket',
						array( 'author' => $_REQUEST['author'] )
					);
					$amsg = 'Auther data have been successfully deleted';
					break;

				// Import Kbuckets via XML upload
				case 'Upload XML':
					//$res = $this->run_admin_action( 'uploadXml' );
					//$amsg = $this->dashboard_get_result_message( $res );
					break;

				// Add Category
				case 'Add Category':

					$data = array(
						'name' => $_REQUEST['category'],
						'add_date' => date( 'Y-m-d' ),
					);

					if ( $_REQUEST['parent_category'] != '' ) {
						$parCatArr = explode('#', $_REQUEST['parent_category'] );

						$data['parent_cat'] = $parCatArr[0];
						$data['level'] = $parCatArr[1] + 1;

						$esc = array(
							'%s',
							'%s',
							'$s',
							'%d',
						);
					} else {
						$data['level'] = 1;

						$esc = array(
							'%s',
							'%s',
							'%d'
						);
					}

					$this->wpdb->insert(
						$this->wpdb->prefix . 'kb_category',
						$data,
						$esc
					);
					break;

				// Add Settings
				case 'Apply Settings':
					$this->wpdb->insert(
						$this->wpdb->prefix . 'kb_page_settings',
						array(
							'sort_by' => $_REQUEST['sortBy'],
							'sort_order' => $_REQUEST['sortOrder'],
							'no_listing_perpage' => $_REQUEST['no_listing_page'],
							'main_font_min' => $_REQUEST['main_font_min'],
							'main_font_max' => $_REQUEST['main_font_max'],
							'main_no_tags' => $_REQUEST['no_main_tags'],
							'author_font_min' => $_REQUEST['author_font_min'],
							'author_font_max' => $_REQUEST['author_font_max'],
							'author_no_tags' => $_REQUEST['no_author_tags'],
							'related_font_min' => $_REQUEST['related_font_min'],
							'related_font_max' => $_REQUEST['related_font_max'],
							'related_no_tags' => $_REQUEST['no_related_tags'],
						)
					);
					break;

				// Update Settings
				case 'Update Settings':

					$apikey = $this->getAPIKey();
					if(!$apikey || $apikey !== $_REQUEST['api_key'] && !empty($_REQUEST['api_key'])){
						// Send reg api key
						$url = 'https://optimalaccess.com/api-gateway/reg-api-key';
						$data = array('apiKey' => $_REQUEST['api_key'],'apiHost' => $_REQUEST['api_host'],'uid'=>get_current_user_id());

						// use key 'http' even if you send the request to https://...
						$options = array(
							'http' => array(
								'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
								'method'  => 'POST',
								'content' => http_build_query($data)
							)
						);
						$context  = stream_context_create($options);
						$result = file_get_contents($url, false, $context);
						if ($result === FALSE) {
							/* Handle error */
						}else{
							if(!empty($result)) {
								$result = json_decode($result);
								if(isset($result->success) && $result->success){
									$amsg = $this->dashboard_get_result_message( array( 'success' => array($result->message)) );

									// Check column for new sites
									$col = $this->wpdb->query("SHOW COLUMNS FROM {$this->wpdb->prefix}kb_page_settings LIKE 'api_key'");
									if(!$col){
										$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN api_key varchar(255) DEFAULT NULL AFTER id_page");
										$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN api_trial varchar(2) DEFAULT NULL AFTER api_key");
										$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN kb_share_popap text NULL AFTER api_trial");
									}
									$res = $this->wpdb->update($this->wpdb->prefix.'kb_page_settings',
										array(
											'api_trial' => $result->demo,
											'api_key' => $_REQUEST['api_key']
										),
										array('id_page' => 1)
									);

								}else $amsg = $this->dashboard_get_result_message( array( 'error' => array($result->message)) );
							}
						}
					}

					if(empty($_REQUEST['api_key'])){
						$col = $this->wpdb->query("SHOW COLUMNS FROM {$this->wpdb->prefix}kb_page_settings LIKE 'api_key'");
						if(!$col){
							$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN api_key varchar(255) DEFAULT NULL AFTER id_page");
							$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN api_trial varchar(2) DEFAULT NULL AFTER api_key");
							$res = $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD COLUMN kb_share_popap text NULL AFTER api_trial");
						}
						$this->wpdb->update($this->wpdb->prefix . 'kb_page_settings',
							array('api_key' => ''),
							array('id_page' => 1)
						);
					}

					$col = $this->wpdb->query("SHOW COLUMNS FROM {$this->wpdb->prefix}kb_page_settings LIKE 'kb_share_popap'");
					if(!$col) $this->wpdb->query("ALTER TABLE {$this->wpdb->prefix}kb_page_settings ADD kb_share_popap text NOT NULL AFTER 'api_trial'");


					$this->wpdb->update(
						$this->wpdb->prefix . 'kb_page_settings',
						array(
							'kb_share_popap' => $_REQUEST['kb_share_popap'],
							'sort_by' => $_REQUEST['sortBy'],
							'sort_order' => $_REQUEST['sortOrder'],
							'no_listing_perpage' => $_REQUEST['no_listing_page'],
							'author_tag_display' => $_REQUEST['atd'],
							'page_title' => $_REQUEST['page_title'],
							'site_search' => $_REQUEST['site_search'],
							'vc' => $_REQUEST['site_vc']
						),
						array(
							'id_page' => 1,
						)
					);

					break;

				// Update Category
				case 'Update':
					foreach ( $_REQUEST['description'] as $i => $categoryDescription ) {

						$err = '';
						$values = array();
						if(isset($_REQUEST['upload_file'][$i]) && !empty($_REQUEST['upload_file'][$i])) $values['image'] = $_REQUEST['upload_file'][$i];

						if ( $categoryDescription != '' ) {
							$values['description'] = $categoryDescription;
						}

						// No action if no upload image and description
						if ( empty( $values ) ) continue;

						$this->wpdb->update($this->wpdb->prefix.'kb_category', $values, array('id_cat' => $_REQUEST['idArr'][ $i ]));

						if ( $err !== '' ): ?>
							<div class="updated updated-error">
								<p><strong><?php echo $err; ?></strong></p>
							</div>
						<?php endif;
					}
					break;
			}// end switch submit
		} // endif submit

		echo $amsg;

		require_once 'templates/admin-tabs.php';
	}

	/**
	 * Render list of Categories for admin "Set Social" section
	 * @TODO must be optimized
	 * @return bool|string
	 */
	private function get_categories_dropdown() {

		$return_text = '';

		$categories = $this->get_subcategories( 0, false, true );
		if ( is_array( $categories ) && count( $categories ) > 0 ) {
			foreach ( $categories as $category ) {
				$id_category = $category['id_cat'];
				$category_name = $category['name'];

				$return_text .= '<tr><th colspan="3" class="kbucket-text-left-h40">'.ucfirst( $category_name ).'</th></tr>';
				$categories2 = $this->get_subcategories( $id_category, false, true );

				foreach ( $categories2 as $category2 ) {

					$return_text .= '<tr><td>----' . ucfirst( $category2['name'] ) . '</td>
									<td><textarea style="width:100%;" name="description[]">' . $category2['description'] . '</textarea>
									<input type="hidden" name="idArr[]" value="' . $category2['id_cat'] . '" />
									</td>
								<td style="position:relative;width:20%;"><input type="hidden" value="" name="upload_file[]"/><button style="position:relative;z-index:2;" class="upload_image_cat button"><i class="fa fa-camera"></i></button>';
					if ( ! empty( $category2['image'] ) ) {
						$return_text .= '<div style="background-image:url('. $category2['image'] .');width:70px;height:70px;background-repeat:no-repeat;background-size:cover;position:absolute;right:0;top:0;"></div>';
					}
					$return_text .= '</td>';
					$return_text .= '</tr>';
				}
			}
		} else {
			return false;
		}

		return $return_text;
	}

	/**
	 * Execute cURL request
	 * @param $url
	 * @return mixed
	 */
	public function curl_get_result( $url ) {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		$data = curl_exec( $ch );
		curl_close( $ch );

		return $data;
	}

	/**
	 * Check if current page is Kbucket listings page
	 * @return bool
	 */
	public function is_kbucket_page(){
		global $wp;

		$slugsArr = explode( '/', $wp->request );

		$kbucketSlug = array_shift( $slugsArr );

		$kb_page_id = kbucketPageID();
		$post = get_post($kb_page_id);
		$slug = $post->post_name;

		if ( !empty( $kbucketSlug ) && $slug == $kbucketSlug ) {
			return true;
		}

		return false;
	}
}
include_once "kb-init.php";
