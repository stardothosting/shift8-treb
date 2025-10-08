<?php
/**
 * Brain/Monkey PHPUnit bootstrap for Shift8 TREB Real Estate Listings
 *
 * @package Shift8\TREB\Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize Brain/Monkey
Brain\Monkey\setUp();

use Brain\Monkey\Functions;

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

if (!defined('FS_CHMOD_FILE')) {
    define('FS_CHMOD_FILE', 0644);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Global test options storage
global $_test_options;
$_test_options = array();

// Mock essential WordPress functions that are called during plugin load
Functions\when('get_option')->alias(function($option, $default = false) {
    global $_test_options;
    
    // Default empty settings for TREB debug to prevent destructor issues
    if ($option === 'shift8_treb_settings') {
        return isset($_test_options[$option]) ? $_test_options[$option] : array('debug_enabled' => '0');
    }
    
    return isset($_test_options[$option]) ? $_test_options[$option] : $default;
});

// Define a testing flag to prevent destructor issues
define('SHIFT8_TREB_TESTING', true);

// Define these functions if they don't exist yet
if (!function_exists('plugin_dir_path')) {
    Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__) . '/');
}
if (!function_exists('plugin_dir_url')) {
    Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/shift8-treb/');
}
if (!function_exists('plugin_basename')) {
    Functions\when('plugin_basename')->justReturn('shift8-treb/shift8-treb.php');
}
Functions\when('current_time')->justReturn(date('Y-m-d H:i:s'));
Functions\when('wp_json_encode')->alias('json_encode');
Functions\when('sanitize_text_field')->alias(function($str) {
    return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
});
Functions\when('sanitize_email')->alias(function($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
});
Functions\when('esc_url_raw')->alias(function($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
});

// Mock wp_set_post_tags function
Functions\when('wp_set_post_tags')->justReturn(true);

// Mock WP_CLI class to prevent code coverage errors
if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function add_command($name, $callable) {
            return true;
        }
        
        public static function success($message) {
            return true;
        }
        
        public static function error($message) {
            return true;
        }
        
        public static function warning($message) {
            return true;
        }
        
        public static function log($message) {
            return true;
        }
        
        public static function confirm($message) {
            return true;
        }
        
        public static function line($message) {
            return true;
        }
    }
}

// Mock WP_Error class for tests
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            return '';
        }

        public function get_error_messages($code = '') {
            if (empty($code)) {
                $all_messages = array();
                foreach ($this->errors as $code => $messages) {
                    $all_messages = array_merge($all_messages, $messages);
                }
                return $all_messages;
            }
            return isset($this->errors[$code]) ? $this->errors[$code] : array();
        }
    }
}

// Mock add_action and add_filter to prevent errors during plugin load
Functions\when('add_action')->justReturn(true);
Functions\when('add_filter')->justReturn(true);
Functions\when('register_activation_hook')->justReturn(true);
Functions\when('register_deactivation_hook')->justReturn(true);

// Mock WordPress HTTP API functions with AMPRE-like responses
Functions\when('wp_remote_get')->justReturn(array(
    'response' => array('code' => 200),
    'body' => json_encode(array(
        '@odata.context' => '$metadata#Property',
        'value' => array(
            array(
                'ListingKey' => 'X12345678',
                'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
                'ListPrice' => 750000.0,
                'ContractStatus' => 'Available',
                'ModificationTimestamp' => '2024-10-01T12:00:00Z',
                'ListAgentKey' => '1525',
                'BedroomsTotal' => 3,
                'BathroomsTotalInteger' => 2,
                'BuildingAreaTotal' => 1500,
                'PublicRemarks' => 'Beautiful home in great location.'
            )
        )
    ))
));
Functions\when('wp_remote_request')->justReturn(array(
    'response' => array('code' => 200),
    'body' => json_encode(array(
        '@odata.context' => '$metadata#Property',
        'value' => array()
    ))
));
Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
    '@odata.context' => '$metadata#Property',
    'value' => array()
)));
Functions\when('wp_remote_retrieve_headers')->justReturn(array());
Functions\when('is_wp_error')->justReturn(false);

// Mock additional essential functions
Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
Functions\when('is_admin')->justReturn(false);
Functions\when('wp_clear_scheduled_hook')->justReturn(true);
Functions\when('wp_schedule_event')->justReturn(true);
Functions\when('wp_next_scheduled')->justReturn(false);
Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
Functions\when('esc_html_e')->alias(function($text, $domain) { echo $text; });
Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
Functions\when('esc_attr')->alias(function($text) { return htmlspecialchars($text); });
Functions\when('wp_verify_nonce')->justReturn(true);
Functions\when('wp_create_nonce')->justReturn('test_nonce_12345');
Functions\when('add_option')->justReturn(true);
Functions\when('update_option')->justReturn(true);
Functions\when('wp_upload_dir')->justReturn(array('basedir' => '/tmp/uploads'));
Functions\when('wp_mkdir_p')->justReturn(true);
Functions\when('is_dir')->justReturn(true);
Functions\when('WP_Filesystem')->justReturn(true);
Functions\when('wp_unslash')->alias(function($value) {
    return is_string($value) ? stripslashes($value) : $value;
});
Functions\when('wp_send_json_success')->alias(function($data) {
    echo json_encode(array('success' => true, 'data' => $data));
    exit;
});
Functions\when('wp_send_json_error')->alias(function($data) {
    echo json_encode(array('success' => false, 'data' => $data));
    exit;
});
Functions\when('check_admin_referer')->justReturn(true);
Functions\when('settings_fields')->alias(function($group) { 
    echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '" />';
});
Functions\when('get_admin_page_title')->justReturn('Test Page Title');
Functions\when('wp_die')->alias(function($message) {
    throw new \Exception($message);
});

// Mock WordPress admin functions
Functions\when('add_settings_error')->justReturn(true);
Functions\when('register_setting')->justReturn(true);
Functions\when('add_menu_page')->justReturn('shift8-settings');
Functions\when('add_submenu_page')->justReturn('shift8-treb');
Functions\when('load_plugin_textdomain')->justReturn(true);
Functions\when('flush_rewrite_rules')->justReturn(true);
Functions\when('wp_kses_post')->alias(function($content) { return strip_tags($content); });
Functions\when('get_category_by_slug')->justReturn(false);
Functions\when('current_time')->justReturn(date('Y-m-d H:i:s'));
Functions\when('wp_next_scheduled')->justReturn(false);
Functions\when('wp_upload_bits')->justReturn(array(
    'file' => '/tmp/test.jpg',
    'url' => 'http://example.com/test.jpg',
    'error' => false
));
Functions\when('wp_schedule_event')->justReturn(true);
Functions\when('wp_insert_category')->justReturn(array('term_id' => 1));
Functions\when('wp_generate_attachment_metadata')->justReturn(array());
Functions\when('wp_update_attachment_metadata')->justReturn(true);
Functions\when('wp_set_post_categories')->justReturn(true);
Functions\when('get_site_url')->justReturn('http://example.com');
Functions\when('wp_trim_words')->alias(function($text, $num_words = 55, $more = null) {
    $words = explode(' ', $text);
    return implode(' ', array_slice($words, 0, $num_words));
});

Functions\when('sanitize_file_name')->alias(function($filename) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
});

// Mock esc_js function
Functions\when('esc_js')->alias(function($text) {
    return addslashes($text);
});

// Mock WordPress post functions
Functions\when('wp_insert_post')->justReturn(123);
Functions\when('wp_update_post')->justReturn(123);
Functions\when('get_posts')->justReturn(array());
Functions\when('wp_delete_file')->justReturn(true);

// Mock WordPress transient functions
Functions\when('get_transient')->justReturn(false); // Always return false to skip cache
Functions\when('set_transient')->justReturn(true);

// Mock global filesystem
global $wp_filesystem;
$wp_filesystem = new class {
    public function is_writable($path) { return true; }
    public function exists($file) { return false; }
    public function put_contents($file, $content) { return true; }
    public function get_contents($file) { return ''; }
};

// Mock WP_Error class for tests
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code] = array($message);
            }
        }
        
        public function get_error_message() {
            $codes = array_keys($this->errors);
            if (empty($codes)) return '';
            return $this->errors[$codes[0]][0];
        }
    }
}

// Load the plugin
require dirname(__DIR__) . '/shift8-treb.php';
