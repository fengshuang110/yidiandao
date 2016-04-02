<?php
define('ROOT_PATH', dirname(__FILE__).'/../');
require_once(ROOT_PATH.'./install/common.php');

if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
{
	$_POST    = stripSlash($_POST);
	$_GET     = stripSlash($_GET);
	$_COOKIE  = stripSlash($_COOKIE);
}

$act = url_get('act');
if($act && preg_match("|^\w+$|",$act))
{
	//载入函数库
	require(ROOT_PATH.'./install/include/function.php');
	$allowAction = array("check_mysql","install_sql");

	if(in_array($act,$allowAction))
	{
		return call_user_func($act);
	}
	else if(file_exists(ROOT_PATH.'./install/'.$act.'.php'))
	{
		require(ROOT_PATH.'./install/'.$act.'.php');
	}
	else
	{
		die("act error");
	}
}
else
{
	require(ROOT_PATH.'./install/install.php');
}