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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cinode-recruitment-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cinode-recruitment-admin.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'cinodeRecruitmentAdmin', array(
			'i18n' => array(
				'selectAStage'         => __( 'Select a stage', 'cinode-recruitment' ),
				'selectAPipelineFirst' => __( 'Select a pipeline first', 'cinode-recruitment' ),
				'copied'               => __( 'Copied!', 'cinode-recruitment' ),
			),
		) );

	}

}
function cinode_recruitment_create_menu()
{

	$iconUrl = plugin_dir_url(__FILE__) . '../images/icon-24x24.png';
	// Create new top-level menu
	add_menu_page('Cinode Recruitment Plugin Page', 'Cinode Recruitment Plugin', 'manage_options', 'cinode_recruitment_main_menu', 'cinode_recruitment_settings_page', $iconUrl);

	// Call register settings function
	add_action('admin_init', 'cinode_recruitment_register_settings');
}
add_action('admin_menu', 'cinode_recruitment_create_menu');

function cinode_recruitment_register_settings()
{

	// Register our settings
	register_setting('cinode_recruitment-settings-group', 'cinode_recruitment_options', 'cinode_recruitment_sanitize_options');
	register_setting('cinode_recruitment-settings-mail', 'cinode_recruitment_options_sendmail', 'cinode_recruitment_sanitize_options_sendmail');	
}

function cinode_recruitment_sanitize_options($input)
{
	$existing = get_option('cinode_recruitment_options', array());

	$input['option_companyId'] = sanitize_text_field($input['option_companyId'] ?? '');

	// Preserve the existing API key when the masked placeholder '***' is submitted.
	if (isset($input['option_apiKey']) && $input['option_apiKey'] === '***') {
		$input['option_apiKey'] = $existing['option_apiKey'] ?? '';
	} else {
		$input['option_apiKey'] = sanitize_text_field($input['option_apiKey'] ?? '');
	}

	$input['option_show_subcontractor_groups']         = isset($input['option_show_subcontractor_groups']) ? 1 : 0;
	$raw_group_ids = $input['option_subcontractor_group_ids'] ?? '';
	if (is_array($raw_group_ids)) {
		$input['option_subcontractor_group_ids'] = implode(',', array_filter(array_map('intval', $raw_group_ids)));
	} else {
		$input['option_subcontractor_group_ids'] = sanitize_text_field($raw_group_ids);
	}
	$allowed_selections                                = array('single', 'multiple');
	$raw_selection                                     = $input['option_subcontractor_group_selection'] ?? 'single';
	$input['option_subcontractor_group_selection']     = in_array($raw_selection, $allowed_selections, true) ? $raw_selection : 'single';
	$input['option_subcontractor_default_language_id'] = intval($input['option_subcontractor_default_language_id'] ?? 1);
	$input['option_default_candidate_pipeline_id']     = intval($input['option_default_candidate_pipeline_id'] ?? 0);
	$input['option_default_candidate_pipeline_stage_id'] = intval($input['option_default_candidate_pipeline_stage_id'] ?? 0);
	if ($input['option_default_candidate_pipeline_id'] <= 0) {
		$input['option_default_candidate_pipeline_stage_id'] = 0;
	}
	$input['option_cv_required']                       = isset($input['option_cv_required']) ? 1 : 0;
	$input['option_parse_cv']                          = isset($input['option_parse_cv']) ? 1 : 0;
	$raw_auto_ids = $input['option_auto_subcontractor_group_ids'] ?? '';
	if (is_array($raw_auto_ids)) {
		$input['option_auto_subcontractor_group_ids'] = implode(',', array_filter(array_map('intval', $raw_auto_ids)));
	} else {
		$input['option_auto_subcontractor_group_ids'] = sanitize_text_field($raw_auto_ids);
	}

	return $input;
}
function cinode_recruitment_sanitize_options_sendmail($input)
{
	$input['option_subject'] = sanitize_text_field($input['option_subject'] ?? '');
	$input['option_message'] = sanitize_textarea_field($input['option_message'] ?? '');

	return $input;
}

function cinode_recruitment_admin_fetch_groups()
{
	$opts      = get_option('cinode_recruitment_options', array());
	$companyId = $opts['option_companyId'] ?? '';
	$token     = $opts['option_apiKey'] ?? '';

	if (empty($companyId) || empty($token)) {
		return array();
	}

	$cache_key = 'cinode_admin_groups_' . md5((string) $companyId . $token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/companies/' . intval($companyId) . '/subcontractors/groups';
	$args = array(
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	);

	$response = wp_remote_get($url, $args);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return array();
	}

	$groups = json_decode(wp_remote_retrieve_body($response), true);
	$groups = is_array($groups) ? $groups : array();

	set_transient($cache_key, $groups, 5 * MINUTE_IN_SECONDS);

	return $groups;
}

function cinode_recruitment_admin_fetch_languages()
{
	$opts  = get_option('cinode_recruitment_options', array());
	$token = $opts['option_apiKey'] ?? '';

	if (empty($token)) {
		return array();
	}

	$cache_key = 'cinode_admin_languages_' . md5($token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/languages';
	$args = array(
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	);

	$response = wp_remote_get($url, $args);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return array();
	}

	$languages = json_decode(wp_remote_retrieve_body($response), true);
	$languages = is_array($languages) ? $languages : array();

	set_transient($cache_key, $languages, HOUR_IN_SECONDS);

	return $languages;
}

function cinode_recruitment_admin_fetch_candidate_pipelines()
{
	$opts      = get_option('cinode_recruitment_options', array());
	$companyId = $opts['option_companyId'] ?? '';
	$token     = $opts['option_apiKey'] ?? '';

	if (empty($companyId) || empty($token)) {
		return array();
	}

	$cache_key = 'cinode_admin_candidate_pipelines_' . md5((string) $companyId . $token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/companies/' . intval($companyId) . '/candidates/pipelines';
	$args = array(
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	);

	$response = wp_remote_get($url, $args);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return array();
	}

	$pipelines = json_decode(wp_remote_retrieve_body($response), true);
	$pipelines = is_array($pipelines) ? $pipelines : array();

	set_transient($cache_key, $pipelines, 5 * MINUTE_IN_SECONDS);

	return $pipelines;
}

function cinode_recruitment_settings_page()
{
	$cinode_opts = get_option('cinode_recruitment_options', array());
	$api_valid   = cinode_recruitment_apiTokenCheck();
	$apiFieldVal = $api_valid ? '***' : '';

	$all_groups         = $api_valid ? cinode_recruitment_admin_fetch_groups() : array();
	$all_languages      = $api_valid ? cinode_recruitment_admin_fetch_languages() : array();
	$all_candidate_pipelines = $api_valid ? cinode_recruitment_admin_fetch_candidate_pipelines() : array();
	$selected_group_ids = array_filter(array_map('intval', explode(',', $cinode_opts['option_subcontractor_group_ids'] ?? '')));
	$selected_auto_ids  = array_filter(array_map('intval', explode(',', $cinode_opts['option_auto_subcontractor_group_ids'] ?? '')));
	$selected_lang_id   = intval($cinode_opts['option_subcontractor_default_language_id'] ?? 1);
	$selected_candidate_pipeline_id = intval($cinode_opts['option_default_candidate_pipeline_id'] ?? 0);
	$selected_candidate_stage_id    = intval($cinode_opts['option_default_candidate_pipeline_stage_id'] ?? 0);

	$mail_opts = get_option('cinode_recruitment_options_sendmail', array());
	if (empty($mail_opts)) {
		$mail_opts = array(
			'option_subject' => 'Thanks for your application',
			'option_message' => 'Thank you for your application. We will look into your application and get back to you soon.',
		);
	}
?>
<div class="wrap cinode-wrap">

	<div class="cinode-page-header">
		<h1 class="cinode-page-title">
			<span class="dashicons dashicons-groups" aria-hidden="true"></span>
			<?php esc_html_e( 'Cinode Recruitment', 'cinode-recruitment' ); ?>
		</h1>
		<?php if ($api_valid) : ?>
			<span class="cinode-badge cinode-badge--active">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Connected', 'cinode-recruitment' ); ?>
			</span>
		<?php else : ?>
			<span class="cinode-badge cinode-badge--inactive">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Not connected — check your API credentials', 'cinode-recruitment' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<nav class="nav-tab-wrapper cinode-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin settings sections', 'cinode-recruitment' ); ?>">
		<a href="#tab-api" class="nav-tab nav-tab-active" data-tab="tab-api">
			<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'API & Activation', 'cinode-recruitment' ); ?></span>
		</a>
		<a href="#tab-subcontractor" class="nav-tab" data-tab="tab-subcontractor">
			<span class="dashicons dashicons-businessperson" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'CV & Subcontractors', 'cinode-recruitment' ); ?></span>
		</a>
		<a href="#tab-candidate" class="nav-tab" data-tab="tab-candidate">
			<span class="dashicons dashicons-id" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'Candidate Defaults', 'cinode-recruitment' ); ?></span>
		</a>
		<a href="#tab-email" class="nav-tab" data-tab="tab-email">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'Email Confirmation', 'cinode-recruitment' ); ?></span>
		</a>
		<a href="#tab-shortcode" class="nav-tab" data-tab="tab-shortcode">
			<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'Shortcode Reference', 'cinode-recruitment' ); ?></span>
		</a>
		<a href="#tab-spam" class="nav-tab" data-tab="tab-spam">
			<span class="dashicons dashicons-shield" aria-hidden="true"></span>
			<span class="cinode-tab-label"><?php esc_html_e( 'Spam Protection', 'cinode-recruitment' ); ?></span>
		</a>
	</nav>

	<!-- ============================================================
	     MAIN SETTINGS FORM — API credentials + CV & Subcontractors
	     Both tab sections share this form so saving from either tab
	     preserves all settings in cinode_recruitment_options.
	     ============================================================ -->
	<form method="post" action="options.php" id="cinode-main-settings-form">
		<?php settings_fields('cinode_recruitment-settings-group'); ?>

		<!-- TAB: API & Activation -->
		<div id="tab-api" class="cinode-tab-content">
			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						<?php esc_html_e( 'API Credentials', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<p><?php printf( __( 'Enter your Cinode %1$sCompany ID%2$s and %1$sAPI Token%2$s to activate the plugin. You can find these in your Cinode account settings.', 'cinode-recruitment' ), '<strong>', '</strong>' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_company_id"><?php esc_html_e( 'Company ID', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="cinode_company_id"
									name="cinode_recruitment_options[option_companyId]"
									value="<?php echo esc_attr($cinode_opts['option_companyId'] ?? ''); ?>"
									class="regular-text"
									placeholder="e.g. 1234"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_api_key"><?php esc_html_e( 'API Token', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									id="cinode_api_key"
									name="cinode_recruitment_options[option_apiKey]"
									value="<?php echo esc_attr($apiFieldVal); ?>"
									class="regular-text"
									autocomplete="new-password"
									placeholder="<?php echo esc_attr( $api_valid ? __( 'Token saved — enter a new value to replace it', 'cinode-recruitment' ) : __( 'Paste your API token here', 'cinode-recruitment' ) ); ?>"
								/>
								<p class="description"><?php echo $api_valid ? wp_kses( __( 'Your token is saved. Leave the field showing <code>***</code> to keep it unchanged.', 'cinode-recruitment' ), array( 'code' => array() ) ) : esc_html__( 'Paste the token from your Cinode account.', 'cinode-recruitment' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save API Settings', 'cinode-recruitment' ), 'primary large', 'submit', true); ?>
				</div>
			</div>
		</div><!-- #tab-api -->

		<!-- TAB: CV & Subcontractors -->
		<div id="tab-subcontractor" class="cinode-tab-content" style="display:none;">

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-groups" aria-hidden="true"></span>
						<?php esc_html_e( 'Subcontractor Groups', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<p><?php esc_html_e( 'Control how the public form presents subcontractor group selection to applicants.', 'cinode-recruitment' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show group selector', 'cinode-recruitment' ); ?></th>
							<td>
								<fieldset>
									<label for="cinode_show_groups">
										<input
											type="checkbox"
											id="cinode_show_groups"
											name="cinode_recruitment_options[option_show_subcontractor_groups]"
											value="1"
											<?php checked(1, $cinode_opts['option_show_subcontractor_groups'] ?? 0); ?>
										/>
										Enable the group selector when the form is used in subcontractor mode
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_group_ids"><?php esc_html_e( 'Groups to display', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<?php if (!empty($all_groups)) : ?>
									<select
										id="cinode_group_ids"
										name="cinode_recruitment_options[option_subcontractor_group_ids][]"
										multiple="multiple"
										size="<?php echo min(count($all_groups), 8); ?>"
										style="min-width:14rem;"
									>
										<?php foreach ($all_groups as $grp) :
											$grp_id   = intval($grp['id']);
											$grp_name = esc_html($grp['name'] ?? '#' . $grp_id);
										?>
											<option value="<?php echo $grp_id; ?>" <?php selected(in_array($grp_id, $selected_group_ids, true)); ?>><?php echo $grp_name; ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php printf( esc_html__( 'Hold %1$sCtrl%2$s / %1$sCmd%2$s to select multiple groups. Leave all unselected to show every group.', 'cinode-recruitment' ), '<kbd>', '</kbd>' ); ?></p>
								<?php else : ?>
									<input
										type="text"
										id="cinode_group_ids"
										name="cinode_recruitment_options[option_subcontractor_group_ids]"
										value="<?php echo esc_attr($cinode_opts['option_subcontractor_group_ids'] ?? ''); ?>"
										class="regular-text"
										placeholder="e.g. 12,34,56"
									/>
									<p class="description"><?php esc_html_e( 'Comma-separated group IDs. Leave empty to show all groups.', 'cinode-recruitment' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_group_selection"><?php esc_html_e( 'Selection mode', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<select id="cinode_group_selection" name="cinode_recruitment_options[option_subcontractor_group_selection]">
									<option value="single" <?php selected('single', $cinode_opts['option_subcontractor_group_selection'] ?? 'single'); ?>><?php esc_html_e( 'Single — dropdown', 'cinode-recruitment' ); ?></option>
									<option value="multiple" <?php selected('multiple', $cinode_opts['option_subcontractor_group_selection'] ?? 'single'); ?>><?php esc_html_e( 'Multiple — checkboxes', 'cinode-recruitment' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'How the applicant selects their group(s) in the form.', 'cinode-recruitment' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-join groups', 'cinode-recruitment' ); ?></th>
							<td>
								<?php if (!empty($all_groups)) : ?>
									<fieldset>
										<?php foreach ($all_groups as $grp) :
											$grp_id   = intval($grp['id']);
											$grp_name = esc_html($grp['name'] ?? '#' . $grp_id);
										?>
											<label>
												<input
													type="checkbox"
													name="cinode_recruitment_options[option_auto_subcontractor_group_ids][]"
													value="<?php echo $grp_id; ?>"
													<?php checked(in_array($grp_id, $selected_auto_ids, true)); ?>
												/>
												<?php echo $grp_name; ?>
											</label><br>
										<?php endforeach; ?>
									</fieldset>
								<p class="description"><?php echo wp_kses( __( 'Every new subcontractor is silently added to the ticked groups — no selector shown to the applicant. Can be overridden per shortcode: <code>auto_subcontractor_group_ids="12,34"</code>.', 'cinode-recruitment' ), array( 'code' => array() ) ); ?></p>
							<?php else : ?>
								<input
									type="text"
									id="cinode_auto_groups"
									name="cinode_recruitment_options[option_auto_subcontractor_group_ids]"
									value="<?php echo esc_attr($cinode_opts['option_auto_subcontractor_group_ids'] ?? ''); ?>"
									class="regular-text"
									placeholder="e.g. 12,34"
								/>
								<p class="description"><?php echo wp_kses( __( 'Comma-separated group IDs every new subcontractor is automatically added to — no selector is shown to the applicant. Can be overridden per shortcode: <code>auto_subcontractor_group_ids="12,34"</code>.', 'cinode-recruitment' ), array( 'code' => array() ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
						<?php esc_html_e( 'CV Upload', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'CV required by default', 'cinode-recruitment' ); ?></th>
							<td>
								<fieldset>
									<label for="cinode_cv_required">
										<input
											type="checkbox"
											id="cinode_cv_required"
											name="cinode_recruitment_options[option_cv_required]"
											value="1"
											<?php checked(1, $cinode_opts['option_cv_required'] ?? 0); ?>
										/>
									<?php esc_html_e( 'Make the file upload field mandatory', 'cinode-recruitment' ); ?>
								</label>
								<p class="description"><?php echo wp_kses( __( 'Override per shortcode: <code>cv_required="1"</code> or <code>cv_required="0"</code>.', 'cinode-recruitment' ), array( 'code' => array() ) ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Parse CV on upload', 'cinode-recruitment' ); ?></th>
							<td>
								<fieldset>
									<label for="cinode_parse_cv">
										<input
											type="checkbox"
											id="cinode_parse_cv"
											name="cinode_recruitment_options[option_parse_cv]"
											value="1"
											<?php checked(1, $cinode_opts['option_parse_cv'] ?? 0); ?>
										/>
									<?php esc_html_e( 'Import the uploaded CV into the applicant\'s Cinode user profile (asynchronous)', 'cinode-recruitment' ); ?>
								</label>
								<p class="description"><?php echo wp_kses( __( 'Applies to candidate and subcontractor forms when Cinode returns a company user ID. Override per shortcode: <code>parse_cv="1"</code> or <code>parse_cv="0"</code>.', 'cinode-recruitment' ), array( 'code' => array() ) ); ?></p>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-translation" aria-hidden="true"></span>
						<?php esc_html_e( 'Localisation', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_lang_id"><?php esc_html_e( 'Default language', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<?php if (!empty($all_languages)) : ?>
									<select id="cinode_lang_id" name="cinode_recruitment_options[option_subcontractor_default_language_id]">
										<?php foreach ($all_languages as $lang) :
											$lang_id   = intval($lang['languageId'] ?? $lang['id'] ?? 0);
											$lang_name = esc_html($lang['name'] ?? 'Language ' . $lang_id);
										?>
											<option value="<?php echo $lang_id; ?>" <?php selected($selected_lang_id, $lang_id); ?>><?php echo $lang_name; ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input
										type="number"
										id="cinode_lang_id"
										min="1"
										name="cinode_recruitment_options[option_subcontractor_default_language_id]"
										value="<?php echo esc_attr($selected_lang_id); ?>"
										class="small-text"
									/>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Language used when creating a subcontractor account in Cinode. Required by the API.', 'cinode-recruitment' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<?php submit_button( __( 'Save CV & Subcontractor Settings', 'cinode-recruitment' ), 'primary large', 'submit', true); ?>
		</div><!-- #tab-subcontractor -->

		<!-- TAB: Candidate Defaults -->
		<div id="tab-candidate" class="cinode-tab-content" style="display:none;">
			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-id" aria-hidden="true"></span>
						<?php esc_html_e( 'Candidate Pipeline Defaults', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<p><?php esc_html_e( 'Choose the candidate pipeline and stage used when a candidate shortcode does not include pipeline settings.', 'cinode-recruitment' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_default_candidate_pipeline_id"><?php esc_html_e( 'Default pipeline', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<?php if (!empty($all_candidate_pipelines)) : ?>
									<select id="cinode_default_candidate_pipeline_id" name="cinode_recruitment_options[option_default_candidate_pipeline_id]">
										<option value="0" <?php selected(0, $selected_candidate_pipeline_id); ?>><?php esc_html_e( 'No default pipeline', 'cinode-recruitment' ); ?></option>
										<?php foreach ($all_candidate_pipelines as $pipeline) :
											$pipeline_id    = intval($pipeline['id'] ?? 0);
											$pipeline_title = esc_html($pipeline['title'] ?? 'Pipeline ' . $pipeline_id);
											if ($pipeline_id <= 0) {
												continue;
											}
										?>
											<option value="<?php echo $pipeline_id; ?>" <?php selected($selected_candidate_pipeline_id, $pipeline_id); ?>><?php echo $pipeline_title; ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input
										type="number"
										id="cinode_default_candidate_pipeline_id"
										min="0"
										name="cinode_recruitment_options[option_default_candidate_pipeline_id]"
										value="<?php echo esc_attr($selected_candidate_pipeline_id); ?>"
										class="small-text"
									/>
								<?php endif; ?>
								<p class="description"><?php echo wp_kses( __( 'Omit <code>pipelineId</code>, <code>pipelineStageId</code>, <code>multiplepipelines</code>, and <code>multiplepipeline_stageid</code> in a candidate shortcode to use this default.', 'cinode-recruitment' ), array( 'code' => array() ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_default_candidate_pipeline_stage_id"><?php esc_html_e( 'Default stage', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<?php if (!empty($all_candidate_pipelines)) : ?>
									<select id="cinode_default_candidate_pipeline_stage_id" name="cinode_recruitment_options[option_default_candidate_pipeline_stage_id]">
										<option value="0" data-pipeline-id="0" <?php selected(0, $selected_candidate_stage_id); ?>><?php esc_html_e( 'Select a pipeline first', 'cinode-recruitment' ); ?></option>
										<?php foreach ($all_candidate_pipelines as $pipeline) :
											$pipeline_id    = intval($pipeline['id'] ?? 0);
											$pipeline_title = esc_html($pipeline['title'] ?? 'Pipeline ' . $pipeline_id);
											$stages         = is_array($pipeline['stages'] ?? null) ? $pipeline['stages'] : array();
											if ($pipeline_id <= 0) {
												continue;
											}
											foreach ($stages as $stage) :
												$stage_id    = intval($stage['id'] ?? 0);
												$stage_title = esc_html($stage['title'] ?? 'Stage ' . $stage_id);
												if ($stage_id <= 0) {
													continue;
												}
											?>
												<option value="<?php echo $stage_id; ?>" data-pipeline-id="<?php echo $pipeline_id; ?>" <?php selected($selected_candidate_stage_id, $stage_id); ?>><?php echo $pipeline_title; ?> &mdash; <?php echo $stage_title; ?></option>
											<?php endforeach;
										endforeach; ?>
									</select>
								<?php else : ?>
									<input
										type="number"
										id="cinode_default_candidate_pipeline_stage_id"
										min="0"
										name="cinode_recruitment_options[option_default_candidate_pipeline_stage_id]"
										value="<?php echo esc_attr($selected_candidate_stage_id); ?>"
										class="small-text"
									/>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Select both a pipeline and stage. The default is ignored unless both IDs are saved.', 'cinode-recruitment' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Candidate Defaults', 'cinode-recruitment' ), 'primary large', 'submit', true); ?>
				</div>
			</div>
		</div><!-- #tab-candidate -->

	</form><!-- #cinode-main-settings-form -->

	<!-- ============================================================
	     EMAIL SETTINGS FORM
	     ============================================================ -->
	<form method="post" action="options.php" id="cinode-email-settings-form">
		<?php settings_fields('cinode_recruitment-settings-mail'); ?>

		<!-- TAB: Email Confirmation -->
		<div id="tab-email" class="cinode-tab-content" style="display:none;">
			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'Confirmation Email', 'cinode-recruitment' ); ?>
					</h2>
				</div>
				<div class="inside">
					<p><?php esc_html_e( 'This email is sent automatically to the applicant after a successful form submission.', 'cinode-recruitment' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_mail_subject"><?php esc_html_e( 'Subject', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="cinode_mail_subject"
									name="cinode_recruitment_options_sendmail[option_subject]"
									value="<?php echo esc_attr($mail_opts['option_subject']); ?>"
									class="large-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_mail_message"><?php esc_html_e( 'Message body', 'cinode-recruitment' ); ?></label>
							</th>
							<td>
								<textarea
									id="cinode_mail_message"
									name="cinode_recruitment_options_sendmail[option_message]"
									class="large-text"
									rows="6"
								><?php echo esc_textarea($mail_opts['option_message']); ?></textarea>
								<p class="description"><?php esc_html_e( 'Plain-text message sent to the candidate.', 'cinode-recruitment' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="cinode-notice cinode-notice--info">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<?php printf( wp_kses( __( 'To use a custom SMTP server, install the %s plugin.', 'cinode-recruitment' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array() ) ), '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="noopener noreferrer"><strong>WP Mail SMTP</strong></a>' ); ?>
					</p>
					<?php submit_button( __( 'Save Email Settings', 'cinode-recruitment' ), 'primary large', 'submit', true); ?>
				</div>
			</div>
		</div><!-- #tab-email -->

	</form><!-- #cinode-email-settings-form -->

	<!-- TAB: Shortcode Reference (no form) -->
	<div id="tab-shortcode" class="cinode-tab-content" style="display:none;">

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> <?php esc_html_e( 'Quick Start', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Paste the shortcode into any WordPress page or post. The simplest version:', 'cinode-recruitment' ); ?></p>
				<div class="cinode-code-block">
					<code id="sc-basic">[cinode]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-basic" aria-label="<?php esc_attr_e( 'Copy shortcode', 'cinode-recruitment' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'cinode-recruitment' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-filter" aria-hidden="true"></span> <?php esc_html_e( 'Core Parameters', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<p>All parameters are optional. If you set <code>pipelineId</code>, you must also set <code>pipelineStageId</code>. Omit both to use the configured candidate defaults. Setting either value, including <code>0</code>, makes the shortcode pipeline settings take precedence.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Default</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>recipient_type</code></td><td><code>"candidate"</code></td><td>Who the form creates: <code>"candidate"</code> or <code>"subcontractor"</code>.</td></tr>
						<tr><td><code>formtitle</code></td><td><code>""</code></td><td>Heading displayed above the form. Leave empty to show no heading.</td></tr>
						<tr><td><code>pipelineId</code></td><td>Candidate default or <code>0</code></td><td>Cinode pipeline ID to assign the candidate to. Omit with <code>pipelineStageId</code> to use the admin default.</td></tr>
						<tr><td><code>pipelineStageId</code></td><td>Candidate default or <code>0</code></td><td>Stage within the pipeline. <strong>Required</strong> when <code>pipelineId</code> is set. Omit with <code>pipelineId</code> to use the admin default.</td></tr>
						<tr><td><code>recruitmentManagerId</code></td><td><code>0</code></td><td>Cinode user ID of the responsible recruitment manager.</td></tr>
						<tr><td><code>teamId</code></td><td><code>0</code></td><td>Team ID to associate with the application.</td></tr>
						<tr><td><code>companyAddressId</code></td><td><code>0</code></td><td>Pre-select a company address. When <code>0</code> the location dropdown fetches all addresses.</td></tr>
						<tr><td><code>recruitmentSourceId</code></td><td><code>0</code></td><td>Source (channel) ID for tracking where the application came from.</td></tr>
						<tr><td><code>campaignCode</code></td><td><code>""</code></td><td>Campaign tracking code.</td></tr>
						<tr><td><code>currencyId</code></td><td><code>1</code></td><td>Currency ID used for any salary-related fields.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-tag" aria-hidden="true"></span> <?php esc_html_e( 'Field Label Parameters', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<p>Override any field label. Set a parameter to an empty string (<code>""</code>) to hide that field entirely.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Hideable?</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>firstname_label</code></td><td>&mdash;</td><td>Label for the first name field.</td></tr>
						<tr><td><code>lastname_label</code></td><td>&mdash;</td><td>Label for the last name field.</td></tr>
						<tr><td><code>email_label</code></td><td>&mdash;</td><td>Label for the email address field.</td></tr>
						<tr><td><code>phone_label</code></td><td>&mdash;</td><td>Label for the phone number field.</td></tr>
						<tr><td><code>message_label</code></td><td>&mdash;</td><td>Label for the message / cover letter field.</td></tr>
						<tr><td><code>linkedin_label</code></td><td>&mdash;</td><td>Label for the LinkedIn profile URL field.</td></tr>
						<tr><td><code>location_label</code></td><td>&#10003; set to <code>""</code></td><td>Label for the location field. Leave empty to hide the field.</td></tr>
						<tr><td><code>attachment_label</code></td><td>&mdash;</td><td>Label for the file upload / CV field.</td></tr>
						<tr><td><code>accept_label</code></td><td>&mdash;</td><td>Label for the GDPR / privacy acceptance checkbox.</td></tr>
						<tr><td><code>availableFrom_label</code></td><td>&#10003; omit to hide</td><td>Label for the &ldquo;available from&rdquo; date field. The field is hidden unless this attribute is present.</td></tr>
						<tr><td><code>submitbutton_label</code></td><td>&mdash;</td><td>Text shown on the submit button.</td></tr>
						<tr><td><code>groups_label</code></td><td>&mdash;</td><td>Label for the subcontractor group selector (subcontractor mode only).</td></tr>
						<tr><td><code>gender_label</code></td><td>&mdash;</td><td>Label for the gender field (subcontractor mode only).</td></tr>
						<tr><td><code>gender_option_male</code></td><td>&mdash;</td><td>Label for the &ldquo;Male&rdquo; gender option.</td></tr>
						<tr><td><code>gender_option_female</code></td><td>&mdash;</td><td>Label for the &ldquo;Female&rdquo; gender option.</td></tr>
						<tr><td><code>gender_option_other</code></td><td>&mdash;</td><td>Label for the &ldquo;Other / prefer not to say&rdquo; gender option.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Message & Validation Parameters', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr><th>Parameter</th><th>Description</th></tr>
					</thead>
					<tbody>
						<tr><td><code>privacy_url</code></td><td>URL linked from the GDPR/privacy checkbox label (e.g. <code>"https://example.com/privacy"</code>).</td></tr>
						<tr><td><code>privacy_error</code></td><td>Error message shown when the privacy checkbox is not ticked.</td></tr>
						<tr><td><code>successful-submit-msg</code></td><td>Message shown to the applicant after a successful submission.</td></tr>
						<tr><td><code>unsuccessful-submit-msg</code></td><td>Message shown when the submission fails.</td></tr>
						<tr><td><code>requiredfield_msg</code></td><td>Generic message used for unfilled required fields.</td></tr>
						<tr><td><code>cv_required</code></td><td>Override the global CV-required setting: <code>"1"</code> = mandatory, <code>"0"</code> = optional.</td></tr>
						<tr><td><code>parse_cv</code></td><td>Override the global parse-CV setting for candidate and subcontractor user profiles: <code>"1"</code> or <code>"0"</code>.</td></tr>
						<tr><td><code>auto_subcontractor_group_ids</code></td><td>Override the global auto-join group IDs for this shortcode instance.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Multiple Pipelines', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<p>Let applicants choose from several open positions via a dropdown. Map each pipeline to its corresponding stage ID in order. This shortcode setting takes precedence over the candidate defaults.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Example value</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>multiplepipelines</code></td>
							<td><code>"1235:Role A,1676:Role B"</code></td>
							<td>Comma-separated <code>pipelineId:Label</code> pairs shown in the dropdown.</td>
						</tr>
						<tr>
							<td><code>multiplepipeline_stageid</code></td>
							<td><code>"6003,7783"</code></td>
							<td>Comma-separated stage IDs, one per pipeline, in the same order.</td>
						</tr>
						<tr>
							<td><code>multiplepipelines_label</code></td>
							<td><code>"Select a position"</code></td>
							<td>Label displayed above the pipeline dropdown.</td>
						</tr>
					</tbody>
				</table>
				<div class="cinode-code-block" style="margin-top:1rem;">
					<code id="sc-multi">[cinode multiplepipelines="1235:Pipeline 1,1676:Pipeline 2" multiplepipeline_stageid="6003,7783" multiplepipelines_label="Select a position"]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-multi" aria-label="<?php esc_attr_e( 'Copy shortcode', 'cinode-recruitment' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'cinode-recruitment' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-media-text" aria-hidden="true"></span> <?php esc_html_e( 'Full Example — Candidate Mode', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<div class="cinode-code-block">
					<code id="sc-full">[cinode recipient_type="candidate" formtitle="Apply now" pipelineId="0" pipelineStageId="0" recruitmentManagerId="0" teamId="0" companyAddressId="0" recruitmentSourceId="0" campaignCode="" currencyId="1" firstname_label="First Name" lastname_label="Last Name" email_label="Email" phone_label="Phone" message_label="Cover Letter" linkedin_label="LinkedIn Profile" location_label="Location" availableFrom_label="Available from" attachment_label="Upload CV" accept_label="I accept the Privacy Policy" privacy_url="https://example.com/privacy" privacy_error="Please accept the privacy policy to continue." submitbutton_label="Submit Application" cv_required="0" parse_cv="1" successful-submit-msg="Thank you! We will be in touch soon." unsuccessful-submit-msg="Something went wrong. Please try again." requiredfield_msg="This field is required."]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-full" aria-label="<?php esc_attr_e( 'Copy shortcode', 'cinode-recruitment' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'cinode-recruitment' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-businessperson" aria-hidden="true"></span> <?php esc_html_e( 'Full Example — Subcontractor Mode', 'cinode-recruitment' ); ?></h2>
			</div>
			<div class="inside">
				<div class="cinode-code-block">
					<code id="sc-full-sub">[cinode recipient_type="subcontractor" formtitle="Join our network" firstname_label="First Name" lastname_label="Last Name" email_label="Email" phone_label="Phone" message_label="Cover Letter" linkedin_label="LinkedIn Profile" attachment_label="Upload CV" accept_label="I accept the Privacy Policy" privacy_url="https://example.com/privacy" privacy_error="Please accept the privacy policy to continue." submitbutton_label="Submit" cv_required="1" parse_cv="1" show_subcontractor_groups="1" subcontractor_group_ids="" subcontractor_group_selection="single" groups_label="Apply to group:" auto_subcontractor_group_ids="" gender_label="Gender" gender_option_male="Male" gender_option_female="Female" gender_option_other="Other / prefer not to say" successful-submit-msg="Thank you! We will be in touch soon." unsuccessful-submit-msg="Something went wrong. Please try again." requiredfield_msg="This field is required."]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-full-sub" aria-label="<?php esc_attr_e( 'Copy shortcode', 'cinode-recruitment' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'cinode-recruitment' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div><!-- #tab-shortcode -->

	<!-- TAB: Spam Protection (no form) -->
	<div id="tab-spam" class="cinode-tab-content" style="display:none;">
		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle">
					<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Google reCAPTCHA', 'cinode-recruitment' ); ?>
				</h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Protect your recruitment form from spam submissions using Google reCAPTCHA.', 'cinode-recruitment' ); ?></p>
				<ol class="cinode-step-list">
					<li>
						<?php printf( wp_kses( __( 'Go to the %1$sGoogle reCAPTCHA Admin Console %2$s%3$s and register your site to obtain a %4$sSite Key%5$s and %4$sSecret Key%5$s.', 'cinode-recruitment' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'span' => array( 'class' => array(), 'style' => array(), 'aria-hidden' => array() ), 'strong' => array() ) ), '<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">', '<span class="dashicons dashicons-external" style="font-size:0.85em;vertical-align:middle;" aria-hidden="true"></span>', '</a>', '<strong>', '</strong>' ); ?>
					</li>
					<li>
						<?php printf( wp_kses( __( 'Install the %1$sCAPTCHA 4WP %2$s%3$s plugin from the WordPress plugin directory.', 'cinode-recruitment' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'span' => array( 'class' => array(), 'style' => array(), 'aria-hidden' => array() ) ) ), '<a href="https://wordpress.org/plugins/advanced-nocaptcha-recaptcha/" target="_blank" rel="noopener noreferrer">', '<span class="dashicons dashicons-external" style="font-size:0.85em;vertical-align:middle;" aria-hidden="true"></span>', '</a>' ); ?>
					</li>
					<li><?php printf( wp_kses( __( 'In the CAPTCHA 4WP settings, enter your %1$sSite Key%2$s and %1$sSecret Key%2$s.', 'cinode-recruitment' ), array( 'strong' => array() ) ), '<strong>', '</strong>' ); ?></li>
					<li><?php esc_html_e( 'The Cinode Recruitment form will automatically integrate with reCAPTCHA once the plugin is configured.', 'cinode-recruitment' ); ?></li>
				</ol>
			</div>
		</div>
	</div><!-- #tab-spam -->

</div><!-- .cinode-wrap -->
<?php
}
