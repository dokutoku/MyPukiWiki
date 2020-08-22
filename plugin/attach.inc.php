<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// attach.inc.php
// Copyright
//   2003-2020 PukiWiki Development Team
//   2002-2003 PANDA <panda@arino.jp> http://home.arino.jp/
//   2002      Y.MASUI <masui@hisec.co.jp> http://masui.net/pukiwiki/
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// File attach plugin

// NOTE (PHP > 4.2.3):
//    This feature is disabled at newer version of PHP.
//    Set this at php.ini if you want.
// Max file size for upload on PHP (PHP default: 2MB)
ini_set('upload_max_filesize', '2M');

// Max file size for upload on script of PukiWikiX_FILESIZE
// default: 1MB
define('PLUGIN_ATTACH_MAX_FILESIZE', 1024 * 1024);

// 管理者だけが添付ファイルをアップロードできるようにする
// false or true
define('PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY', true);

// 管理者だけが添付ファイルを削除できるようにする
// false or true
define('PLUGIN_ATTACH_DELETE_ADMIN_ONLY', true);

// 管理者が添付ファイルを削除するときは、バックアップを作らない
// PLUGIN_ATTACH_DELETE_ADMIN_ONLY=TRUEのとき有効
// false or true
define('PLUGIN_ATTACH_DELETE_ADMIN_NOBACKUP', false);

// アップロード/削除時にパスワードを要求する(ADMIN_ONLYが優先)
// false or true
define('PLUGIN_ATTACH_PASSWORD_REQUIRE', false);

// 添付ファイル名を変更できるようにする
// false or true
define('PLUGIN_ATTACH_RENAME_ENABLE', true);

// ファイルのアクセス権
define('PLUGIN_ATTACH_FILE_MODE', 0644);

// for XREA.COM
//define('PLUGIN_ATTACH_FILE_MODE', 0604);

// File icon image
define('PLUGIN_ATTACH_FILE_ICON', '<img src="'.IMAGE_DIR.'file.png" width="20" height="20" alt="file" style="border-width:0" decoding="async" />');

// mime-typeを記述したページ
define('PLUGIN_ATTACH_CONFIG_PAGE_MIME', 'plugin/attach/mime-type');

//-------- convert
function plugin_attach_convert(string ...$args) : string
{
	global $vars;

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	$noform = false;
	$nolist = false;

	if (func_num_args() > 0) {
		foreach ($args as $arg) {
			$arg = strtolower($arg);
			$nolist |= ($arg == 'nolist');
			$noform |= ($arg == 'noform');
		}
	}

	$ret = '';

	if (!$nolist) {
		$obj = new AttachPages($page);
		$ret .= $obj->toString($page, true);
	}

	if (!$noform) {
		$ret .= attach_form($page);
	}

	return $ret;
}

//-------- action
function plugin_attach_action() : array
{
	global $vars;
	global $_attach_messages;

	// Backward compatible
	if (isset($vars['openfile'])) {
		$vars['file'] = $vars['openfile'];
		$vars['pcmd'] = 'open';
	}

	if (isset($vars['delfile'])) {
		$vars['file'] = $vars['delfile'];
		$vars['pcmd'] = 'delete';
	}

	$pcmd = (isset($vars['pcmd'])) ? ($vars['pcmd']) : ('');
	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$pass = (isset($vars['pass'])) ? ($vars['pass']) : (null);
	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if (($refer === '') && ($page !== '')) {
		$refer = $page;
	}

	if (($refer != '') && (is_pagename($refer))) {
		if (in_array($pcmd, ['info', 'open', 'list'], true)) {
			check_readable($refer);
		} else {
			check_editable($refer);
		}
	}

	// Dispatch
	if (isset($_FILES['attach_file'])) {
		// Upload
		return attach_upload($_FILES['attach_file'], $refer, $pass);
	} else {
		switch ($pcmd) {
			case 'delete':
			case 'freeze':
			case 'unfreeze':
				if (PKWK_READONLY) {
					die_message('PKWK_READONLY prohibits editing');
				}

				break;

			default:
				break;
		}

		switch ($pcmd) {
			case 'info':
				return attach_info();

			case 'delete':
				return attach_delete();

			case 'open':
				return attach_open();

			case 'list':
				return attach_list();

			case 'freeze':
				return attach_freeze(true);

			case 'unfreeze':
				return attach_freeze(false);

			case 'rename':
				return attach_rename();

			case 'upload':
				return attach_showform();

			default:
				break;
		}

		if (($page == '') || (!is_page($page))) {
			return attach_list();
		} else {
			return attach_showform();
		}
	}
}

//-------- call from skin
function attach_filelist() : string
{
	global $vars;
	global $_attach_messages;

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	$obj = new AttachPages($page, 0);

	if (!isset($obj->pages[$page])) {
		return '';
	} else {
		return $_attach_messages['msg_file'].': '.$obj->toString($page, true)."\n";
	}
}

//-------- 実体
// ファイルアップロード
// $pass = null : パスワードが指定されていない
// $pass = true : アップロード許可
function attach_upload(array $file, string $page, $pass = null) : array
{
	global $_attach_messages;
	global $notify;
	global $notify_subject;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	// Check query-string
	$query = 'plugin=attach&amp;pcmd=info&amp;refer='.rawurlencode($page).'&amp;file='.rawurlencode($file['name']);

	if ((PKWK_QUERY_STRING_MAX) && (strlen($query) > PKWK_QUERY_STRING_MAX)) {
		pkwk_common_headers();
		echo 'Query string (page name and/or file name) too long';

		exit;
	} elseif (!is_page($page)) {
		die_message('No such page');
	} elseif (($file['tmp_name'] == '') || (!is_uploaded_file($file['tmp_name']))) {
		return ['result'=>false];
	} elseif ($file['size'] > PLUGIN_ATTACH_MAX_FILESIZE) {
		return ['result'=>false, 'msg'=>$_attach_messages['err_exceed']];
	} elseif ((!is_pagename($page)) || (($pass !== true) && (!is_editable($page)))) {
		return ['result'=>false, 'msg'=>$_attach_messages['err_noparm']];
	} elseif ((PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY) && ($pass !== true) && (($pass === null) || (!pkwk_login($pass)))) {
		return ['result'=>false, 'msg'=>$_attach_messages['err_adminpass']];
	}

	$obj = new AttachFile($page, $file['name']);

	if ($obj->exist) {
		return ['result'=>false, 'msg'=>$_attach_messages['err_exists']];
	}

	if (move_uploaded_file($file['tmp_name'], $obj->filename)) {
		chmod($obj->filename, PLUGIN_ATTACH_FILE_MODE);
	}

	if (is_page($page)) {
		pkwk_touch_file(get_filename($page));
	}

	$obj->getstatus();
	$obj->status['pass'] = (($pass !== true) && ($pass !== null)) ? (md5($pass)) : ('');
	$obj->putstatus();

	if ($notify) {
		$footer['ACTION'] = 'File attached';
		$footer['FILENAME'] = $file['name'];
		$footer['FILESIZE'] = $file['size'];
		$footer['PAGE'] = $page;

		// MD5 may heavy
		$footer['URI'] = get_base_uri(PKWK_URI_ABSOLUTE).'?plugin=attach&refer='.rawurlencode($page).'&file='.rawurlencode($file['name']).'&pcmd=info';

		$footer['USER_AGENT'] = true;
		$footer['REMOTE_ADDR'] = true;

		if (!pkwk_mail_notify($notify_subject, "\n", $footer)) {
			die('pkwk_mail_notify(): Failed');
		}
	}

	return ['result'=>true, 'msg'=>$_attach_messages['msg_uploaded']];
}

// 詳細フォームを表示
function attach_info(string $err = '') : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$file = (isset($vars['file'])) ? ($vars['file']) : ('');
	$age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ((int) ($vars['age'])) : (0);

	$obj = new AttachFile($refer, $file, $age);

	return ($obj->getstatus()) ? ($obj->info($err)) : (['msg'=>$_attach_messages['err_notfound']]);
}

// 削除
function attach_delete() : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$file = (isset($vars['file'])) ? ($vars['file']) : ('');
	$age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ((int) ($vars['age'])) : (0);
	$pass = (isset($vars['pass'])) ? ($vars['pass']) : ('');

	if ((is_freeze($refer)) || (!is_editable($refer))) {
		return ['msg'=>$_attach_messages['err_noparm']];
	}

	$obj = new AttachFile($refer, $file, $age);

	if (!$obj->getstatus()) {
		return ['msg'=>$_attach_messages['err_notfound']];
	}

	return $obj->delete($pass);
}

// 凍結
function attach_freeze(string $freeze) : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$file = (isset($vars['file'])) ? ($vars['file']) : ('');
	$age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ((int) ($vars['age'])) : (0);
	$pass = (isset($vars['pass'])) ? ($vars['pass']) : ('');

	if ((is_freeze($refer)) || (!is_editable($refer))) {
		return ['msg'=>$_attach_messages['err_noparm']];
	} else {
		$obj = new AttachFile($refer, $file, $age);

		return ($obj->getstatus()) ? ($obj->freeze($freeze, $pass)) : (['msg'=>$_attach_messages['err_notfound']]);
	}
}

// リネーム
function attach_rename() : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$file = (isset($vars['file'])) ? ($vars['file']) : ('');
	$age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ((int) ($vars['age'])) : (0);
	$pass = (isset($vars['pass'])) ? ($vars['pass']) : ('');
	$newname = (isset($vars['newname'])) ? ($vars['newname']) : ('');

	if ((is_freeze($refer)) || (!is_editable($refer))) {
		return ['msg'=>$_attach_messages['err_noparm']];
	}

	$obj = new AttachFile($refer, $file, $age);

	if (!$obj->getstatus()) {
		return ['msg'=>$_attach_messages['err_notfound']];
	}

	return $obj->rename($pass, $newname);
}

// ダウンロード
function attach_open() : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$file = (isset($vars['file'])) ? ($vars['file']) : ('');
	$age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ((int) ($vars['age'])) : (0);

	$obj = new AttachFile($refer, $file, $age);

	return ($obj->getstatus()) ? ($obj->open()) : (['msg'=>$_attach_messages['err_notfound']]);
}

// 一覧取得
function attach_list() : array
{
	global $vars;
	global $_attach_messages;

	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');

	$obj = new AttachPages($refer);

	$msg = $_attach_messages[($refer == '') ? ('msg_listall') : ('msg_listpage')];
	$body = (($refer == '') || (isset($obj->pages[$refer]))) ? ($obj->toString($refer, false)) : ($_attach_messages['err_noexist']);

	return ['msg'=>$msg, 'body'=>$body];
}

// アップロードフォームを表示 (action時)
function attach_showform() : array
{
	global $vars;
	global $_attach_messages;

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');
	$vars['refer'] = $page;
	$body = attach_form($page);

	return ['msg'=>$_attach_messages['msg_upload'], 'body'=>$body];
}

//-------- サービス
// mime-typeの決定
function attach_mime_content_type($filename, $displayname) : string
{
	// default
	$type = 'application/octet-stream';

	if (!file_exists($filename)) {
		return $type;
	}

	$pathinfo = pathinfo($displayname);
	$ext0 = $pathinfo['extension'];

	if (preg_match('/^(gif|jpg|jpeg|png|swf)$/i', $ext0)) {
		$size = @getimagesize($filename);

		if (is_array($size)) {
			switch ($size[2]) {
				case 1:
					return 'image/gif';

				case 2:
					return 'image/jpeg';

				case 3:
					return 'image/png';

				case 4:
					return 'application/x-shockwave-flash';

				default:
					break;
			}
		}
	}

	// mime-type一覧表を取得
	$config = new Config(PLUGIN_ATTACH_CONFIG_PAGE_MIME);
	$table = ($config->read()) ? ($config->get('mime-type')) : ([]);

	// メモリ節約
	unset($config);

	foreach ($table as $row) {
		$_type = trim($row[0]);
		$exts = preg_split('/\s+|,/', trim($row[1]), -1, PREG_SPLIT_NO_EMPTY);

		foreach ($exts as $ext) {
			if (preg_match("/\\.{$ext}$/i", $displayname)) {
				return $_type;
			}
		}
	}

	return $type;
}

// アップロードフォームの出力
function attach_form(string $page) : string
{
	global $vars;
	global $_attach_messages;

	$script = get_base_uri();
	$r_page = rawurlencode($page);
	$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
	$navi = <<<EOD
		<span class="small">[<a href="{$script}?plugin=attach&amp;pcmd=list&amp;refer={$r_page}">{$_attach_messages['msg_list']}</a>] [<a href="{$script}?plugin=attach&amp;pcmd=list">{$_attach_messages['msg_listall']}</a>] </span><br />
EOD;

	if (!ini_get('file_uploads')) {
		return '#attach(): file_uploads disabled<br />'."\n".$navi;
	}

	if (!is_page($page)) {
		return '#attach(): No such page<br />'."\n".$navi;
	}

	$maxsize = PLUGIN_ATTACH_MAX_FILESIZE;
	$msg_maxsize = sprintf($_attach_messages['msg_maxsize'], number_format($maxsize / 1024).'KB');

	$pass = '';

	if ((PLUGIN_ATTACH_PASSWORD_REQUIRE) || (PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY)) {
		$title = $_attach_messages[(PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY) ? ('msg_adminpass') : ('msg_password')];
		$pass = '<br />'.$title.': <input type="password" name="pass" size="8" />';
	}

	return <<<EOD
<form enctype="multipart/form-data" action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="attach" />
		<input type="hidden" name="pcmd" value="post" />
		<input type="hidden" name="refer" value="{$s_page}" />
		<input type="hidden" name="max_file_size" value="{$maxsize}" />
{$navi}
		<span class="small">{$msg_maxsize}</span><br />
		<label for="_p_attach_file">{$_attach_messages['msg_file']}:</label> <input type="file" name="attach_file" id="_p_attach_file" />
		{$pass}
		<input type="submit" value="{$_attach_messages['btn_upload']}" />
	</div>
</form>
EOD;
}

//-------- クラス
// ファイル
class AttachFile
{
	public /* string */ $page;

	public /* string */ $file;

	public /* int */ $age;

	public /* string */ $basename;

	public /* string */ $filename;

	public /* string */ $logname;

	public /* int */ $time = 0;

	public /* int */ $size = 0;

	public /* string */ $time_str = '';

	public /* string */ $size_str = '';

	public /* array */ $status = ['count'=>[0], 'age'=>'', 'pass'=>'', 'freeze'=>false];

	public /* bool */ $exist;

	public /* string */ $type;

	public function AttachFile(string $page, string $file, int $age = 0) : void
	{
		$this->__construct($page, $file, $age);
	}

	public function __construct(string $page, string $file, int $age = 0)
	{
		$this->page = $page;
		$this->file = preg_replace('#^.*/#', '', $file);
		$this->age = $age;

		$this->basename = UPLOAD_DIR.encode($page).'_'.encode($this->file);
		$this->filename = $this->basename.(($age) ? ('.'.((string) ($age))) : (''));
		$this->logname = $this->basename.'.log';
		$this->exist = file_exists($this->filename);
		$this->time = ($this->exist) ? (filemtime($this->filename) - LOCALZONE) : (0);
	}

	public function gethash() : string
	{
		return ($this->exist) ? (md5_file($this->filename)) : ('');
	}

	// ファイル情報取得
	public function getstatus() : bool
	{
		// ログファイル取得
		if (file_exists($this->logname)) {
			$data = file($this->logname);

			foreach ($this->status as $key=>$value) {
				$this->status[$key] = rtrim(array_shift($data));
			}

			$this->status['count'] = explode(',', $this->status['count']);
		}

		if (!$this->exist) {
			return false;
		}

		$this->time_str = get_date('Y/m/d H:i:s', $this->time);
		$this->size = filesize($this->filename);
		$this->size_str = sprintf('%01.1f', round($this->size / 1024, 1)).'KB';
		$this->type = attach_mime_content_type($this->filename, $this->file);

		return true;
	}

	// ステータス保存
	public function putstatus() : void
	{
		$this->status['count'] = implode(',', $this->status['count']);

		if (!($fp = fopen($this->logname, 'wb'))) {
			die_message('cannot write '.$this->logname);
		}

		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		rewind($fp);

		foreach ($this->status as $key=>$value) {
			fwrite($fp, $value."\n");
		}

		flock($fp, LOCK_UN);
		fclose($fp);
	}

	// 日付の比較関数
	static function datecomp(AttachFile $a, AttachFile $b) : int
	{
		return ($a->time == $b->time) ? (0) : (($a->time > $b->time) ? (-1) : (1));
	}

	public function toString(bool $showicon, bool $showinfo) : string
	{
		global $_attach_messages;

		$script = get_base_uri();
		$this->getstatus();
		$param = '&amp;file='.rawurlencode($this->file).'&amp;refer='.rawurlencode($this->page).(($this->age) ? ('&amp;age='.((string) ($this->age))) : (''));
		$title = $this->time_str.' '.$this->size_str;
		$label = (($showicon) ? (PLUGIN_ATTACH_FILE_ICON) : ('')).htmlspecialchars($this->file, ENT_COMPAT, 'UTF-8');

		if ($this->age) {
			$label .= ' (backup No.'.((string) ($this->age)).')';
		}

		$count = '';
		$info = '';

		if ($showinfo) {
			$_title = str_replace('$1', rawurlencode($this->file), $_attach_messages['msg_info']);
			$info = "\n".'<span class="small">[<a href="'.$script.'?plugin=attach&amp;pcmd=info'.$param.'" title="'.$_title.'">'.$_attach_messages['btn_info'].'</a>]</span>'."\n";
			$count = (($showicon) && (!empty($this->status['count'][$this->age]))) ? (sprintf($_attach_messages['msg_count'], $this->status['count'][$this->age])) : ('');
		}

		return '<a href="'.$script.'?plugin=attach&amp;pcmd=open'.$param.'" title="'.$title.'">'.$label.'</a>'.$count.$info;
	}

	// 情報表示
	public function info(string $err) : array
	{
		global $_attach_messages;

		$script = get_base_uri();
		$r_page = rawurlencode($this->page);
		$s_page = htmlspecialchars($this->page, ENT_COMPAT, 'UTF-8');
		$s_file = htmlspecialchars($this->file, ENT_COMPAT, 'UTF-8');
		$s_err = ($err === '') ? ('') : ('<p style="font-weight:bold">'.$_attach_messages[$err].'</p>');

		$msg_rename = '';

		if ($this->age) {
			$msg_freezed = '';
			$msg_delete = '<input type="radio" name="pcmd" id="_p_attach_delete" value="delete" /><label for="_p_attach_delete">'.$_attach_messages['msg_delete'].$_attach_messages['msg_require'].'</label><br />';
			$msg_freeze = '';
		} else {
			if ($this->status['freeze']) {
				$msg_freezed = "<dd>{$_attach_messages['msg_isfreeze']}</dd>";
				$msg_delete = '';
				$msg_freeze = '<input type="radio" name="pcmd" id="_p_attach_unfreeze" value="unfreeze" /><label for="_p_attach_unfreeze">'.$_attach_messages['msg_unfreeze'].$_attach_messages['msg_require'].'</label><br />';
			} else {
				$msg_freezed = '';
				$msg_delete = '<input type="radio" name="pcmd" id="_p_attach_delete" value="delete" /><label for="_p_attach_delete">'.$_attach_messages['msg_delete'];

				if ((PLUGIN_ATTACH_DELETE_ADMIN_ONLY) || ($this->age)) {
					$msg_delete .= $_attach_messages['msg_require'];
				}

				$msg_delete .= '</label><br />';
				$msg_freeze = '<input type="radio" name="pcmd" id="_p_attach_freeze" value="freeze" /><label for="_p_attach_freeze">'.$_attach_messages['msg_freeze'].$_attach_messages['msg_require'].'</label><br />';

				if (PLUGIN_ATTACH_RENAME_ENABLE) {
					$msg_rename = '<input type="radio" name="pcmd" id="_p_attach_rename" value="rename" /><label for="_p_attach_rename">'.$_attach_messages['msg_rename'].$_attach_messages['msg_require'].'</label><br />&nbsp;&nbsp;&nbsp;&nbsp;<label for="_p_attach_newname">'.$_attach_messages['msg_newname'].':</label> <input type="text" name="newname" id="_p_attach_newname" size="40" value="'.$this->file.'" /><br />';
				}
			}
		}

		$info = $this->toString(true, false);
		$hash = $this->gethash();

		$retval = ['msg'=>sprintf($_attach_messages['msg_info'], htmlspecialchars($this->file, ENT_COMPAT, 'UTF-8'))];
		$retval['body'] = <<< EOD
<p class="small"> [<a href="{$script}?plugin=attach&amp;pcmd=list&amp;refer={$r_page}">{$_attach_messages['msg_list']}</a>]  [<a href="{$script}?plugin=attach&amp;pcmd=list">{$_attach_messages['msg_listall']}</a>] </p>
<dl>
	<dt>{$info}</dt>
	<dd>{$_attach_messages['msg_page']}:{$s_page}</dd>
	<dd>{$_attach_messages['msg_filename']}:{$this->filename}</dd>
	<dd>{$_attach_messages['msg_md5hash']}:{$hash}</dd>
	<dd>{$_attach_messages['msg_filesize']}:{$this->size_str} ({$this->size} bytes)</dd>
	<dd>Content-type:{$this->type}</dd>
	<dd>{$_attach_messages['msg_date']}:{$this->time_str}</dd>
	<dd>{$_attach_messages['msg_dlcount']}:{$this->status['count'][$this->age]}</dd>
	{$msg_freezed}
</dl>
<hr />
{$s_err}
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="attach" />
		<input type="hidden" name="refer" value="{$s_page}" />
		<input type="hidden" name="file" value="{$s_file}" />
		<input type="hidden" name="age" value="{$this->age}" />
		{$msg_delete}
		{$msg_freeze}
		{$msg_rename}
		<br />
		<label for="_p_attach_password">{$_attach_messages['msg_password']}:</label>
		<input type="password" name="pass" id="_p_attach_password" size="8" />
		<input type="submit" value="{$_attach_messages['btn_submit']}" />
	<div>
</form>
EOD;

		return $retval;
	}

	public function delete(string $pass) : array
	{
		global $_attach_messages;
		global $notify;
		global $notify_subject;

		if ($this->status['freeze']) {
			return attach_info('msg_isfreeze');
		}

		if (!pkwk_login($pass)) {
			if ((PLUGIN_ATTACH_DELETE_ADMIN_ONLY) || ($this->age)) {
				return attach_info('err_adminpass');
			} elseif ((PLUGIN_ATTACH_PASSWORD_REQUIRE) && (md5($pass) !== $this->status['pass'])) {
				return attach_info('err_password');
			}
		}

		// バックアップ
		if (($this->age) || ((PLUGIN_ATTACH_DELETE_ADMIN_ONLY) && (PLUGIN_ATTACH_DELETE_ADMIN_NOBACKUP))) {
			@unlink($this->filename);
		} else {
			do {
				$age = ++$this->status['age'];
			} while (file_exists($this->basename.'.'.((string) ($age))));

			if (!rename($this->basename, $this->basename.'.'.((string) ($age)))) {
				// 削除失敗 why?
				return ['msg'=>$_attach_messages['err_delete']];
			}

			$this->status['count'][$age] = $this->status['count'][0];
			$this->status['count'][0] = 0;
			$this->putstatus();
		}

		if (is_page($this->page)) {
			touch(get_filename($this->page));
		}

		if ($notify) {
			$footer['ACTION'] = 'File deleted';
			$footer['FILENAME'] = $this->file;
			$footer['PAGE'] = $this->page;
			$footer['URI'] = get_page_uri($this->page, PKWK_URI_ABSOLUTE);
			$footer['USER_AGENT'] = true;
			$footer['REMOTE_ADDR'] = true;

			if (!pkwk_mail_notify($notify_subject, "\n", $footer)) {
				die('pkwk_mail_notify(): Failed');
			}
		}

		return ['msg'=>$_attach_messages['msg_deleted']];
	}

	public function rename(string $pass, string $newname) : array
	{
		global $_attach_messages;
		global $notify;
		global $notify_subject;

		if ($this->status['freeze']) {
			return attach_info('msg_isfreeze');
		}

		if (!pkwk_login($pass)) {
			if ((PLUGIN_ATTACH_DELETE_ADMIN_ONLY) || ($this->age)) {
				return attach_info('err_adminpass');
			} elseif ((PLUGIN_ATTACH_PASSWORD_REQUIRE) && (md5($pass) !== $this->status['pass'])) {
				return attach_info('err_password');
			}
		}

		$newbase = UPLOAD_DIR.encode($this->page).'_'.encode($newname);

		if (file_exists($newbase)) {
			return ['msg'=>$_attach_messages['err_exists']];
		}

		if (!PLUGIN_ATTACH_RENAME_ENABLE) {
			return ['msg'=>$_attach_messages['err_rename']];
		}

		if (!rename($this->basename, $newbase)) {
			return ['msg'=>$_attach_messages['err_rename']];
		}

		// Rename primary file succeeded.
		// Then, rename backup(archive) files and log file)
		$rename_targets = [];
		$dir = opendir(UPLOAD_DIR);

		if ($dir) {
			$matches_leaf = [];

			if (preg_match('/(((?:[0-9A-F]{2})+)_((?:[0-9A-F]{2})+))$/', $this->basename, $matches_leaf)) {
				$attachfile_leafname = $matches_leaf[1];
				$attachfile_leafname_pattern = preg_quote($attachfile_leafname, '/');
				$pattern = "/^({$attachfile_leafname_pattern})(\\.((\\d+)|(log)))$/";
				$matches = [];

				while ($file = readdir($dir)) {
					if (!preg_match($pattern, $file, $matches)) {
						continue;
					}

					$basename2 = $matches[0];
					$newbase2 = $newbase.$matches[2];
					$rename_targets[$basename2] = $newbase2;
				}
			}

			closedir($dir);
		}

		foreach ($rename_targets as $basename2=>$newbase2) {
			$basename2path = UPLOAD_DIR.$basename2;
			rename($basename2path, $newbase2);
		}

		return ['msg'=>$_attach_messages['msg_renamed']];
	}

	public function freeze(string $freeze, string $pass) : array
	{
		global $_attach_messages;

		if (!pkwk_login($pass)) {
			return attach_info('err_adminpass');
		}

		$this->getstatus();
		$this->status['freeze'] = $freeze;
		$this->putstatus();

		return ['msg'=>$_attach_messages[($freeze) ? ('msg_freezed') : ('msg_unfreezed')]];
	}

	public function open() : void
	{
		$this->getstatus();
		$this->status['count'][$this->age]++;
		$this->putstatus();
		$filename = $this->file;

		// Care for Japanese-character-included file name
		$legacy_filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');
		$utf8filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');

		ini_set('default_charset', '');
		mb_http_output('pass');

		pkwk_common_headers();
		header('Content-Disposition: inline; filename="'.$legacy_filename.'"; filename*=utf-8\'\''.rawurlencode($utf8filename));
		header('Content-Length: '.$this->size);
		header('Content-Type: '.$this->type);
		// Disable output bufferring
		while (ob_get_level()) {
			ob_end_flush();
		}

		flush();
		@readfile($this->filename);

		exit;
	}
}

// ファイルコンテナ
class AttachFiles
{
	public /* string */ $page;

	public /* array */ $files = [];

	public function AttachFiles(string $page) : void
	{
		$this->__construct($page);
	}

	public function __construct(string $page)
	{
		$this->page = $page;
	}

	public function add(string $file, int $age) : void
	{
		$this->files[$file][$age] = new AttachFile($this->page, $file, $age);
	}

	// ファイル一覧を取得
	public function toString(bool $flat) : string
	{
		global $_title_cannotread;

		if (!check_readable($this->page, false, false)) {
			return str_replace('$1', make_pagelink($this->page), $_title_cannotread);
		} elseif ($flat) {
			return $this->to_flat();
		}

		$ret = '';
		$files = array_keys($this->files);
		sort($files, SORT_STRING);

		foreach ($files as $file) {
			$_files = [];

			foreach (array_keys($this->files[$file]) as $age) {
				$_files[$age] = $this->files[$file][$age]->toString(false, true);
			}

			if (!isset($_files[0])) {
				$_files[0] = htmlspecialchars($file, ENT_COMPAT, 'UTF-8');
			}

			ksort($_files, SORT_NUMERIC);
			$_file = $_files[0];
			unset($_files[0]);
			$ret .= "\t".'<li>'.$_file."\n";

			if (count($_files)) {
				$ret .= "\t\t".'<ul>'."\n\t\t\t".'<li>'.implode('</li>'."\n\t\t\t".'<li>', $_files)."\t\t\t".'</li>'."\n\t\t".'</ul>'."\n";
			}

			$ret .= "\t".'</li>'."\n";
		}

		return make_pagelink($this->page)."\n".'<ul>'."\n".$ret.'</ul>'."\n";
	}

	// ファイル一覧を取得(inline)
	public function to_flat() : string
	{
		$ret = '';
		$files = [];

		foreach (array_keys($this->files) as $file) {
			if (isset($this->files[$file][0])) {
				$files[$file] = $this->files[$file][0];
			}
		}

		uasort($files, ['AttachFile', 'datecomp']);

		foreach (array_keys($files) as $file) {
			$ret .= $files[$file]->toString(true, true).' ';
		}

		return $ret;
	}
}

// ページコンテナ
class AttachPages
{
	public /* array */ $pages = [];

	public function AttachPages(string $page = '', int $age = null) : void
	{
		$this->__construct($page, $age);
	}

	public function __construct(string $page = '', ?int $age = null)
	{
		if (!($dir = opendir(UPLOAD_DIR))) {
			die('directory '.UPLOAD_DIR.' is not exist or not readable.');
		}

		$page_pattern = ($page === '') ? ('(?:[0-9A-F]{2})+') : (preg_quote(encode($page), '/'));
		$age_pattern = ($age === null) ? ('(?:\.([0-9]+))?') : (($age) ? ('.('.((string) ($age)).')') : (''));
		$pattern = "/^({$page_pattern})_((?:[0-9A-F]{2})+){$age_pattern}$/";

		$matches = [];

		while (($file = readdir($dir)) !== false) {
			if (!preg_match($pattern, $file, $matches)) {
				continue;
			}

			$_page = decode($matches[1]);
			$_file = decode($matches[2]);
			$_age = ((isset($matches[3])) && (is_numeric($matches[3]))) ? ((int) ($matches[3])) : (0);

			if (!isset($this->pages[$_page])) {
				$this->pages[$_page] = new AttachFiles($_page);
			}

			$this->pages[$_page]->add($_file, $_age);
		}

		closedir($dir);
	}

	public function toString(string $page = '', bool $flat = false) : string
	{
		if ($page != '') {
			if (!isset($this->pages[$page])) {
				return '';
			} else {
				return $this->pages[$page]->toString($flat);
			}
		}

		$ret = '';

		$pages = array_keys($this->pages);
		sort($pages, SORT_STRING);

		foreach ($pages as $page) {
			if (check_non_list($page)) {
				continue;
			}

			$ret .= '<li>'.$this->pages[$page]->toString($flat).'</li>'."\n";
		}

		return "\n".'<ul>'."\n".$ret.'</ul>'."\n";
	}
}
