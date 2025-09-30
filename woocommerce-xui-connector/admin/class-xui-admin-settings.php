<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class XUI_Admin_Settings
 *
 * Handles the admin settings page for the plugin.
 */
class XUI_Admin_Settings {

    /**
     * The unique ID for the settings page.
     */
    const PAGE_ID = 'woocommerce-xui-connector';

    /**
     * Initialize the class and set up the hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_xui_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'settings_page_' . self::PAGE_ID !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'xui-admin-settings',
            plugin_dir_url( __FILE__ ) . 'js/settings.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script(
            'xui-admin-settings',
            'xui_admin_settings',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'xui-test-connection-nonce' ),
            ]
        );
    }

    /**
     * Handle the AJAX request for testing the API connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'xui-test-connection-nonce', 'nonce' );

        $url      = esc_url_raw( $_POST['url'] );
        $username = sanitize_text_field( $_POST['username'] );
        $password = sanitize_text_field( $_POST['password'] );

        if ( empty( $url ) || empty( $username ) || empty( $password ) ) {
            wp_send_json_error( __( 'All fields are required.', 'woocommerce-xui-connector' ) );
        }

        $api_client = new XUI_Api_Client( $url );
        $login_result = $api_client->login( $username, $password );

        if ( is_wp_error( $login_result ) ) {
            wp_send_json_error( $login_result->get_error_message() );
        }

        $inbounds = $api_client->get_inbounds();

        if ( is_wp_error( $inbounds ) ) {
            wp_send_json_error( $inbounds->get_error_message() );
        }

        wp_send_json_success( [
            'message'          => __( 'Connection successful!', 'woocommerce-xui-connector' ),
            'inbounds'         => $inbounds,
            'enabled_inbounds' => get_option( 'xui_enabled_inbounds', [] ),
        ] );
    }

    /**
     * Add the admin menu item.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'X-UI Connector', 'woocommerce-xui-connector' ),
            __( 'X-UI Connector', 'woocommerce-xui-connector' ),
            'manage_options',
            self::PAGE_ID,
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register the settings fields.
     */
    public function register_settings() {
        register_setting( self::PAGE_ID, 'xui_api_url' );
        register_setting( self::PAGE_ID, 'xui_api_username' );
        register_setting( self::PAGE_ID, 'xui_api_password' );
        register_setting( self::PAGE_ID, 'xui_enabled_inbounds' );
    }

    /**
     * Register the settings fields.
     */
    public function register_settings() {
        register_setting( self::PAGE_ID, 'xui_api_url', 'esc_url_raw' );
        register_setting( self::PAGE_ID, 'xui_api_username', 'sanitize_text_field' );
        register_setting( self::PAGE_ID, 'xui_api_password', 'sanitize_text_field' );
        register_setting( self::PAGE_ID, 'xui_enabled_inbounds', [ $this, 'sanitize_inbounds' ] );

        add_settings_section(
            'xui_api_credentials',
            __( 'API Credentials', 'woocommerce-xui-connector' ),
            '__return_false',
            self::PAGE_ID
        );

        add_settings_field(
            'xui_api_url',
            __( 'Panel URL', 'woocommerce-xui-connector' ),
            [ $this, 'render_text_input' ],
            self::PAGE_ID,
            'xui_api_credentials',
            [ 'id' => 'xui_api_url', 'type' => 'url', 'placeholder' => 'https://panel.example.com' ]
        );

        add_settings_field(
            'xui_api_username',
            __( 'Username', 'woocommerce-xui-connector' ),
            [ $this, 'render_text_input' ],
            self::PAGE_ID,
            'xui_api_credentials',
            [ 'id' => 'xui_api_username' ]
        );

        add_settings_field(
            'xui_api_password',
            __( 'Password', 'woocommerce-xui-connector' ),
            [ $this, 'render_text_input' ],
            self::PAGE_ID,
            'xui_api_credentials',
            [ 'id' => 'xui_api_password', 'type' => 'password' ]
        );

        add_settings_section(
            'xui_inbounds_section',
            __( 'Inbound Settings', 'woocommerce-xui-connector' ),
            [ $this, 'render_inbounds_section_text' ],
            self::PAGE_ID
        );

        add_settings_field(
            'xui_enabled_inbounds',
            __( 'Enabled Inbounds', 'woocommerce-xui-connector' ),
            [ $this, 'render_inbounds_field' ],
            self::PAGE_ID,
            'xui_inbounds_section'
        );
    }

    /**
     * Renders a standard text input field.
     *
     * @param array $args The field arguments.
     */
    public function render_text_input( $args ) {
        $id    = $args['id'];
        $value = get_option( $id );
        $type  = $args['type'] ?? 'text';
        $placeholder = $args['placeholder'] ?? '';
        echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder ) . '">';
    }

    /**
     * Render the introductory text for the inbounds section.
     */
    public function render_inbounds_section_text() {
        echo '<p>' . __( 'Test your API credentials to fetch available inbounds. Then, select which inbounds you want to enable for new subscriptions.', 'woocommerce-xui-connector' ) . '</p>';
        echo '<button type="button" class="button" id="xui-test-connection">' . __( 'Test Connection & Fetch Inbounds', 'woocommerce-xui-connector' ) . '</button>';
        echo '<div id="xui-test-connection-notice" style="display:none; margin-top: 10px;"></div>';
    }

    /**
     * Render the checkboxes for enabling inbounds.
     */
    public function render_inbounds_field() {
        // This field will be populated dynamically via JavaScript.
        echo '<fieldset id="xui-inbounds-list"></fieldset>';
    }

    /**
     * Sanitize the enabled inbounds array.
     *
     * @param array $input The input array.
     * @return array The sanitized array.
     */
    public function sanitize_inbounds( $input ) {
        return is_array( $input ) ? array_map( 'absint', $input ) : [];
    }

    /**
     * Render the settings page HTML.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( self::PAGE_ID );
                do_settings_sections( self::PAGE_ID );
                submit_button( __( 'Save Settings', 'woocommerce-xui-connector' ) );
                ?>
            </form>
        </div>
        <?php
    }
}