<?php
function helper_fl_capitalize($str){

	$fl = mb_substr($str,0,1);
	return mb_convert_case($fl,MB_CASE_UPPER).mb_substr($str,1);
}
?>