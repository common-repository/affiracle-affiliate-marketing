<?php
/*
Plugin Name: Affiracle affiliate marketing
Description: This plugin connects your affiracle account to your store. <a target="_blank" href="https://affiracle.com/merchant_login.html?action=connect_woocoomerce">Log in</a> or <a target="_blank" href="https://affiracle.com/merchant_login.html?action=connect_woocoomerce">Register</a> to your Affiracle account to add this site to your profile
Version: 1.0.10
Author: Affiracle Technologies
*/
if ( ! defined( 'ABSPATH' ) ) exit;

$affiracle_plugin_version = "1.0.10";
$affiracle_plugin_version_code = 1;

$affiracle_token_key = 'affiracle_public_token';

if(is_multisite()){
	$affiracle_token_key .= "_".get_current_blog_id();
}

register_activation_hook( __FILE__, 'affiracle_on_activated' );

add_action( 'rest_api_init', 'affiracle_add_api_route');

function affiracle_add_api_route(){
    register_rest_route( 'affiracle', '/config', array(
        'methods' => 'GET',
		'callback' => 'affiracle_get_config',
        'permission_callback' => '__return_true'
	));
    register_rest_route('affiracle','/public_token', array(
        'methods' => 'GET',
        'callback' => 'affiracle_get_public_token',
        'permission_callback'=>'__return_true'
    ));

}

function affiracle_get_config(){

    global $affiracle_plugin_version;
    global $affiracle_plugin_version_code;
	$main_plugin_file = plugin_basename(__FILE__);
	
	$data = array(
		'affiracle_public_token'=> affiracle_get_public_token(),
		'store_name'=> get_option('blogname'),
		'plugin_version' => $affiracle_plugin_version,
		'plugin_version_code' => $affiracle_plugin_version_code
	);
    if (is_plugin_active($main_plugin_file)) {
        $data['plugin_status'] = 'Activated';
    } else {
        $data['plugin_status'] = 'Not Activated';
    }
	return $data;
}

function affiracle_generateValidShortUUID() {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charLength = strlen($characters);
    $shortUUID = '';

    for ($i = 0; $i < 22; $i++) {
        $randomIndex = mt_rand(0, $charLength - 1);
        $shortUUID .= $characters[$randomIndex];
    }

    return $shortUUID;
}

function affiracle_on_activated(){
	global $affiracle_token_key;
	$affiracle_unique_token = affiracle_generateValidShortUUID();
	add_option($affiracle_token_key, $affiracle_unique_token);
	return $affiracle_unique_token;
}

function affiracle_get_public_token(){
	global $affiracle_token_key;
	$affiracle_unique_token = get_option($affiracle_token_key);
	if($affiracle_unique_token) {
		return $affiracle_unique_token;
	}else{
		return affiracle_on_activated();
	}
}

function affiracle_client_footer(){
    $public_token = affiracle_get_public_token();
    $version = date('dmY'); 
    $javascript_url = 'https://miracle.affiracle.com/' . $public_token . '.js?v=' . $version;
    wp_enqueue_script("affiracle_ref_tracking", $javascript_url);
}

add_action('wp_head', 'affiracle_client_footer');

function affiracle_add_script_to_thankyou_page($order_id = '') {
	if ( isset($_GET['key']) && substr( $_GET['key'], 0, 9 ) === "wc_order_" ) {
		$order_id = wc_get_order_id_by_order_key($_GET['key']);
    }
    if(empty($order_id)) return false;
	
    $order = wc_get_order($order_id);
    $order_data = array(
        'code' => $order_id, // Your numeric order number in your ecommerce store
        'total' => $order->get_total(), // Total order sum, the amount the user paid
        'line_items' => array(),
    );

    foreach ($order->get_items() as $item) {
    $product = $item->get_product();
    $line_item = array(
        'name' => $item->get_name(),
        'quantity' => $item->get_quantity(),
        'price' => $item->get_subtotal(),
        'sku' => $product->get_sku(),
        'product_id' => $product->get_id(),
        'tax' => $item->get_total_tax(),
        'discount' => $item->get_subtotal() - $item->get_total(),
        'categories' => array(),
    );

    $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'id=>name'));
    foreach ($categories as $category_id => $category_name) {
        $category_data = array(
            'term_id' => $category_id,
            'name' => $category_name,
            'parent' => wp_get_term_taxonomy_parent_id($category_id, 'product_cat'),
        );
        $line_item['categories'][] = $category_data;
    }

    $order_data['line_items'][] = $line_item;
}


    $affiracle_script = 'window.affiracle_order_details=' . json_encode($order_data) . ';';
	affiracle_client_footer();
	wp_add_inline_script("affiracle_ref_tracking",$affiracle_script,'before');
	
	try {
		$order = wc_get_order($order_id);
		$webhook_url = 'https://affiracle.com/api/woocommerce/'.affiracle_get_public_token().'/'.$order_id;
		wp_add_inline_script("affiracle_ref_tracking",$affiracle_script,'after');
		$response = wp_safe_remote_post($webhook_url, array(
			'headers' => array('Content-Type' => 'application/json'),
		));

		
	} catch (Exception $e) {
        error_log('Error processing new order: ' . $e->getMessage());
    }
}
add_action('woocommerce_thankyou', 'affiracle_add_script_to_thankyou_page',10,1);






add_action('wp_footer', 'affiracle_add_script_to_thankyou_page');
// function wpsites_add_tracking_code() {
	
//         if ( isset($_GET['key']) && substr( $_GET['key'], 0, 9 ) === "wc_order_" ) {
// echo'<h1 class="tracking-code">add your tracking code here.</h1>';
//         } 
//     }