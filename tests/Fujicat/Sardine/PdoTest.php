<?php
namespace Fujicat\Sardine;

class PdoTest extends \PHPUnit_Framework_TestCase
{
	static protected $models = array(
		'Model_Pdo_User',
		'Model_Pdo_MailSendHistory',
	);

	static public function setUpBeforeClass() {
		echo "\n
###########################################
# Begining Test Case: PdoTest...
###########################################\n";
		foreach ( static::$models as $model ) {
			call_user_func(array('Fujicat\\Sardine\\'.$model, 'create'));
			echo "Create table '".call_user_func(array('Fujicat\\Sardine\\'.$model, 'table_name'))."'.\n";
		}
	}

	static public function tearDownAfterClass() {
		echo "\n";
		foreach ( static::$models as $model ) {
			call_user_func(array('Fujicat\\Sardine\\'.$model, 'drop'));
			echo "Drop table '".call_user_func(array('Fujicat\\Sardine\\'.$model, 'table_name'))."'.\n";
		}
		Model_Pdo::disconnect();
		echo "Closed database connection.";

		echo "
###########################################
# Finished Test Case: PdoTest...
###########################################\n";
	}

	protected function parsecsv( $csv, $keys ) {
		$lines = explode("\n", $csv);
		$result = array();
		foreach ( $lines as $line ) {
			if ( ($line = trim($line)) == "" ) {
				continue;
			}
			$values = explode(",", $line);
			$record = array();
			foreach ( $keys as $index=>$key ) {
				$value = trim($values[$index]);
				switch ( $key ) {
					case 'password':	$record[$key] = md5($value);			break;
					case 'sex':			$record[$key] = intval($value);			break;
					default:			$record[$key] = $value;					break;
				}
			}
			$result[] = array($record);
		}
		return $result;
	}

	/**
	 * DBに登録するデータ
	 */
	public function user_provider() {
		$keys = array(
			'name', 	'password', 	'email', 					'birth', 	'sex',	'phone',		'dummy'
		);
		$csv = "
			Takashi,	pass,			takashi1979@gmail.com,		1979-06-19,		1,	010-1234-5678,	hoge
			Takashi,	ssap,			takashi1972@gmail.com,		1972-02-23,		1,	010-2345-6789,	foo
			Keiko,		keiko1966,		keiko1966@gmail.com,		1966-01-16,		2,	020-1234-5678,	bar
			Jaqueline,	jack,			jack1972@gmail.com,			1972-03-09,		2,	090-1111-1111,	1
			Suyeon,		s1990,			suyoen1990_12@gmail.com,	1990-12-02,		2,	080-3623-2589,	2
		";
		return $this->parsecsv($csv, $keys);
	}


	/**
	 * INSERTのテスト
	 * 
	 * @dataProvider user_provider
	 */
	public function test_insert( $user ) {
		$obj = Model_Pdo_User::forge($user)->save();

		// 入力値が正しく保存されている
		$this->assertEquals($obj->name, $user['name']);
		$this->assertEquals($obj->password, $user['password']);
		$this->assertEquals($obj->email, $user['email']);
		$this->assertEquals($obj->birth, $user['birth']);
		$this->assertEquals($obj->sex, $user['sex']);
		$this->assertEquals($obj->phone, $user['phone']);

		// データを登録していないフィールドはデフォルト値が入る
		$this->assertEquals($obj->reserve1, 999);
		$this->assertEquals($obj->reserve2, '1999-12-31');
		$this->assertEquals($obj->reserve3, 'abcdefg');

		// 登録日・更新日のフィールドを定義している場合は自動的に記録される
		$this->assertNotEmpty($obj->created_at);
		$this->assertNotEmpty($obj->updated_at);

		// $_propertiesで定義していないフィールドは登録されない
		$this->assertEquals(isset($obj->null), false);

		// ArrayAccessを実装しているので配列としてもアクセスできる
		$this->assertEquals($obj['name'], $user['name']);
		$this->assertEquals($obj['password'], $user['password']);
		$this->assertEquals($obj['email'], $user['email']);
		$this->assertEquals($obj['birth'], $user['birth']);
		$this->assertEquals($obj['sex'], $user['sex']);
		$this->assertEquals($obj['phone'], $user['phone']);
		$this->assertEquals($obj['reserve1'], 999);
		$this->assertEquals($obj['reserve2'], '1999-12-31');
		$this->assertEquals($obj['reserve3'], 'abcdefg');
		$this->assertNotEmpty($obj['created_at']);
		$this->assertNotEmpty($obj['updated_at']);
		
		// Iteratorを実装しているのでforeachで回せる
		foreach ( $obj as $name=>$value ) {
			if ( isset($user[$name]) ) {
				$this->assertEquals($value, $user[$name]);
			}
		}

		// 複合インデックステーブルへのINSERT
		$hist = Model_Pdo_MailSendHistory::forge(array(
			'user_id'	=> $obj->insert_id(),
			'send_date'	=> date('Y-m-d'),
			'from_addr'	=> $obj->email,
			'to_addr'	=> 'takashi1972@gmail.com',
			'subject'	=> 'メール送信テスト',
			'body'		=> '本文です。',
		))->save();
	}

	/**
	 * 複合インデックスの確認テスト
	 */
	public function test_multi_column_index() {
		$sql = "SHOW INDEX FROM mail_send_history";
		$indexes = Model_Pdo_MailSendHistory::find($sql);

		// 2件のインデックスがある
		$this->assertEquals(count($indexes), 2);
		// インデックス名はどちらもPRIMARY
		$this->assertEquals($indexes[0]['Key_name'], 'PRIMARY');
		$this->assertEquals($indexes[1]['Key_name'], 'PRIMARY');
		// カラムはuser_id, send_dateが指定されている
		$this->assertEquals($indexes[0]['Column_name'], 'user_id');
		$this->assertEquals($indexes[1]['Column_name'], 'send_date');
	}

	/**
	 * メソッド：find のテスト
	 */
	public function test_method_find() {
		// パラメータはSQL文内で:xxxで指定し、値をfindメソッドの第2引数で配列で渡す
		// これによって引き渡されたパラメータはエスケープされでシングルクォートで囲われる
		$sql = "SELECT name, email, sex, birth
				FROM users
				WHERE birth >= :birth
				ORDER BY sex ASC, birth DESC";
		$users = Model_Pdo_User::find($sql, array(
			'birth'	=> '1970-01-01',
		));

		$emails = array(
			'takashi1979@gmail.com',
			'takashi1972@gmail.com',
			'suyoen1990_12@gmail.com',
			'jack1972@gmail.com'
		);
		$cnt = 0;

		foreach ( $users as $user ) {
			// name, email, sex, birthを取得、passwordは取得していないのでデフォルト値の空文字
			$this->assertEquals(is_string($user->name), true);
			$this->assertEquals(is_string($user->email), true);
			$this->assertEquals(is_string($user->sex), true);	// MySQLはintでも文字列に変換して返す
			$this->assertEquals(is_string($user->birth), true);
			$this->assertEquals($user->password, "");
			// 性別昇順、生年月日降順でソートされている
			$this->assertEquals($user->email, $emails[$cnt++]);
		}

		// INのように複数のパラメータをカンマ区切りで指定する場合は、パラメータを配列で渡すことができる
		$emails = array(
			'takashi1972@gmail.com',
			'jack1972@gmail.com',
			'suyoen1990_12@gmail.com',
		);
		$sql = "SELECT * FROM users WHERE email IN (:emails)";
		$users = Model_Pdo_User::find($sql, array(
			'emails'	=> $emails,
		));
		$idx = 0;
		foreach ( $users as $user ) {
			$this->assertEquals($user->email, $emails[$idx++]);
		}

		// LIMITのようにシングルクォートで囲われてはまずいパラメータの場合はSQL文内で@xxxで指定する
		$limit = 2;
		$offset = 0;
		$sql = "SELECT * FROM users LIMIT @offset, @limit";
		$users = Model_Pdo_User::find($sql, array(
			'limit'		=> $limit,
			'offset'	=> $offset,
		));
		$this->assertEquals(count($users), $limit);
	}

	/**
	 * メソッド：find_one_by_xxx のテスト
	 */
	public function test_method_find_one_by_xxx() {
		$user = Model_Pdo_User::find_one_by_name('Jaqueline');
		$this->assertEquals($user->name, 'Jaqueline');
		$this->assertEquals($user->password, md5('jack'));
		$this->assertEquals($user->email, 'jack1972@gmail.com');
		$this->assertEquals($user->sex, 2);
		$this->assertEquals($user->birth, '1972-03-09');
	}

	/**
	 * メソッド：find_one_by のテスト
	 */
	public function test_method_find_one_by() {
		// 通常取得
		$user = Model_Pdo_User::find_one_by('name', 'Jaqueline');
		$this->assertEquals($user->name, 'Jaqueline');
		$this->assertEquals($user->password, md5('jack'));
		$this->assertEquals($user->email, 'jack1972@gmail.com');
		$this->assertEquals($user->sex, 2);
		$this->assertEquals($user->birth, '1972-03-09');

		// 条件にマッチしない場合はnullが返る
		$user = Model_Pdo_User::find_one_by('name', 'Takoyaki');
		$this->assertNull($user);
	}

	/**
	 * メソッド：find_by_xxx のテスト
	 */
	public function test_method_find_by_xxx() {
		// 存在する名前を指定
		$users = Model_Pdo_User::find_by_name('Takashi');
		// 2件取得できる
		$this->assertEquals(count($users), 2);
		// 一人目の誕生日は1979-06-19
		$this->assertEquals($users[0]->birth, '1979-06-19');
		// 二人目の誕生日は1972-02-23
		$this->assertEquals($users[1]->birth, '1972-02-23');

		// 存在しない名前を指定
		$users = Model_Pdo_User::find_by_name('Takoyaki');
		// 0件になる
		$this->assertEquals(count($users), 0);
		// $usersは空の配列
		$this->assertSame($users, array());
	}

	/**
	 * メソッド：find_by のテスト
	 */
	public function test_method_find_by() {
		$users = Model_Pdo_User::find_by('name', 'Takashi');
		$this->assertEquals(count($users), 2);
		$this->assertEquals($users[0]->birth, '1979-06-19');
		$this->assertEquals($users[1]->birth, '1972-02-23');
	}

	/**
	 * UPDATEのテスト
	 */
	public function test_update() {
		$email = "jaqueline-lieon@gmail.com";
		$user = Model_Pdo_User::find_one_by_name('Jaqueline');
		$user->email = $email;		// メールアドレスを変更する
		$user->dummy = "dummy";		// $_propertiesに定義されていないフィールドにデータを登録してみる
		$user->save();				// 更新

		// 改めて取得
		$updated = Model_Pdo_User::find_one_by_name('Jaqueline');
		// メールアドレスが更新されている
		$this->assertEquals($updated->email, $email);
		// $_propertiesで定義していないフィールドは登録されない
		$this->assertEquals(isset($updated->dummy), false);

		// 複合インデックステーブルのテスト(saveメソッドは内部でプライマリキーを条件指定したSQLを実行しているため動作確認)
		$email = "jack1972@gmail.com";
		$hist = Model_Pdo_MailSendHistory::find_one_by_from_addr($email);
		$hist->to_addr = "hogehoge";
		$hist->save();

		// 改めて取得
		$updated = Model_Pdo_MailSendHistory::find_one_by_from_addr($email);
		$this->assertEquals($updated->to_addr, "hogehoge");
	}

	/**
	 * DELETEのテスト
	 */
	public function test_delete() {
		// 取得してから削除する
		$user = Model_Pdo_User::find_one_by_name('Keiko');
		$result = $user->delete();

		// 削除したのでnull
		$user = Model_Pdo_User::find_one_by_name('Keiko');
		$this->assertNull($user);

		// 複合インデックステーブルのテスト(deleteメソッドは内部でプライマリキーを条件指定したSQLを実行しているため動作確認)
		$email = "jack1972@gmail.com";
		$hist = Model_Pdo_MailSendHistory::find_one_by_from_addr($email);
		$result = $hist->delete();

		// 削除したのでnull
		$updated = Model_Pdo_MailSendHistory::find_one_by_from_addr($email);
		$this->assertNull($updated);
	}
}