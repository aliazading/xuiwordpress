<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class XUI_Product_Settings
 *
 * Handles the custom product fields for X-UI subscriptions.
 */
class XUI_Product_Settings {

    /**
     * Initialize the class and set up the hooks.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
    }

    /**
     * Add the meta box to the product edit screen.
     */
    public function add_meta_box() {
        add_meta_box(
            'xui_subscription_options',
            __( 'X-UI Subscription', 'woocommerce-xui-connector' ),
            array( $this, 'render_meta_box_content' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render the content of the meta box.
     */
    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'xui_product_meta_nonce', 'xui_product_meta_nonce' );

        $is_xui_product = get_post_meta( $post->ID, '_is_xui_product', true );
        $duration       = get_post_meta( $post->ID, '_xui_duration_days', true );
        $data_limit     = get_post_meta( $post->ID, '_xui_data_limit_gb', true );
        $inbound_id     = get_post_meta( $post->ID, '_xui_inbound_id', true );

        ?>
        <div class="options_group">
            <p class="form-field">
                <label for="_is_xui_product">
                    <input type="checkbox" id="_is_xui_product" name="_is_xui_product" value="yes" <?php checked( $is_xui_product, 'yes' ); ?>>
                    <?php esc_html_e( 'Enable as X-UI Subscription', 'woocommerce-xui-connector' ); ?>
                </label>
            </p>
            <div id="xui_subscription_fields">
                <p class="form-field _xui_duration_days_field">
                    <label for="_xui_duration_days"><?php esc_html_e( 'Duration (days)', 'woocommerce-xui-connector' ); ?></label>
                    <input type="number" id="_xui_duration_days" name="_xui_duration_days" value="<?php echo esc_attr( $duration ); ?>" style="width:100%;">
                </p>
                <p class="form-field _xui_data_limit_gb_field">
                    <label for="_xui_data_limit_gb"><?php esc_html_e( 'Data Limit (GB)', 'woocommerce-xui-connector' ); ?></label>
                    <input type="number" id="_xui_data_limit_gb" name="_xui_data_limit_gb" value="<?php echo esc_attr( $data_limit ); ?>" style="width:100%;">
                </p>
                <p class="form-field _xui_inbound_id_field">
                    <label for="_xui_inbound_id"><?php esc_html_e( 'Target Inbound', 'woocommerce-xui-connector' ); ?></label>
                    <?php $this->render_inbounds_dropdown( $inbound_id ); ?>
                </p>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var checkbox = $('#_is_xui_product');
                var fields = $('#xui_subscription_fields');

                function toggleFields() {
                    fields.toggle(checkbox.is(':checked'));
                }

                toggleFields();
                checkbox.on('change', toggleFields);
            });
        </script>
        <?php
    }

    /**
     * Renders the dropdown of enabled inbounds.
     */
    private function render_inbounds_dropdown( $selected_inbound_id ) {
        $enabled_inbound_ids = get_option( 'xui_enabled_inbounds', array() );

        if ( empty( $enabled_inbound_ids ) ) {
            echo '<p>' . esc_html__( 'No inbounds enabled. Please configure them in the plugin settings.', 'woocommerce-xui-connector' ) . '</p>';
            return;
        }

        $api_url  = get_option( 'xui_api_url' );
        $username = get_option( 'xui_api_username' );
        $password = get_option( 'xui_api_password' );

        if ( ! $api_url || ! $username || ! $password ) {
            echo '<p>' . esc_html__( 'API credentials are not set.', 'woocommerce-xui-connector' ) . '</p>';
            return;
        }

        $api_client = new XUI_Api_Client( $api_url );
        $login      = $api_client->login( $username, $password );

        if ( is_wp_error( $login ) ) {
            echo '<p>' . esc_html__( 'Could not connect to X-UI panel.', 'woocommerce-xui-connector' ) . '</p>';
            return;
        }

        $all_inbounds = $api_client->get_inbounds();

        if ( is_wp_error( $all_inbounds ) ) {
            echo '<p>' . esc_html__( 'Could not fetch inbounds.', 'woocommerce-xui-connector' ) . '</p>';
            return;
        }

        $enabled_inbounds = array_filter( $all_inbounds, function( $inbound ) use ( $enabled_inbound_ids ) {
            return in_array( $inbound['id'], $enabled_inbound_ids );
        } );

        if ( empty( $enabled_inbounds ) ) {
            echo '<p>' . esc_html__( 'Enabled inbounds not found on panel.', 'woocommerce-xui-connector' ) . '</p>';
            return;
        }

        echo '<select id="_xui_inbound_id" name="_xui_inbound_id" style="width:100%;">';
        echo '<option value="">' . esc_html__( 'Select an inbound', 'woocommerce-xui-connector' ) . '</option>';
        foreach ( $enabled_inbounds as $inbound ) {
            $remark = isset($inbound['remark']) && $inbound['remark'] ? $inbound['remark'] : 'No name';
            echo '<option value="' . esc_attr( $inbound['id'] ) . '" ' . selected( $selected_inbound_id, $inbound['id'], false ) . '>';
            echo esc_html( $remark . ' (' . $inbound['protocol'] . ')' );
            echo '</option>';
        }
        echo '</select>';
    }

    /**
     * Save the custom product meta fields.
     */
    public function save_product_meta( $post_id ) {
        if ( ! isset( $_POST['xui_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['xui_product_meta_nonce'], 'xui_product_meta_nonce' ) ) {
            return;
        }

        $is_xui_product = isset( $_POST['_is_xui_product'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_is_xui_product', $is_xui_product );

        if ( 'yes' === $is_xui_product ) {
            if ( isset( $_POST['_xui_duration_days'] ) ) {
                update_post_meta( $post_id, '_xui_duration_days', absint( $_POST['_xui_duration_days'] ) );
            }
            if ( isset( $_POST['_xui_data_limit_gb'] ) ) {
                update_post_meta( $post_id, '_xui_data_limit_gb', abs( floatval( $_POST['_xui_data_limit_gb'] ) ) );
            }
            if ( isset( $_POST['_xui_inbound_id'] ) ) {
                update_post_meta( $post_id, '_xui_inbound_id', absint( $_POST['_xui_inbound_id'] ) );
            }
        } else {
            delete_post_meta( $post_id, '_xui_duration_days' );
            delete_post_meta( $post_id, '_xui_data_limit_gb' );
            delete_post_meta( $post_id, '_xui_inbound_id' );
        }
    }
}