<?php
/**
* Change Proceed To Checkout Text in WooCommerce
**/

function woocommerce_button_proceed_to_checkout() {
	$checkout_url = WC()->cart->get_checkout_url();
	?>
	<a href="<?php echo $checkout_url; ?>" class="checkout-button button alt wc-forward"><?php _e( 'Order', 'woocommerce' ); ?></a>
<?php
}


add_filter( 'woocommerce_payment_gateways', 'cur_remove_unused_payment_gateways', 20, 1 );
/**
 *  This function will remove all of the WooCommerce standard gateways from the 
 *  WooCommerce > Settings > Checkout dashboard.
 */
function cur_remove_unused_payment_gateways( $load_gateways ) {
	$remove_gateways = array( 
		'WC_Gateway_BACS',
		'WC_Gateway_Cheque',
		'WC_Gateway_COD',
		'WC_Gateway_Paypal',
		'WC_Gateway_Simplify_Commerce',
		'WC_Addons_Gateway_Simplify_Commerce'
	);

	foreach ( $load_gateways as $key => $value ) {
		if ( in_array( $value, $remove_gateways ) ) {
			unset( $load_gateways[ $key ] );
		}
	}
	return $load_gateways;
}


add_action( 'plugins_loaded', 'init_default_no_gateway_class' );
function init_default_no_gateway_class() {
	class WC_Default_No_Gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'owp';
			$this->method_title       = __( 'Order without Payment', 'woocommerce' );
			$this->method_description = __( 'Have your customers order without any online payment.', 'woocommerce' );
			$this->has_fields         = false;
			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			// Get settings
			$this->title              = $this->get_option( 'title' );
			$this->description        = $this->get_option( 'description' );
			$this->instructions       = $this->get_option( 'instructions', $this->description );
			$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
			$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$shipping_methods = array();
			if ( is_admin() )
				foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
					$shipping_methods[ $method->id ] = $method->get_title();
				}
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable OWP', 'woocommerce' ),
					'label'       => __( 'Enable Order without Payment', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Order without Payment', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
					'default'     => __( 'Order without any online payment.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				/*'instructions' => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
					'default'     => __( 'Order without any online payment.', 'woocommerce' ),
					'desc_tip'    => true,
				),*/
				'enable_for_methods' => array(
					'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
					'type'              => 'multiselect',
					'class'             => 'wc-enhanced-select',
					'css'               => 'width: 450px;',
					'default'           => '',
					'description'       => __( 'If OWP is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
					'options'           => $shipping_methods,
					'desc_tip'          => true,
					'custom_attributes' => array(
						'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
					)
				),
				'enable_for_virtual' => array(
					'title'             => __( 'Accept for virtual orders', 'woocommerce' ),
					'label'             => __( 'Accept OWP if the order is virtual', 'woocommerce' ),
					'type'              => 'checkbox',
					'default'           => 'yes'
				)
		   );
		}

		function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
		
			// Mark as on-hold (we're awaiting the cheque)
			$order->update_status('on-hold', __( 'Payment to be made upon confirmation', 'woocommerce' ));
		
			// Reduce stock levels
			$order->reduce_order_stock();
		
			// Remove cart
			$woocommerce->cart->empty_cart();
		
			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

	}
}


function add_default_no_gateway_class( $methods ) {
	$methods[] = 'WC_Default_No_Gateway'; 
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_default_no_gateway_class' );


function hide_payment_methods() {
	if( is_checkout() ) {
?>
	<style type="text/css">
		#payment .payment_methods { display: none; }
		ul.order_details li.method { display: none; }
		table.order_details tfoot > tr:nth-last-child(2) { display: none; }
	</style>
<?php
	}
}
add_action( 'wp_head', 'hide_payment_methods' );


function change_checkout_title( $title ) {
	if( is_checkout() && in_the_loop() ) {
		$title = 'Order';
	}
	return $title;
}
add_filter( 'the_title', 'change_checkout_title' );


add_filter( 'manage_edit-shop_order_columns', 'cur_set_custom_column_order_columns');
function cur_set_custom_column_order_columns($columns) {
	// global $woocommerce;
	$new_col_array = array();
	foreach($columns as $key => $title) {
		if ($key=='shipping_address') {
			$new_col_array['items']  = __( 'Items', 'woocommerce' );
		}
		$new_col_array[$key] = $title;
	}
    return $new_col_array;
}

add_action( 'manage_shop_order_posts_custom_column' , 'cur_custom_shop_order_column', 10, 2 );
function cur_custom_shop_order_column( $column ) {
	global $post, $woocommerce, $the_order;

    switch ( $column ) {

        case 'items' :
            $terms = $the_order->get_items();
            //$terms = $the_order;

	    if ( is_array( $terms ) ) {
			foreach($terms as $term) {
				echo $term['item_meta']['_qty'][0] .' x ' . $term['name'] .'<br />';
			}
        } else {
            _e( '____', 'woocommerce' );
			//print_r($terms);
		}

		break;

    }
}


function hide_order_payment_method() {
	if( is_account_page() ) {
?>
	<style type="text/css">
		table.order_details tfoot > tr:nth-last-child(2) { display: none; }
	</style>
<?php
	}
}
add_action( 'wp_head', 'hide_order_payment_method' );
?>
