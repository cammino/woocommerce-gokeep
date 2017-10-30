<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gokeep_JS class
 *
 * JS for recording Gokeep info
 */
class WC_Gokeep_JS {
    
    /** @var object Class Instance */
    private static $instance;

    /** @var array Inherited Analytics options */
    private static $options;

    /**
     * Get the class instance
     */
    public static function get_instance( $options = array() ) {
        return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
    }

    /**
     * Constructor
     * Takes our options from the parent class so we can later use them in the JS snippets
     */
    public function __construct( $options = array() ) {
        self::$options = $options;
    }

    public  static function create_vars()
    {
        wc_enqueue_js("var productImpressions = new Array");
    }

    public static function get_tracking_default() {
        wc_enqueue_js(
            "(function(w, d, s, h, g, a, m) {
                window[\"gokeep\"] = window[\"gokeep\"] || function() {
                    (window[\"gokeep\"].q = window[\"gokeep\"].q || []).push(arguments)
                }, window[\"gokeep\"].l = 1 * new Date();
                
                a = d.createElement(s),
                    m = d.getElementsByTagName(s)[0];
                a.async = 1;
                a.src = h;
                m.parentNode.insertBefore(a, m)
            })(window, document, 'script', '//tracking.gokeep.me', 'gokeep');
            gokeep(\"create\", \"" . esc_js( self::get( 'gokeep_id' ) ) . "\");
            // pageview
            gokeep(\"send\", \"pageview\");"
        );

        wc_enqueue_js( self::get( 'gokeep_additional_script' ) );
    }

    /**
     * Builds the add_to_cart event
     */
    public static function add_to_cart( $product, $selector ) {

        wc_enqueue_js("
            jQuery('{$selector}').on('click', function(){
                gokeep('send', 'cartadd', [{
                    'id': '" . esc_js( $product->id ) . "',
                    'name': '" . str_replace( '&amp;', 'E', esc_js( $product->get_title() ) ) . "',
                    'sku': '" . esc_js( $product->get_sku() ) . "',
                    'price': " . esc_js( floatval($product->get_price()) ) . ",
                    'url': '" . esc_js( $product->post->guid ) . "',
                    'image': '" . esc_js( self::get_image($product->id) ) . "',
                    'qty': jQuery( 'input.qty' ).val() ? jQuery( 'input.qty' ).val() : '1',
                    'category': " . self::product_get_category_line( $product ) . "
                }]);
                gokeep('send', 'pageview');
            })
        ");
    }

    /**
     * Tracks an gokeep remove from cart action
     */
    function remove_from_cart() {
        wc_enqueue_js( "
            jQuery( '.remove, .product-remove a' ).on( 'click', function() {
                gokeep( 'send', 'cartremove', {
                    'id': $(this).data('product_id'),
                    'qty': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1'
                });
                gokeep('send', 'pageview');
            });
        ");
    }

    /**
     * Builds the addImpression object
     */
    public static function listing_impression( $product, $position ) {
        wc_enqueue_js("
            productImpressions.push({
                'id': '" . esc_js( $product->id ) . "',
                'name': '" . str_replace( '&amp;', 'E', esc_js( $product->get_title() ) ) . "',
                'sku': '" . esc_js( $product->get_sku() ) . "',
                'price': " . esc_js( floatval($product->get_price()) ) . ",
                'url': '" . esc_js( get_permalink( $product->id ) ) . "',
                'image': '" . esc_js( self::get_image($product->id) ) . "',
                'category': " . self::product_get_category_line( $product ) . "
            });

        ");
    }

    public static function listing_impression_code()
    {
        
        if ( isset( $_GET['s'] ) ) {
            $list = "Search Results";
        } else {
            $list = "Product List";
        }

        wc_enqueue_js( "
            if (productImpressions.length > 0) {
                gokeep( 'send', 'productimpression', {
                    list: '" . esc_js( $list ) . "',
                    items: productImpressions
                });
            }
        ");
    }

    /**
     * Tracks a product detail view
     */
    public static function product_detail( $product ) {
        wc_enqueue_js( 
            "gokeep( 'send', 'productview', {
                'id': '" . esc_js( $product->id ) . "',
                'sku': '" . esc_js( $product->get_sku() ) . "',
                'name': '" . str_replace( '&amp;', 'E', esc_js( $product->get_title() ) ) . "',
                'url': '" . esc_js( get_permalink( $product->id ) ) . "',
                'price': '" . esc_js( floatval($product->get_price()) ) . "',
                'image': '" . esc_js( self::get_image( $product->id ) ) . "',
                'category': " . self::product_get_category_line( $product ) . "
            });"
        );
    }

    /**
     * Tracks when the checkout process is started
     */
    public static function checkout_process( $cart ) {
        $code = "";

        foreach ( $cart as $cart_item_key => $cart_item ) {
            $product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $items .= "{
                'id': '" . esc_js( $product->id ) . "',
                'name': '" . str_replace( '&amp;', 'E', esc_js( $product->get_title() ) ) . "',
                'category': " . self::product_get_category_line( $product ) . "
                'price': '" . esc_js( floatval($product->get_price()) ) . "',
                'image': '" . esc_js( self::get_image( $product->id ) ) . "',
                'qty': '" . esc_js( $cart_item['quantity'] ) . "',
                'sku': '" . esc_js( $product->get_sku() ) . "',
                'url': '" . esc_js( get_permalink( $product->id ) ) . "',
            },";
        }

        $code .= "gokeep('send','checkout', {
            step: 1, step_label: 'OneStep Checkout', additional: '',
            items: [$items]
        });";
        wc_enqueue_js( $code );
    }

    /**
     * Save lead
     */
    public static function lead_checkout()
    {
        wc_enqueue_js( " 
            // form checkout
            jQuery('.woocommerce-checkout, form.checkout').on('submit', function() {
                var email = jQuery('#billing_email').val(),
                name = jQuery('#billing_first_name').val(),
                lastname = jQuery('#billing_last_name').val(),
                invalid_email = jQuery('#billing_email').parent().hasClass('woocommerce-invalid');

                if (email !== '' && !invalid_email) {
                    var fullname = name + ' ' + lastname;
                    
                    gokeep('send','lead', {
                        name: fullname,
                        email: email
                    });
                }
            });
            
            // form login
            jQuery('.login').on('submit', function() {
                var email = jQuery('#username').val();

                if (email !== '') {
                    gokeep('send','lead', {
                        name: '',
                        email: email
                    });
                }
            });"
        );
    }


    public static function resume_order($order) {
        $items =  "";
        $products = $order->get_items(); 
        
        foreach ($products as $product_key => $_product) {
            $product = WC()->product_factory->get_product($_product['product_id']);
            var_dump($_product['qty']); 
            $items .= "{
                'id': '" . esc_js( $product->id ) . "',
                'name': '" . str_replace( '&amp;', 'E', esc_js( $product->get_title() ) ) . "',
                'category': " . self::product_get_category_line( $product ) . "
                'price': '" . esc_js( floatval($product->get_price()) ) . "',
                'image': '" . esc_js( self::get_image( $product->id ) ) . "',
                'qty': '" . esc_js( $_product['qty'] ) . "',
                'sku': '" . esc_js( $product->get_sku() ) . "',
                'url': '" . esc_js( get_permalink( $product->id ) ) . "'
            },";
        }

        $shipping = $order->get_items( 'shipping' );

        // coupons
        $coupons =  $order->get_used_coupons();
        $coupons_list = "";
        if ($coupons) {
            $coupons_count = count( $coupons );
            $i = 1;
    
            foreach( $coupons as $coupon) {
                $coupons_list .=  $coupon;
                if( $i < $coupons_count )
                    $coupons_list .= ', ';
                $i++;
            }
        }

        $code = "{
            'id': '" . esc_js( $product->id ) . "',
            'total': " . esc_js( $order->get_total() ) . ",
            'shipping': " . esc_js( count($shipping) ? $shipping['cost'] : 0.00 ) . ",
            'tax': " . esc_js( 0.00 ) . ",
            'coupon': '" . esc_js( $coupons_list ) . "',
            'items': [" . $items . "]
        }";

        wc_enqueue_js("gokeep('send', 'order', $code )");
    }

     /* 
     * Returns a 'category' JSON line based on $product
     * @param  object $product  Product to pull info for
     * @return string          Line of JSON
     */
    private static function product_get_category_line( $_product ) {
        if ( is_array( $_product->variation_data ) && ! empty( $_product->variation_data ) ) {
            $code = "'" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";
        } else {
            $out = array();
            $categories = get_the_terms( $_product->id, 'product_cat' );
            if ( $categories ) {
                foreach ( $categories as $category ) {
                    $out[] = $category->name;
                }
            }
            $code = "'" . str_replace("&amp;", "E", esc_js( join( "/", $out ) )) . "',";
        }

        return $code;
    }
    

    /**
     * get product image
     **/
    private static function get_image( $product_id )
    {
        $attachment = "";

        if ( has_post_thumbnail( $product_id ) ) {
            $attachment_ids[0] = get_post_thumbnail_id( $product_id );
            $attachment = wp_get_attachment_image_src($attachment_ids[0], 'full' );
        }


        return $attachment[0];
    }

    /**
     * Return one of our options
     * @param  string $option Key/name for the option
     * @return string         Value of the option
     */
    public static function get( $option ) {
        return self::$options[$option];
    }
}
