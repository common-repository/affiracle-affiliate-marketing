<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$affiracle_token_key = 'affiracle_public_token';
if(is_multisite()){
	$affiracle_token_key .= "_".get_current_blog_id();
}
delete_option($affiracle_token_key);