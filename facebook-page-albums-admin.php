<?php
class FacebookPageAlbumsAdmin {
	private $db = null;

	public function __construct() {
		require_once( 'class-facebook-page-albums-dbmanager.php' );
		$this->db = new FacebookPageAlbumsDBManager();

		$main_file = dirname( __FILE__ ) . '/facebook-page-albums.php';
		register_activation_hook($main_file, array($this, 'activation'));
		register_deactivation_hook($main_file, array($this, 'deactivation'));

		add_action('admin_menu', array($this, 'menu'));
	}

	public function activation() {
		$this->db->initialize();
	}

	public function deactivation() {
		$this->db->destroy();

		wp_clear_scheduled_hook('facebook_page_albums_cron_hook');
	}

	public function menu() {
		add_options_page(__('Facebook Page Albums', 'facebook_page_albums')
						 , __('Facebook Page Albums', 'facebook_page_albums')
						 , 'manage_options'
						 , basename(__FILE__)
						 , array($this, 'admin_page'));
	}

	public function admin_page() {
		$messages = array();

		if ( !empty($_POST) && $_POST['action'] == 'save_setting' ) {
			// API Settings
			$config = array();
			$config['appId'] = $_POST['appId'];
			$config['secret'] = $_POST['secret'];
			$config['pageId'] = $_POST['pageId'];
			$this->db->set_api_option($config);

			$messages[] = __('Saved');
		}
?>
	<div class="wrap">
		<h2><?php _e('Facebook Page Albums Configuration', 'facebook_page_albums');?></h2>

		<?php if (!empty($messages)) :?>
		<div class="updated" style="background-color:#FFFBCC;">
			<?php foreach ($messages as $item):?>
			<p><?php echo $item;?></p>
			<?php endforeach;?>
		</div>
		<?php endif;?>

		<form method="POST" action="#">
			<input type="hidden" name="action" value="save_setting" />
			<?php
			$this->api_settings();
			?>

			<?php submit_button(); ?>
		</form>
	</div> <!-- /.wrap -->
	<?php
	}

	public function api_settings() {
		$config = $this->db->get_api_option();
	?>
	<h3 class="title"><?php _e('API Settings', 'facebook_page_albums');?></h3>
	<p>
		<?php _e('Get the "App ID" and "App Secret" on <a href="https://developers.facebook.com/apps" target="_blank">Facebook Dev Center</a>.', 'facebook_page_albums');?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php _e('App ID', 'facebook_page_albums'); ?></th>
			<td>
				<input class="regular-text" type="text" name="appId" value="<?php echo $config['appId'];?>"/>
				<p class="example"><?php _e('Example: ', 'facebook_page_albums'); _e('123456789012345', 'facebook_page_albums');?></p>
			</td>
		</tr>
		<tr>
			<th><?php _e('App Secret', 'facebook_page_albums'); ?></th>
			<td>
				<input class="regular-text" type="text" name="secret" value="<?php echo $config['secret'];?>"/>
				<p class="example"><?php _e('Example: ', 'facebook_page_albums'); _e('adcacbdfbe1806b9c15e5c5aa020176a', 'facebook_page_albums');?></p>
			</td>
		</tr>
		<tr>
			<th><?php _e('Page ID/Slug', 'facebook_page_albums'); ?></th>
			<td>
				<input class="regular-text" type="text" name="pageId" value="<?php echo $config['pageId'];?>"/>
				<p class="example"><?php _e('Example: ', 'facebook_page_albums'); _e('cocacola', 'facebook_page_albums');?></p>
			</td>
		</tr>
	</table>
<?php
	}
}

new FacebookPageAlbumsAdmin();
?>