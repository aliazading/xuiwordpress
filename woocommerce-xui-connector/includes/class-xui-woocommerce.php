<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class XUI_WooCommerce
 *
 * Handles the integration with WooCommerce, such as creating subscriptions on purchase.
 */
class XUI_WooCommerce {

    /**
     * Initialize the class and set up the hooks.
     */
    public function __construct() {
        // Core functionality
        add_action( 'woocommerce_order_status_completed', array( $this, 'create_subscription_on_purchase' ), 10, 1 );

        // Display hooks
        add_action( 'woocommerce_thankyou', array( $this, 'display_subscription_link_on_thankyou' ), 10, 1 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_subscription_link_in_account' ), 10, 1 );
        add_filter( 'woocommerce_order_item_name', array( $this, 'display_subscription_link_in_email' ), 10, 2 );
    }

    /**
     * Creates an X-UI subscription when an order is marked as complete.
     *
     * @param int $order_id The ID of the completed order.
     */
    public function create_subscription_on_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $api_url  = get_option( 'xui_api_url' );
        $username = get_option( 'xui_api_username' );
        $password = get_option( 'xui_api_password' );

        if ( empty( $api_url ) || empty( $username ) || empty( $password ) ) {
            $order->add_order_note( __( 'X-UI Connector: Cannot create subscription because API settings are incomplete.', 'woocommerce-xui-connector' ) );
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();

            // Check if it's an X-UI product and if a subscription hasn't been created yet.
            if ( 'yes' !== get_post_meta( $product_id, '_is_xui_product', true ) || wc_get_order_item_meta( $item_id, '_xui_subscription_link', true ) ) {
                continue;
            }

            $duration   = get_post_meta( $product_id, '_xui_duration_days', true );
            $data_limit = get_post_meta( $product_id, '_xui_data_limit_gb', true );
            $inbound_id = get_post_meta( $product_id, '_xui_inbound_id', true );

            if ( empty( $duration ) || empty( $data_limit ) || empty( $inbound_id ) ) {
                $order->add_order_note( sprintf( __( 'X-UI Connector: Skipping product #%d because its subscription settings are incomplete.', 'woocommerce-xui-connector' ), $product_id ) );
                continue;
            }

            // Create a unique email/identifier for the new client.
            $client_email = 'user' . $order->get_user_id() . '_order' . $order_id . '_item' . $item_id;

            $api_client = new XUI_Api_Client( $api_url );
            $login      = $api_client->login( $username, $password );

            if ( is_wp_error( $login ) ) {
                $order->add_order_note( sprintf( __( 'X-UI Connector: API login failed. Reason: %s', 'woocommerce-xui-connector' ), $login->get_error_message() ) );
                continue; // Move to next item
            }

            $sub_id = $api_client->add_client( $inbound_id, $client_email, $data_limit, $duration );

            if ( is_wp_error( $sub_id ) ) {
                $order->add_order_note( sprintf( __( 'X-UI Connector: Failed to create subscription for product #%d. Reason: %s', 'woocommerce-xui-connector' ), $product_id, $sub_id->get_error_message() ) );
                continue; // Move to next item
            }

            // Construct the subscription link.
            $subscription_link = rtrim( $api_url, '/' ) . '/sub/' . $sub_id;

            // Save the link to the order item to prevent re-creation and for display to the user.
            wc_add_order_item_meta( $item_id, '_xui_subscription_link', $subscription_link );

            $order->add_order_note( sprintf( __( 'X-UI Connector: Successfully created subscription for product #%d. Link: %s', 'woocommerce-xui-connector' ), $product_id, $subscription_link ) );
        }
    }

    /**
     * Display the subscription link on the "Thank You" page.
     *
     * @param int $order_id The ID of the order.
     */
    public function display_subscription_link_on_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $this->render_subscription_links_for_order( $order, '<h2>' . __( 'Your Subscriptions', 'woocommerce-xui-connector' ) . '</h2>' );
    }

    /**
     * Display the subscription link in the "My Account" order view.
     *
     * @param WC_Order $order The order object.
     */
    public function display_subscription_link_in_account( $order ) {
        $this->render_subscription_links_for_order( $order, '<h2>' . __( 'Your Subscriptions', 'woocommerce-xui-connector' ) . '</h2>' );
    }

    /**
     * Display the subscription link in the order item name in emails.
     *
     * @param string $item_name The original item name.
     * @param WC_Order_Item $item The order item.
     * @return string The modified item name.
     */
    public function display_subscription_link_in_email( $item_name, $item ) {
        $link = wc_get_order_item_meta( $item->get_id(), '_xui_subscription_link', true );
        if ( $link ) {
            $item_name .= '<br/><small>' . __( 'Subscription Link:', 'woocommerce-xui-connector' ) . ' <a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></small>';
        }
        return $item_name;
    }

    /**
     * Helper function to render subscription links for a given order.
     *
     * @param WC_Order $order The order object.
     * @param string $title The title to display before the links.
     */
    private function render_subscription_links_for_order( $order, $title ) {
        $has_links = false;
        ob_start();
        foreach ( $order->get_items() as $item ) {
            $link = wc_get_order_item_meta( $item->get_id(), '_xui_subscription_link', true );
            if ( $link ) {
                $has_links = true;
                echo '<div class="xui-subscription-link">';
                echo '<strong>' . esc_html( $item->get_name() ) . ':</strong> ';
                echo '<input type="text" readonly value="' . esc_attr( $link ) . '" size="40" onclick="this.select();" style="width: 100%; max-width: 400px; margin-top: 5px;">';
                echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(\'' . esc_attr( $link ) . '\')">' . __( 'Copy', 'woocommerce-xui-connector' ) . '</button>';
                echo '</div>';
            }
        }
        $output = ob_get_clean();

        if ( $has_links ) {
            echo $title;
            echo $output;
        }
    }
}