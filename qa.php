<?php
/*
Plugin Name: Q&A - WordPress Questions and Answers Plugin
Plugin URI: http://premium.wpmudev.org/project/qa-wordpress-questions-and-answers-plugin
Description: Q&A allows any WordPress site to have a fully featured questions and answers section - just like StackOverflow, Yahoo Answers, Quora and more...
Author: WPMU DEV
Version: 1.4.5.2-git-1
Author URI: http://premium.wpmudev.org/
WDP ID: 217
Text Domain: qa
*/

/*

Authors - Marko Miljus, S H Mohanjith, scribu, Hakan Evin, Arnold Bailey

Copyright 2007-2015 Incsub, (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

add_action( 'init', 'github_plugin_updater_test_init' );
function github_plugin_updater_test_init() {
	include_once 'gitupdate.php';
	define( 'WP_GITHUB_FORCE_UPDATE', true );
	if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin
		$config = array(
			'slug' => plugin_basename( __FILE__ ),
			'proper_folder_name' => 'github-updater',
			'api_url' => 'https://api.github.com/repos/hikikomorime/qa',
			'raw_url' => 'https://raw.github.com/hikikomorime/qa/master',
			'github_url' => 'https://github.com/hikikomorime/qa',
			'zip_url' => 'https://github.com/hikikomorime/qa/archive/master.zip',
			'sslverify' => true,
			'requires' => '3.0',
			'tested' => '3.3',
			'readme' => 'README.md',
			'access_token' => '',
		);
		new WP_GitHub_Updater( $config );
	}
}

class WPGitHubUpdaterSetup {
	/**
	 * Full file system path to the main plugin file
	 *
	 * @var string
	 */
	var $plugin_file;
	/**
	 * Path to the main plugin file relative to WP_CONTENT_DIR/plugins
	 *
	 * @var string
	 */
	var $plugin_basename;
	/**
	 * Name of options page hook
	 *
	 * @var string
	 */
	var $options_page_hookname;
	function __construct() {
		// Full path and plugin basename of the main plugin file
		$this->plugin_file = __FILE__;
		$this->plugin_basename = plugin_basename( $this->plugin_file );
		add_action( 'admin_init', array( $this, 'settings_fields' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'network_admin_menu', array( $this, 'add_page' ) );
		add_action( 'wp_ajax_set_github_oauth_key', array( $this, 'ajax_set_github_oauth_key' ) );
	}
	/**
	 * Add the options page
	 *
	 * @return none
	 */
	function add_page() {
		if ( current_user_can ( 'manage_options' ) ) {
			$this->options_page_hookname = add_plugins_page ( __( 'GitHub Updates', 'github_plugin_updater' ), __( 'GitHub Updates', 'github_plugin_updater' ), 'manage_options', 'github-updater', array( $this, 'admin_page' ) );
			add_filter( "network_admin_plugin_action_links_{$this->plugin_basename}", array( $this, 'filter_plugin_actions' ) );
			add_filter( "plugin_action_links_{$this->plugin_basename}", array( $this, 'filter_plugin_actions' ) );
		}
	}
	/**
	 * Add fields and groups to the settings page
	 *
	 * @return none
	 */
	public function settings_fields() {
		register_setting( 'ghupdate', 'ghupdate', array( $this, 'settings_validate' ) );
		// Sections: ID, Label, Description callback, Page ID
		add_settings_section( 'ghupdate_private', 'Private Repositories', array( $this, 'private_description' ), 'github-updater' );
		// Private Repo Fields: ID, Label, Display callback, Menu page slug, Form section, callback arguements
		add_settings_field(
			'client_id', 'Client ID', array( $this, 'input_field' ), 'github-updater', 'ghupdate_private',
			array(
				'id' => 'client_id',
				'type' => 'text',
				'description' => '',
			)
		);
		add_settings_field(
			'client_secret', 'Client Secret', array( $this, 'input_field' ), 'github-updater', 'ghupdate_private',
			array(
				'id' => 'client_secret',
				'type' => 'text',
				'description' => '',
			)
		);
		add_settings_field(
			'access_token', 'Access Token', array( $this, 'token_field' ), 'github-updater', 'ghupdate_private',
			array(
				'id' => 'access_token',
			)
		);
		add_settings_field(
			'gh_authorize', '<p class="submit"><input class="button-primary" type="submit" value="'.__( 'Authorize with GitHub', 'github_plugin_updater' ).'" /></p>', null, 'github-updater', 'ghupdate_private', null
		);
	}
	public function private_description() {
?>
		<p>Updating from private repositories requires a one-time application setup and authorization. These steps will not need to be repeated for other sites once you receive your access token.</p>
		<p>Follow these steps:</p>
		<ol>
			<li><a href="https://github.com/settings/applications/new" target="_blank">Create an application</a> with the <strong>Main URL</strong> and <strong>Callback URL</strong> both set to <code><?php echo bloginfo( 'url' ) ?></code></li>
			<li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> from your <a href="https://github.com/settings/applications" target="_blank">application details</a> into the fields below.</li>
			<li><a href="javascript:document.forms['ghupdate'].submit();">Authorize with GitHub</a>.</li>
		</ol>
		<?php
	}
	public function input_field( $args ) {
		extract( $args );
		$gh = get_option( 'ghupdate' );
		$value = $gh[$id];
?>
		<input value="<?php esc_attr_e( $value )?>" name="<?php esc_attr_e( $id ) ?>" id="<?php esc_attr_e( $id ) ?>" type="text" class="regular-text" />
		<?php echo $description ?>
		<?php
	}
	public function token_field( $args ) {
		extract( $args );
		$gh = get_option( 'ghupdate' );
		$value = $gh[$id];
		if ( empty( $value ) ) {
?>
			<p>Input Client ID and Client Secret, then <a href="javascript:document.forms['ghupdate'].submit();">Authorize with GitHub</a>.</p>
			<input value="<?php esc_attr_e( $value )?>" name="<?php esc_attr_e( $id ) ?>" id="<?php esc_attr_e( $id ) ?>" type="hidden" />
			<?php
		}else {
?>
			<input value="<?php esc_attr_e( $value )?>" name="<?php esc_attr_e( $id ) ?>" id="<?php esc_attr_e( $id ) ?>" type="text" class="regular-text" />
			<p>Add to the <strong>$config</strong> array: <code>'access_token' => '<?php echo $value ?>',</code>
			<?php
		}
?>
		<?php
	}
	public function settings_validate( $input ) {
		if ( empty( $input ) ) {
			$input = $_POST;
		}
		if ( !is_array( $input ) ) {
			return false;
		}
		$gh = get_option( 'ghupdate' );
		$valid = array();
		$valid['client_id']     = strip_tags( $input['client_id'] );
		$valid['client_secret'] = strip_tags( $input['client_secret'] );
		$valid['access_token']  = strip_tags( $input['access_token'] );
		if ( empty( $valid['client_id'] ) ) {
			add_settings_error( 'client_id', 'no-client-id', __( 'Please input a Client ID before authorizing.', 'github_plugin_updater' ), 'error' );
		}
		if ( empty( $valid['client_secret'] ) ) {
			add_settings_error( 'client_secret', 'no-client-secret', __( 'Please input a Client Secret before authorizing.', 'github_plugin_updater' ), 'error' );
		}
		return $valid;
	}
	/**
	 * Add a settings link to the plugin actions
	 *
	 * @param array   $links Array of the plugin action links
	 * @return array
	 */
	function filter_plugin_actions( $links ) {
		$settings_link = '<a href="plugins.php?page=github-updater">' . __( 'Setup', 'github_plugin_updater' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
	/**
	 * Output the setup page
	 *
	 * @return none
	 */
	function admin_page() {
		$this->maybe_authorize();
?>
		<div class="wrap ghupdate-admin">

			<div class="head-wrap">
				<?php screen_icon( 'plugins' ); ?>
				<h2><?php _e( 'Setup GitHub Updates' , 'github_plugin_updater' ); ?></h2>
			</div>

			<div class="postbox-container primary">
				<form method="post" id="ghupdate" action="options.php">
					<?php
		settings_errors();
		settings_fields( 'ghupdate' ); // includes nonce
		do_settings_sections( 'github-updater' );
?>
				</form>
			</div>

		</div>
		<?php
	}
	public function maybe_authorize() {
		$gh = get_option( 'ghupdate' );
		if ( 'false' == $_GET['authorize'] || 'true' != $_GET['settings-updated'] || empty( $gh['client_id'] ) || empty( $gh['client_secret'] ) ) {
			return;
		}
		$redirect_uri = urlencode( admin_url( 'admin-ajax.php?action=set_github_oauth_key' ) );
		// Send user to GitHub for account authorization
		$query = 'https://github.com/login/oauth/authorize';
		$query_args = array(
			'scope' => 'repo',
			'client_id' => $gh['client_id'],
			'redirect_uri' => $redirect_uri,
		);
		$query = add_query_arg( $query_args, $query );
		wp_redirect( $query );
		exit;
	}
	public function ajax_set_github_oauth_key() {
		$gh = get_option( 'ghupdate' );
		$query = admin_url( 'plugins.php' );
		$query = add_query_arg( array( 'page' => 'github-updater' ), $query );
		if ( isset( $_GET['code'] ) ) {
			// Receive authorized token
			$query = 'https://github.com/login/oauth/access_token';
			$query_args = array(
				'client_id' => $gh['client_id'],
				'client_secret' => $gh['client_secret'],
				'code' => $_GET['code'],
			);
			$query = add_query_arg( $query_args, $query );
			$response = wp_remote_get( $query, array( 'sslverify' => false ) );
			parse_str( $response['body'] ); // populates $access_token, $token_type
			if ( !empty( $access_token ) ) {
				$gh['access_token'] = $access_token;
				update_option( 'ghupdate', $gh );
			}
			wp_redirect( admin_url( 'plugins.php?page=github-updater' ) );
			exit;
		}else {
			$query = add_query_arg( array( 'authorize'=>'false' ), $query );
			wp_redirect( $query );
			exit;
		}
	}
}
add_action( 'init', create_function( '', 'global $WPGitHubUpdaterSetup; $WPGitHubUpdaterSetup = new WPGitHubUpdaterSetup();' ) );

// Check if we already have Q&A or Q&A Lite installed and running
if ( !class_exists( 'QA_Core' ) ) {

	// The plugin version
	define( 'QA_VERSION', '1.4.5' );

	// The full url to the plugin directory
	define( 'QA_PLUGIN_URL', plugin_dir_url(__FILE__) );

	// The full path to the plugin directory
	define( 'QA_PLUGIN_DIR', plugin_dir_path(__FILE__) );

	// The text domain for strings localization
	define( 'QA_TEXTDOMAIN', 'qa' );

	// The key for the options array
	define( 'QA_OPTIONS_NAME', 'qa_options' );

	// The minimum number of seconds between two user posts
	if (!defined('QA_FLOOD_SECONDS')) define( 'QA_FLOOD_SECONDS', 10 );

	// Rewrite slugs
	if (!defined('QA_SLUG_ROOT')) define( 'QA_SLUG_ROOT','questions' );
	if (!defined('QA_SLUG_ASK')) define( 'QA_SLUG_ASK', 'ask' );
	if (!defined('QA_SLUG_EDIT')) define( 'QA_SLUG_EDIT', 'edit' );
	if (!defined('QA_SLUG_UNANSWERED')) define( 'QA_SLUG_UNANSWERED', 'unanswered' );
	if (!defined('QA_SLUG_TAGS')) define( 'QA_SLUG_TAGS', 'tags' );
	if (!defined('QA_SLUG_CATEGORIES')) define( 'QA_SLUG_CATEGORIES', 'categories' );
	if (!defined('QA_SLUG_USER')) define( 'QA_SLUG_USER', 'user' );

	// Reputation multipliers
	if (!defined('QA_ANSWER_ACCEPTED')) define( 'QA_ANSWER_ACCEPTED', 15 );
	if (!defined('QA_ANSWER_ACCEPTING')) define( 'QA_ANSWER_ACCEPTING', 2 );
	if (!defined('QA_ANSWER_UP_VOTE')) define( 'QA_ANSWER_UP_VOTE', 10 );
	if (!defined('QA_QUESTION_UP_VOTE')) define( 'QA_QUESTION_UP_VOTE', 5 );
	if (!defined('QA_DOWN_VOTE')) define( 'QA_DOWN_VOTE', -2 );
	if (!defined('QA_DOWN_VOTE_PENALTY')) define( 'QA_DOWN_VOTE_PENALTY', -1 );

	if (!defined('QA_DEFAULT_TEMPLATE_DIR')) define( 'QA_DEFAULT_TEMPLATE_DIR', 'default-templates' );

	global $qa_email_notification_content, $qa_email_notification_subject;

	$qa_email_notification_subject = "[SITE_NAME] New Question";  // SITE_NAME
	$qa_email_notification_content = "Dear TO_USER,

	New question was posted on SITE_NAME.

	QUESTION_TITLE

	QUESTION_DESCRIPTION

	If you wish to answer it please goto QUESTION_LINK.

	Thanks,
	SITE_NAME";

	// Load plugin files
	include_once QA_PLUGIN_DIR . 'core/core.php';
	include_once QA_PLUGIN_DIR . 'core/answers.php';
	include_once QA_PLUGIN_DIR . 'core/edit.php';
	include_once QA_PLUGIN_DIR . 'core/votes.php';
	include_once QA_PLUGIN_DIR . 'core/subscriptions.php';
	include_once QA_PLUGIN_DIR . 'core/functions.php';
	include_once QA_PLUGIN_DIR . 'core/template-tags.php';
	include_once QA_PLUGIN_DIR . 'core/widgets.php';
	include_once QA_PLUGIN_DIR . 'core/ajax.php';
	include_once QA_PLUGIN_DIR . 'core/class.virtualpage.php';
	
	function qa_bp_integration() {
		include_once QA_PLUGIN_DIR . 'core/buddypress.php';
	}
	add_action( 'bp_loaded', 'qa_bp_integration' );

	if ( is_admin() ) {
		include_once QA_PLUGIN_DIR . 'core/admin.php';
	}
}
else {
	if ( is_multisite() )
		add_action( 'network_admin_notices', 'wpmudev_qa_duplicate' );
	else
		add_action( 'admin_notices', 'wpmudev_qa_duplicate' );
}

if ( !function_exists( 'wpmudev_qa_duplicate' ) ) {
	function wpmudev_qa_duplicate() {
		echo '<div class="error fade"><p>' .
			__("<b>[Q&A]</b> There is already a running version of Q&A. Please check if you have already installed Q&A or Q&A Lite beforehand. You need to deactivate the other version to install and run this.", QA_TEXTDOMAIN) .
			'</p></div>';

	}
}

if ( !function_exists( 'wpmudev_qa_uninstall' ) ) {
	function wpmudev_qa_uninstall() {
		remove_role( 'visitor' );
		/* Uninstall options only if Q&A Lite is not installed
		In other words, uninstall when Q&A is installed alone
		*/
		if ( !file_exists( WP_PLUGIN_DIR ."/qa-lite/qa-lite.php" ) ) {
			delete_option( 'qa_no_visit' );
			delete_option( 'qa_installed_version' );
			delete_option( 'qa_capabilties_set' );
			delete_option( QA_OPTIONS_NAME );
			delete_option( 'qa_email_notification_subject' );
			delete_option( 'qa_email_notification_content' );
		}
	}
}
register_uninstall_hook(  __FILE__ , 'wpmudev_qa_uninstall' );
