<?php
namespace Fujicat\Sardine\Rdb;

class Core implements  \Iterator, \ArrayAccess
{
	/**
	 * @var  string  $_table_name  The table name (must set this in your Model)
	 */
	static protected $_table_name = '';

	/**
	 * @var  array  $_properties  The table column names (must set this in your Model to use)
	 */
	static protected $_properties = array();

	/**
	 * @var  string  $_primary_key  The primary key for the table
	 */
	static protected $_primary_key = 'id';

	/**
	 * @var  string  $_created_at  fieldname of created_at field
	 */
	static protected $_created_at = 'created_at';

	/**
	 * @var  string  $_updated_at  fieldname of updated_at field
	 */
	static protected $_updated_at = 'updated_at';



	/**
	 * @var resource $_db  The instance of database connection
	 */
	static protected $_db = null;

	/**
	 * @var string $_query  The query string last execution
	 */
	static protected $_query = null;

	/**
	 * 最後に実行したSQL文を取得する
	 */
	static public function get_last_query() {
		return static::$_query;
	}

	/**
	 * データ型に対するデフォルト値を取得する
	 * @param string $type データ型
	 * @return string|number
	 */
	static public function default_value( $type ) {
		if ( preg_match("/^(.*?)\([0-9].*?\)$/", $type, $matches) ) {
			$type = $matches[1];
		}
		switch ( strtolower($type) ) {
			case 'char':
			case 'varchar':
			case 'text':
			case 'mediumtext':
			case 'longtext':
			case 'longblob':
			case 'blob':
				return '';		

			case 'date':
				return '0000-00-00';
			case 'datetime':
				return '0000-00-00 00:00:00';

			case 'tinyint':
			case 'int':
			case 'bigint':
			case 'decimal':
			case 'timestamp':
			default:
				return 0;
		}
	}



	/********************************************************************************************************
	 *
	* インスタンスメソッド
	*
	*******************************************************************************************************/

	/**
	 * データベース接続
	 * @param array $options  array('host':ホスト名, 'port:ポート番号, 'user':ユーザー名, 'pass':パスワード, 'database':DB名)
	 * @return boolean
	 */
	static public function connect( array $options=array() ) {
		if ( null !== static::$_db ) {
			return true;
		}

		$host = isset($options['host'])? $options['host'] : 'localhost';
		$port = isset($options['port'])? $options['port'] : '3306';
		$user = isset($options['user'])? $options['user'] : 'root';
		$pass = isset($options['pass'])? $options['pass'] : '';
		if ( !isset($options['database']) ) {
			return false;
		}
		$database = $options['database'];

		return static::_connect($host, $port, $user, $pass, $database);
	}
	static protected function _connect( $host, $port, $user, $pass, $database ){}

	/**
	 * 切断
	 */
	static public function disconnect() {
		if ( null === static::$_db ) {
			return false;
		}
		return static::_disconnect();
	}
	static protected function _disconnect(){}

	/**
	 * テーブル作成
	 */
	static public function create() {
		return static::_create();
	}
	static protected function _create(){}

	/**
	 * テーブル削除
	 */
	static public function drop() {
		return static::_drop();
	}
	static protected function _drop(){}

	/**
	 * インスタンス生成
	 * @param array $data
	 * @param boolena $new
	 */
	static public function forge( array $data=array(), $new=true ) {
		return new static($data, $new);
	}

	/**
	 * テーブルの実定義とモデルのプロパティを比較して差異を取得する
	 */
	static public function check_schema() {
		if ( !static::$_table_name ) {
			return false;
		}

		// 実定義
		$sql = "DESC ".static::$_table_name;
		if ( !($desc = static::find($sql)) ) {
			return false;
		}
		$database = array();
		foreach ( $desc as $rec ) {
			$type = $rec->Type;
			if ( substr($type, 0, 4) != 'char' && substr($type, 0, 7) != 'varchar' ) {
				if ( false !== ($pos = strpos($type, '(')) ) {
					$type = substr($type, 0, $pos);
				}
			}
			$database[$rec->Field] = array(
				'type'				=> $type,
				'default'			=> $rec->Default,
			);
			if ( $rec->Extra == 'auto_increment' ) {
				$database[$rec->Field]['auto_increment'] = true;
				unset($database[$rec->Field]['default']);
			}
		}
		ksort($database);

		// モデル定義
		$properties = static::$_properties;
		ksort($properties);

		$result = array();

		// フィールドの存在比較
		foreach ( $database as $field=>$def ) {
			if ( !isset($properties[$field]) ) {
				$result[] = "Field: {$field} does not exist in the model property.";
			}
		}
		foreach ( $properties as $field=>$def ) {
			if ( !isset($database[$field]) ) {
				$result[] = "Field: {$field} does not exist in the real table.";
			}
		}

		// フィールドの定義の比較
		foreach ( $properties as $field=>$def ) {
			if ( isset($database[$field]) ) {
				// データ型
				if ( isset($def['type']) && ($def['type'] != $database[$field]['type']) ) {
					$result[] = "In field: {$field}, type of field is different, model table: {$def['type']}, real table: {$database[$field]['type']}.";
				}
				// デフォルト値
				if ( isset($def['default']) && ($def['default'] != $database[$field]['default']) ) {
					$result[] = "In field: {$field}, default value is different, model table: {$def['default']}, real table: {$database[$field]['default']}.";
				}
				// auto increment
				if ( isset($def['auto_increment']) && !isset($database[$field]['auto_increment']) ||
					 !isset($def['auto_increment']) && isset($database[$field]['auto_increment']) ) {
					$m = isset($def['auto_increment'])? 'defined' : 'undefined';
					$d = isset($database[$field]['auto_increment'])? 'defined' : 'undefined';
					$result[] = "In field: {$field}, auto_increment definition is different, model table: {$m}, real table: {$d}.";
				}
			}
		}

		return $result;
	}

	/**
	 * SQLにパラメータを割り当てる
	 * @param string $sql
	 * @param array $params
	 */
	static protected function _bind( $sql, array $params=array() ) {
		if ( !$params ) {
			return $sql;
		}

		$parameters = array();
		foreach ( $params as $name=>$value ) {
			if ( is_array($value) ) {
				// 配列の場合はINの条件式と仮定してカンマ区切りに変換する
				$values = array();
				foreach ( $value as $v ) {
					$values[] = "'".static::escape($v)."'";
				}
				$parameters[":{$name}"] = implode(",", $values);

				$values = array();
				foreach ( $value as $v ) {
					$values[] = static::escape($v);
				}
				$parameters["@{$name}"] = implode(",", $values);
			} else {
				$parameters[":{$name}"] = "'".static::escape($value)."'";
				$parameters["@{$name}"] = static::escape($value);
			}
		}

		return strtr($sql, $parameters);
	}

	/**
	 * クエリ実行
	 * @param string $sql
	 * @param array $params
	 */
	static public function query( $sql, array $params=array() ) {
		if ( !static::connect() ) {
			return false;
		}
		if ( false !== strpos(strtoupper($sql), "FOUND_ROWS()") ) {
			return static::_query(static::_bind($sql, $params));
		}
		static::$_query = static::_bind($sql, $params);
		return static::_query(static::$_query);
	}
	static protected function _query( $sql ){}

	/**
	 * レコードの配列を取得する
	 * @param string $sql
	 * @param array $params
	 */
	static public function find( $sql, array $params=array() ) {
		return static::_find($sql, $params);
	}
	static protected function _find( $sql, array $params=array() ){}

	/**
	 * カラム値で検索してレコードの配列を取得する
	 * @param string $name  カラム名
	 * @param mixed  $value 検索値
	 */
	static public function find_by( $name, $value ) {
		$table = static::$_table_name;
		$name = static::escape($name);
		$sql = "SELECT * FROM {$table} WHERE {$name} = :value";
		return static::find($sql, array(
			'value'	=> $value,
		));
	}

	/**
	 * 1レコードを取得する
	 * @param string $sql
	 * @param array $params
	 */
	static public function find_one( $sql, array $params=array() ) {
		return static::_find_one($sql, $params);
	}
	static protected function _find_one( $sql, array $params=array() ){}

	/**
	 * カラム値で検索して1レコードを取得する
	 * @param string $name  カラム名
	 * @param mixed  $value 検索値
	 */
	static public function find_one_by( $name, $value ) {
		$table = static::$_table_name;
		$name = static::escape($name);
		$sql = "SELECT * FROM {$table} WHERE {$name} = :value LIMIT 1";
		return static::find_one($sql, array(
			'value'	=> $value,
		));
	}

	/**
	 * find_by_xxx, find_one_by_xxx の処理
	 */
	public static function __callStatic( $name, $args ) {
		if ( strncmp($name, 'find_by_', 8) === 0 ) {
			return static::find_by(substr($name, 8), reset($args));
		}
		elseif ( strncmp($name, 'find_one_by_', 12) === 0 ) {
			return static::find_one_by(substr($name, 12), reset($args));
		}
		throw new \BadMethodCallException('Method "'.$name.'" does not exist.');
	}

	/**
	 * 1カラムの値の配列を取得する
	 * @param string $sql
	 * @param array $params
	 */
	static public function find_values( $sql, array $params=array() ) {
		return static::_find_values($sql, $params);
	}
	static protected function _find_values( $sq, array $params=array() ){}

	/**
	 * 1カラムの値を取得する
	 * @param string $sql
	 * @param array $params
	 */
	static public function find_value( $sql, array $params=array() ) {
		return static::_find_value($sql, $params);
	}
	static protected function _find_value( $sql, array $params=array() ){}

	/**
	 * INSERT文を実行した際のAUTO INCREMENT値を取得する
	 * @return int 最後にINSERTした際のAUTO INCREMENT値
	 */
	static public function insert_id() {
		if ( !static::connect() ) {
			return false;
		}
		return static::_insert_id();
	}
	static protected function _insert_id(){}

	/**
	 * 入力値のサニタイズ
	 * @param mixed $value
	 */
	static public function escape( $value ) {
		if ( !static::connect() ) {
			return false;
		}
		return static::_escape($value);
	}
	static protected function _escape( $value ){}

	/**
	 * トランザクション開始
	 */
	static public function begin() {
		if ( !static::connect() ) {
			return false;
		}
		return static::_begin();
	}
	static protected function _begin(){}

	/**
	 * トランザクションコミット
	 * @return boolean
	 */
	static public function commit() {
		if ( !static::connect() ) {
			return false;
		}
		return static::_commit();
	}
	static protected function _commit(){}

	/**
	 * トランザクションロールバック
	 * @return boolean
	 */
	static public function rollback() {
		if ( !static::connect() ) {
			return false;
		}
		return static::_rollback();
	}
	static protected function _rollback(){}




	/********************************************************************************************************
	 * 
	 * インスタンスメソッド
	 * 
	 *******************************************************************************************************/

	protected $_is_new = true;
	protected $_is_deleted = false;

	/**
	 * コンストラクタ
	 */
	public function __construct( array $data=array(), $new=true ) {
		// デフォルト値のセット
		foreach ( static::$_properties as $field => $property ) {
			if ( isset($property['default']) ) {
				$this->{$field} = $property['default'];
			} else {
				$this->{$field} = static::default_value($property['type']);
			}
		}

		// データのセット
		if ( !empty($data) ) {
			foreach ( $data as $field=>$value ) {
				$this->{$field} = $value;
			}
		}
		$this->is_new($new);
	}

	/**
	 * セッター
	 */
	public function __set( $field, $value ) {
		$this->{$field} = $value;
	}

	/**
	 * ゲッター
	 */
	public function & __get( $field ) {
		$result = isset($this->{$field})? $this->{$field} : null;
		return $result;
	}

	/**
	 * 一括登録
	 * @param array $data
	 */
	public function set( array $data ) {
		foreach ( $data as $field=>$value ) {
			$this->{$field} = $value;
		}
		return $this;
	}

	public function is_new( $new=null ) {
		if ( $new === null ) {
			return $this->_is_new;
		}
		$this->_is_new = (bool)$new;
		return $this;
	}

	public function is_deleted( $deleted=null ) {
		if ( $deleted === null ) {
			return $this->_is_deleted;
		}
		$this->_is_deleted = (bool)$deleted;
		return $this;
	}

	/**
	 * データ保存
	 */
	public function save() {
		// 削除済みオブジェクトの場合は処理せず
		if ( $this->is_deleted() ) {
			return false;
		}

		$vars = $this->to_array();

		// INSERTの場合
		if ( $this->is_new() ) {
			// INSERT前のデータ処理
			$vars = $this->_pre_save($vars);

			// INSERT処理
			$sql = $this->_get_insert_sql($vars);
			if ( !static::query($sql) ) {
				return false;
			}
			$this->is_new(false);

			// INSERT後のデータ処理
			$vars = $this->_post_save($vars);
		}

		// UPDATEの場合
		else {
			// UPDATE前のデータ処理
			$vars = $this->_pre_update($vars);

			// UPDATE処理
			$sql = $this->_get_update_sql($vars);
			if ( !static::query($sql) ) {
				return false;
			}

			// UPDATE後のデータ処理
			$vars = $this->_post_update($vars);
		}

		return $this->set($vars);
	}

	/**
	 * データ削除
	 */
	public function delete() {
		if ( $this->is_deleted() || $this->is_new() ) {
			return false;
		}

		// DELETE処理
		$sql = $this->_get_delete_sql();
		if ( !static::query($sql) ) {
			return false;
		}

		// 変数解放
		$vars = $this->to_array();
		foreach ( $vars as $field=>$value ) {
			unset($this->{$field});
		}

		// 削除済みフラグON
		$this->is_deleted(true);

		return $this;
	}

	/**
	 * INSERT前のデータ処理
	 * @param array $vars
	 */
	protected function _pre_save( $vars ) {
		$ret_vars = array();
		foreach ( $vars as $field=>$value ) {
			if ( array_key_exists($field, static::$_properties) ) {
				if ( array_key_exists('json', static::$_properties[$field]) && static::$_properties[$field]['json'] ) {
					$ret_vars[$field] = json_encode($value);
				} else {
					$ret_vars[$field] = $value;
				}
			}
		}

		// created_at, updated_at
		$datetime = date('Y-m-d H:i:s');
		if ( array_key_exists(static::$_created_at, static::$_properties) ) {
			$ret_vars[static::$_created_at] = $datetime;
		}
		if ( array_key_exists(static::$_updated_at, static::$_properties) ) {
			$ret_vars[static::$_updated_at] = $datetime;
		}

		return $ret_vars;
	}

	/**
	 * INSERT後のデータ処理
	 * @param array $vars
	 */
	protected function _post_save( $vars ) {
		// プライマリキーがauto incrementの場合は、発行された番号を取得
		if ( !is_array(static::$_primary_key) &&
				isset(static::$_properties[static::$_primary_key]['auto_increment']) &&
				static::$_properties[static::$_primary_key]['auto_increment'] == true ) {
			$vars[static::$_primary_key] = static::insert_id();
		}

		// json形式のフィールドを配列に戻す
		foreach ( $vars as $field=>$value ) {
			if ( array_key_exists($field, static::$_properties) ) {
				if ( array_key_exists('json', static::$_properties[$field]) && static::$_properties[$field]['json'] ) {
					$vars[$field] = json_decode($value, true);
				}
			}
		}

		return $vars;
	}

	/**
	 * UPDATE前のデータ処理
	 * @param array $vars
	 */
	protected function _pre_update( $vars ) {
		$ret_vars = array();
		foreach ( $vars as $field=>$value ) {
			if ( $field == static::$_primary_key ) {
				continue;
			}
			if ( array_key_exists($field, static::$_properties) ) {
				if ( array_key_exists('json', static::$_properties[$field]) && static::$_properties[$field]['json'] ) {
					$ret_vars[$field] = json_encode($value);
				} else {
					$ret_vars[$field] = $value;
				}
			}
		}

		$datetime = date('Y-m-d H:i:s');
		if ( array_key_exists(static::$_updated_at, static::$_properties) ) {
			$ret_vars[static::$_updated_at] = $datetime;
		}

		return $ret_vars;
	}

	/**
	 * UPDATE後のデータ処理
	 * @param array $vars
	 */
	protected function _post_update( $vars ) {
		// json形式のフィールドを配列に戻す
		foreach ( $vars as $field=>$value ) {
			if ( array_key_exists($field, static::$_properties) ) {
				if ( array_key_exists('json', static::$_properties[$field]) && static::$_properties[$field]['json'] ) {
					$vars[$field] = json_decode($value, true);
				}
			}
		}

		return $vars;
	}

	/**
	 * CREATEのSQL文を生成する
	 */
	static protected function _get_create_sql() {
		$sql = "CREATE TABLE `".static::$_table_name."` (\n";
		foreach ( static::$_properties as $name=>$property ) {
			$null = "NOT NULL";
			if ( isset($property['null']) && true === $property['null'] ) {
				$null = "NULL";
			}
			$default = static::_get_default_for_create_sql($property);
			$auto_increment = "";
			if ( isset($property['auto_increment']) && true == $property['auto_increment'] ) {
				$auto_increment = "AUTO_INCREMENT";
			}
			$sql .= "`{$name}` {$property['type']} {$null} {$default} {$auto_increment},\n";
		}
		if ( is_array(static::$_primary_key) ) {
			$sql .= "PRIMARY KEY (`".implode("`,`", static::$_primary_key)."`)\n";
		} else {
			$sql .= "PRIMARY KEY (`".static::$_primary_key."`)\n";
		}
		$sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
		return $sql;
	}
	static protected function _get_default_for_create_sql( $property ) {
		// text/blobはデフォルト値を定義できない
		switch ( strtolower($property['type']) ) {
			case 'text':
			case 'mediumtext':
			case 'longtext':
			case 'longblob':
			case 'blob':
				return '';
		}

		if ( !isset($property['default']) ) {
			if ( isset($property['auto_increment']) && true == $property['auto_increment'] ) {
				return '';
			}
			$default = static::default_value($property['type']);
		} else {
			$default = $property['default'];
		}
		if ( is_string($default) ) {
			return "DEFAULT '{$default}'";
		}
		return "DEFAULT {$default}";
	}

	/**
	 * DROP TABLEのSQL文を生成する
	 */
	static protected function _get_drop_sql() {
		return "DROP TABLE IF EXISTS ".static::$_table_name;
	}

	/**
	 * INSERTのSQL文を生成する
	 * @return string INSERTのSQL
	 */
	protected function _get_insert_sql( $vars ) {
		$sql = "INSERT INTO ".static::$_table_name." (";

		// カラム名の列挙
		foreach ( $vars as $field=>$value ) {
			// 定義に存在しないカラムは含めない
			if ( !isset(static::$_properties[$field]) ) {
				continue;
			}
			$sql .= "`".$field."`,";
		}

		// 値
		$sql = substr($sql, 0, -1).") VALUES (";
		foreach ( $vars as $field=>$value ) {
			// 定義に存在しないカラムは含めない
			if ( !isset(static::$_properties[$field]) ) {
				continue;
			}

			if ( null === $value ) {
				// null値の場合は'NULL'
				$sql .= "NULL,";
			} else {
				// エスケープ処理
				$sql .= "'".static::escape($value)."',";
			}
		}
		$sql = substr($sql, 0, -1).")";
		return $sql;
	}
	
	/**
	 * UPDATEのSQL文を生成する
	 * @return string UPDATEのSQL
	 */
	protected function _get_update_sql( $vars ) {
		$sql = "UPDATE ".static::$_table_name." SET ";

		// 更新値
		foreach ( $vars as $field=>$value ) {
			// 定義に存在しないカラムは含めない
			if ( !isset(static::$_properties[$field]) ) {
				continue;
			}

			if ( null === $value ) {
				// null値の場合は'NULL'
				$sql .= "`".$field."`=NULL,";
			} else {
				// エスケープ処理
				$sql .= "`".$field."`='".static::escape($value)."',";
			}
		}
		return substr($sql, 0, -1) . $this->_get_where();
	}
	
	/**
	 * DELETEのSQL文を生成する
	 * @return string DELETEのSQL
	 */
	protected function _get_delete_sql() {
		return "DELETE FROM ".static::$_table_name ." ". $this->_get_where();
	}

	/**
	 * UPDATE/DELETEのWHERE句を生成する
	 * @return string WHERE句
	 */
	 protected function _get_where() {
		$sql = "";

		// 複合キーの場合
		if ( is_array(static::$_primary_key) ) {
			$connector = "WHERE";
			foreach ( static::$_primary_key as $field ) {
				$value = static::escape($this->{$field});
				$sql .= " $connector $field='$value'";
				if ( $connector == "WHERE" ) {
					$connector = "AND";
				}
			}
		}

		// 単一キーの場合
 		else {
			$value = static::escape($this->{static::$_primary_key});
			$sql .= " WHERE ".static::$_primary_key."='$value'";
		}

		return $sql;
	 }



	/*****************************************************************************************
	 * 
	 * for Iterator implements
	 * 
	 ****************************************************************************************/

	protected $_iterable = array();
	public function rewind() {
		$this->_iterable = $this->to_array();
		reset($this->_iterable);
	}
	public function current() {
		return current($this->_iterable);
	}
	public function key() {
		return key($this->_iterable);
	}
	public function next() {
		return next($this->_iterable);
	}
	public function valid() {
		return key($this->_iterable) !== null;
	}
	public function to_array() {
		return get_object_vars($this);
	}

	/*****************************************************************************************
	 * 
	 * for ArrayAccess implements
	 * 
	*****************************************************************************************/

	public function offsetSet( $offset, $value ) {
		$this->{$offset} = $value;
	}
	public function offsetExists( $offset ) {
		return isset($this->{$offset});
	}
	public function offsetUnset( $offset ) {
		unset($this->{$offset});
	}
	public function offsetGet( $offset ) {
		if ( isset($this->{$offset}) ) {
			return $this->{$offset};
		}
		throw new Exception('Property "'.$offset.'" not found for '.get_called_class());
	}
}