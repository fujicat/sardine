<?php
namespace Fujicat\Sardine\Rdb;

class MysqlException extends \Exception {}

class Mysql extends Core
{
	/**
	 * 接続
	 */
	static protected function _connect( $host, $port, $user, $pass, $database ) {
		if ( !(static::$_db = \mysql_connect("$host:$port", $user, $pass)) ) {
			throw new MysqlException(\mysql_error(static::$_db), \mysql_errno(static::$_db));
		}
		if ( !\mysql_select_db($database, static::$_db) ) {
			throw new MysqlException(\mysql_error(static::$_db), \mysql_errno(static::$_db));
		}
		return true;
	}

	/**
	 * 切断
	 */
	static protected function _disconnect() {
		if ( !\mysql_close(static::$_db) ) {
			throw new MysqlException(\mysql_error(static::$_db), \mysql_errno(static::$_db));
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
	 * テーブル削除
	 */
	static protected function _drop() {
		return static::query(static::_get_drop_sql());
	}

	/**
	 * クエリ実行
	 */
	static protected function _query( $sql ) {
		$result = \mysql_query($sql, static::$_db);
		if ( !$result ) {
			throw new MysqlException(\mysql_error(static::$_db), \mysql_errno(static::$_db));
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
		if ( \mysql_num_rows($result) > 0 ) {
			while ( $rec = \mysql_fetch_assoc($result) ) {
				$records[] = new static($rec, false);
			}
		}
		\mysql_free_result($result);

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
		if ( \mysql_num_rows($result) > 0 ) {
			if ( $rec = \mysql_fetch_assoc($result) ) {
				$record = new static($rec, false);
			}
		}
		\mysql_free_result($result);

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
		if ( \mysql_num_rows($result) > 0 ) {
			while ( $rec = \mysql_fetch_array($result) ) {
				$values[] = $rec[0];
			}
		}
		\mysql_free_result($result);

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
		if ( $rec = \mysql_fetch_array($result) ) {
			$value = $rec[0];
		}
		\mysql_free_result($result);

		return $value;
	}

	/**
	 * AUTO INCREMENTで生成された連番を取得する
	 */
	static protected function _insert_id() {
		return \mysql_insert_id(static::$_db);
	}

	/**
	 * 入力値のサニタイズ
	 * @param mixed $value
	 */
	static protected function _escape( $value ) {
		return \mysql_real_escape_string($value, static::$_db);
	}

	/**
	 * トランザクション開始
	 */
	static protected function _begin() {
		if ( static::query("set autocommit = 0") ) {
			if ( static::query("begin") ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * トランザクションコミット
	 * @return boolean
	 */
	static protected function _commit() {
		if ( static::query("commit") ) {
			return true;
		}
		return false;
	}

	/**
	 * トランザクションロールバック
	 * @return boolean
	 */
	static protected function _rollback() {
		if ( static::query("rollback") ) {
			return true;
		}
		return false;
	}
}