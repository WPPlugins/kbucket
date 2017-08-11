<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


$srch = trim( substr( sanitize_text_field($_REQUEST['srch']),0, 100 ) );

if ( $srch == '' ) return false;


$srch = urldecode( $srch );
$highlight = '<span class="kb-hlt">'.$srch.'</span>';

if ( strlen( $srch ) < 2 ):?>
	<h1 class="page-title">No results found for your search</h1>
	<?php return false; ?>
<?php endif; ?>


<h1 class="page-title">Searching in Kbucket, Cabinet, Drawer, Link for <b><?php echo esc_html( $srch ); ?></b></h1>

<?php
$sql = "SELECT id_cat,name FROM `{$this->wpdb->prefix}kb_category` WHERE parent_cat=0 AND name like '%%%s%%'";

$query = $this->wpdb->prepare( $sql, $srch );
$data = $this->wpdb->get_results( $query );

foreach ( $data as $m ): ?>
	<div class="kb-item">
		<h3>
			<span style="background-color: #217BBA;color: #FFFFFF;padding: 1px 3px;">KBUCKET</span>
			<a href="<?php echo KBUCKET_URL . '/kc/' . $m->id_cat; ?>" class="highlight"><?php echo str_ireplace( $srch, $highlight, $m->name ); ?></a>
		</h3>
	</div>
<?php endforeach;

$sql = 'SELECT c.`id_cat` AS parid,
				b.`id_cat` AS subid,
				c.`name` AS parcat,
				b.`name` AS subcat
		FROM `' . $this->wpdb->prefix . 'kb_category` b
		INNER JOIN `' . $this->wpdb->prefix . "kb_category` c
			ON c.`id_cat` = b.`parent_cat`
		WHERE b.`parent_cat`<>0 AND b.`name` LIKE '%%%s%%'";

$query = $this->wpdb->prepare( $sql, $srch );
$data = $this->wpdb->get_results( $query );

foreach ( $data as $m ): ?>
	<div class="kb-item">
		<h3>
			<span style="background-color: #217BBA;color: #FFFFFF;padding: 1px 3px;">KBUCKET</span>
			<a href="<?php echo KBUCKET_URL . '/kc/' .$m->parid; ?>"><?php echo str_ireplace( $srch, $highlight, $m->parcat ); ?></a>
			/
			<a href="<?php echo KBUCKET_URL . '/kc/'  . $m->subid?>"><?php echo str_ireplace( $srch, $highlight, $m->subcat ); ?></a>
		</h3>
	</div>
<?php
endforeach;

$sql = 'SELECT a.`id_kbucket`
		FROM `' . $this->wpdb->prefix . 'kbucket` a
		INNER JOIN `' . $this->wpdb->prefix . 'kb_tags` b ON a.`id_kbucket` = b.`id_kbucket`
		INNER JOIN `' . $this->wpdb->prefix . "kb_tag_details` c ON b.`id_tag` = c.`id_tag`
		WHERE
		(
			a.`title` LIKE '%%%s%%'
			OR a.`description` LIKE '%%%s%%'
			OR a.`author`=%s
			OR c.`name`=%s
		)
		GROUP BY a.`id_kbucket`
		LIMIT 100";

$query = $this->wpdb->prepare( $sql, array( $srch, $srch, $srch, $srch ) );
$res = $this->wpdb->get_results( $query );

$id_kbucket = array();

foreach ( $res as $row ) {
	$id_kbucket[] = $row->id_kbucket;
}
$id_kbucket = "'" . implode( "','", esc_sql( $id_kbucket ) ) . "'";

$sql = 'SELECT a.`id_kbucket`,GROUP_CONCAT(`name`) AS tags
		FROM `' . $this->wpdb->prefix . 'kbucket` a
		INNER JOIN `' . $this->wpdb->prefix . 'kb_tags` b
			ON b.`id_kbucket` = a.`id_kbucket`
		INNER JOIN `' . $this->wpdb->prefix . "kb_tag_details` c
			ON c.`id_tag` = b.`id_tag`
		WHERE a.`id_kbucket` IN ( $id_kbucket)
		GROUP BY a.`id_kbucket`
		LIMIT 100";

$tag = array();

$res = $this->wpdb->get_results( $sql );

foreach ( $res as $r ) {
	$tag[ $r->id_kbucket ] = $r->tags;
}

$sql = 'SELECT a.`id_kbucket`,
				a.`id_cat`,
				a.`title`,
				a.`description`,
				a.`link`,
				a.`author`,
				STR_TO_DATE(a.`pub_date`, "%a, %d %b %Y" ) AS postedDate,
				c.`id_cat` AS parid,
				b.`id_cat` AS subid,
				c.`name` AS parcat,
				b.`name` AS subcat
		FROM `' . $this->wpdb->prefix . 'kbucket` a
		INNER JOIN `' . $this->wpdb->prefix . 'kb_category` b ON a.`id_cat` = b.`id_cat`
		INNER JOIN `' . $this->wpdb->prefix . "kb_category` c ON c.`id_cat` = b.`parent_cat`
		WHERE a.`id_kbucket` IN ( $id_kbucket)
		LIMIT 100";

$data = $this->wpdb->get_results( $sql );

$i = 0;
foreach ( $data as $m ) {
	$i ++;
	$description = wp_kses($m->description,
		array(
			'a' => array(
				'href' => array(),
				'title' => array()
			),
			'br' => array(),
		)
	);?>
	<div id="kb-item-<?php echo esc_attr( $m->id_kbucket );?>" class="kb-item">

		<h3>
			<span style="background-color: #217BBA;color: #FFFFFF;padding: 1px 3px; line-height:35px;">KBUCKET</span>
			<a href="<?php echo KBUCKET_URL . '/kc/' . $m->parid; ?>" style="color:<?php echo esc_attr( $this->kbPage->settings->header_color ); ?>;"><?php	echo str_ireplace( $srch, $highlight, $m->parcat ); ?></a>
			/
			<a href="<?php echo KBUCKET_URL . '/kc/' . $m->subid;?>" style="color:<?php echo esc_attr( $this->kbPage->settings->header_color ); ?>;"><?php echo str_ireplace( $srch, $highlight, $m->subcat ); ?></a>
			/
			<a href="<?php echo esc_attr( $m->link ); ?>" target="_blank" style="color:<?php echo esc_attr( $this->kbPage->settings->header_color );?>;"><b><?php echo str_ireplace( $srch, $highlight, $m->title ); ?></b></a>
		</h3>

		<span style=" margin:0px; color:<?php echo esc_attr( $this->kbPage->settings->author_color );?>;">Publisher: <?php echo str_ireplace( $srch, $highlight, $m->author ); ?></span>
		<span class="kb-item-date"><?php echo esc_html( $m->postedDate ); ?></span>

		<p>
			<span style="color:<?php echo esc_attr( $this->kbPage->settings->content_color ); ?>;">
				<?php echo str_ireplace( $srch, $highlight, substr( $description, 0, 100 ) );?>
			</span>

			<?php if ( strlen( $description ) > 100 ): ?>
				<span style="display:none;" id="kb-item-text-<?php echo (int) $i; ?>">
					<?php echo str_ireplace( $srch, $highlight, ( substr($description, 100, strlen( $description ) ) )); ?>
				</span>

				<a style="color:<?php echo esc_attr( $this->kbPage->settings->tag_color ); ?>;" id="kb-share-item-<?php echo esc_attr( $m->id_kbucket ); ?>" class="kb-share-item" title="<?php echo esc_html( $m->title ); ?>">
					<img src="<?php echo WPKB_PLUGIN_URL . '/images/kshare.png'; ?>" alt="Share Button"/>
				</a>
			<?php endif; ?>
		</p>

		<p class="blue" style=" margin-bottom:2px;">
			<span style="color:<?php echo esc_attr( $this->kbPage->settings->tag_color ); ?>;">Tags: <?php echo str_ireplace( $srch, $highlight, $tag[ $m->id_kbucket ] ); ?></span>
		</p>

	</div>
<?php }

$res = $this->create_search_condition( $srch, array( 'post_title', 'post_content' ), array( '=', 'LIKE' ) );
$conditions = $res['conditions'];
$values = $res['values'];

$sql = 'SELECT a.`ID`,`user_nicename`,DATE(`post_date`) AS dt,`post_content`,`post_title`
		FROM `' . $this->wpdb->prefix . 'posts` a
		INNER JOIN `' . $this->wpdb->prefix . "users` b ON a.`post_author` = b.`ID`
		WHERE ( $conditions)
			AND `post_type` IN ( 'post' )
			AND `post_status` = 'publish'
		LIMIT 100";

$query = $this->wpdb->prepare( $sql, $values );

$data = $this->wpdb->get_results( $query );

foreach ( $data as $m ): ?>
	<div class="kb-item">
		<h3>
			<span style="background-color: #217BBA;color: #FFFFFF;padding: 1px 3px;">LINK</span>
			<a href="<?php echo esc_attr( get_permalink( $m->ID ) ); ?>" target="_blank"><?php echo str_ireplace( $srch, $highlight, $m->post_title ); ?></a>
		</h3>
		<p>Publisher: <?php echo str_ireplace( $srch, $highlight, $m->user_nicename ); ?></p>
		<p>Date:<?php echo esc_html( $m->dt ); ?></p>
		<span><?php	echo str_ireplace( $srch, $highlight, substr( strip_tags( $m->post_content ), 0, 300 ) );?></span>
	</div>
<?php endforeach; ?>
