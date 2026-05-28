<?php
/**
 * Minimal WordPress function stubs so cinode-recruitment.php can be loaded
 * without a full WordPress environment.
 *
 * Only the functions actually called by the code under test are stubbed.
 */

/* ---- constants ---- */
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

/* ---- WordPress functions used by the plugin ---- */

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}

if (!function_exists('sanitize_file_name')) {
    /**
     * Simplified stub of WP's sanitize_file_name():
     * strips path, replaces whitespace, removes special chars.
     */
    function sanitize_file_name($name) {
        $name = basename((string) $name);
        $name = preg_replace('/[\s]+/', '-', $name);
        $name = preg_replace('/[^A-Za-z0-9._\-]/', '', $name);
        return $name;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) { return filter_var((string) $email, FILTER_SANITIZE_EMAIL); }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return trim(strip_tags((string) $str)); }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return filter_var((string) $url, FILTER_SANITIZE_URL); }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return str_repeat('X', $length); // deterministic stub
    }
}

/**
 * get_option stub – returns a configurable array via a global.
 */
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['__wp_options'][$key] ?? $default;
    }
}

/* ---- HTTP transport layer ---- */

/**
 * wp_remote_post / wp_remote_get stubs.
 *
 * When $GLOBALS['__pre_http_request'] is set to a callable, it receives
 * ($url, $args) and must return a WP-style response array or a WP_Error.
 * This mirrors WordPress's own `pre_http_request` filter pattern.
 */

if (!class_exists('WP_Error')) {
    class WP_Error {
        protected $code;
        protected $message;
        public function __construct($code = '', $message = '') {
            $this->code    = $code;
            $this->message = $message;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct($data = null, $status = 200) {
            $this->data   = $data;
            $this->status = $status;
        }
    }
}

/**
 * Minimal WP_REST_Request stand-in for testing.
 * Supports array access (for $postData['field']) and get_file_params().
 */
class FakeWPRestRequest implements ArrayAccess {
    private array $params;
    private array $fileParams;

    public function __construct(array $params = [], array $fileParams = []) {
        $this->params     = $params;
        $this->fileParams = $fileParams;
    }

    public function get_file_params(): array { return $this->fileParams; }

    public function offsetExists(mixed $offset): bool { return isset($this->params[$offset]); }
    public function offsetGet(mixed $offset): mixed    { return $this->params[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->params[$offset] = $value; }
    public function offsetUnset(mixed $offset): void   { unset($this->params[$offset]); }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof WP_Error; }
}

function _cinode_test_dispatch_http($url, $args) {
    if (isset($GLOBALS['__pre_http_request']) && is_callable($GLOBALS['__pre_http_request'])) {
        return call_user_func($GLOBALS['__pre_http_request'], $url, $args);
    }
    // Default: return a 200 with empty body
    return array(
        'response' => array('code' => 200),
        'body'     => '',
    );
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        return _cinode_test_dispatch_http($url, $args);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        return _cinode_test_dispatch_http($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['response']['code'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

/* ---- Hooks / shortcodes / misc stubs ---- */

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $cb) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $cb) {}
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('register_rest_route')) {
    function register_rest_route($ns, $route, $args) {}
}
if (!function_exists('add_action')) {
    function add_action($hook, $cb, $prio = 10, $args = 1) {}
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $cb, $prio = 10, $args = 1) {}
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $cb) {}
}
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {}
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args) {}
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args) {}
}
if (!function_exists('add_options_page')) {
    function add_options_page(...$args) {}
}
if (!function_exists('register_setting')) {
    function register_setting(...$args) {}
}
if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(...$args) {}
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($defaults, $atts, $shortcode = '') {
        $out = $defaults;
        foreach ($atts as $k => $v) {
            if (array_key_exists($k, $out)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var((string)$url, FILTER_SANITIZE_URL); }
}
if (!function_exists('wp_mail')) {
    function wp_mail(...$args) {
        $GLOBALS['__wp_mail_log'][] = $args;
        return true;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['__wp_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['__wp_transients'][$key] ?? false;
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() { return array('path' => sys_get_temp_dir(), 'url' => ''); }
}
if (!function_exists('wp_unique_filename')) {
    function wp_unique_filename($dir, $name) { return $name; }
}
if (!function_exists('wp_delete_file')) {
    function wp_delete_file($file) { @unlink($file); }
}
if (!function_exists('wp_check_filetype_and_ext')) {
    function wp_check_filetype_and_ext($file, $filename, $mimes = null) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return array('ext' => $ext, 'type' => 'application/octet-stream');
    }
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

/* ---- Autoload ---- */
require_once dirname(__DIR__) . '/vendor/autoload.php';

/* ---- Load the plugin file ---- */
require_once dirname(__DIR__) . '/cinode-recruitment.php';
