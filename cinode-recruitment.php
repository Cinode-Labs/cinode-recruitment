<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              cinode.com
 * @since             1.0.0
 * @package           Cinode_Recruitment
 *
 * @wordpress-plugin
 * Plugin Name:       Cinode recruitment plugin
 * Plugin URI:        cinode.com
 * Description:       This is Cinode Candidate Recruitment plugin. 
 * Version:           1.1.0
 * Author:            Cinode
 * Author URI:        cinode.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cinode-recruitment
 
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 */
define('CINODE_RECRUITMENT_VERSION', '1.1.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cinode-recruitment-activator.php
 */
function activate_cinode_recruitment()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment-activator.php';
	Cinode_Recruitment_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cinode-recruitment-deactivator.php
 */
function deactivate_cinode_recruitment()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment-deactivator.php';
	Cinode_Recruitment_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_cinode_recruitment');
register_deactivation_hook(__FILE__, 'deactivate_cinode_recruitment');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cinode_recruitment()
{

	$plugin = new Cinode_Recruitment();
	$plugin->run();
}
run_cinode_recruitment();



function cinode_recruitment_route()
{
	register_rest_route(
		'cinode/v2',
		'cinode-recruitment',
		array(
			array(
				'methods' => 'POST',
				'callback' => 'cinodeRecruitmentPost',
				'args' =>  array(
					'firstName' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'First name'
					),
					'lastName' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Last name'
					),
					'email' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Email address'
					),
					'phone' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Phone ID'
					),
					'description' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Description'
					),
					'linkedInUrl' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'linkedIn  address'
					),
					'state' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'State',
						'value' => 0,
					),
					'currencyId' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'currencyId',
						'value' => 1,
					),
					'pipelineId' => array(
						'type' => 'int',
						'description' => 'pipelineId',
					),
					'pipelineStageId' => array(
						'type' => 'int',
						'description' => 'pipelineStageId',
					),
					'recruitmentManagerId' => array(
						'type' => 'int',
						'description' => 'recruitmentManagerId',
					),
					'teamId' => array(
						'type' => 'int',
						'description' => 'teamId',
					),
					'companyAddressId' => array(
						'type' => 'int',
						'description' => 'companyAddressId',
					),
					'recruitmentSourceId' => array(
						'type' => 'int',
						'description' => 'recruitmentSourceId',
					),
					'campaignCode' => array(
						'type' => 'int',
						'description' => 'campaignCode',
					),

					'files' => array(),


				)
			)
		)
	);
}


function cinodeRecruitmentPost($postData)
{

	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId . "/candidates";

	$body = array(
		'firstName' => $postData['firstName'],
		'lastName' => $postData['lastName'],
		'description' => $postData['description'],
		'email' => $postData['email'],
		'phone' => $postData['phone'],
		'linkedInUrl' => $postData['linkedInUrl'],
		'state' => $postData['state'],
		'currencyId' => $postData['currencyId'],
		'pipelineId' => $postData['pipelineId'],
		'pipelineStageId' => (int) $postData['pipelineStageId'],
		'recruitmentManagerId' => $postData['recruitmentManagerId'],
		'teamId' => $postData['teamId'],
		'companyAddressId' => $postData['companyAddressId'],
		'recruitmentSourceId' => $postData['recruitmentSourceId'],
		'currencyId' => $postData['currencyId'],
	);


	$args = array(
		'body' => wp_json_encode($body),
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),

	);

	$post_result = wp_remote_post($url, $args);

	$json_response =  json_decode(wp_remote_retrieve_body($post_result), true);

	$candidateId = $json_response['id'];

	if (!empty($postData->get_file_params())) {
		cinode_recruitment_upload_file($postData, $candidateId);
	}

	if ($post_result['response']['code'] == 201) {
		cinode_recruitment_send_mail($postData['email']);
	}

	return $post_result;
}

add_action('rest_api_init', 'cinode_recruitment_route');

function cinode_recruitment_upload_file($request, $candidateId)
{
	// Get the file 
	$files   = $request->get_file_params();
	$target_dir_array =  wp_upload_dir();
	$target_dir = $target_dir_array['path'] . '/';
	$target_file = $target_dir . basename($files['files']['name']);

	move_uploaded_file($files['files']['tmp_name'], $target_file);

	$name       = $files['files']['name'];
	$type       = $files['files']['type'];
	$path 		= $target_file;


	$file = @fopen($path, 'r');
	$file_size = filesize($path);
	$file_data = fread($file, $file_size);


	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];

	$url_attach = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/candidates/' . $candidateId . '/attachments';

	$boundary = boundary();

	$body = '';
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename($path) . "\"\r\n";
	$body .= 'Content-Type: ' . $type . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="title"' . "\r\n";
	$body .= 'Content-Type: application/json' . "\r\n\r\n";
	$body .= $name . "\r\n";
	$body .= '--' . $boundary . '--' . "\r\n";


	$args = array(
		'body' => $body,
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $token,
		),

	);

	$post_attach_result = wp_remote_post($url_attach, $args);

	wp_delete_file($path);

	return $post_attach_result;
}

function boundary()
{
	$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
	$pass = array();
	$alphaLength = strlen($alphabet) - 1;
	for ($i = 0; $i < 8; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet[$n];
	}
	return implode($pass);
}


add_action('admin_menu', 'cinode_recruitment_create_menu');

function cinode_recruitment_create_menu()
{

	$iconUrl = plugin_dir_url(__FILE__) . 'images/icon-24x24.png';
	//create new top-level menu
	add_menu_page('Cinode Recruitment Plugin Page', 'Cinode Recruitment Plugin', 'manage_options', 'cinode_recruitment_main_menu', 'cinode_recruitment_settings_page', $iconUrl);

	//call register settings function
	add_action('admin_init', 'cinode_recruitment_register_settings');
}

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

function cinode_recruitment_apiTokenCheck()
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId;

	$args = array(
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$get_result = wp_remote_get($url, $args);

	$json_response =  json_decode(wp_remote_retrieve_body($get_result), true);

	if ($json_response) {
		return true;
	} else {
		return false;
	}
}
function cinode_recruitment_send_mail($email)
{
	$to = $email;
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$cinode_recruitment_options_sendmail = get_option('cinode_recruitment_options_sendmail');
	$subject = $cinode_recruitment_options_sendmail['option_subject'];
	$body = $cinode_recruitment_options_sendmail['option_message'];

	wp_mail($to, $subject, $body, $headers);
}

function cinode_recruitment_companyAddresses($location_label)
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId;

	$args = array(
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$get_result = wp_remote_get($url, $args);
	$json_response =  json_decode(wp_remote_retrieve_body($get_result), true);

	if (sizeof($json_response['addresses']) > 0) {

?>
		<label for="companyAddressId"><?php echo $location_label; ?></label><br>

		<select name="companyAddressId" id="companyAddressId">

			<?php

			for ($i = 0; $i < sizeof($json_response['addresses']); $i++) {

			?>
				<option value="<?php echo $json_response['addresses'][$i]['id']; ?>"><?php echo $json_response['addresses'][$i]['city']; ?></option>
			<?php
			}
			?>
		</select>
	<?php
	}
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
				$activatedPlugin = 'Plugin is Activated!';
				$apiFieldVal = '***';
			}
			wp_nonce_field('cinode_recruitment_settings_form_save', 'cinode_recruitment_nonce_field'); ?>
			<p>Welcome to Cinode Recruitment Plugin settings page. Set your Company ID and API key.</p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Company ID</th>
					<td><input type="text" name="cinode_recruitment_options[option_companyId]" value="<?php echo esc_attr($cinode_recruitment_options['option_companyId']); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row">API Key</th>
					<td><input type="password" name="cinode_recruitment_options[option_apiKey]" value="<?php echo $apiFieldVal ?>" /></td>
				</tr>

			</table>
			<p style="color:green; font-weight:bold;"> <?php echo $activatedPlugin;  ?></p>

			<p class="submit">
				<input type="submit" class="button-primary" value="Save Changes" />
			</p>
		</form>


		<h3>How to use shortcode</h3>

		<p>Default shortcode: [cinode]</p>
		<p>Insert your shortcode into your page or post.</p>

		<p>If you want to set custom parametters for your recruitment, add one of the following combination of parameters. </p>

		<p>[cinode pipelineId = "0" pipelineStageId = "0" recruitmentManagerId = "0" teamId = "0" recruitmentSourceId = "0" campaignCode = "0" currencyId = "1"]</p>
		<p>If you want to setup pipelineId, you need to set pipelineStageId. <br>
			Custom labels for the fields can be changed with tags in shortcode. Text must be inside the quotes "".</p>
		<p> firstname_label="Custom Name" lastname_label="Custom Last Name" email_label="Custom e-mail" phone_label="Custom Phone" message_label="Custom Message" linkedin_label="Custom LinkedIn" location_label="Custom Location Label" attachment_label="Custom Attachment" accept_label="Custom Accept text" privacy_url="https://google.com" privacy_error="Please Accept GDPR" submitbutton_label="Custom Submit application" successful-submit-msg="Thanks for application" unsuccessful-submit-msg="App Not Send" requiredfield_msg="Custom Required Message"</p>
		<p>All available shortcodes are:</p>
		<p>[cinode pipelineId = "0" pipelineStageId = "0" recruitmentManagerId = "0" teamId = "0" recruitmentSourceId = "0" campaignCode = "0" currencyId = "1" firstname_label="Custom Name" lastname_label="Custom Last Name" email_label="Custom e-mail" phone_label="Custom Phone" message_label="Custom Message" linkedin_label="Custom LinkedIn" location_label="Custom Location Label" attachment_label="Custom Attachment" accept_label="Custom Accept text" privacy_url="https://google.com" privacy_error="Please Accept GDPR" submitbutton_label="Custom Submit application" successful-submit-msg="Thanks for application" unsuccessful-submit-msg="App Not Send" requiredfield_msg="Custom Required Message"]</p>

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
					<td><input type="text" name="cinode_recruitment_options_sendmail[option_message]" style="width:50%;" value="<?php echo $cinode_recruitment_options_sendmail['option_message'] ?>" /></td>
				</tr>

			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="Save email message" />
			</p>
		</form>
		<p>If you want to use custom SMTP server to send mail, please install WP mail SMTP plugin. </p>
	</div>
<?php
}


add_shortcode('cinode', 'cinode_recruitment_shortcode');

function cinode_recruitment_shortcode($atts = [])
{
	$atts = array_change_key_case((array) $atts, CASE_LOWER);

	$args = shortcode_atts(array(
		'pipelineid' => 0,
		'pipelinestageid' => 0,
		'recruitmentmanagerid' => 0,
		'teamid' => 0,
		'companyaddressid' => 0,
		'recruitmentsourceid' => 0,
		'campaigncode' => 0,
		'currencyid' => 1,
		// add custom labels
		'firstname_label' => 'First name',
		'lastname_label' => 'Last name',
		'email_label' => 'E-mail',
		'phone_label' => 'Phone',
		'message_label' => 'Message',
		'linkedin_label' => 'LinkedIn Url',
		'location_label' => 'Choose location:',
		'attachment_label' => 'Attachment',
		'accept_label' => 'I accept that my personal data is processed in accordance with GDPR',
		'privacy_url' => '',
		'privacy_error' => 'You must accept the terms & conditions.',
		'submitbutton_label' => 'Submit your application',
		'successful-submit-msg' => 'Thanks for your application, we\'re looking forward to have a look at your profile!',
		'unsuccessful-submit-msg' => 'Your application is not sent!',
		'requiredfield_msg' => 'Required field.',
		'formtitle' => '',
	), $atts, $shortcode = "cinode");

	ob_start();
?>

	<div class="wrap">

		<h2><?php echo $args['formtitle']; ?></h2>
		<div role="form" class="cinode-form" lang="en-US" dir="ltr">
			<form action="#" method="post" id="cinode-form" enctype="multipart/form-data">
				<script>
					var pipelineId = <?php echo $args['pipelineid']; ?>;
					var pipelineStageId = <?php echo $args['pipelinestageid']; ?>;
					var recruitmentManagerId = <?php echo $args['recruitmentmanagerid']; ?>;
					var teamId = <?php echo $args['teamid']; ?>;
					var companyAddressId = <?php echo $args['companyaddressid']; ?>;
					var recruitmentSourceId = <?php echo $args['recruitmentsourceid']; ?>;
					var campaignCode = "<?php echo $args['campaigncode']; ?>";
					var currencyId = <?php echo $args['currencyid']; ?>;
				</script>
				<div>
					<label><?php echo $args['firstname_label']; ?> *<br>
						<span class=""><input type="text" id="first_name-input" name="first_name" value="" size="100%" class="text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="first_name-required" class="alert-required" style="display: none"><?php echo $args['requiredfield_msg']; ?></span></span>
					</label><br>
					<label><?php echo $args['lastname_label']; ?> *<br>
						<span class=""><input type="text" id="last_name-input" name="last_name" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="last_name-required" class="alert-required" style="display: none"><?php echo $args['requiredfield_msg']; ?></span></span> </label><br>
					<label><?php echo $args['email_label']; ?> *<br>
						<span class=""><input type="email" id="email-input" name="email" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="email-required" class="alert-required" style="display: none"><?php echo $args['requiredfield_msg']; ?></span></span>
					</label><br>
					<label><?php echo $args['phone_label']; ?><br>
						<span class=""><input type="text" id="phone-input" placeholder=" " name="phone" value="" size="100%" class=" text" aria-required="false" aria-invalid="false">
							<span role="alert" id="phone-required" class="alert-required" style="display: none"><?php echo $args['requiredfield_msg']; ?></span>
						</span>
					</label><br>
					<label><?php echo $args['message_label']; ?> <br>
						<textarea class="autosize" cols="20" id="description-input" name="Description" size="100%" rows="2" style="overflow-wrap: break-word; resize: vertical; height: 150px;"></textarea>
					</label><br>
					<label><?php echo $args['linkedin_label']; ?><br>
						<input data-val="true" size="100%" id="LinkedInUrl" name="LinkedInUrl" type="text" value="">
					</label> <br>

					<?php
					$location_label = $args['location_label'];
					cinode_recruitment_companyAddresses($location_label);
					?>
					<br><br>
					<div class="block recruit-attachment">
						<div class="box">
							<div class="btn-upload-single">
								<label for="Attachments"><?php echo $args['attachment_label']; ?></label>
								<input id="Attachments" name="Attachments" type="file">
								<span class="field-validation-valid" data-valmsg-for="Attachments" data-valmsg-replace="true"></span>
							</div>


						</div>
						<label id="file-name"></label>
					</div>
					<br>
					<input type="checkbox" name="terms" id="terms">
					<a href="<?php echo $args['privacy_url']; ?>" rel="noopener noreferrer" target="_blank">
						<?php echo $args['accept_label']; ?></a>
					<br>
					<span id="terms-validate" style="display:none; color:red;"> <?php echo $args['privacy_error']; ?></span>
				</div>
		</div>

		<div class="row">
			<div>
				<br>
				<p><input type="submit" id="submit" value="<?php echo $args['submitbutton_label']; ?>"></p>
			</div>
		</div>
		<div class="spinner" style="display: none;">
			<div class="bounce1"></div>
			<div class="bounce2"></div>
			<div class="bounce3"></div>
		</div>

		<div class="alert" id="successful-submit-msg" style="display:none; background: green; color: white; text-align: center;">
			<?php echo $args['successful-submit-msg']; ?>
		</div>
		<div class="alert" id="unsuccessful-submit-msg" style="display: none; background: red; color: white; text-align: center;">
			<?php echo $args['unsuccessful-submit-msg']; ?>
		</div>
	</div>
	
<?php

	return ob_get_clean();
}
