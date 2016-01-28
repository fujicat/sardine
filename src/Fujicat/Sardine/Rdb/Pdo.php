<?php
namespace Fujicat\Sardine\Rdb;

class PdoException extends \Exception {}

class Pdo extends Core
{
	/**
	 * 接続
	 */
	static protected function _connect( $host, $port, $user, $pass, $database ) {
		try {
			static::$_db = new \PDO('mysql:dbname='.$database.';host='.$host.';port='.$port, $user, $pass);
		} catch ( \PDOException $e ) {
			throw new PdoException($e->getMessage(), $e->getCode());
		}
		return true;
	}

	/**
	 * 切断
	 */
	static protected function _disconnect() {
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
			list($sql_err_code, $err_code, $err_message) = static::$_db->errorInfo();
			throw new PdoException($err_message, $err_code);
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
		while ( $rec = $result->fetch(\PDO::FETCH_ASSOC) ) {
			$records[] = new static($rec, false);
		}
		$result = null;

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
		if ( $rec = $result->fetch(\PDO::FETCH_ASSOC) ) {
			$record = new static($rec, false);
		}
		$result = null;

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
		while ( $result->fetch(\PDO::FETCH_ASSOC) ) {
			$values[] = $rec[0];
		}
		$result = null;

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
		if ( $rec = $result->fetch(\PDO::FETCH_NUM) ) {
			$value = $rec[0];
		}
		$result = null;

		return $value;
	}

	/**
	 * AUTO INCREMENTで生成された連番を取得する
	 */
	static protected function _insert_id() {
		return static::$_db->lastInsertId();
	}

	/**
	 * 入力値のサニタイズ
	 * @param mixed $value
	 */
	static protected function _escape( $value ) {
		return static::$_db->quote($value);
	}

	/**
	 * トランザクション開始
	 */
	static protected function _begin() {
		return static::$_db->beginTransaction();
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