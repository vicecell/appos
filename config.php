<?php
define( "DB_HOST", "localhost" );
define( "DB_UID", "root" );
define( "DB_PWD", "" );
define( "DB_DATABASE", "bacaleg" );

define( "DB_HOST2", "localhost" );
define( "DB_UID2", "radesa_user" );
define( "DB_PWD2", "" );
define( "DB_DATABASE2", "radesa" );

define( "DB_HOST3", "localhost" );
define( "DB_UID3", "root" );
define( "DB_PWD3", "" );
define( "DB_DATABASE3", "ompkb" );

date_default_timezone_set("Asia/Bangkok");


define( "ROOT_PATH", "d:/xampp/htdocs/bacaleg" );
define( "ROOT_URL", "http://localhost/bacaleg" );




$search['BAD']=array('select','insert','update','delete','tbl_');
$replace['BAD']=array('','','','','');

$arrJd=array('1'=>'Struktur','2'=>'Relawan','3'=>'Bacaleg RI','4'=>'Bacaleg Provinsi');

$arNreg=array('1'=>'DPR-RI','2'=>'DPRD1','3'=>'DPRD2');
$arKabDapil=array('Dapil I',
'Dapil II',
'Dapil III',
'Dapil IV',
'Dapil V',
'Dapil VI',
'Dapil VII',
'Dapil VIII',
'Dapil IX',
'Dapil X',
'Dapil XI',
'Dapil XII',
'Dapil XIII',
'Dapil XIV',
'Dapil XV');

$arrSTMember = array(
	'0' => '-',
	'1' => 'BMS',
	'2' => 'Revisi',
	'4' => 'MS'
);
?>
