<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       cinode.com
 * @since      1.0.0
 *
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/admin
 * @author     Cinode <info@cinode.com>
 */
class Cinode_Recruitment_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cinode_Recruitment_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cinode_Recruitment_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cinode-recruitment-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cinode_Recruitment_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cinode_Recruitment_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cinode-recruitment-admin.js', array( 'jquery' ), $this->version, false );

	}

}
function cinode_recruitment_create_menu()
{

	$iconUrl = plugin_dir_url(__FILE__) . '../images/icon-24x24.png';
	//create new top-level menu
	add_menu_page('Cinode Recruitment Plugin Page', 'Cinode Recruitment Plugin', 'manage_options', 'cinode_recruitment_main_menu', 'cinode_recruitment_settings_page', $iconUrl);

	//call register settings function
	add_action('admin_init', 'cinode_recruitment_register_settings');
}
add_action('admin_menu', 'cinode_recruitment_create_menu');

function cinode_recruitment_register_settings()
{

	//register our settings
	register_setting('cinode_recruitment-settings-group', 'cinode_recruitment_options', 'cinode_recruitment_sanitize_options');
	register_setting('cinode_recruitment-settings-mail', 'cinode_recruitment_options_sendmail', 'cinode_recruitment_sanitize_options_sendmail');	
}

function cinode_recruitment_sanitize_options($input)
{

	$input['option_companyId']  = sanitize_text_field($input['option_companyId']);
	$input['option_apiKey'] =  sanitize_text_field($input['option_apiKey']);


	return $input;
}
function cinode_recruitment_sanitize_options_sendmail($input)
{

	$input['option_subject']  = sanitize_text_field($input['option_subject']);
	$input['option_message'] =  sanitize_text_field($input['option_message']);


	return $input;
}

function cinode_recruitment_settings_page()
{
?>
<div class="wrap">
	<h2>Cinode Recruitment Plugin Settings</h2>

	<form method="post" action="options.php">
		<?php settings_fields('cinode_recruitment-settings-group');
		$cinode_recruitment_options = get_option('cinode_recruitment_options');
		$activatedPlugin = '';
		$apiFieldVal = '';
		if (cinode_recruitment_apiTokenCheck()) {
			$activatedPlugin = '<span style="color:green; font-weight:bold;">Plugin is Activated!</span>';
			$apiFieldVal = '***';
		} else{
			$activatedPlugin = '<span style="color:red; font-weight:bold;">Plugin is not activated or API token is expired!</span>';
		}
		wp_nonce_field('cinode_recruitment_settings_form_save', 'cinode_recruitment_nonce_field'); ?>
		<p>Welcome to Cinode Recruitment Plugin settings page. Set your Company ID and API token.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Company ID</th>
				<td><input type="text" name="cinode_recruitment_options[option_companyId]" value="<?php echo esc_attr($cinode_recruitment_options['option_companyId']); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">API Token</th>
				<td><input type="password" name="cinode_recruitment_options[option_apiKey]" value="<?php echo $apiFieldVal ?>" /></td>
			</tr>

		</table>
		<p > <?php echo $activatedPlugin;  ?></p>

		<p class="submit">
			<input type="submit" class="button-primary" value="Save Changes" />
		</p>
	</form>


	<h3>How to use shortcode</h3>

	<p><b>Default shortcode:</b> [cinode]</p>
	<p>Insert your shortcode into your page or post.</p>

	<p><b>If you want to set custom parametters for your recruitment, add one of the following combination of parameters.</b> </p>

	<p>[cinode pipelineId = "0" pipelineStageId = "0" recruitmentManagerId = "0" teamId = "0" recruitmentSourceId = "0" campaignCode = "0" currencyId = "1"]</p>
	<p>If you want to setup pipelineId, you need to set pipelineStageId. <br>
		Custom labels for the fields can be changed with tags in shortcode. Text must be inside the quotes "".</p>
	<p> firstname_label="Custom Name" lastname_label="Custom Last Name" email_label="Custom e-mail" phone_label="Custom Phone" message_label="Custom Message" linkedin_label="Custom LinkedIn" location_label="Custom Location Label" attachment_label="Custom Attachment" accept_label="Custom Accept text" privacy_url="https://google.com" privacy_error="Please Accept GDPR" submitbutton_label="Custom Submit application" successful-submit-msg="Thanks for application" unsuccessful-submit-msg="App Not Send" requiredfield_msg="Custom Required Message"</p>
	<p><b>All available shortcodes are:</b></p>
	<p>[cinode pipelineId = "0" pipelineStageId = "0" recruitmentManagerId = "0" teamId = "0" recruitmentSourceId = "0" campaignCode = "0" currencyId = "1" firstname_label="Custom Name" lastname_label="Custom Last Name" email_label="Custom e-mail" phone_label="Custom Phone" message_label="Custom Message" linkedin_label="Custom LinkedIn" location_label="Custom Location Label" attachment_label="Custom Attachment" accept_label="Custom Accept text" privacy_url="https://google.com" privacy_error="Please Accept GDPR" submitbutton_label="Custom Submit application" successful-submit-msg="Thanks for application" unsuccessful-submit-msg="App Not Send" requiredfield_msg="Custom Required Message"]</p>
	<p>If you want to hide Location field use shortcode tag location_label="". If there is no text, field is not shown in the form.</p>	
	<p></p>
	<p>If you want to enable field for availableFrom use shortcode tag: availableFrom_label="Available from:"</p>
	<p></p>
	<p>If you want enable field for multi pipeline selection add shortcode tags, example: [cinode multiplepipelines="1235:Pipeline 1,1676:Pipeline 2" multiplepipeline_stageid="6003,7783" multiplepipelines_label="Multiple Pipeline select one"]</p>
	<p>multiplepipelines="pipelineID:Pipeline Label for dropdown"</p>
	<p>multiplepipeline_stageid="StageID corespondin to PipelineID"</p>
	<p>multiplepipelines_label="Label on top of the dropdown"</p>
	<h2>Send confirmation mail to candidate</h2>

	<form method="post" action="options.php">
		<?php settings_fields('cinode_recruitment-settings-mail');

		$cinode_recruitment_options_sendmail = get_option('cinode_recruitment_options_sendmail');
		if (!$cinode_recruitment_options_sendmail) {
			$cinode_recruitment_options_sendmail['option_subject'] = 'Thanks for your application';
			$cinode_recruitment_options_sendmail['option_message'] = 'Thank you for your application. We will look into your application and get back to you soon.';
		}
		?>
		<p>Set confirmation mail to send to candidate.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Subject</th>
				<td><input type="text" name="cinode_recruitment_options_sendmail[option_subject]" style="width:50%;" value="<?php echo esc_attr($cinode_recruitment_options_sendmail['option_subject']); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">Message body</th>
				<td><input type="text" name="cinode_recruitment_options_sendmail[option_message]" style="width:50%;" value="<?php echo esc_attr($cinode_recruitment_options_sendmail['option_message']); ?>" /></td>
			</tr>

		</table>

		<p class="submit">
			<input type="submit" class="button-primary" value="Save email message" />
		</p>
	</form>
	<p>If you want to use custom SMTP server to send mail, please install WP mail SMTP plugin. </p>

	<h2>Spam protection with Google Recaptcha</h2>
	
	<p>If you want to protect from spam, Register your Recaptca keys on <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Google Recaptcha</a>. Install plugin <a href="https://wordpress.org/plugins/advanced-nocaptcha-recaptcha/">CAPTCHA 4WP</a> (version 7.6 or later) and set your Site Key and Secret.</p>
	<?php if (cinode_recruitment_captcha_enabled()) : ?>
		<p><span style="color:green; font-weight:bold;">CAPTCHA is active and verified on the server for every application.</span></p>
	<?php else : ?>
		<p><span style="color:#b26200; font-weight:bold;">CAPTCHA is not active. Applications are only protected by rate limiting.</span></p>
	<?php endif; ?>

	
</div>
<?php
}