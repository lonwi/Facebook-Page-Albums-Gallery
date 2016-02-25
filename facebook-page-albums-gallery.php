<?php
/*
* Plugin Name: Facebook Page Albums Gallery
* Description: Special addon plugin that uses http://wordpress.org/extend/plugins/facebook-page-albums/
* Version: 1.0
* Author: Websquare
* Author URI: http://www.websquare.co.uk
*/

define('FACEBOOK_PAGE_ALBUMS_DIR', dirname(__FILE__));
define('FACEBOOK_PAGE_ALBUMS_CACHE_GROUP', 'facebook_page_albums');
define('FACEBOOK_PAGE_ALBUMS_CACHE_TIMEOUT', 60 * 60 ); //60 minutes

if ( is_admin() ) {
	require_once( FACEBOOK_PAGE_ALBUMS_DIR . '/facebook-page-albums-admin.php' );
}

class FacebookPageAlbums {
	public $paging = null;

	private $api = null;

	public function __construct() {
		$this->load_api();
	}

	protected function load_api() {
		if ( empty($this->api) ) {
			require_once( FACEBOOK_PAGE_ALBUMS_DIR . '/class-facebook-page-albums-apimanager.php' );
			$this->api = new FacebookPageAlbumsAPIManager();
		}
	}

	public function get_album_list( $args=array() ) {
		return $this->api->get_albums( $args );
	}

	public function get_album_info( $album_id ) {
		return $this->api->get_album( $album_id );
	}

	public function get_paging_params( $args=array() ) {
		$args = wp_parse_args($args, array(
			'url' => false // "false" means use "$_SERVER['REQUEST_URI']" in add_query_arg
		));

		$result = array();

		// Previous
		if ($next = $this->parsing_paging_url( 'previous', $args )) {
			$result['previous'] = add_query_arg($next, $args['url']);
		}

		// Next
		if ($next = $this->parsing_paging_url( 'next', $args )) {
			$result['next'] = add_query_arg($next, $args['url']);
		}

		return $result;
	}

	protected function parsing_paging_url( $slug, $args=array() ) {
		$args = wp_parse_args($args, array(
			'access_token',
			'fields'
		));

		if (!isset($this->paging->{$slug})) {
			return false;
		}

		if (!$query = parse_url(urldecode($this->paging->{$slug}), PHP_URL_QUERY)) {
			return false;
		}

		parse_str($query, $params);

		// Remove
		foreach ($args as $item) {
			if (isset($params[$item])) {
				unset($params[$item]);
			}
		}

		return $params;
	}

	public function get_photo_list( $args=array() ) {
		return $this->api->get_photos( $args );
	}
}

function facebook_page_albums_get_album_list( $args=array() ) {
	/** @var FacebookPageAlbums $facebook_page_albums */
	global $facebook_page_albums;

	if ( empty($facebook_page_albums) ) {
		$facebook_page_albums = new FacebookPageAlbums();
	}

	// Get Object Cache
	$cache_name = 'album_list' . implode('', $args);
	$result = wp_cache_get( $cache_name, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP );

	if ( empty($result) ) {
		// Get from Facebook API
		$result = $facebook_page_albums->get_album_list( $args );
		if ( !empty($result) ) {
			// Save Object Cache
			wp_cache_set( $cache_name, $result, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP, FACEBOOK_PAGE_ALBUMS_CACHE_TIMEOUT );
		}
	}

	// Paging
	if (!empty($result['paging'])) {
		$facebook_page_albums->paging = $result['paging'];
	}

	return empty($result['data']) ? false : $result['data'];
}

function facebook_page_albums_get_paging($args=array()) {
	/** @var FacebookPageAlbums $facebook_page_albums */
	global $facebook_page_albums;

	if (!$facebook_page_albums) {
		return false;
	}

	return $facebook_page_albums->get_paging_params($args);
}

function facebook_page_albums_get_album( $album_id ) {
	/** @var FacebookPageAlbums $facebook_page_albums */
	global $facebook_page_albums;

	if ( empty($facebook_page_albums) ) {
		$facebook_page_albums = new FacebookPageAlbums();
	}

	// Get Object Cache
	$cache_name = 'album_info' . $album_id;
	$result = wp_cache_get( $cache_name, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP );

	if ( empty($result) ) {
		// Get from Facebook API
		$result = $facebook_page_albums->get_album_info( $album_id );
		if ( !empty($result) ) {
			// Save Object Cache
			wp_cache_set( $cache_name, $result, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP, FACEBOOK_PAGE_ALBUMS_CACHE_TIMEOUT );
		}
	}

	return $result;
}

function facebook_page_albums_get_photo_list( $album_id, $args=array() ) {
	/** @var FacebookPageAlbums $facebook_page_albums */
	global $facebook_page_albums;

	if ( empty($facebook_page_albums) ) {
		$facebook_page_albums = new FacebookPageAlbums();
	}

	// Get Object Cache
	$cache_name = 'photo_list' . $album_id . implode('', $args);
	$result = wp_cache_get( $cache_name, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP );

	if ( empty($result) ) {
		// Get from Facebook API
		$args['album_id'] = $album_id;
		$result = $facebook_page_albums->get_photo_list( $args );
		if ( !empty($result) ) {
			// Save Object Cache
			wp_cache_set( $cache_name, $result, FACEBOOK_PAGE_ALBUMS_CACHE_GROUP, FACEBOOK_PAGE_ALBUMS_CACHE_TIMEOUT );
		}
	}

	return $result;
}

if ( !function_exists('alog') ) {

	function alog() {
		if ( !WP_DEBUG ) {return;}

		if ( !class_exists('dBug') ) {
			require_once (FACEBOOK_PAGE_ALBUMS_DIR . '/lib/dBug.php');
		}
		foreach ( func_get_args() as $v ) new dBug($v);
	}
}

if ( !function_exists('dlog') ) {

	function dlog() {
		if ( !WP_DEBUG ) {return;}

		if ( !class_exists('dBug') ) {
			require_once (FACEBOOK_PAGE_ALBUMS_DIR . '/lib/dBug.php');
		}

		// buffering
		ob_start();
		foreach ( func_get_args() as $v ) new dBug($v);
		$html = ob_get_contents();
		ob_end_clean();

		// write down to html file.
		$html .= '<br/><br/>';
		$upload_dir = wp_upload_dir();
		$file = $upload_dir['basedir'] . '/debug.html';
		if ($handle = fopen($file, 'a')) {
			@chmod($file, 0777);
			fwrite($handle, $html);
			fclose($handle);
		}
	}
}

function facebook_page_albums_custom_rewrite_basic() {
	add_rewrite_rule('^gallery/([^/]*)$', 'index.php?pagename=gallery&gallery_id=$matches[1]', 'top');
	add_rewrite_tag('%gallery_id%','([^/]*)');
}
add_action('init', 'facebook_page_albums_custom_rewrite_basic');

function facebook_page_albums_show_gallery(){
	global $wp_query;
	$gallery_id = $wp_query->query_vars['gallery_id'];
	$per_page = 10;
	if(isset($_GET['gallery_page'])){
		$gallery_page = $_GET['gallery_page'];
	}else{
		$gallery_page = 1;
	}
	
	if(isset($gallery_id) && !empty($gallery_id)){
		$images = facebook_page_albums_get_photo_list($gallery_id, wp_parse_args($_GET, array(
			'per_page' => $per_page,
			'paged'    => $gallery_page
		)));
		$album = facebook_page_albums_get_album($gallery_id);
	}else{
		$albums = facebook_page_albums_get_album_list(wp_parse_args($_GET, array(
			'per_page' => $per_page,
			'paged'    => $gallery_page
		)));
	}
	?>
    <style>
		.facebook-gallery {
		}
		.facebook-gallery li{
			margin-bottom:30px;
		}
		.facebook-gallery-image-box {
			display:block;
			text-align:center;
			position:relative;
			-webkit-transition: all 0.3s ease-in-out;
			-moz-transition: all 0.3s ease-in-out;
			-ms-transition: all 0.3s ease-in-out;
			-o-transition: all 0.3s ease-in-out;
			transition: all 0.3s ease-in-out;
		}
		.facebook-gallery-image-box img {
			max-height:380px;
			width:520px;
			object-fit: cover;
		}
		.facebook-gallery-image-box span {
			display: block;
		}
		.facebook-gallery-image-box > span {
			opacity: 0;
			height: 100%;
			position: absolute;
			top: 0;
			width: 100%;
		}
		.facebook-gallery-image-box:hover > span{
			opacity: 1;
		}
		
		.facebook-gallery-image-box > span > span{
			height: 100px;
			width: 100px;
			margin-left: auto;
			margin-right: auto;
			position: relative;
			top: 50%;
			border-radius: 100%;
			margin-top: -50px;
			background-color: rgba(0, 144, 204, 0.5);
		}
		.facebook-gallery-image-box > span > span > span {
			height: 100px;
			width:100px;
			background-position: center center;
    		background-repeat: no-repeat;
			background-image: url("/wp-content/themes/fable/media/image/public/image_overlay.png");
		}
		
		.facebook-gallery-image-text {
			padding: 20px 15px 20px;
    		text-align: center;
		}
		.facebook-gallery-image-text-caption {
		}
		.facebook-gallery-image-text-caption a {
			text-decoration: none;
		}
		.pb-button-arrow-left {
			-moz-transform: rotate(225deg);
			-webkit-transform: rotate(225deg);
			-o-transform: rotate(225deg);
			-ms-transform: rotate(225deg);
			transform: rotate(225deg);
			margin-left: 0;
			margin-right: 10px;
		}
		.facebook-buttons {
		}
		.facebook-buttons .pb-layout-column-left {
			text-align:left;
		}
		.facebook-buttons .pb-layout-column-right {
			text-align:right;
		}
		.facebook-buttons-mobile {
				display:none;
		}
		@media only screen and (min-width:0px) and (max-width:767px)
		{
			.facebook-buttons .pb-layout-column-left,
			.facebook-buttons .pb-layout-column-right {
				text-align:center;
			}
			.facebook-buttons-dektop {
				display:none;
			}
			.facebook-buttons-mobile {
				display:block;
			}
		}
	</style>
    <?php
	
	if(isset($images) && !empty($images) && isset($album) && !empty($album)):?>
        
    <div class="facebook-buttons facebook-buttons-dektop pb-clear-fix">
        
        <ul class="pb-layout-50x50 pb-reset-list pb-clear-fix">
            <li class="pb-layout-column-left">
            
                <div class="pb-button pb-button-size-small">
                    <a style="border-width:0px;border-radius:0px;" href="<?php echo get_permalink(361);?>">
                        <span class="pb-button-box">
                            <span class="pb-button-icon"></span>
                            <span class="pb-button-content"><i class="pb-button-arrow pb-button-arrow-left" style=""></i>Go Back to Galleries</span>
                        </span>
                    </a>
                </div>
            
            </li>
            <li class="pb-layout-column-right">
        
                <div class="pb-button pb-button-size-small">
                    <a style="border-width:0px;border-radius:0px;" class="pb-window-target-blank" href="<?php echo $album['link'];?>" target="_blank">
                        <span class="pb-button-box">
                            <span class="pb-button-icon"></span>
                            <span class="pb-button-content">See on Facebook<i class="pb-button-arrow pb-button-arrow-right"></i></span>
                        </span>
                    </a>
                </div>
                
            </li>
        </ul>
        <div style="height:50px;" class="pb-space pb-clear-fix"></div>
    </div>  
    
    <div class="pb-clear-fix">
        <h4 style="text-align:center;">
            <?php echo $album['name'];?>
        </h4>
        <div style="height:50px;" class="pb-space pb-clear-fix"></div>
    </div>        

    
    <div class="facebook-gallery pb-clear-fix">
    	<ul class="pb-layout-50x50 pb-reset-list pb-clear-fix">
        	
        	<?php 
			$i=0;
			foreach($images as $image):
				if($i%2 == 0){ 
					$class = 'pb-layout-column-left';
				}else{
					$class = 'pb-layout-column-right';
				}
				$img_url = $image['source'];			
			?>
            <li class="<?php echo $class;?>">
            	<a href="<?php echo $img_url;?>" class="facebook-gallery-image-box" rel="facebook-gallery">
                	<img src="<?php echo $img_url;?>" alt="">
                    <span><span><span></span></span></span>
                </a>
                <div class="facebook-gallery-image-text">
                	
                </div>
            </li>
        	<?php $i++;endforeach;?>
        </ul>
    </div>
    <div class="theme-blog-pagination-box">
    	<div class="theme-blog-pagination">
		<?php
		echo paginate_links( array(
			'base' => add_query_arg( 'gallery_page', '%#%' ),
			'format' => '',
			'total' => ceil($album['count'] / $per_page),
			'current' => $gallery_page,
			'prev_text' => 'Previous',
			'next_text' => 'Next',
			'mid_size' => 2
		));
		?>
        </div>
	</div>
    
    <div class="facebook-buttons facebook-buttons-mobile pb-clear-fix">
        
        <ul class="pb-layout-50x50 pb-reset-list pb-clear-fix">
            <li class="pb-layout-column-left">
            
                <div class="pb-button pb-button-size-small">
                    <a style="border-width:0px;border-radius:0px;" href="<?php echo get_permalink(361);?>">
                        <span class="pb-button-box">
                            <span class="pb-button-icon"></span>
                            <span class="pb-button-content"><i class="pb-button-arrow pb-button-arrow-left" style=""></i>Go Back to Galleries</span>
                        </span>
                    </a>
                </div>
            
            </li>
            <li class="pb-layout-column-right">
        
                <div class="pb-button pb-button-size-small">
                    <a style="border-width:0px;border-radius:0px;" class="pb-window-target-blank" href="<?php echo $album['link'];?>" target="_blank">
                        <span class="pb-button-box">
                            <span class="pb-button-icon"></span>
                            <span class="pb-button-content">See on Facebook<i class="pb-button-arrow pb-button-arrow-right"></i></span>
                        </span>
                    </a>
                </div>
                
            </li>
        </ul>
        <div style="height:50px;" class="pb-space pb-clear-fix"></div>
    </div>  
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$('.facebook-gallery-image-box').fancybox();
		});
	</script>
    <?php
	endif;
	
	if(isset($albums) && !empty($albums)):
	?>

    <div class="facebook-gallery pb-clear-fix">
    	<ul class="pb-layout-50x50 pb-reset-list pb-clear-fix">
        	
        	<?php 
			$i=0;
			foreach($albums as $album): 
				if($i%2 == 0){ 
					$class = 'pb-layout-column-left';
				}else{
					$class = 'pb-layout-column-right';
				}
				$img_url = $album['cover_photo_data']['source'];			
			?>
            <li class="<?php echo $class;?>">
            	<a href="<?php echo get_permalink();?><?php echo $album['id'];?>/" class="facebook-gallery-image-box">
                	<img src="<?php echo $img_url;?>" alt="<?php echo $album['name'];?>">
                    <span><span><span></span></span></span>
                </a>
                <div class="facebook-gallery-image-text">
                	<h6 class="facebook-gallery-image-text-caption"><a href="<?php echo get_permalink();?><?php echo $album['id'];?>/"><?php echo $album['name'];?></a></h6>
                </div>
            </li>
        	<?php $i++;endforeach;?>
        </ul>
    </div>
    <div class="theme-blog-pagination-box">
    	<div class="theme-blog-pagination">
		<?php
		echo paginate_links( array(
			'base' => add_query_arg( 'gallery_page', '%#%' ),
			'format' => '',
			'total' => ceil(count($albums) / $per_page),
			'current' => $gallery_page,
			'prev_text' => 'Previous',
			'next_text' => 'Next',
			'mid_size' => 2
		));
		?>
        </div>
	</div>
    <?php
	endif;	
}
add_shortcode('facebook_page_albums_show_gallery', 'facebook_page_albums_show_gallery');

/* Debug */
function print_result($result = false){
	if($result != ""){
		echo '<pre>';
		print_r($result);
		echo '</pre>';
	}
}
function dump_result($result = false){
	if($result != ""){
		echo '<pre>';
		var_dump($result);
		echo '</pre>';
	}
}
?>