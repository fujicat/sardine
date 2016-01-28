<?php
namespace Fujicat\Sardine;

class Model_Mysqli_MailSendHistory extends Model_Mysqli
{
	static protected $_table_name = 'mail_send_history';
	static protected $_primary_key = array('user_id', 'send_date');	// 複合インデックスは配列で指定
	static protected $_created_at = 'created_at';

	static protected $_properties = array(
		'user_id'		=> array('type'	=> 'int',			'default'	=> 0),
		'send_date'		=> array('type'	=> 'date',			'default'	=> '0000-00-00'),
		'from_addr'		=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'to_addr'		=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'subject'		=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'body'			=> array('type'	=> 'varchar(255)',	'default'	=> ''),
		'created_at'	=> array('type'	=> 'datetime',		'default'	=> '0000-00-00 00:00:00'),
	);
}