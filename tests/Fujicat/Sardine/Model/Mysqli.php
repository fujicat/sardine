<?php
namespace Fujicat\Sardine;

class Model_Mysqli extends Rdb\Mysqli
{
	static public function table_name() {
		return static::$_table_name;
	}

	static public function connect( array $options=array() ) {
		$options = array(
			'host'	=> 'localhost',
			'port'	=> 3306,
			'user'	=> 'root',
			'pass'	=> '',
			'database'	=> 'test',
		);
		return parent::connect($options);
	}
}