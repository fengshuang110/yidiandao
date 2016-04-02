<?php
return array(
	'logs'=>array(
		'path'=>'backup/logs/log',
		'type'=>'file'
	),
	'DB'=>array(
		'type'=>'mysqli',
        'tablePre'=>'pre_',
		'read'=>array(
			array('host'=>'localhost:3306','user'=>'fengshuang','passwd'=>'123456','name'=>'spark_yidiano2o'),
		),

		'write'=>array(
			'host'=>'localhost:3306','user'=>'fengshuang','passwd'=>'123456','name'=>'spark_yidiano2o',
		),
	),
	'interceptor' => array('themeroute@onCreateController','layoutroute@onCreateView','hookCreateAction@onCreateAction','hookFinishAction@onFinishAction'),
	'langPath' => 'language',
	'viewPath' => 'views',
	'skinPath' => 'skin',
    'classes' => 'classes.*',
    'rewriteRule' =>'url',
	'theme' => array('pc' => array('default' => 'default','sysdefault' => 'green','sysseller' => 'green'),'mobile' => array('mobile' => 'default','sysdefault' => 'default','sysseller' => 'default')),
	'timezone'	=> 'Etc/GMT-8',
	'upload' => 'upload',
	'dbbackup' => 'backup/database',
	'safe' => 'cookie',
	'lang' => 'zh_sc',
	'debug'=> false,
	'configExt'=> array('site_config'=>'config/site_config.php'),
	'encryptKey'=>'31d91bd6595589164451dd9388a765bb',
	'authorizeCode' => '',
);
?>