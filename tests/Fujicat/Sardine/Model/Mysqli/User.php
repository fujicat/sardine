<?php
namespace Fujicat\Sardine;

class Model_Mysqli_User extends Model_Mysqli
{
	static protected $_table_name = 'users';
	static protected $_primary_key = 'id';
	static protected $_created_at = 'created_at';
	static protected $_updated_at = 'updated_at';

	static protected $_properties = array(
		'id'			=> array('type'	=> 'int',			'auto_increment'	=> true),
		'name'			=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'password'		=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'email'			=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'birth'			=> array('type'	=> 'date',			'default'	=> '0000-00-00'),
		'sex'			=> array('type'	=> 'int',			'default'	=> 0),
		'phone'			=> array('type'	=> 'varchar(12)',	'default'	=> ''),
		'reserve1'		=> array('type'	=> 'int',			'default'	=> 999),
		'reserve2'		=> array('type'	=> 'date',			'default'	=> '1999-12-31'),
		'reserve3'		=> array('type'	=> 'text',			'default'	=> 'abcdefg'),
		'created_at'	=> array('type'	=> 'datetime',		'default'	=> '0000-00-00 00:00:00'),
		'updated_at'	=> array('type'	=> 'datetime',		'default'	=> '0000-00-00 00:00:00'),
	);
}