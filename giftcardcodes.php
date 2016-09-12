<?php
/*
Plugin Name: Gift Card Codes
Plugin URI: https://www.elance.com/s/djlsa
Description: Use the shortcode [giftcardcodes] to insert a form field that accepts and validates a code
Version: 1.0
Author: David Salsinha
Author URI: https://www.elance.com/s/djlsa
Author Email: davidsalsinha@gmail.com

*/

$better_wp_security_options = get_option('bit51_bwps');
$better_wp_security_options['st_loginerror'] = 0;
update_option('bit51_bwps', $better_wp_security_options);

define("LOCKOUT_ATTEMPTS", get_option('lockout_attempts', 4) );
define("LOCKOUT_PERIOD", get_option('lockout_period', 24) );

require_once( 'giftcardcodes_table.php' );

class GiftCardCodes {

	function __construct() {
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );

		$plugin = plugin_basename(__FILE__);
		add_filter("plugin_action_links_$plugin", array( $this, 'filter_clear_cache_link' ) );
		add_action( 'wp_head', array( $this, 'action_add_maskedinput_include' ) );
		add_shortcode( 'giftcardcodes', array( $this, 'shortcode_giftcardcodes' ) );

		add_action('register_form', array( $this, 'action_register_form' ) );
		add_action('user_register', array( $this, 'action_user_register' ) );
		add_action('registration_errors', array( $this, 'action_registration_errors' ), 10, 3);

		add_action('admin_init', array( $this, 'action_admin_init' ) );
		add_action('admin_menu', array( $this, 'action_admin_menu' ) );
	}

	function action_admin_init() {
		register_setting( 'giftcardcodes', 'lockout_attempts' );
		register_setting( 'giftcardcodes', 'lockout_period' );
	}

	function action_admin_menu() {
		add_menu_page('Gift Card Codes', 'Gift Card Codes', 'administrator', 'giftcardcodes', array( $this, 'page_settings' ), '', 71 );
	}

	function page_settings() {
		settings_fields( 'giftcardcodes' );
		$giftcardcodes_table = new GiftCardCodes_Table();
		$giftcardcodes_table->prepare_items();
?>
<div class="wrap">
<h2>Gift Card Codes</h2>
<div style="margin:25px 0 25px 0; height:1px; line-height:1px; background:#CCCCCC;"></div>
<h3>Options</h3>
<form method="post" action="options.php">
	<table class="form-table" style="width: 640px">
		<tr valign="top">
		<td>Attempts before lockout</td>
		<td><input type="number" name="lockout_attempts" value="<?php echo LOCKOUT_ATTEMPTS; ?>" /></td>
		<td>Lockout duration (in hours)</td>
		<td><input type="number" name="lockout_period" value="<?php echo LOCKOUT_PERIOD; ?>" /></td>
		</tr>
	</table>
	
	<?php submit_button(); ?>
</form>
<input type="button" name="clear_lockouts" id="clear_lockouts" class="button-primary" value="Clear lockouts" onclick="window.location='<?php echo plugins_url( 'giftcardcodes/clear-lockouts.php'); ?>'" />
<div style="margin:25px 0 25px 0; height:1px; line-height:1px; background:#CCCCCC;"></div>
<h3>Generate</h3>
<form method="get" action="<?php echo plugins_url( 'giftcardcodes/generator.php'); ?>">
	<table class="form-table" style="width: 640px">
		<tr valign="top">
		<td>Number of codes</td>
		<td><input type="number" name="n" value="1" /></td>
		<td>Stockist</td>
		<td><input type="text" name="s" value="" /></td>
		</tr>
	</table>
	<input type="submit" name="generate_codes" id="generate_codes" class="button-primary" value="Generate" />
</form>
<div style="margin:25px 0 25px 0; height:1px; line-height:1px; background:#CCCCCC;"></div>
<input type="button" name="refresh" id="refresh" class="button-primary" value="Refresh" onclick="location.reload()" />
<form id="giftcardcodes-filter" method="get">
	<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
	<?php $giftcardcodes_table->display() ?>
</form>
</div>

<?php
	}

	public function activate( $network_wide ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'giftcardcodes';
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				code_id bigint(20) unsigned NOT NULL,
				code varchar(20) NOT NULL,
				stockist varchar(255) DEFAULT NULL,
				date_created bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				date_redeemed bigint(20) unsigned DEFAULT NULL,
				KEY code_id (code_id),
				KEY user_id (user_id)
		);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$wp_login_path = ABSPATH  . 'wp-login.php';
		if(!get_option('users_can_register')) {
			if(is_writable($wp_login_path)) {
				$wp_login = file_get_contents( $wp_login_path );
				$wp_login = str_replace("if ( !get_option('users_can_register') ) {", "if ( !get_option('users_can_register') && !isset(" . '$' . "_REQUEST['giftcardcodes_id']) ) {", $wp_login);
				file_put_contents($wp_login_path, $wp_login);
			} else {
				trigger_error('You need to set <i>wp-login.php</i> to be writable so that this plugin can make required changes to it. Use your FTP program to change the permissions, set writable on all fields.', E_USER_ERROR);
				exit;
			}
		}
	}

	public function deactivate( $network_wide ) {
	}

	public function uninstall( $network_wide ) {
	}

	public function plugin_textdomain() {

		$domain = 'giftcardcodes_locale';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR.'/'.$domain.'/'.$domain.'-'.$locale.'.mo' );
		load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	function filter_clear_cache_link($links) {
		$link = '<a href="' . plugins_url( 'giftcardcodes/clear-lockouts.php') . '">Clear all lockouts</a>'; 
		array_unshift($links, $link); 
		return $links; 
	}

	function action_register_form() {
		if(isset($_REQUEST['giftcardcodes_id'])) {
		?>
		<input type="hidden" name="giftcardcodes_id" value="<?php echo $_REQUEST['giftcardcodes_id']; ?>" />
		<?php
		}
	}

	private function check_code() {
		global $wpdb;
		if( isset( $_REQUEST['giftcardcodes_id'] ) && is_numeric($_REQUEST['giftcardcodes_id']) ) {
			$code_id = $_REQUEST['giftcardcodes_id'];
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(code_id) as code_count FROM " . $wpdb->prefix . "giftcardcodes WHERE code_id = %d AND user_id IS NULL",
					array(
						$code_id
					)
				)
			);
			if($result->code_count == 1)
				return 1;
		}
		return 0;
	}

	function action_user_register($user_id) {
		global $wpdb;
		if($this->check_code() == 1) {
			$code_id = $_REQUEST['giftcardcodes_id'];
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT code, stockist FROM " . $wpdb->prefix . "giftcardcodes WHERE code_id = %d",
					array(
						$code_id
					)
				)
			);
			$user = new WP_User( $user_id );
			$user->set_role('s2member_level4');
			update_user_meta($user_id, 's2member_subscr_id', $result->stockist . ' ' . $result->code);
			update_user_meta($user_id, 's2member_custom', 'pilotaptitudetest.com');
			update_user_meta($user_id, 's2member_auto_eot_time', strtotime('+60 days') );
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"UPDATE " . $wpdb->prefix . "giftcardcodes SET user_id = %d, date_redeemed = %d WHERE code_id = %d",
					array(
						$user_id,
						time(),
						$code_id
					)
				)
			);
		}
	}

	function action_registration_errors($errors, $sanitized_user_login, $user_email) {
		if( isset( $_REQUEST['giftcardcodes_id'] ) && $this->check_code() == 0 ) {
			$errors->add( 'giftcardcodes_error', __('<strong>ERROR</strong>: Wrong code. Registration halted.','giftcardcodes_locale') );
		}
		return $errors;
	}

	private function get_temp_dir() {
		if (file_exists('/dev/shm') ) { return '/dev/shm'; }
		if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
		if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
		if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
		$tempfile=tempnam(__FILE__,'');
		if (file_exists($tempfile)) {
			unlink($tempfile);
			return realpath(dirname($tempfile));
		}
		return null;
	}

	private function write_cache($name, $data) {
		$dir = $this->get_temp_dir() . '/giftcardcodes/';
		@mkdir($dir);
		@file_put_contents($dir . $name, serialize($data));
	}

	private function read_cache($name, $expire) {
		$file = $this->get_temp_dir() . '/giftcardcodes/' . $name;
		clearstatcache();
		if(file_exists($file) && time() - filemtime($file) < $expire)
			return unserialize(file_get_contents($file));
		@unlink($file);
		return null;
	}

	private function get_request_code() {
		$ip = "";
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		$ip .= '_' . $_SERVER['HTTP_USER_AGENT'];
		return md5($ip);
	}

	private function max_code_attempts() {
		$req = $this->get_request_code() . '.txt';
		$att = $this->read_cache($req, LOCKOUT_PERIOD * 60 * 60);
		if($att === null) {
			$this->write_cache($req, 1);
			return 0;
		} else if ($att >= LOCKOUT_ATTEMPTS) {
			return 1;
		}
		if( strstr($_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']) !== FALSE )
			$this->write_cache($req, ++$att);
		return 0;
	}

	function shortcode_giftcardcodes($atts) {
		ob_start();
		if($this->max_code_attempts() == 0) {
			if(isset($_REQUEST['error'])) {
?>
	<div id="errorbox">
		<p>Wrong code, please try again</p>
	</div>
<?php
			}

?>
	<div id="giftcardcodes_form">
	<form action="<?php echo plugins_url( 'giftcardcodes/validate-code.php' ); ?>">
		<p><input id="giftcardcodes_input" name="giftcardcodes_input" tabindex="1" type="text" /></p>
		<p><input id="giftcardcodes_submit_button" type="submit" value="Redeem" /></p>
	</form>
	</div>
	<script type="text/javascript">
		var code_input = $("#giftcardcodes_input");
		if(code_input.length == 1)
			code_input.mask("******-******-******");
	</script>
<?php
		} else {
?>
	<p id="errorbox">TOO MANY ATTEMPTS, TRY AGAIN TOMORROW</p>
<?php
		}
		$display = ob_get_contents();
		ob_end_clean();
		return $display;
	}

}

$plugin_name = new GiftCardCodes();
