<?php
class mpd_api{

	var $mpd_categories;

	function __construct(){

		$this->api_endpoint = 'https://myprivateboutique.ch/dressing/API/B2b';

		$this->mpd_login = $_ENV['mpd_login'];
		$this->mpd_password = $_ENV['mpd_password'];
		$this->token = null;

		$this->brand_translate = null;
		$this->category_translate = null;
		$this->condition_translate = null;

		$this->user_agent = 'MPD Integration API';
	}

	function login(){

		$url = 'https://myprivateboutique.ch/dressing/API/B2b/login';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

		$post = [
			'email'=>$this->mpd_login,
			'password'=>$this->mpd_password
			];

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));

		$this->content = curl_exec($ch);
		$this->content_type = curl_getinfo($ch,  CURLINFO_CONTENT_TYPE );
		$this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$res = json_decode($this->content);

		$this->token = $res->token;
	}

	function api_request_get($uri){

		if(is_null($this->token)){

			$this->login();
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->api_endpoint.$uri);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

		curl_setopt($ch, CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer '.$this->token,
			)
		);

		$this->content = curl_exec($ch);
		$this->content_type = curl_getinfo($ch,  CURLINFO_CONTENT_TYPE );
		$this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$res = json_decode($this->content);

		return $res;
	}

	function api_request_put($uri, $data){

		if(is_null($this->token)){

			$this->login();
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->api_endpoint.$uri);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

		curl_setopt($ch, CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer '.$this->token,
			    'Content-Type: application/json',
			)
		);

		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		$this->content = curl_exec($ch);
		$this->content_type = curl_getinfo($ch,  CURLINFO_CONTENT_TYPE );
		$this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$res = json_decode($this->content);

		return $res;
	}

	function api_request_delete($uri){

		if(is_null($this->token)){

			$this->login();
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->api_endpoint.$uri);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

		curl_setopt($ch, CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer '.$this->token,
			)
		);

		$this->content = curl_exec($ch);
		$this->content_type = curl_getinfo($ch,  CURLINFO_CONTENT_TYPE );
		$this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$res = json_decode($this->content);

		return $res;
	}

	function api_request_post_image($product_id, $img_url, $is_main = true){

		if(is_null($this->token)){

			$this->login();
		}

		$ch = curl_init();

		if($is_main){
			curl_setopt($ch, CURLOPT_URL, $this->api_endpoint.'/'.$product_id.'/mainImage');
		}else{
			curl_setopt($ch, CURLOPT_URL, $this->api_endpoint.'/'.$product_id.'/secondaryImage');
		}

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer '.$this->token,
//			    'Content-Type: multipart/form-data',
			)
		);
		$fields = [
			'image' => new CURLFile($img_url,'image/jpeg','image')
		];
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		$this->content = curl_exec($ch);
		$this->content_type = curl_getinfo($ch,  CURLINFO_CONTENT_TYPE );
		$this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$res = json_decode($this->content);

		return $res;
	}

	function get_brands(){

		$res = $this->api_request_get('/brands');

		return $res;
	}

	function load_brand_map(){

		$this->brand_translate = [];

		$fl = fopen('translate/mpd_brand_translate.csv', 'r');
		$row_head = fgets($fl);

		while(!feof($fl)){

			$row = fgets($fl);
			$bt = explode(',',$row);

			$this->brand_translate[$bt[0]]=intval($bt[2]);
		}
	}

	function load_category_map(){

		$this->category_translate = [];

		$fl = fopen('translate/mpd_category_translate.csv', 'r');
		$row_head = fgets($fl);

		while(!feof($fl)){

			$row = fgets($fl);
			$ct = explode(',',$row);

			$this->category_translate[$ct[0]]=intval($ct[1]);
		}
	}

	function load_condition_map(){

		$this->condition_translate = [];

		$fl = fopen('translate/mpd_condition_translate.csv', 'r');
		$row_head = fgets($fl);

		while(!feof($fl)){

			$row = fgets($fl);
			$ct = explode(',',$row);

			$this->condition_translate[$ct[0]]=$ct[1];
		}
	}

	function convert_brand_id($brand_name){

		static $brand_list;

		if(is_null($brand_list)){

			$brands = $this->get_brands();
			foreach($brands as $obj){

				$brand_list[$obj->name] = $obj->id;
			}
		}

		if(is_null($this->brand_translate)){

			$this->load_brand_map();
		}

		if(isset($this->brand_translate[$brand_name])){

			return $this->brand_translate[$brand_name];
		}

		if(isset($brand_list[$brand_name])){

			return $brand_list[$brand_name];
		}

		return 0;
	}

	function convert_category_id($category_name){

		if(is_null($this->category_translate)){

			$this->load_category_map();
		}

		if(isset($this->category_translate[$category_name])){

			return $this->category_translate[$category_name];
		}

		return 0;
	}

	function convert_condition_rating($condition){

		if(is_null($this->condition_translate)){

			$this->load_condition_map();
		}

		$condition_lowercase = mb_convert_case($condition, MB_CASE_LOWER);

		if(isset($this->condition_translate[$condition_lowercase])){

			return $this->condition_translate[$condition_lowercase];
		}

		return null;
	}

	function get_categories(){

		if(is_null($this->mpd_categories)){

			$this->mpd_categories = $this->api_request_get('/categories');
		}

		return $this->mpd_categories;
	}

	function get_required_fields($cat_id){

		if(is_null($this->mpd_categories)){

			$this->mpd_categories = $this->api_request_get('/categories');
		}

		$fields = [];

		foreach($this->mpd_categories as $cat){

			if($cat->id == $cat_id){

				$fields = $cat->requiredFields;
			}
		}

		asort($fields);

		return $fields;
	}

	function get_detail_fields(){

		$res = $this->api_request_get('/detailFields');
		return $res;
	}

	function get_products(){

		$res = $this->api_request_get('/products/live');
		return $res;
	}

	function convert_price($price){

		return intval(1.1 * $price);
	}

	function put_product($product){

		$res = $this->api_request_put('/products', [$product]);
		return $res;
	}

	function add_image($product_id, $img_url, $is_main){

		$res = $this->api_request_post_image($product_id, $img_url, $is_main);
		return $res;
	}

	function delete_image($product_id, $image_id){

		$res = $this->api_request_delete('/'.$product_id.'/image/'.$image_id);
		return $res;
	}

	function delete_images($product_id, $image_list){

		foreach($image_list as $image_id){

			$this->delete_image($product_id, $image_id);
		}
	}
}
?>