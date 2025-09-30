<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class XUI_Api_Client
 *
 * Handles all communication with the 3x-ui panel API.
 */
class XUI_Api_Client {

    private $base_url;
    private $cookie_jar;

    /**
     * XUI_Api_Client constructor.
     *
     * @param string $base_url The base URL of the X-UI panel.
     */
    public function __construct( $base_url ) {
        // Ensure the URL has a trailing slash.
        $this->base_url = rtrim( $base_url, '/' ) . '/';
        $this->cookie_jar = new WP_Http_Cookie_Simple();
    }

    /**
     * Logs into the X-UI panel and stores the session cookie.
     *
     * @param string $username The panel username.
     * @param string $password The panel password.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function login( $username, $password ) {
        $response = wp_remote_post(
            $this->base_url . 'login',
            array(
                'body'    => array(
                    'username' => $username,
                    'password' => $password,
                ),
                'cookies' => $this->cookie_jar->get_cookies(),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Authentication failed.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_login_failed', $error_message );
        }

        // Store the session cookies for subsequent requests.
        $this->cookie_jar->set_cookies( wp_remote_retrieve_cookies( $response ) );

        return true;
    }

    /**
     * Retrieves the list of inbounds from the X-UI panel.
     *
     * @return array|WP_Error An array of inbounds on success, WP_Error on failure.
     */
    public function get_inbounds() {
        $response = wp_remote_get(
            $this->base_url . 'panel/api/inbounds/list',
            array(
                'cookies' => $this->cookie_jar->get_cookies(),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Failed to retrieve inbounds.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_inbounds_failed', $error_message );
        }

        return $body['obj'];
    }

    /**
     * Creates a new client in a specific inbound.
     *
     * @param int    $inbound_id The ID of the inbound to add the client to.
     * @param string $email      The email to assign to the new client.
     * @param float  $data_limit_gb The data limit in GB.
     * @param int    $duration_days The subscription duration in days.
     * @return string|WP_Error The generated subscription ID (`subId`) on success, WP_Error on failure.
     */
    public function add_client( $inbound_id, $email, $data_limit_gb, $duration_days ) {
        // Generate a new UUID for the client and a random subId for the link.
        $uuid   = wp_generate_uuid4();
        $sub_id = wp_generate_password( 32, false );

        // Calculate total bytes (totalGB).
        $total_gb = $data_limit_gb * 1024 * 1024 * 1024;

        // Calculate expiry time (expireTime) in milliseconds.
        $expire_time = ( time() + ( $duration_days * 24 * 60 * 60 ) ) * 1000;

        $client_settings = array(
            'clients' => array(
                array(
                    'id'         => $uuid,
                    'flow'       => '',
                    'email'      => $email,
                    'totalGB'    => $total_gb,
                    'expiryTime' => $expire_time,
                    'enable'     => true,
                    'tgId'       => '',
                    'subId'      => $sub_id,
                ),
            ),
        );

        $response = wp_remote_post(
            $this->base_url . 'panel/api/inbounds/addClient',
            array(
                'body'    => json_encode( array(
                    'id'       => $inbound_id,
                    'settings' => json_encode( $client_settings ),
                ) ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'cookies' => $this->cookie_jar->get_cookies(),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Failed to create client.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_add_client_failed', $error_message );
        }

        // Return the generated subId so the subscription link can be constructed.
        return $sub_id;
    }
}