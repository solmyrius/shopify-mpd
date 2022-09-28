<?php

class mysqli_db{

	var $resource, $result, $db_host, $db_user, $db_password, $db_name, $collation;

	function __construct(){

		$this->collation = "utf8";
	}

	function connect($db_host,$db_user,$db_password, $db_name){

		$this->db_host = $db_host;
		$this->db_user = $db_user;
		$this->db_password = $db_password;
		$this->db_name = $db_name;

		return $this->fconnect();
	}

	function fconnect(){

		$this->resource = mysqli_connect($this->db_host, $this->db_user, $this->db_password, $this->db_name);
		mysqli_query($this->resource,"SET NAMES ".$this->collation);
		return $this->resource;
	}

	function close(){

		mysqli_close($this->resource);
	}

	function select_db($db_name){

		mysqli_query($this->resource, "USE ".$db_name);
		$this->db_name = $db_name;
	}

	function get_db(){

		return $this->db_name;
	}

	function query($sql){

		$this->last_sql = $sql;

		$this->result = mysqli_query($this->resource,$sql);
		if (!$this->result){
			if($this->errno == 2006){
				$this->close();
				$this->fconnect();
				$this->select_db($this->db_name);
				$this->result = mysqli_query($this->resource,$sql);
			}
		}

		return ($this->result);
	}

	function q($sql){

		return (mysqli_query($this->resource,$sql));
	}

	function row(){

		if($this->result){
			try{
				return mysqli_fetch_assoc($this->result);
			}catch(Exception $e){
				error_log("Error in SQL while fetching row ".$this->last_sql);
			}
		}
	}

	function num_rows(){

		return mysqli_num_rows($this->result);
	}

	function affected_rows(){

		return mysqli_affected_rows($this->resource);
	}

	function insert_id(){

		return mysqli_insert_id($this->resource);
	}

	function insert_row($tname, $values, $shema = null){

		if($shema){
			$vl = array();
			foreach($shema as $key => $s){
				if(isset($values[$key])){
					if($s['type']=='text'){
						$vl[$key] = "'".mysqli_real_escape_string($this->resource,$values[$key])."'";
					}elseif($s['type']=='function'){
						$vl[$key] = $values[$key];
					}else{
						$vl[$key] = "'".$values[$key]."'";
					}
				}
			}
			$sql = "INSERT INTO ".$tname." (`".implode("`,`",array_keys($vl))."`) VALUES(".implode(",",$vl).")";
		}else{
			$sql = "INSERT INTO ".$tname." (`".implode("`,`",array_keys($values))."`) VALUES('".implode("','",$values)."')";
		}

		return ($this->query($sql));
	}

	function update_row($tname, $id, $values, $id_name = 'id', $shema = null){
		$update = array();
		if($shema){
			foreach($shema as $key => $s){
				if(isset($values[$key])){
					if(empty($s['type'])){
						$this->log_error('Shema type is not set');
					}
					if($s['type']=='text'){
						$update[] = "`".$key."` = '".mysqli_real_escape_string($this->resource,$values[$key])."'";
					}elseif($s['type']=='function'){
						$update[] = "`".$key."` = ".$values[$key]."";
					}else{
						$update[] = "`".$key."` = '".$values[$key]."'";
					}
				}
			}
		}else{
			foreach($values as $key=>$vl){
				$update[] = "`".$key."` = '".$vl."'";
			}
		}
		$sql = "UPDATE ".$tname." SET ".implode(", ",$update)." WHERE `".$id_name."`='".$id."'";
		return ($this->query($sql,$this->resource));
	}

	function escape($text){

		return mysqli_real_escape_string($this->resource,$text);
	}

	function ping(){

		return mysqli_ping($this->resource);
	}

	function pingr(){

		if (!mysqli_ping($this->resource)){
			$this->close();
			$this->fconnect();
			$this->select_db($this->db_name);
		};
	}
}

class db_factory{
		
	var $db_host, $db_user, $db_password, $db_name, $collation, $init_sql;
		
	function __construct($db_host,$db_user,$db_password,$db_name){

		$this->db_host = $db_host;
		$this->db_user = $db_user;
		$this->db_password = $db_password;
		$this->db_name = $db_name;
		$this->collation = "utf8";
		$this->init_sql = "";
	}

	function set_collation($collation){

		$this->collation = $collation;
	}

	function set_init_sql($init_sql){

		$this->init_sql = $init_sql;
	}

	function new_db(){

		$db = new mysqli_db();
		$db->connect($this->db_host,$this->db_user,$this->db_password,$this->db_name);

		if($this->init_sql!=""){
			$db->q($this->init_sql);
		}

		return $db;
	}
}
?>