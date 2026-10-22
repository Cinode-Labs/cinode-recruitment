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
 * Version:           1.5.2
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
define('CINODE_RECRUITMENT_VERSION', '1.5.2');

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
				// Public application form. Abuse is limited by CAPTCHA, rate limiting and the signed form config.
				'permission_callback' => '__return_true',
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
						'type' => 'string',
						'description' => 'Phone'
					),
					'description' => array(
						'type' => 'string',
						'description' => 'Description'
					),
					'linkedInUrl' => array(
						'type' => 'string',
						'description' => 'LinkedIn address'
					),
					'companyAddressId' => array(
						'type' => 'string',
						'description' => 'Selected company address, only used when the form shows the location field'
					),
					'selectedPipelineId' => array(
						'type' => 'string',
						'description' => 'Selected pipeline, must be one of the pipelines configured in the shortcode'
					),
					'availableFrom' => array(
						'type' => 'string',
						'description' => 'availableFrom (YYYY-MM-DD)',
					),
					'captchaResponse' => array(
						'type' => 'string',
						'description' => 'CAPTCHA token',
					),
					'formConfig' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Signed form configuration rendered by the shortcode',
					),
				)
			)
		)
	);
}

function cinodeRecruitmentPost($request)
{
	if (cinode_recruitment_is_rate_limited()) {
		return cinode_recruitment_rest_response(429);
	}

	$config = cinode_recruitment_read_form_config($request['formConfig']);
	if ($config === null) {
		return cinode_recruitment_rest_response(400);
	}

	if (!cinode_recruitment_verify_captcha($request)) {
		return cinode_recruitment_rest_response(403);
	}

	$first_name = sanitize_text_field((string) $request['firstName']);
	$last_name  = sanitize_text_field((string) $request['lastName']);
	$email      = trim((string) $request['email']);

	// Validate before sanitizing: sanitize_email() would turn "a@b.c, x@y.z" into a different valid-looking address.
	if ($first_name === '' || $last_name === '' || !is_email($email)) {
		return cinode_recruitment_rest_response(400);
	}

	$email = sanitize_email($email);

	// Validate the attachment before anything is sent to Cinode.
	$file = cinode_recruitment_get_validated_file($request);
	if (is_wp_error($file)) {
		return cinode_recruitment_rest_response($file->get_error_data());
	}

	if (true !== apply_filters('cinode_recruitment_verify_submission', true, $request)) {
		return cinode_recruitment_rest_response(403);
	}

	$body = array(
		'firstName' => $first_name,
		'lastName' => $last_name,
		'description' => sanitize_textarea_field((string) $request['description']),
		'email' => $email,
		'phone' => sanitize_text_field((string) $request['phone']),
		'linkedInUrl' => esc_url_raw((string) $request['linkedInUrl'], array('http', 'https')),
		'state' => 0,
		'currencyId' => intval($config['currencyId'] ?? 1),
	);

	// Routing fields come from the signed shortcode config, never directly from the request.
	$pipeline_id       = intval($config['pipelineId'] ?? 0);
	$pipeline_stage_id = intval($config['pipelineStageId'] ?? 0);
	$selected_pipeline = intval($request['selectedPipelineId']);
	if ($selected_pipeline > 0 && !empty($config['pipelines']) && is_array($config['pipelines'])) {
		foreach ($config['pipelines'] as $pipeline) {
			if (intval($pipeline['id'] ?? 0) === $selected_pipeline) {
				$pipeline_id       = $pipeline_id ?: $selected_pipeline;
				$pipeline_stage_id = $pipeline_stage_id ?: intval($pipeline['stageId'] ?? 0);
				break;
			}
		}
	}

	$company_address_id = intval($config['companyAddressId'] ?? 0);
	if ($company_address_id === 0 && !empty($config['locationSelect'])) {
		$company_address_id = intval($request['companyAddressId']);
	}

	$optional_ids = array(
		'pipelineId' => $pipeline_id,
		'pipelineStageId' => $pipeline_stage_id,
		'recruitmentManagerId' => intval($config['recruitmentManagerId'] ?? 0),
		'teamId' => intval($config['teamId'] ?? 0),
		'companyAddressId' => $company_address_id,
		'recruitmentSourceId' => intval($config['recruitmentSourceId'] ?? 0),
	);
	foreach ($optional_ids as $key => $value) {
		if ($value > 0) {
			$body[$key] = $value;
		}
	}

	$available_from = (string) $request['availableFrom'];
	if (!empty($config['availableFrom']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $available_from)) {
		$body['availableFromDate'] = $available_from;
	}

	$campaign_code = sanitize_text_field((string) ($config['campaignCode'] ?? ''));
	if ($campaign_code !== '') {
		$body['campaignCode'] = $campaign_code;
	}

	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = intval($cinode_recruitment_options['option_companyId'] ?? 0);
	$token = $cinode_recruitment_options['option_apiKey'] ?? '';
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId . "/candidates";

	$args = array(
		'body' => wp_json_encode($body),
		'timeout' => 20,
		'headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$post_result = wp_remote_post($url, $args);

	if (is_wp_error($post_result)) {
		error_log('Cinode Recruitment: candidate create failed: ' . $post_result->get_error_message());
		return cinode_recruitment_rest_response(502);
	}

	$response_code = wp_remote_retrieve_response_code($post_result);
	if ($response_code !== 201) {
		error_log('Cinode Recruitment: candidate create returned HTTP ' . $response_code);
		return cinode_recruitment_rest_response(502);
	}

	$json_response = json_decode(wp_remote_retrieve_body($post_result), true);
	$candidateId = intval($json_response['id'] ?? 0);

	if ($file !== null && $candidateId > 0) {
		cinode_recruitment_upload_file($file, $candidateId);
	}

	cinode_recruitment_send_mail($email);

	return cinode_recruitment_rest_response(201);
}

add_action('rest_api_init', 'cinode_recruitment_route');

function cinode_recruitment_rest_response($status)
{
	return new WP_REST_Response(array('success' => $status === 201), $status);
}

function cinode_recruitment_is_rate_limited()
{
	$limit  = (int) apply_filters('cinode_recruitment_rate_limit', 5);
	$window = (int) apply_filters('cinode_recruitment_rate_limit_window', 10 * MINUTE_IN_SECONDS);

	if ($limit < 1 || $window < 1) {
		return false;
	}

	// REMOTE_ADDR on purpose: forwarded headers can be spoofed by the client.
	$ip    = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$key   = 'cinode_recruitment_rl_' . md5($ip);
	$count = (int) get_transient($key);

	if ($count >= $limit) {
		return true;
	}

	set_transient($key, $count + 1, $window);
	return false;
}

function cinode_recruitment_captcha_enabled()
{
	if (!function_exists('c4wp_verify_captcha') || !class_exists('C4WP\C4WP_Functions')) {
		return false;
	}

	return trim((string) \C4WP\C4WP_Functions::c4wp_get_secret_key()) !== '';
}

function cinode_recruitment_verify_captcha($request)
{
	if (!cinode_recruitment_captcha_enabled()) {
		return true;
	}

	$token = sanitize_text_field((string) $request['captchaResponse']);

	// CAPTCHA 4WP accepts a missing token by default, so it has to be required here.
	if ($token === '') {
		return false;
	}

	return (bool) c4wp_verify_captcha($token);
}

function cinode_recruitment_render_captcha()
{
	if (class_exists('C4WP\C4WP_Functions') && method_exists('C4WP\C4WP_Functions', 'c4wp_captcha_form_field')) {
		\C4WP\C4WP_Functions::c4wp_captcha_form_field(true);
		return;
	}

	do_action('c4wp_captcha_form_field');
}

function cinode_recruitment_sign_form_config(array $config)
{
	$payload = base64_encode(wp_json_encode($config));
	return $payload . '.' . cinode_recruitment_form_config_signature($payload);
}

function cinode_recruitment_form_config_signature($payload)
{
	return hash_hmac('sha256', 'cinode-recruitment-form|' . $payload, wp_salt('auth'));
}

function cinode_recruitment_read_form_config($token)
{
	if (!is_string($token) || substr_count($token, '.') !== 1) {
		return null;
	}

	list($payload, $signature) = explode('.', $token);

	if (!hash_equals(cinode_recruitment_form_config_signature($payload), $signature)) {
		return null;
	}

	$config = json_decode((string) base64_decode($payload, true), true);

	return is_array($config) ? $config : null;
}

function cinode_recruitment_allowed_file_types()
{
	return apply_filters('cinode_recruitment_allowed_file_types', array(
		'pdf' => 'application/pdf',
		'doc' => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'odt' => 'application/vnd.oasis.opendocument.text',
		'rtf' => 'application/rtf',
	));
}

function cinode_recruitment_max_file_size()
{
	return (int) apply_filters('cinode_recruitment_max_file_size', min(10 * MB_IN_BYTES, wp_max_upload_size()));
}

/**
 * Returns null when no file was sent, the validated file on success, or a WP_Error
 * whose data is the HTTP status to respond with.
 */
function cinode_recruitment_get_validated_file($request)
{
	$files = $request->get_file_params();

	if (empty($files)) {
		return null;
	}

	$file = $files['files'] ?? null;

	if (!is_array($file) || is_array($file['name'] ?? null) || count($files) !== 1) {
		return new WP_Error('cinode_invalid_file', 'Invalid upload.', 400);
	}

	$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

	if ($error === UPLOAD_ERR_NO_FILE) {
		return null;
	}

	if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
		return new WP_Error('cinode_file_too_large', 'File too large.', 413);
	}

	if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
		return new WP_Error('cinode_invalid_file', 'Invalid upload.', 400);
	}

	$size = (int) filesize($file['tmp_name']);

	if ($size < 1) {
		return new WP_Error('cinode_invalid_file', 'Empty file.', 400);
	}

	if ($size > cinode_recruitment_max_file_size()) {
		return new WP_Error('cinode_file_too_large', 'File too large.', 413);
	}

	// Checks both the extension and the actual file content against the allowlist.
	$check = wp_check_filetype_and_ext($file['tmp_name'], (string) $file['name'], cinode_recruitment_allowed_file_types());

	if (empty($check['ext']) || empty($check['type'])) {
		return new WP_Error('cinode_invalid_file_type', 'File type not allowed.', 415);
	}

	$filename = cinode_recruitment_safe_filename((string) $file['name'], $check['ext']);

	return array(
		'tmp_name' => $file['tmp_name'],
		'name' => $filename,
		'type' => $check['type'],
	);
}

function cinode_recruitment_safe_filename($original, $ext)
{
	$name = sanitize_file_name(pathinfo($original, PATHINFO_FILENAME));
	$name = preg_replace('/[^A-Za-z0-9._ -]/', '', $name);
	$name = trim(substr($name, 0, 100), ' .');

	if ($name === '') {
		$name = 'cv';
	}

	return $name . '.' . $ext;
}

function cinode_recruitment_upload_file($file, $candidateId)
{
	// Read directly from PHP's temporary upload; the file is never written to wp-content/uploads.
	$file_data = file_get_contents($file['tmp_name']);

	if ($file_data === false) {
		error_log('Cinode Recruitment: could not read uploaded file');
		return null;
	}

	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = intval($cinode_recruitment_options['option_companyId'] ?? 0);
	$token = $cinode_recruitment_options['option_apiKey'] ?? '';

	$url_attach = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/candidates/' . intval($candidateId) . '/attachments';

	$boundary = cinode_recruitment_boundary();

	$body = '';
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="files"; filename="' . $file['name'] . "\"\r\n";
	$body .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="title"' . "\r\n";
	$body .= 'Content-Type: application/json' . "\r\n\r\n";
	$body .= $file['name'] . "\r\n";
	$body .= '--' . $boundary . '--' . "\r\n";

	$args = array(
		'body' => $body,
		'timeout' => 30,
		'headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$post_attach_result = wp_remote_post($url_attach, $args);

	if (is_wp_error($post_attach_result)) {
		error_log('Cinode Recruitment: attachment upload failed: ' . $post_attach_result->get_error_message());
	} elseif (wp_remote_retrieve_response_code($post_attach_result) >= 300) {
		error_log('Cinode Recruitment: attachment upload returned HTTP ' . wp_remote_retrieve_response_code($post_attach_result));
	}

	return $post_attach_result;
}

function cinode_recruitment_boundary()
{
	return 'cinode' . bin2hex(random_bytes(24));
}


add_action('admin_menu', 'cinode_recruitment_create_menu');

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

function cinode_recruitment_availableFrom($availableFrom_label)
{
?>
	<label for="availableFrom"><?php echo esc_html($availableFrom_label); ?></label><br>
	<input type="date" id="availableFrom" />
	<br>
	<?php
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

	if (wp_remote_retrieve_response_code($get_result) !== 200) {
		return;
	}

	$json_response = json_decode(wp_remote_retrieve_body($get_result), true);
	$addresses = isset($json_response['addresses']) && is_array($json_response['addresses']) ? $json_response['addresses'] : array();

	if (count($addresses) > 0) {
		?>
			<label for="companyAddressId"><?php echo esc_html($location_label); ?></label><br>

			<select name="companyAddressId" id="companyAddressId">
				<?php foreach ($addresses as $address) : ?>
					<option value="<?php echo intval($address['id'] ?? 0); ?>"><?php echo esc_html($address['city'] ?? ''); ?></option>
				<?php endforeach; ?>
			</select>
			<br>
		<?php
	}
}

/**
 * Parses multiplepipelines="id:Label,id:Label" and multiplepipeline_stageid="stage,stage".
 */
function cinode_recruitment_parse_pipelines($pipelines_string, $stageIds)
{
	$pipelines = array();

	if ((string) $pipelines_string === '') {
		return $pipelines;
	}

	$stages = explode(',', (string) $stageIds);

	foreach (explode(',', (string) $pipelines_string) as $i => $pair) {
		$parts = explode(':', $pair, 2);
		$pipelines[] = array(
			'id' => intval($parts[0]),
			'stageId' => intval($stages[$i] ?? 0),
			'label' => trim($parts[1] ?? ''),
		);
	}

	return $pipelines;
}

function cinode_recruitment_multiplepipelines($multiplepipelines_label, $pipelines)
{
	echo '<label for="selectedPipelineId">' . esc_html($multiplepipelines_label) . '</label>';
	echo '<br><select id="selectedPipelineId">';
	echo '<option value="">Select</option>';

	foreach ($pipelines as $pipeline) {
		echo '<option value="' . intval($pipeline['id']) . '">' . esc_html($pipeline['label']) . '</option>';
	}
	echo "</select><br>";
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
		'campaigncode' => '',
		'currencyid' => 1,
		'multiplepipelines' => '',
		'multiplepipeline_stageid' => 0,
		'availableFrom' => 0,
		'availablefrom_label' => '',
		// add custom labels
		'firstname_label' => 'First name',
		'lastname_label' => 'Last name',
		'email_label' => 'E-mail',
		'phone_label' => 'Phone',
		'message_label' => 'Message',
		'linkedin_label' => 'LinkedIn Url',
		'location_label' => 'Choose location:',
		'multiplepipelines_label' => '',
		'attachment_label' => 'Attachment',
		'accept_label' => 'I accept that my personal data is processed in accordance with GDPR',
		'privacy_url' => '',
		'privacy_error' => 'You must accept the terms & conditions.',
		'submitbutton_label' => 'Submit your application',
		'successful-submit-msg' => 'Thanks for your application, we\'re looking forward to have a look at your profile!',
		'unsuccessful-submit-msg' => 'Your application is not sent!',
		'requiredfield_msg' => 'Required field.',
		'formtitle' => '',
		'invalidfile_msg' => 'Please attach a PDF, Word or OpenDocument file (max %s MB).',
	), $atts, $shortcode = "cinode");

	$pipelines = cinode_recruitment_parse_pipelines($args['multiplepipelines'], $args['multiplepipeline_stageid']);

	// Routing values are signed so the REST endpoint can trust them without taking IDs from the visitor.
	$form_config = cinode_recruitment_sign_form_config(array(
		'pipelineId' => intval($args['pipelineid']),
		'pipelineStageId' => intval($args['pipelinestageid']),
		'recruitmentManagerId' => intval($args['recruitmentmanagerid']),
		'teamId' => intval($args['teamid']),
		'companyAddressId' => intval($args['companyaddressid']),
		'recruitmentSourceId' => intval($args['recruitmentsourceid']),
		'campaignCode' => sanitize_text_field($args['campaigncode']),
		'currencyId' => intval($args['currencyid']),
		'pipelines' => array_map(function ($pipeline) {
			return array('id' => $pipeline['id'], 'stageId' => $pipeline['stageId']);
		}, $pipelines),
		'locationSelect' => $args['location_label'] !== '',
		'availableFrom' => $args['availablefrom_label'] !== '',
	));

	$max_file_size_mb = round(cinode_recruitment_max_file_size() / MB_IN_BYTES, 1);
	$accept = implode(',', array_map(function ($ext) {
		return '.' . $ext;
	}, array_keys(cinode_recruitment_allowed_file_types())));

	ob_start();
	?>

	<div class="wrap">

		<h2><?php echo esc_html($args['formtitle']); ?></h2>
		<div role="form" class="cinode-form" lang="en-US" dir="ltr">
			<form action="#" method="post" id="cinode-form" enctype="multipart/form-data" data-cinode-config="<?php echo esc_attr($form_config); ?>">
				<div>
					<label><?php echo esc_html($args['firstname_label']); ?> *<br>
						<span class=""><input type="text" id="first_name-input" name="first_name" value="" size="100%" class="text " aria-required="true" aria-invalid="false" placeholder=" "></span>
						<span role="alert" id="first_name-required" class="alert-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
					</label><br>
					<label><?php echo esc_html($args['lastname_label']); ?> *<br>
						<span class=""><input type="text" id="last_name-input" name="last_name" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="last_name-required" class="alert-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span></span> </label><br>
					<label><?php echo esc_html($args['email_label']); ?> *<br>
						<span class=""><input type="email" id="email-input" name="email" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="email-required" class="alert-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span></span>
					</label><br>
					<label><?php echo esc_html($args['phone_label']); ?><br>
						<span class=""><input type="text" id="phone-input" placeholder=" " name="phone" value="" size="100%" class=" text" aria-required="false" aria-invalid="false"></span>
						<span role="alert" id="phone-required" class="alert-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
						</span>
					</label><br>
					<label><?php echo esc_html($args['message_label']); ?> <br>
						<textarea class="autosize" cols="20" id="description-input" name="Description" size="100%" rows="2" style="overflow-wrap: break-word; resize: vertical; height: 150px;"></textarea>
					</label><br>
					<label><?php echo esc_html($args['linkedin_label']); ?><br>
						<input data-val="true" size="100%" id="LinkedInUrl" name="LinkedInUrl" type="text" value="">
					</label> <br>

					<?php
					$location_label = $args['location_label'];
					if ($location_label != '') {
						cinode_recruitment_companyAddresses($location_label);
					}

					$availableFrom_label = $args['availablefrom_label'];
					if ($availableFrom_label != '') {
						cinode_recruitment_availableFrom($availableFrom_label);
					}

					if (!empty($pipelines)) {
						cinode_recruitment_multiplepipelines($args['multiplepipelines_label'], $pipelines);
					}

					?>
					<br>
					<div for="file-upload" class="custom-file-upload ">
							<?php echo esc_html($args['attachment_label']); ?>
						<input id="file-upload" class="file-upload" type="file" accept="<?php echo esc_attr($accept); ?>" data-max-size="<?php echo intval(cinode_recruitment_max_file_size()); ?>" />
						<label class="file-name"></label>
					</div>
					<span role="alert" id="file-invalid" class="alert-required" style="display: none"><?php echo esc_html(str_replace('%s', $max_file_size_mb, $args['invalidfile_msg'])); ?></span>
					<br>
					<input type="checkbox" name="terms" id="terms">
					<a href="<?php echo esc_url($args['privacy_url']); ?>" rel="noopener noreferrer" target="_blank">
						<?php echo esc_html($args['accept_label']); ?></a>
					<br>
					<span id="terms-validate" style="display:none; color:red;"> <?php echo esc_html($args['privacy_error']); ?></span>
					<input type="hidden" name="g-recaptcha-response" value="" id="g-recaptcha-response">
					<?php cinode_recruitment_render_captcha(); ?>
				</div>
				<div class="row">
					<div>
						<br>
						<p><input type="submit" id="submit" value="<?php echo esc_attr($args['submitbutton_label']); ?>"></p>
					</div>
				</div>
				<div class="spinner" style="display: none;">
					<div class="bounce1"></div>
					<div class="bounce2"></div>
					<div class="bounce3"></div>
				</div>

				<div class="alert" id="successful-submit-msg" style="display:none; background: green; color: white; text-align: center;">
					<?php echo esc_html($args['successful-submit-msg']); ?>
				</div>
				<div class="alert" id="unsuccessful-submit-msg" style="display: none; background: red; color: white; text-align: center;">
					<?php echo esc_html($args['unsuccessful-submit-msg']); ?>
				</div>
			</form>
		</div>
	</div>


<?php
	return ob_get_clean();
}
