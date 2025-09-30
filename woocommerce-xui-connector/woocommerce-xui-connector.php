<?php
/**
 * Plugin Name: WooCommerce X-UI Connector
 * Plugin URI: https://github.com/
 * Description: Connects WooCommerce to an X-UI panel to sell subscriptions.
 * Version: 1.0.0
 * Author: Jules
 * Author URI: https://github.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-xui-connector
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The main plugin class.
 */
final class WooCommerce_XUI_Connector {

    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Main WooCommerce_XUI_Connector Instance.
     *
     * Ensures only one instance of WooCommerce_XUI_Connector is loaded or can be loaded.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-xui-api-client.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-xui-woocommerce.php';

        if ( is_admin() ) {
            require_once plugin_dir_path( __FILE__ ) . 'admin/class-xui-admin-settings.php';
            require_once plugin_dir_path( __FILE__ ) . 'admin/class-xui-product-settings.php';
        }
    }

    /**
     * Initialize the plugin.
     */
    private function init() {
        new XUI_WooCommerce();
        if ( is_admin() ) {
            new XUI_Admin_Settings();
            new XUI_Product_Settings();
        }
    }
}

/**
 * Begins execution of the plugin.
 */
function woocommerce_xui_connector() {
    return WooCommerce_XUI_Connector::instance();
}

// Let's go!
woocommerce_xui_connector();