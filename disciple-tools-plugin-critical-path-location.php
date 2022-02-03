<?php
/**
 *Plugin Name: Disciple.Tools - Plugin Critical Path Location
 * Description: Disciple.Tools - Plugin Critical Path Location is intended filter the critical path with location
 * Version:  0.1
 * Author: Covalience DEv
 * GitHub Plugin URI: https://github.com/athakur138/PluginDT-Critical-Path-Location
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Gets the instance of the `Disciple_Tools_Plugin_Critical_Path_Location` class.
 *
 * @return object|bool
 * @since  0.1
 * @access public
 */
//function disciple_tools_plugin_critical_path_location() {
function disciple_tools_plugin_critical_path_location()
{
    $disciple_tools_plugin_critical_path_location_required_dt_theme_version = '1.19';
    $wp_theme = wp_get_theme();
    $version = $wp_theme->version;

    /*
     * Check if the Disciple.Tools theme is loaded and is the latest required version
     */
    $is_theme_dt = class_exists("Disciple_Tools");
//    if ( $is_theme_dt && version_compare( $version, $disciple_tools_plugin_critical_path_location_required_dt_theme_version, "<" ) ) {
//        add_action( 'admin_notices', 'disciple_tools_plugin_critical_path_location_hook_admin_notice' );
//        add_action( 'wp_ajax_dismissed_notice_handler', 'dt_hook_ajax_notice_handler' );
//        return false;
//    }
    if (!$is_theme_dt) {
        return false;
    }
    /**
     * Load useful function from the theme
     */
    if (!defined('DT_FUNCTIONS_READY')) {
        require_once get_template_directory() . '/dt-core/global-functions.php';
    }

    return Disciple_Tools_Plugin_Critical_Path_Location::instance();

}

add_action('after_setup_theme', 'disciple_tools_plugin_critical_path_location', 20);

/**
 * Singleton class for setting up the plugin.
 *
 * @since  0.1
 * @access public
 */
class Disciple_Tools_Plugin_Critical_Path_Location
{

    private static $_instance = null;

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        $is_rest = dt_is_rest();



        /**
         * If we want to enhance the metrics section
         */
        if (strpos(dt_get_url_path(), 'metrics') !== false || ($is_rest && strpos(dt_get_url_path(), 'disciple-tools-plugin-critical-path-location-metrics') !== false)) {
            require_once('critical-path/critical-path-location.php');  // add custom charts to the metrics area

        }


    }



    /**
     * Method that runs only when the plugin is activated.
     *
     * @return void
     * @since  0.1
     * @access public
     */
    public static function activation()
    {
        // add elements here that need to fire on activation
    }

    /**
     * Method that runs only when the plugin is deactivated.
     *
     * @return void
     * @since  0.1
     * @access public
     */
    public static function deactivation()
    {
        // add functions here that need to happen on deactivation
        delete_option('dismissed-disciple-tools-plugin-critical-path-location');
    }

    /**
     * Loads the translation files.
     *
     * @return void
     * @since  0.1
     * @access public
     */
    public function i18n()
    {
        $domain = 'disciple-tools-plugin-critical-path-location';
        load_plugin_textdomain($domain, false, trailingslashit(dirname(plugin_basename(__FILE__))) . 'languages');
    }

    /**
     * Magic method to output a string if trying to use the object as a string.
     *
     * @return string
     * @since  0.1
     * @access public
     */
    public function __toString()
    {
        return 'disciple-tools-plugin-critical-path-location';
    }

    /**
     * Magic method to keep the object from being cloned.
     *
     * @return void
     * @since  0.1
     * @access public
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, 'Whoah, partner!', '0.1');
    }

    /**
     * Magic method to keep the object from being unserialized.
     *
     * @return void
     * @since  0.1
     * @access public
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, 'Whoah, partner!', '0.1');
    }

    /**
     * Magic method to prevent a fatal error when calling a method that doesn't exist.
     *
     * @param string $method
     * @param array $args
     * @return null
     * @since  0.1
     * @access public
     */
    public function __call($method = '', $args = array())
    {
        _doing_it_wrong("disciple_tools_plugin_critical_path_location::" . esc_html($method), 'Method does not exist.', '0.1');
        unset($method, $args);
        return null;
    }
}


// Register activation hook.
register_activation_hook(__FILE__, ['Disciple_Tools_Plugin_Critical_Path_Location', 'activation']);
register_deactivation_hook(__FILE__, ['Disciple_Tools_Plugin_Critical_Path_Location', 'deactivation']);


if (!function_exists('disciple_tools_plugin_critical_path_location_hook_admin_notice')) {
    function disciple_tools_plugin_critical_path_location_hook_admin_notice()
    {
        global $disciple_tools_plugin_critical_path_location_required_dt_theme_version;
        $wp_theme = wp_get_theme();
        $current_version = $wp_theme->version;
        $message = "'Disciple.Tools - Plugin Starter Template' plugin requires 'Disciple.Tools' theme to work. Please activate 'Disciple.Tools' theme or make sure it is latest version.";
        if ($wp_theme->get_template() === "disciple-tools-theme") {
            $message .= ' ' . sprintf(esc_html('Current Disciple.Tools version: %1$s, required version: %2$s'), esc_html($current_version), esc_html($disciple_tools_plugin_critical_path_location_required_dt_theme_version));
        }
        // Check if it's been dismissed...
        if (!get_option('dismissed-disciple-tools-plugin-critical-path-location', false)) { ?>
            <div class="notice notice-error notice-disciple-tools-plugin-critical-path-location is-dismissible"
                 data-notice="disciple-tools-plugin-critical-path-location">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <script>
                jQuery(function ($) {
                    $(document).on('click', '.notice-disciple-tools-plugin-critical-path-location .notice-dismiss', function () {
                        $.ajax(ajaxurl, {
                            type: 'POST',
                            data: {
                                action: 'dismissed_notice_handler',
                                type: 'disciple-tools-plugin-critical-path-location',
                                security: '<?php echo esc_html(wp_create_nonce('wp_rest_dismiss')) ?>'
                            }
                        })
                    });
                });
            </script>
        <?php }
    }
}

/**
 * AJAX handler to store the state of dismissible notices.
 */
if (!function_exists("dt_hook_ajax_notice_handler")) {
    function dt_hook_ajax_notice_handler()
    {
        check_ajax_referer('wp_rest_dismiss', 'security');
        if (isset($_POST["type"])) {
            $type = sanitize_text_field(wp_unslash($_POST["type"]));
            update_option('dismissed-' . $type, true);
        }
    }
}

