<?php
/**
 * Plugin Name: Custom User Role
 * Plugin URI: N/A
 * Version: 1.0
 * Description: Woocommerce percentage based discounts on products by custom user role.
 * Author: Saddam Hossain Azad
 * Author URI: https://github.com/saddamazad
**/

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  return;
}

// Define contants
define('CUR_ROOT', dirname(__FILE__));
define('CUR_URL', plugins_url('/', __FILE__));
define('CUR_HOME', home_url('/'));

require_once( CUR_ROOT . '/includes/custom-sales-and-po.php' );

// create custom plugin settings menu
/*add_action('admin_menu', 'custom_user_role_menu');*/

function custom_user_role_menu() {

	//create new top-level menu
	add_submenu_page('options-general.php', 'Custom User Role Settings', 'Custom User Role Settings', 'administrator', __FILE__, 'custom_user_role_settings_page');
	add_submenu_page('options-general.php', 'Discounts', 'Discounts', 'administrator', 'custom-discounts-page', 'custom_discounts_page');

	//call register settings function
	/*add_action( 'admin_init', 'register_custom_user_role_settings' );*/
}


function register_custom_user_role_settings() {
	//register our settings
	register_setting( 'custom-user-role-settings-group', 'cur_user_roles' );
	register_setting( 'custom-user-role-settings-group', 'cur_prod_categories' );
	//register_setting( 'custom-user-role-settings-group', 'option_etc' );
}

function custom_user_role_settings_page() {
	if( isset($_POST['submit']) ) {
		$cur_user_role = $_POST['cur_user_roles'];
		update_option('cur_user_roles', $cur_user_role);
		$prod_args = array( 'hide_empty' => false );
		$prod_categories = get_terms('product_cat', $prod_args);
		foreach( $prod_categories as $cat ) {
			$cur_prod_cat_discount = $_POST['cur_prod_cat_'.$cat->term_id];
			update_option($cur_user_role.'.cur_prod_cat_'.$cat->term_id, $cur_prod_cat_discount);
		}
		echo '<div class="updated"><p>Discounts added Successfully</p></div>';
	}
?>
<div class="wrap">
<h2>Custom User Role</h2>

<form method="post" action="">
    <?php //settings_fields( 'custom-user-role-settings-group' ); ?>
    <?php //do_settings_sections( 'custom-user-role-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">User Roles</th>
        <td>
        	<select name="cur_user_roles">
        		<?php wp_dropdown_roles(); ?>
            </select>
        </td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Product Categories</th>
        <td>
			<?php
                $args = array( 'hide_empty' => false );
                $product_categories = get_terms('product_cat', $args);
                foreach( $product_categories as $cat ) {
                    //echo '<option value="'.$cat->term_id.'"' . selected( esc_attr(get_option('cur_prod_categories')), $cat->term_id, false ) . '>'.$cat->name.'</option>';
                    echo '<p><label style="width: 120px; display: inline-block;">'.$cat->name.'</label><input type="text" name="cur_prod_cat_'.$cat->term_id.'" />'.' <small style="color: #666666;">Percentage up to 3 decimal places</small></p>';
                }
            ?>
        </td>
        </tr>
        
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php
}

function custom_discounts_page() {
?>
<div class="wrap">
<h2>Discounts</h2>
<table class="form-table">
<tr style="border-bottom: 1px solid #e2e2e2;">
	<th style="text-align:center; padding-right:0;">User Role</th>
	<th style="text-align:center; padding-right:0;">Product Category</th>
	<th style="text-align:center; padding-right:0;">Discount(%)</th>
</tr>
<?php
	global $wp_roles;
	foreach ( $wp_roles->roles as $key=>$value ):
		//echo $key.'=';
		//echo $value['name'].'<br />';
		$args = array( 'hide_empty' => false );
		$product_categories = get_terms('product_cat', $args);
		foreach( $product_categories as $cat ) {
			echo '<tr style="border-bottom: 1px solid #e2e2e2;">';
				echo '<td style="text-align:center;">'.$value['name'].'</td>';
				echo '<td style="text-align:center;">'.$cat->name.'</td>';
				echo '<td style="text-align:center;">'.esc_attr(get_option($key.'.cur_prod_cat_'.$cat->term_id)).'</td>';
			echo '</tr>';
		}
    endforeach;
?>
</table>
</div>
<?php } ?>
<?php
//add_filter('woocommerce_get_regular_price','cur_return_custom_price', 10, 2);
//add_filter('woocommerce_get_sale_price','cur_return_custom_price', 10, 2);
add_filter('woocommerce_order_amount_item_subtotal', 'cur_return_custom_price', 10, 2);
add_filter('woocommerce_get_price', 'cur_return_custom_price', 10, 2);
function cur_return_custom_price($price, $product) {
	global $post, $woocommerce;
	$product_id = $product->id; 

	// Get user's ip location and correspond it to the custom field key
	/*$user_country = $_SESSION['user_location'];
	$get_user_currency = strtolower($user_country.'_price');*/
	// If the IP detection is enabled look for the correct price
	/*if($get_user_currency!=''){
		$new_price = get_post_meta($post_id, $get_user_currency, true);
		if($new_price==''){
			$new_price = $price;
		}
	}*/

	if ( is_user_logged_in() ) {
		//$user_role = get_user_role();
		$user_ID = get_current_user_id();
		$product_category = get_product_category_by_id( $product_id );
		$discount = get_the_author_meta($user_ID.'_cur_prod_cat_'.$product_category, $user_ID);
		if( isset($discount) ) {
			$discount_price = $price*($discount/100);
			$price = $price - $discount_price;
		}
	}

	return $price;
}

function get_user_role() {
	global $current_user;

	$user_roles = $current_user->roles;
	$user_role = array_shift($user_roles);

	return $user_role;
}

function get_product_category_by_id( $product_id ) {
	$term_list = wp_get_post_terms($product_id,'product_cat',array('fields'=>'ids'));
	$cat_id = (int)$term_list[0];
	return $cat_id;
	//echo get_term_link ($cat_id, 'product_cat');
}


add_action( 'show_user_profile', 'cur_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'cur_show_extra_profile_fields' );
function cur_show_extra_profile_fields( $user ) { ?>
	<h3>Custom Discount Info</h3>
	<table class="form-table">
		<!--<tr>
			<th><label for="active">Active</label></th>
			<td>
				<select class="text-select" name="active" id="active">
					<option <?php //if(get_the_author_meta( 'active', $user->ID ) == 'Yes') echo 'selected="selected"'; ?> value="Yes">Yes</option>
					<option <?php //if(get_the_author_meta( 'active', $user->ID ) == 'No') echo 'selected="selected"'; ?> value="No">No</option>
				</select>
			</td>
		</tr>-->
		
		<tr>
			<th>Product Categories</th>
			<td>
				<?php
					$args = array( 'hide_empty' => false );
					$product_categories = get_terms('product_cat', $args);
					$read_only = '';
					if( ! current_user_can( 'manage_options' ) ) {
						$read_only = 'readonly="readonly"';
					}

					foreach( $product_categories as $cat ) {
						echo '<p><label style="width: 120px; display: inline-block;">'.$cat->name.'</label><input type="text" name="cur_prod_cat_'.$cat->term_id.'" value="'.get_the_author_meta( $user->ID.'_cur_prod_cat_'.$cat->term_id, $user->ID ).'" '.$read_only.' />'.' <small style="color: #666666;">Percentage up to 3 decimal places</small></p>';
					}
				?>
			</td>
		</tr>
	</table>
<?php }


add_action( 'personal_options_update', 'cur_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'cur_save_extra_profile_fields' );
function cur_save_extra_profile_fields( $user_id ) {

	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

	// Copy and paste this line for additional fields.
	/*update_usermeta( $user_id, 'active', $_POST['active'] );*/

	$prod_args = array( 'hide_empty' => false );
	$prod_categories = get_terms('product_cat', $prod_args);
	foreach( $prod_categories as $cat ) {
		$cur_prod_cat_discount = $_POST['cur_prod_cat_'.$cat->term_id];
		update_user_meta($user_id, $user_id.'_cur_prod_cat_'.$cat->term_id, $cur_prod_cat_discount);
	}
}
?>
