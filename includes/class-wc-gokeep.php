<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Google Analytics Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Gokeep
 * @extends WC_Integration
 */
class WC_Gokeep extends WC_Integration {

    /**
     * Init and hook in the integration.
     *
     * @return void
     */
    public function __construct() {
        $this->id                    = 'gokeep_tracking';
        $this->method_title          = __( 'Gokeep Tracking' );
        $this->method_description    = __( '' );

        $this->form_fields = array(
            'gokeep_id' => array(
                'title'       => __( 'Gokeep ID' ),
                'description' => __( 'Digite seu código Gokeep' ),
                'type'        => 'text',
                'placeholder' => 'Seu código aqui',
                'default'     => get_option( 'woocommerce_gokeep_id' ) // Backwards compat
            ),
            'gokeep_additional_script' => array(
                'title'       => __( 'Additional Script' ),
                'description' => __( 'Area para códigos javascript adicionais' ),
                'type'        => 'textarea',
                'placeholder' => 'Seu código aqui',
                'default'     => get_option( 'woocommerce_gokeep_additional_script' ) // Backwards compat
            )
        );

        $this->init_settings();
        $constructor = $this->init_options();

        // Contains snippets/JS tracking code
        include_once( 'class-wc-gokeep-js.php' );
        WC_Gokeep_JS::get_instance( $constructor );


        // admin options
        add_action( 'woocommerce_update_options_integration_gokeep_tracking', array( $this, 'process_admin_options') );
        add_action( 'woocommerce_update_options_integration_gokeep_tracking', array( $this, 'show_options_info') );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets') );


        // Tracking code
        add_action( 'wp_head', array( $this, 'tracking_code_display' ), 1 );
        add_action( 'wp_head', array( $this, 'tracking_vars' ), 2 );


        // Event tracking code

        // product impression
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ));
        add_action( 'wp_footer', array( $this, 'listing_impression_tracking_code' ), 4 );
        
        // product detail
        add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

        // add cart
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
        // carte remove
        add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
        add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );

        // checkout
        add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

        // order
        add_action( 'wp_footer', array( $this, 'resume_order_tracking' ), 5 );
        
    }

    /**
     * Loads all of our options for this plugin
     * @return array An array of options that can be passed to other classes
     */
    public function init_options() {
        $options = array(
            'gokeep_id',
            'gokeep_additional_script'
        );

        $constructor = array();
        foreach ( $options as $option ) {
            $constructor[ $option ] = $this->$option = $this->get_option( $option );
        }

        return $constructor;
    }

    public function tracking_code_display() {
        global $wp;

        if ( is_admin() ) {
            return false;
        } 

        WC_Gokeep_JS::get_instance()->get_tracking_default();
    }

    /**
     * Tracks when the order resume is loaded
     */
    public function resume_order_tracking() {
        global $wp, $wpdb;
        
        $order_id = isset( $wp->query_vars['order-received'] ) ? intval( $wp->query_vars['order-received'] ) : 0;
        if ($order_id) {
            WC_Gokeep_JS::get_instance()->resume_order( WC()->order_factory->get_order($order_id) );
        }
    }

    /**
     * Tracks when the checkout form is loaded
     */
    public function checkout_process( $checkout ) {
        WC_Gokeep_JS::get_instance()->checkout_process( WC()->cart->get_cart() );
        WC_Gokeep_JS::get_instance()->lead_checkout();
    }

    /**
     * Gokeep event tracking for single product add to cart
     *
     * @return void
     */
    public function add_to_cart() {
        if ( ! is_single() ) {
            return;
        }

        global $product;

        WC_Gokeep_JS::get_instance()->add_to_cart( $product , '.single_add_to_cart_button' );
    }

    /**
     * Gokeep event tracking for removing a product from the cart
     */
    public function remove_from_cart() {
        WC_Gokeep_JS::get_instance()->remove_from_cart();
    }

    
    /**
    * create javascript vars to use in tracking.
    */
    public function tracking_vars() {
        WC_Gokeep_JS::get_instance()->create_vars();
    }

    public function listing_impression_tracking_code() {
        WC_Gokeep_JS::get_instance()->listing_impression_code();
    }

    /**
     * Measures a listing impression (from search results)
     */
    public function listing_impression() {

        global $product, $woocommerce_loop;
        $products = WC_Gokeep_JS::get_instance()->listing_impression( $product, $woocommerce_loop['loop'] );
    }


    /**
     * Measure a product detail view
     */
    public function product_detail() {
        global $product;
        WC_Gokeep_JS::get_instance()->product_detail( $product );
    }
}
