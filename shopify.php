<?php
//require_once __DIR__ . '/env_config.php';
require_once __DIR__ . '/dotenv.php';
require_once __DIR__ . '/vendor/autoload.php';

//require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/mpd_api.php';
require_once __DIR__ . '/helper.php';

$mpd_api = new mpd_api();

use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Auth\FileSessionStorage;
use Shopify\Rest\Admin2022_07\Metafield;
use Shopify\Utils;

Context::initialize(
    $_ENV['shopify_api_key'],
    $_ENV['shopify_api_secret_key'],
    'read_products, write_products',
    $_ENV['shopify_shop'],
    new FileSessionStorage('/tmp/php_sessions'),
    '2022-04',
    true,
    false,
);

$shopify_client = new Rest($_ENV['shopify_shop'], $_ENV['shopify_api_secret_token']);

class shopify_product{

	var $shopify_body = null;
	var $shopify_meta = null;
	var $mpd_product = null;

	function __construct($args){

		if(isset($args['shopify_list_body'])){

			$this->init_from_shopify_list($args['shopify_list_body']);
		}
	}

	function init_from_shopify_list($body){

		$this->shopify_body = $body;
	}

	function load_shopify_meta(){

		global $shopify_client;

		$meta = $shopify_client->get('products/'.$this->get_shopify_id().'/metafields.json')->getDecodedBody();

		$this->shopify_meta = $meta;
	}

	function get_shopify_id(){

		return $this->shopify_body['id'];
	}

	function get_sku(){

		return $this->shopify_body['variants'][0]['sku'];
	}

	function get_vendor(){

		return $this->shopify_body['vendor'];
	}

	function get_product_type(){

		return $this->shopify_body['product_type'];
	}

	function get_mpd_product(){

		if(is_null($this->mpd_product)){

			$this->mpd_convert_product();
		}

		return $this->mpd_product;
	}

	function mpd_convert_product(){

		global $mpd_api;

		if(is_null($this->shopify_meta)){

			$this->load_shopify_meta();
		}

		$mpd_product = [];

		// Currently we process only products with exactly 1 variant

		if(count($this->shopify_body['variants']) !=1){

			return null;
		}

		if($this->shopify_body['vendor'] == 'Tabita Bags with Love'){

			return null;
		}

		$product_type = $this->shopify_body['product_type'];
		$mpd_product['catId'] = $mpd_api->convert_category_id($product_type);

		if($mpd_product['catId'] == 0){

			return null;
		}

		$mpd_product['SKU'] = $this->shopify_body['variants'][0]['sku'];

		$vendor = $this->shopify_body['vendor'];
		$mpd_product['brandId'] = $mpd_api->convert_brand_id($vendor);

		if($mpd_product['brandId'] == 0){

			return null;
		}

		if($this->is_visible()){
			$mpd_product['status'] = 'live';
		}else{
			$mpd_product['status'] = 'sold';
		}

		if(empty($this->shopify_body['tags'])){
			$mpd_product['hasRetailTags'] = 0;
		}else{
			$mpd_product['hasRetailTags'] = 1;
		}

		$mpd_product['price'] = $mpd_api->convert_price($this->shopify_body['variants'][0]['price']);

		$mpd_product['name_en'] = $this->shopify_body['title'];

		$conition_rating = $this->get_shopify_condition($this->shopify_body);
		$mpd_product['conditionRating'] = $mpd_api->convert_condition_rating($conition_rating);

		if(empty($mpd_product['conditionRating'])){

			return null;
		}

		$mpd_product['wearSignsDesc_en'] = $this->smpd_build_wearsigns();

		// Description

		$mpd_product['description_en'] = $this->shopify_body['body_html'];

		$desc_add_list = [];

		if(!empty($mpd_product['conditionRating'])){
			$desc_add_list[] = 'Condition rating: '.$mpd_product['conditionRating'];
		}

		$production = $this->get_meta_value('my_fields', 'production');
		if(!empty($production)){
			$desc_add_list[] = 'Production: '.$production;
		}

		$accessories = $this->get_meta_value('my_fields', 'accessories');
		if(!empty($accessories)){
			$desc_add_list[] = 'Comes with: '.$accessories;
		}

		if(count($desc_add_list) > 0){

			$mpd_product['description_en'].='<ul>';

			foreach($desc_add_list as $ln){
				$mpd_product['description_en'].='<li>'.$ln.'</li>';
			}

			$mpd_product['description_en'].='</ul>';
		}

		$this->mpd_product = $mpd_product;

		// $this->extract_custom_properties();

		// Set blank for required if notset

		$required_fields = $mpd_api->get_required_fields($mpd_product['catId']);

		foreach($required_fields as $field){

			$this->mpd_product[$field] = $this->get_custom_property($field);
		}

		$this->mpd_product['Length'] = null;

		$length = $this->get_custom_property('Length');

		if(empty($this->mpd_product['Height']) AND in_array('Height', $required_fields) AND !empty($length)){

			$this->mpd_product['Height'] = $length;
		}
	}

	function is_visible(){

		if(!$this->shopify_body['status']=='active'){

			return false;
		}

		if(!(intval($this->shopify_body['variants'][0]['inventory_quantity'])>0)){

			return false;
		}

		return true;
	}

	function get_shopify_condition(){

		return $this->get_meta_value('my_fields', 'conditionrating');
	}

	function get_meta_value($namespace, $meta_key){

		if(is_null($this->shopify_meta)){

			$this->load_shopify_meta();
		}

		$value = null;

		foreach($this->shopify_meta['metafields'] as $meta_item){

			if(isset($meta_item['namespace']) AND $meta_item['namespace'] == $namespace AND isset($meta_item['key']) AND $meta_item['key'] == $meta_key){

				$value = $meta_item['value'];
			}
		}

		return $value;
	}

	function smpd_build_wearsigns(){

		$hardware = $this->get_meta_value('my_fields', 'hardware_condition');
		$exterior = $this->get_meta_value('my_fields', 'exterior_condition');
		$interior = $this->get_meta_value('my_fields', 'hardware_condition');

		$parts = [];
		if(!is_null($hardware)){

			if(mb_substr($hardware,-1)!='.'){
				$hardware .= '.';
			}

			$parts[] = helper_fl_capitalize($hardware);
		}

		if(!is_null($exterior)){

			if(mb_substr($exterior,-1)!='.'){
				$exterior .= '.';
			}

			$parts[] = 'Exterior: '.$exterior;
		}

		if(!is_null($interior)){

			if(mb_substr($interior,-1)!='.'){
				$interior .= '.';
			}

			$parts[] = 'Interior: '.$interior;
		}

		return implode(' ', $parts);
	}

	function get_meta_property_map(){

		return [
			'Colour' => [
					'namespace' => 'mm-google-shopping',
					'key' => 'color'
				],
			'Material' => [
					'namespace' => 'mm-google-shopping',
					'key' => 'material'
				],
			'Depth' => [
					'namespace' => 'my_fields',
					'key' => 'heightexport'
				],
			'Width' => [
					'namespace' => 'my_fields',
					'key' => 'widthexport'
				],
			'Length' => [
					'namespace' => 'my_fields',
					'key' => 'lenghtexport'
				],
			];
	}

	function extract_custom_properties(){

		$map = $this->get_meta_property_map();

		foreach($map as $key => $pt){

			$vl = $this->get_meta_value($pt['namespace'], $pt['key']);
			if(!empty($vl)){

				$this->mpd_product[$key] = $vl;
			}
		}
	}

	function get_custom_property($property){

		$map = $this->get_meta_property_map();

		$vl = null;

		if(isset($map[$property])){

			$vl = $this->get_meta_value($map[$property]['namespace'], $map[$property]['key']);
		}

		return $vl;
	}

	function get_cover_image(){

		if (isset($this->shopify_body['image'])){

			return $this->shopify_body['image'];

		}else{

			return null;
		}
	}

	function get_images(){

		if (isset($this->shopify_body['images'])){

			return $this->shopify_body['images'];

		}else{

			return null;
		}
	}

	function get_mpd_hash(){

		if(is_null($this->mpd_product)){

			$this->mpd_convert_product();
		}

		return md5(json_encode($this->mpd_product));
	}
}

class shopify_mpd{

	var $vendor_list = [];
	var $cat_list = [];
	var $condition_list = [];

	var $count_new = 0;

	function __construct(){
	}

	function smpd_iterate_products(){

		global $shopify_client;

		$product_list = [];

		$response = $shopify_client->get('products');

		$body = $response->getDecodedBody();
		$pageinfo = $response->getPageInfo();
		$this->smpd_iterate_products_body($body);

		while($pageinfo->hasNextPage()){

			$response = $shopify_client->get('products', [], $pageinfo->getNextPageQuery());
			$body = $response->getDecodedBody();
			$pageinfo = $response->getPageInfo();

			$product_list[] = $body;
		}

		foreach($product_list as $body){

			$this->smpd_iterate_products_body($body);
		}
	}

	function smpd_iterate_products_body($body){

		$fl = fopen('shopify_dump.txt','a');

		echo count($body['products'])."\n";

		foreach($body['products'] as $list_body){

			$product = new shopify_product([
					'shopify_list_body' => $list_body,
				]);

			$this->smpd_process_product($product);
		}
	}

	function smpd_process_product($product){

		global $mpd_api, $db;

		$hash = $product->get_mpd_hash();

		$db->query("SELECT * FROM product_map WHERE shopify_id='".$product->get_shopify_id()."'");
		if($db->num_rows() == 0){

			if($product->is_visible()){

				$this->smpd_product_new($product);
				print($product->get_shopify_id()." - product is new, sent to MPD\n");

			}else{

				print($product->get_shopify_id()." - product is new, but invisible. No action\n");
			}

		}else{

			$row = $db->row();

			if($hash == $row['mpd_hash']){

				print($product->get_shopify_id()." - hash is not changed. No action\n");

			}else{

				$this->smpd_product_update($product);
				print($product->get_shopify_id()." - product sent for update to MPD\n");
			}
		}

		$this->add_stat($product);
	}

	function smpd_product_update($product){

		global $mpd_api, $db;

		echo 'Upd product id='.$product->get_shopify_id()."\n";

		$this->smpd_product_transfer($product);

		$ts = date('Y-m-d H:i:s');

		if(!is_null($this->last_mpd)){

			$db->query("UPDATE product_map SET mpd_product_last = mpd_product WHERE shopify_id='".$product->get_shopify_id()."'");
			$db->query("UPDATE product_map SET mpd_id='".$this->last_mpd->id."', mpd_hash='".$product->get_mpd_hash()."', sku='".$product->get_sku()."', last_update='".$ts."', is_visible='".intval($product->is_visible())."', mpd_product='".$db->escape(json_encode($this->last_mpd))."' WHERE shopify_id='".$product->get_shopify_id()."'");
		}
	}

	function smpd_product_new($product){

		global $mpd_api, $db;

		echo 'New product id='.$product->get_shopify_id()."\n";

		$this->smpd_product_transfer($product);

		if(!is_null($this->last_mpd)){

			$shopify_mpd = [
				'shopify_id' => $product->get_shopify_id(),
				'mpd_id' => $this->last_mpd->id,
				'mpd_hash' => $product->get_mpd_hash(),
				'sku' => $product->get_sku(),
				'last_update' => date('Y-m-d H:i:s'),
				'is_visible' => intval($product->is_visible()),
				'mpd_product' => $db->escape(json_encode($this->last_mpd)),
				];

			$db->insert_row('product_map', $shopify_mpd);
		}

		$this->count_new = $this->count_new+1;
	}

	function smpd_product_transfer($product){

		global $mpd_api;

		$this->last_mpd = null;
		$mpd_product = $product->get_mpd_product();

		if(is_null($mpd_product)){

			echo 'Pass product id='.$product->get_shopify_id()." (not converted)\n";

		}else{

			$res = $mpd_api->put_product($mpd_product);

			if(!is_array($res)){

				print_r($mpd_product);
				print_r($res);
			}

			$this->last_mpd = $res[0];
			$mpd_id = $res[0]->id;

			if(isset($res[0]->imgIds)){

				$mpd_api->delete_images($mpd_id, $res[0]->imgIds);
			}

			$image = $product->get_cover_image();

			if(!empty($image)){

				$img_front_id = $image['id'];
				$img_url = $image['src'];
				$res = $mpd_api->add_image($mpd_id, $img_url, true);
				$this->last_mpd->images = $res;

			}else{

				$img_front_id = 0;
			}

			$images = $product->get_images();

			if(!empty($images)){

				foreach($images as $img){

					if($img_front_id > 0 AND $img_front_id == $img['id']){

						// Nope

					}else{

						$img_url = $img['src'];
						$res = $mpd_api->add_image($mpd_id, $img_url, false);
						$this->last_mpd->images = $res;
					}
				}
			}
		}
	}

	function add_stat($product){

		global $mpd_api;

		$vendor = $product->get_vendor();
		$mpd_brand_id = $mpd_api->convert_brand_id($vendor);

		if(!isset($this->vendor_list[$vendor])){

			$this->vendor_list[$vendor] = [
				'count' => 1,
				'mpd_id' => $mpd_brand_id,
				];
		}else{

			$this->vendor_list[$vendor]['count']++;
		}

		$cat = $product->get_product_type();
		$mpd_cat_id = $mpd_api->convert_category_id($vendor);

		if(!isset($this->cat_list[$cat])){

			$this->cat_list[$cat] = [
				'count' => 1,
				'mpd_id' => $mpd_cat_id,
				];

		}else{

			$this->cat_list[$cat]['count']++;
		}

		$cond = $product->get_shopify_condition();

		if(!isset($this->condition_list[$cond])){

			$this->condition_list[$cond] = [
				'count' => 1,
				'mpd_id' => 0,
				];

		}else{

			$this->condition_list[$cond]['count']++;
		}
	}

	function write_vendor_stats(){

		global $mpd_api;

		$fl = fopen('vendor.csv','w');
		fwrite($fl, 'brand,id,count'."\n");
		foreach($this->vendor_list as $key=>$vl){
			$brand_id = $mpd_api->convert_brand_id($key);
			fwrite($fl, $key.','.$brand_id.','.$vl['count']."\n");
		}
		fclose($fl);
	}
}

//$sm = new shopify_mpd();
//$sm->smpd_iterate_products();

//print_r($sm->condition_list);

?>