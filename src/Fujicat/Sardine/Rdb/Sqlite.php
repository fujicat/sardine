<?php
namespace Fujicat\Sardine\Rdb;

class SqliteException extends \Exception {}

class Sqlite extends Core
{
	/**
	 * 接続
	 */
	static public function connect( array $options=array() ) {
		if ( null !== static::$_db ) {
			return true;
		}

		if ( !($filename = $options['filename']) ) {
			throw new SqliteException("You must specify a database file.");
		}
		$flag = isset($options['flag'])? $options['flag'] : SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
		$key  = isset($options['key'])? $options['key'] : null;
		static::$_db = new \SQLite3($filename, $flag, $key);

		return true;
	}

	/**
	 * 切断
	 */
	static protected function _disconnect() {
		if ( !static::$_db->close() ) {
			throw new SqliteException(static::$_db->lastErrorMsg(), static::$_db->lastErrorCode());
		}
		static::$_db = null;
		return true;
	}

	/**
	 * テーブル作成
	 */
	static protected function _create() {
		return static::query(static::_get_create_sql());
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
			$type = static::_get_column_type_name($property['type']);
			$default = static::_get_default_for_create_sql($property);
			$auto_increment = "";
			if ( $name == static::$_primary_key && isset($property['auto_increment']) && true == $property['auto_increment'] ) {
				$auto_increment = "PRIMARY KEY AUTOINCREMENT";
			}
			$sql .= "`{$name}` {$type} {$null} {$default} {$auto_increment},\n";
		}
		$sql = substr($sql, 0, -2)."\n)";
		return $sql;
	}

	/**
	 * データ型名を取得
	 */
	static protected function _get_column_type_name( $type ) {
		$t = static::_get_column_type($type);
		switch ( $t ) {
			case SQLITE3_INTEGER:	return 'INTEGER';
			case SQLITE3_FLOAT:		return 'REAL';
			case SQLITE3_TEXT:		return 'TEXT';
			case SQLITE3_BLOB:		return 'BLOB';
		}
		throw new SqliteException("Type: {$type} is not allowed.");
	}

	/**
	 * データ型を取得
	 */
	static protected function _get_column_type( $type ) {
		$t = strtolower($type);
		switch ( $t ) {
			case 'int':
			case 'integer':
			case 'tinyint':
			case 'bigint':
				return SQLITE3_INTEGER;
		
			case 'float':
			case 'double':
			case 'real':
				return SQLITE3_FLOAT;
		
			case 'char':
			case 'varchar':
			case 'text':
			case 'mediumtext':
			case 'longtext':
				return SQLITE3_TEXT;
		
			case 'longblob':
			case 'blob':
				return SQLITE3_BLOB;

			case null:
				return SQLITE3_NULL;
		}
		
		if ( substr_compare($t, 'varchar', 0, 7) == 0 ) {
			return SQLITE3_TEXT;
		}
		
		throw new SqliteException("Type: {$type} is not allowed.");
	}

	/**
	 * 値から相当するデータ型を取得
	 */
	static protected function _get_value_type( $value ) {
		if ( is_int($value) ) {
			return SQLITE3_INTEGER;
		} else if ( is_float($value) ) {
			return SQLITE3_FLOAT;
		} else if ( is_string($value) ) {
			return SQLITE3_TEXT;
		} else if ( false ) {	// PHP5ではバイナリと文字列の区別が付けられない...
			return SQLITE3_BLOB;
		} else {
			return SQLITE3_NULL;
		}
	}

	/**
	 * テーブル削除
	 */
	static protected function _drop() {
		return static::query(static::_get_drop_sql());
	}

	/**
	 * INSERT前のデータ処理
	 * @param array $vars
	 */
	protected function _pre_save( $vars ) {
		$ret_vars = array();
		foreach ( $vars as $field=>$value ) {
			if ( array_key_exists($field, static::$_properties) ) {
				$property = static::$_properties[$field];
				if ( array_key_exists('json', static::$_properties[$field]) && static::$_properties[$field]['json'] ) {
					$ret_vars[$field] = json_encode($value);
				} else {
					$ret_vars[$field] = $value;
				}
				if ( $field == static::$_primary_key && isset($property['auto_increment']) && true == $property['auto_increment'] ) {
					$ret_vars[$field] = null;
				}
			}
		}
	
		// created_at, updated_at
		$datetime = time();
		if ( array_key_exists(static::$_created_at, static::$_properties) ) {
			$ret_vars[static::$_created_at] = $datetime;
		}
		if ( array_key_exists(static::$_updated_at, static::$_properties) ) {
			$ret_vars[static::$_updated_at] = $datetime;
		}
	
		return $ret_vars;
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
	
		$datetime = time();
		if ( array_key_exists(static::$_updated_at, static::$_properties) ) {
			$ret_vars[static::$_updated_at] = $datetime;
		}
	
		return $ret_vars;
	}

	/**
	 * SQL文にパラメータを割り当てる
	 */
	static protected function _bind( $sql, array $params=array() ) {
		$types = array();
		$parameters = array();
		foreach ( $params as $name=>$value ) {
			if ( is_array($value) ) {
				// IN句はbindValueが対応できないらしいので自前でbindする
				$str = "";
				foreach ( $value as $v ) {
					$str .= "'".static::escape($v)."',";
				}
				$str = substr($str, 0, -1);
				$sql = strtr($sql, array(":{$name}"=>$str));
			}
			else {
				if ( isset(static::$_properties[$name]) ) {
					$types[":{$name}"] = static::_get_column_type(static::$_properties[$name]['type']);
				} else {
					$types[":{$name}"] = static::_get_value_type($value);
				}
				$parameters[":{$name}"] = $value;
			}
		}

		if ( !$params ) {
			return static::$_db->prepare($sql);
		}
		$sql = strtr($sql, array('@'=>':'));
		$stmt = static::$_db->prepare($sql);

		foreach ( $parameters as $name=>$value ) {
			$stmt->bindValue($name, $value, $types[$name]);
		}

		return $stmt;
	}

	/**
	 * クエリ実行
	 */
	static protected function _query( $sql ) {
		if ( get_class($sql) == 'SQLite3Stmt' ) {
			$result = $sql->execute();
		}
		else {
			$str = strtoupper(substr($sql, 0, 6));
			if ( $str == "SELECT" ) {
				$result = static::$_db->query($sql);
			} else {
				$result = static::$_db->exec($sql);
			}
		}
		if ( !$result ) {
			throw new SqliteException(static::$_db->lastErrorMsg(), static::$_db->lastErrorCode());
		}
		return $result;
	}

	/**
	 * レコードの配列を取得する
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	static protected function _find( $sql, array $params=array() ) {
		if ( !($result = static::query($sql, $params)) ) {
			return array();
		}

		$records = array();
		while ( $rec = $result->fetchArray(SQLITE3_ASSOC) ) {
			$records[] = new static($rec, false);
		}

		return $records;
	}

	/**
	 * 1レコードを取得する
	 * @param string $sql
	 * @param array $params
	 */
	static protected function _find_one( $sql, array $params=array() ) {
		if ( !($result = static::query($sql, $params)) ) {
			return false;
		}

		$record = null;
		if ( $rec = $result->fetchArray(SQLITE3_ASSOC) ) {
			$record = new static($rec, false);
		}

		return $record;
	}

	/**
	 * 1カラムの値の配列を取得する
	 * @param string $sql
	 * @param array $params
	 */
	static protected function _find_values( $sql, array $params=array() ) {
		if ( !($result = static::query($sql, $params)) ) {
			return array();
		}

		$values = array();
		while ( $rec = $result->fetchArray(SQLITE3_NUM) ) {
			$values[] = $rec[0];
		}

		return $values;
	}

	/**
	 * 1カラムの値を取得する
	 * @param string $sql
	 * @param array $params
	 */
	static protected function _find_value( $sql, array $params=array() ) {
		if ( !($result = static::query($sql, $params)) ) {
			return false;
		}

		$value = null;
		if ( $rec = $result->fetchArray(SQLITE3_NUM) ) {
			$value = $rec[0];
		}

		return $value;
	}

	/**
	 * AUTO INCREMENTで生成された連番を取得する
	 */
	static protected function _insert_id() {
		return static::$_db->lastInsertRowID();
	}

	/**
	 * 入力値のサニタイズ
	 * @param mixed $value
	 */
	static protected function _escape( $value ) {
		return static::$_db->escapeString($value);
	}

	/**
	 * トランザクション開始
	 */
	static protected function _begin() {
		return true;
	}

	/**
	 * トランザクションコミット
	 * @return boolean
	 */
	static protected function _commit() {
		return true;
	}

	/**
	 * トランザクションロールバック
	 * @return boolean
	 */
	static protected function _rollback() {
		return true;
	}
}