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
    private $cookies = array();

    /**
     * XUI_Api_Client constructor.
     */
    public function __construct( $base_url ) {
        $this->base_url = rtrim( $base_url, '/' ) . '/';
    }

    /**
     * Logs into the X-UI panel and stores the session cookie.
     */
    public function login( $username, $password ) {
        $response = wp_remote_post(
            $this->base_url . 'login',
            array(
                'body'    => array(
                    'username' => $username,
                    'password' => $password,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset($body['success']) || ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Authentication failed.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_login_failed', $error_message );
        }

        $this->cookies = wp_remote_retrieve_cookies( $response );
        return true;
    }

    /**
     * Retrieves the list of inbounds from the X-UI panel.
     */
    public function get_inbounds() {
        $response = wp_remote_get(
            $this->base_url . 'panel/api/inbounds/list',
            array(
                'cookies' => $this->cookies,
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset($body['success']) || ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Failed to retrieve inbounds.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_inbounds_failed', $error_message );
        }

        return isset($body['obj']) ? $body['obj'] : array();
    }

    /**
     * Creates a new client in a specific inbound.
     */
    public function add_client( $inbound_id, $email, $data_limit_gb, $duration_days ) {
        $uuid   = wp_generate_uuid4();
        $sub_id = wp_generate_password( 32, false );
        $total_gb = $data_limit_gb * 1024 * 1024 * 1024;
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
                'cookies' => $this->cookies,
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset($body['success']) || ! $body['success'] ) {
            $error_message = isset( $body['msg'] ) ? $body['msg'] : __( 'Failed to create client.', 'woocommerce-xui-connector' );
            return new WP_Error( 'xui_add_client_failed', $error_message );
        }

        return $sub_id;
    }
}