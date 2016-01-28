<?php
namespace Fujicat\Sardine\Rdb;

class MysqliException extends \Exception {}

class Mysqli extends Core
{
	/**
	 * 接続
	 */
	static protected function _connect( $host, $port, $user, $pass, $database ) {
		static::$_db = new \mysqli($host, $user, $pass, $database, $port);
		if ( mysqli_connect_error() ) {
			throw new MysqliException(mysqli_connect_error(static::$_db), mysqli_connect_errno(static::$_db));
		}
		return true;
	}

	/**
	 * 切断
	 */
	static protected function _disconnect() {
		if ( !static::$_db->close() ) {
			throw new MysqliException(static::$_db->error, static::$_db->errno);
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
		$result = static::$_db->query($sql);
		if ( !$result ) {
			throw new MysqliException(static::$_db->error, static::$_db->errno);
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
		if ( $result->num_rows > 0 ) {
			while ( $rec = $result->fetch_assoc() ) {
				$records[] = new static($rec, false);
			}
		}
		mysqli_free_result($result);

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
		if ( $result->num_rows > 0 ) {
			if ( $rec = $result->fetch_assoc() ) {
				$record = new static($rec, false);
			}
		}
		mysqli_free_result($result);

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
		if ( $result->num_rows > 0 ) {
			while ( $rec = $result->fetch_array() ) {
				$values[] = $rec[0];
			}
		}
		mysqli_free_result($result);

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
		if ( $rec = $result->fetch_array(MYSQLI_NUM) ) {
			$value = $rec[0];
		}
		mysqli_free_result($result);

		return $value;
	}

	/**
	 * AUTO INCREMENTで生成された連番を取得する
	 */
	static protected function _insert_id() {
		return static::$_db->insert_id;
	}

	/**
	 * 入力値のサニタイズ
	 * @param mixed $value
	 */
	static protected function _escape( $value ) {
		return static::$_db->real_escape_string($value);
	}

	/**
	 * トランザクション開始
	 */
	static protected function _begin() {
		return static::$_db->autocommit(false);
	}

	/**
	 * トランザクションコミット
	 * @return boolean
	 */
	static protected function _commit() {
		return static::$_db->commit();
	}

	/**
	 * トランザクションロールバック
	 * @return boolean
	 */
	static protected function _rollback() {
		return static::$_db->rollback();
	}
}