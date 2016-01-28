<?php
namespace Fujicat\Sardine;

class Model_Sqlite extends Rdb\Sqlite
{
	static public function table_name() {
		return static::$_table_name;
	}

	static protected $_database_filename = null;
	static public function database_filename() {
		return static::$_database_filename;
	}

	static public function connect( array $options=array() ) {
		if ( static::$_database_filename === null ) {
			$directory = sys_get_temp_dir();
			static::$_database_filename = tempnam($directory, "sqlite");
		}
		$options = array(
			'filename'		=> static::$_database_filename,
		);
		return parent::connect($options);
	}
}