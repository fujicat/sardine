<?php
namespace Fujicat\Sardine;

class Model_Sqlite_User extends Model_Sqlite
{
	static protected $_table_name = 'users';
	static protected $_properties = array(
		'id'			=> array('type'	=> 'int',	'auto_increment'	=> true),
		'name'			=> array('type'	=> 'text',	'default'	=> ''),
		'password'		=> array('type'	=> 'text',	'default'	=> ''),
		'email'			=> array('type'	=> 'text',	'default'	=> ''),
		'birth'			=> array('type'	=> 'int',	'default'	=> 0),
		'sex'			=> array('type'	=> 'int',	'default'	=> 0),
		'phone'			=> array('type'	=> 'text',	'default'	=> ''),
		'reserve1'		=> array('type'	=> 'int',	'default'	=> 999),
		'reserve2'		=> array('type'	=> 'text',	'default'	=> '1999-12-31'),
		'reserve3'		=> array('type'	=> 'text',	'default'	=> 'abcdefg'),
		'created_at'	=> array('type'	=> 'int',	'default'	=> 0),
		'updated_at'	=> array('type'	=> 'int',	'default'	=> 0),
	);
}