<?php declare(strict_types=1);
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
define('PLUGIN_ATTACH_MAX_FILESIZE', (1024 * 1024)); // default: 1MB

// 管理者だけが添付ファイルをアップロードできるようにする
define('PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY', true); // FALSE or TRUE

// 管理者だけが添付ファイルを削除できるようにする
define('PLUGIN_ATTACH_DELETE_ADMIN_ONLY', true); // FALSE or TRUE

// 管理者が添付ファイルを削除するときは、バックアップを作らない
// PLUGIN_ATTACH_DELETE_ADMIN_ONLY=TRUEのとき有効
define('PLUGIN_ATTACH_DELETE_ADMIN_NOBACKUP', false); // FALSE or TRUE

// アップロード/削除時にパスワードを要求する(ADMIN_ONLYが優先)
define('PLUGIN_ATTACH_PASSWORD_REQUIRE', false); // FALSE or TRUE

// 添付ファイル名を変更できるようにする
define('PLUGIN_ATTACH_RENAME_ENABLE', true); // FALSE or TRUE

// ファイルのアクセス権
define('PLUGIN_ATTACH_FILE_MODE', 0644);
//define('PLUGIN_ATTACH_FILE_MODE', 0604); // for XREA.COM

// File icon image
define('PLUGIN_ATTACH_FILE_ICON', '<img src="'.IMAGE_DIR.'file.png"'.
	' width="20" height="20" alt="file"'.
	' style="border-width:0" />');

// mime-typeを記述したページ
define('PLUGIN_ATTACH_CONFIG_PAGE_MIME', 'plugin/attach/mime-type');

//-------- convert
function plugin_attach_convert()
{
	global $vars;

	$page = isset($vars['page']) ? $vars['page'] : '';

	$nolist = $noform = false;

	if (func_num_args() > 0) {
		foreach (func_get_args() as $arg) {
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
function plugin_attach_action()
{
	global $vars, $_attach_messages;

	// Backward compatible
	if (isset($vars['openfile'])) {
		$vars['file'] = $vars['openfile'];
		$vars['pcmd'] = 'open';
	}

	if (isset($vars['delfile'])) {
		$vars['file'] = $vars['delfile'];
		$vars['pcmd'] = 'delete';
	}

	$pcmd = isset($vars['pcmd']) ? $vars['pcmd'] : '';
	$refer = isset($vars['refer']) ? $vars['refer'] : '';
	$pass = isset($vars['pass']) ? $vars['pass'] : null;
	$page = isset($vars['page']) ? $vars['page'] : '';

	if ($refer === '' && $page !== '') {
		$refer = $page;
	}

	if ($refer != '' && is_pagename($refer)) {
		if (in_array($pcmd, ['info', 'open', 'list'])) {
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
		case 'delete':	// FALLTHROUGH
		case 'freeze':
		case 'unfreeze':
			if (PKWK_READONLY) {
				die_message('PKWK_READONLY prohibits editing');
			}
		}

		switch ($pcmd) {
		case 'info': return attach_info();
		case 'delete': return attach_delete();
		case 'open': return attach_open();
		case 'list': return attach_list();
		case 'freeze': return attach_freeze(true);
		case 'unfreeze': return attach_freeze(false);
		case 'rename': return attach_rename();
		case 'upload': return attach_showform();
		}

		if ($page == '' || !is_page($page)) {
			return attach_list();
		} else {
			return attach_showform();
		}
	}
}

//-------- call from skin
function attach_filelist()
{
	global $vars, $_attach_messages;

	$page = isset($vars['page']) ? $vars['page'] : '';

	$obj = new AttachPages($page, 0);

	if (!isset($obj->pages[$page])) {
		return '';
	} else {
		return $_attach_messages['msg_file'].': '.
		$obj->toString($page, true)."\n";
	}
}

//-------- 実体
// ファイルアップロード
// $pass = NULL : パスワードが指定されていない
// $pass = TRUE : アップロード許可
function attach_upload($file, $page, $pass = null)
{
	global $_attach_messages, $notify, $notify_subject;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	// Check query-string
	$query = 'plugin=attach&amp;pcmd=info&amp;refer='.rawurlencode($page).
		'&amp;file='.rawurlencode($file['name']);

	if (PKWK_QUERY_STRING_MAX && strlen($query) > PKWK_QUERY_STRING_MAX) {
		pkwk_common_headers();
		echo 'Query string (page name and/or file name) too long';

		exit;
	} elseif (!is_page($page)) {
		die_message('No such page');
	} elseif ($file['tmp_name'] == '' || !is_uploaded_file($file['tmp_name'])) {
		return ['result'=>false];
	} elseif ($file['size'] > PLUGIN_ATTACH_MAX_FILESIZE) {
		return [
			'result'=>false,
			'msg'=>$_attach_messages['err_exceed'], ];
	} elseif (!is_pagename($page) || ($pass !== true && !is_editable($page))) {
		return [
			'result'=>false, '
			msg'=>$_attach_messages['err_noparm'], ];
	} elseif (PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY && $pass !== true &&
		  ($pass === null || !pkwk_login($pass))) {
		return [
			'result'=>false,
			'msg'=>$_attach_messages['err_adminpass'], ];
	}

	$obj = new AttachFile($page, $file['name']);

	if ($obj->exist) {
		return ['result'=>false,
			'msg'=>$_attach_messages['err_exists'], ];
	}

	if (move_uploaded_file($file['tmp_name'], $obj->filename)) {
		chmod($obj->filename, PLUGIN_ATTACH_FILE_MODE);
	}

	if (is_page($page)) {
		pkwk_touch_file(get_filename($page));
	}

	$obj->getstatus();
	$obj->status['pass'] = ($pass !== true && $pass !== null) ? md5($pass) : '';
	$obj->putstatus();

	if ($notify) {
		$footer['ACTION'] = 'File attached';
		$footer['FILENAME'] = $file['name'];
		$footer['FILESIZE'] = $file['size'];
		$footer['PAGE'] = $page;

		$footer['URI'] = get_base_uri(PKWK_URI_ABSOLUTE).
			// MD5 may heavy
			'?plugin=attach'.
				'&refer='.rawurlencode($page).
				'&file='.rawurlencode($file['name']).
				'&pcmd=info';

		$footer['USER_AGENT'] = true;
		$footer['REMOTE_ADDR'] = true;

		pkwk_mail_notify($notify_subject, "\n", $footer) ||
			die('pkwk_mail_notify(): Failed');
	}

	return [
		'result'=>true,
		'msg'=>$_attach_messages['msg_uploaded'], ];
}

// 詳細フォームを表示
function attach_info($err = '')
{
	global $vars, $_attach_messages;

	foreach (['refer', 'file', 'age'] as $var) {
		${$var} = isset($vars[$var]) ? $vars[$var] : '';
	}

	$obj = new AttachFile($refer, $file, $age);

	return $obj->getstatus() ?
		$obj->info($err) :
		['msg'=>$_attach_messages['err_notfound']];
}

// 削除
function attach_delete()
{
	global $vars, $_attach_messages;

	foreach (['refer', 'file', 'age', 'pass'] as $var) {
		${$var} = isset($vars[$var]) ? $vars[$var] : '';
	}

	if (is_freeze($refer) || !is_editable($refer)) {
		return ['msg'=>$_attach_messages['err_noparm']];
	}

	$obj = new AttachFile($refer, $file, $age);

	if (!$obj->getstatus()) {
		return ['msg'=>$_attach_messages['err_notfound']];
	}

	return $obj->delete($pass);
}

// 凍結
function attach_freeze($freeze)
{
	global $vars, $_attach_messages;

	foreach (['refer', 'file', 'age', 'pass'] as $var) {
		${$var} = isset($vars[$var]) ? $vars[$var] : '';
	}

	if (is_freeze($refer) || !is_editable($refer)) {
		return ['msg'=>$_attach_messages['err_noparm']];
	} else {
		$obj = new AttachFile($refer, $file, $age);

		return $obj->getstatus() ?
			$obj->freeze($freeze, $pass) :
			['msg'=>$_attach_messages['err_notfound']];
	}
}

// リネーム
function attach_rename()
{
	global $vars, $_attach_messages;

	foreach (['refer', 'file', 'age', 'pass', 'newname'] as $var) {
		${$var} = isset($vars[$var]) ? $vars[$var] : '';
	}

	if (is_freeze($refer) || !is_editable($refer)) {
		return ['msg'=>$_attach_messages['err_noparm']];
	}
	$obj = new AttachFile($refer, $file, $age);

	if (!$obj->getstatus()) {
		return ['msg'=>$_attach_messages['err_notfound']];
	}

	return $obj->rename($pass, $newname);
}

// ダウンロード
function attach_open()
{
	global $vars, $_attach_messages;

	foreach (['refer', 'file', 'age'] as $var) {
		${$var} = isset($vars[$var]) ? $vars[$var] : '';
	}

	$obj = new AttachFile($refer, $file, $age);

	return $obj->getstatus() ?
		$obj->open() :
		['msg'=>$_attach_messages['err_notfound']];
}

// 一覧取得
function attach_list()
{
	global $vars, $_attach_messages;

	$refer = isset($vars['refer']) ? $vars['refer'] : '';

	$obj = new AttachPages($refer);

	$msg = $_attach_messages[($refer == '') ? 'msg_listall' : 'msg_listpage'];
	$body = ($refer == '' || isset($obj->pages[$refer])) ?
		$obj->toString($refer, false) :
		$_attach_messages['err_noexist'];

	return ['msg'=>$msg, 'body'=>$body];
}

// アップロードフォームを表示 (action時)
function attach_showform()
{
	global $vars, $_attach_messages;

	$page = isset($vars['page']) ? $vars['page'] : '';
	$vars['refer'] = $page;
	$body = attach_form($page);

	return ['msg'=>$_attach_messages['msg_upload'], 'body'=>$body];
}

//-------- サービス
// mime-typeの決定
function attach_mime_content_type($filename, $displayname)
{
	$type = 'application/octet-stream'; // default

	if (!file_exists($filename)) {
		return $type;
	}
	$pathinfo = pathinfo($displayname);
	$ext0 = $pathinfo['extension'];

	if (preg_match('/^(gif|jpg|jpeg|png|swf)$/i', $ext0)) {
		$size = @getimagesize($filename);

		if (is_array($size)) {
			switch ($size[2]) {
				case 1: return 'image/gif';
				case 2: return 'image/jpeg';
				case 3: return 'image/png';
				case 4: return 'application/x-shockwave-flash';
			}
		}
	}
	// mime-type一覧表を取得
	$config = new Config(PLUGIN_ATTACH_CONFIG_PAGE_MIME);
	$table = $config->read() ? $config->get('mime-type') : [];
	unset($config); // メモリ節約

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
function attach_form($page)
{
	global $vars, $_attach_messages;

	$script = get_base_uri();
	$r_page = rawurlencode($page);
	$s_page = htmlsc($page);
	$navi = <<<EOD
  <span class="small">
   [<a href="{$script}?plugin=attach&amp;pcmd=list&amp;refer={$r_page}">{$_attach_messages['msg_list']}</a>]
   [<a href="{$script}?plugin=attach&amp;pcmd=list">{$_attach_messages['msg_listall']}</a>]
  </span><br />
EOD;

	if (!ini_get('file_uploads')) {
		return '#attach(): file_uploads disabled<br />'.$navi;
	}

	if (!is_page($page)) {
		return '#attach(): No such page<br />'.$navi;
	}

	$maxsize = PLUGIN_ATTACH_MAX_FILESIZE;
	$msg_maxsize = sprintf($_attach_messages['msg_maxsize'], number_format($maxsize / 1024).'KB');

	$pass = '';

	if (PLUGIN_ATTACH_PASSWORD_REQUIRE || PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY) {
		$title = $_attach_messages[PLUGIN_ATTACH_UPLOAD_ADMIN_ONLY ? 'msg_adminpass' : 'msg_password'];
		$pass = '<br />'.$title.': <input type="password" name="pass" size="8" />';
	}

	return <<<EOD
<form enctype="multipart/form-data" action="{$script}" method="post">
 <div>
  <input type="hidden" name="plugin" value="attach" />
  <input type="hidden" name="pcmd"   value="post" />
  <input type="hidden" name="refer"  value="{$s_page}" />
  <input type="hidden" name="max_file_size" value="{$maxsize}" />
  {$navi}
  <span class="small">
   {$msg_maxsize}
  </span><br />
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
	public $page;

	public $file;

	public $age;

	public $basename;

	public $filename;

	public $logname;

	public $time = 0;

	public $size = 0;

	public $time_str = '';

	public $size_str = '';

	public $status = ['count'=>[0], 'age'=>'', 'pass'=>'', 'freeze'=>false];

	public function AttachFile($page, $file, $age = 0) : void
	{
		$this->__construct($page, $file, $age);
	}

	public function __construct($page, $file, $age = 0)
	{
		$this->page = $page;
		$this->file = preg_replace('#^.*/#', '', $file);
		$this->age = is_numeric($age) ? $age : 0;

		$this->basename = UPLOAD_DIR.encode($page).'_'.encode($this->file);
		$this->filename = $this->basename.($age ? '.'.$age : '');
		$this->logname = $this->basename.'.log';
		$this->exist = file_exists($this->filename);
		$this->time = $this->exist ? filemtime($this->filename) - LOCALZONE : 0;
	}

	public function gethash()
	{
		return $this->exist ? md5_file($this->filename) : '';
	}

	// ファイル情報取得
	public function getstatus()
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
		$fp = fopen($this->logname, 'wb') ||
			die_message('cannot write '.$this->logname);
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
	public function datecomp($a, $b)
	{
		return ($a->time == $b->time) ? 0 : (($a->time > $b->time) ? -1 : 1);
	}

	public function toString($showicon, $showinfo)
	{
		global $_attach_messages;

		$script = get_base_uri();
		$this->getstatus();
		$param = '&amp;file='.rawurlencode($this->file).'&amp;refer='.rawurlencode($this->page).
			($this->age ? '&amp;age='.$this->age : '');
		$title = $this->time_str.' '.$this->size_str;
		$label = ($showicon ? PLUGIN_ATTACH_FILE_ICON : '').htmlsc($this->file);

		if ($this->age) {
			$label .= ' (backup No.'.$this->age.')';
		}
		$info = $count = '';

		if ($showinfo) {
			$_title = str_replace('$1', rawurlencode($this->file), $_attach_messages['msg_info']);
			$info = "\n<span class=\"small\">[<a href=\"{$script}?plugin=attach&amp;pcmd=info{$param}\" title=\"{$_title}\">{$_attach_messages['btn_info']}</a>]</span>\n";
			$count = ($showicon && !empty($this->status['count'][$this->age])) ?
				sprintf($_attach_messages['msg_count'], $this->status['count'][$this->age]) : '';
		}

		return "<a href=\"{$script}?plugin=attach&amp;pcmd=open{$param}\" title=\"{$title}\">{$label}</a>{$count}{$info}";
	}

	// 情報表示
	public function info($err)
	{
		global $_attach_messages;

		$script = get_base_uri();
		$r_page = rawurlencode($this->page);
		$s_page = htmlsc($this->page);
		$s_file = htmlsc($this->file);
		$s_err = ($err == '') ? '' : '<p style="font-weight:bold">'.$_attach_messages[$err].'</p>';

		$msg_rename = '';

		if ($this->age) {
			$msg_freezed = '';
			$msg_delete = '<input type="radio" name="pcmd" id="_p_attach_delete" value="delete" />'.
				'<label for="_p_attach_delete">'.$_attach_messages['msg_delete'].
				$_attach_messages['msg_require'].'</label><br />';
			$msg_freeze = '';
		} else {
			if ($this->status['freeze']) {
				$msg_freezed = "<dd>{$_attach_messages['msg_isfreeze']}</dd>";
				$msg_delete = '';
				$msg_freeze = '<input type="radio" name="pcmd" id="_p_attach_unfreeze" value="unfreeze" />'.
					'<label for="_p_attach_unfreeze">'.$_attach_messages['msg_unfreeze'].
					$_attach_messages['msg_require'].'</label><br />';
			} else {
				$msg_freezed = '';
				$msg_delete = '<input type="radio" name="pcmd" id="_p_attach_delete" value="delete" />'.
					'<label for="_p_attach_delete">'.$_attach_messages['msg_delete'];

				if (PLUGIN_ATTACH_DELETE_ADMIN_ONLY || $this->age) {
					$msg_delete .= $_attach_messages['msg_require'];
				}
				$msg_delete .= '</label><br />';
				$msg_freeze = '<input type="radio" name="pcmd" id="_p_attach_freeze" value="freeze" />'.
					'<label for="_p_attach_freeze">'.$_attach_messages['msg_freeze'].
					$_attach_messages['msg_require'].'</label><br />';

				if (PLUGIN_ATTACH_RENAME_ENABLE) {
					$msg_rename = '<input type="radio" name="pcmd" id="_p_attach_rename" value="rename" />'.
						'<label for="_p_attach_rename">'.$_attach_messages['msg_rename'].
						$_attach_messages['msg_require'].'</label><br />&nbsp;&nbsp;&nbsp;&nbsp;'.
						'<label for="_p_attach_newname">'.$_attach_messages['msg_newname'].
						':</label> '.
						'<input type="text" name="newname" id="_p_attach_newname" size="40" value="'.
						$this->file.'" /><br />';
				}
			}
		}
		$info = $this->toString(true, false);
		$hash = $this->gethash();

		$retval = ['msg'=>sprintf($_attach_messages['msg_info'], htmlsc($this->file))];
		$retval['body'] = <<< EOD
<p class="small">
 [<a href="{$script}?plugin=attach&amp;pcmd=list&amp;refer={$r_page}">{$_attach_messages['msg_list']}</a>]
 [<a href="{$script}?plugin=attach&amp;pcmd=list">{$_attach_messages['msg_listall']}</a>]
</p>
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
 </div>
</form>
EOD;

		return $retval;
	}

	public function delete($pass)
	{
		global $_attach_messages, $notify, $notify_subject;

		if ($this->status['freeze']) {
			return attach_info('msg_isfreeze');
		}

		if (!pkwk_login($pass)) {
			if (PLUGIN_ATTACH_DELETE_ADMIN_ONLY || $this->age) {
				return attach_info('err_adminpass');
			} elseif (PLUGIN_ATTACH_PASSWORD_REQUIRE &&
				md5($pass) !== $this->status['pass']) {
				return attach_info('err_password');
			}
		}

		// バックアップ
		if ($this->age ||
			(PLUGIN_ATTACH_DELETE_ADMIN_ONLY && PLUGIN_ATTACH_DELETE_ADMIN_NOBACKUP)) {
			@unlink($this->filename);
		} else {
			do {
				$age = ++$this->status['age'];
			} while (file_exists($this->basename.'.'.$age));

			if (!rename($this->basename, $this->basename.'.'.$age)) {
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
			pkwk_mail_notify($notify_subject, "\n", $footer) ||
				die('pkwk_mail_notify(): Failed');
		}

		return ['msg'=>$_attach_messages['msg_deleted']];
	}

	public function rename($pass, $newname)
	{
		global $_attach_messages, $notify, $notify_subject;

		if ($this->status['freeze']) {
			return attach_info('msg_isfreeze');
		}

		if (!pkwk_login($pass)) {
			if (PLUGIN_ATTACH_DELETE_ADMIN_ONLY || $this->age) {
				return attach_info('err_adminpass');
			} elseif (PLUGIN_ATTACH_PASSWORD_REQUIRE &&
				md5($pass) !== $this->status['pass']) {
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

	public function freeze($freeze, $pass)
	{
		global $_attach_messages;

		if (!pkwk_login($pass)) {
			return attach_info('err_adminpass');
		}

		$this->getstatus();
		$this->status['freeze'] = $freeze;
		$this->putstatus();

		return ['msg'=>$_attach_messages[$freeze ? 'msg_freezed' : 'msg_unfreezed']];
	}

	public function open() : void
	{
		$this->getstatus();
		$this->status['count'][$this->age]++;
		$this->putstatus();
		$filename = $this->file;

		// Care for Japanese-character-included file name
		$legacy_filename = mb_convert_encoding($filename, 'UTF-8', SOURCE_ENCODING);

		if (LANG == 'ja') {
			switch (UA_NAME.'/'.UA_PROFILE) {
			case 'MSIE/default':
				$legacy_filename = mb_convert_encoding($filename, 'SJIS', SOURCE_ENCODING);

				break;
			}
		}
		$utf8filename = mb_convert_encoding($filename, 'UTF-8', SOURCE_ENCODING);

		ini_set('default_charset', '');
		mb_http_output('pass');

		pkwk_common_headers();
		header('Content-Disposition: inline; filename="'.$legacy_filename
			.'"; filename*=utf-8\'\''.rawurlencode($utf8filename));
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
	public $page;

	public $files = [];

	public function AttachFiles($page) : void
	{
		$this->__construct($page);
	}

	public function __construct($page)
	{
		$this->page = $page;
	}

	public function add($file, $age) : void
	{
		$this->files[$file][$age] = new AttachFile($this->page, $file, $age);
	}

	// ファイル一覧を取得
	public function toString($flat)
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
				$_files[0] = htmlsc($file);
			}
			ksort($_files, SORT_NUMERIC);
			$_file = $_files[0];
			unset($_files[0]);
			$ret .= " <li>{$_file}\n";

			if (count($_files)) {
				$ret .= "<ul>\n<li>".implode("</li>\n<li>", $_files)."</li>\n</ul>\n";
			}
			$ret .= " </li>\n";
		}

		return make_pagelink($this->page)."\n<ul>\n{$ret}</ul>\n";
	}

	// ファイル一覧を取得(inline)
	public function to_flat()
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
	public $pages = [];

	public function AttachPages($page = '', $age = null) : void
	{
		$this->__construct($page, $age);
	}

	public function __construct($page = '', $age = null)
	{
		$dir = opendir(UPLOAD_DIR) ||
			die('directory '.UPLOAD_DIR.' is not exist or not readable.');

		$page_pattern = ($page == '') ? '(?:[0-9A-F]{2})+' : preg_quote(encode($page), '/');
		$age_pattern = ($age === null) ?
			'(?:\.([0-9]+))?' : ($age ? "\\.({$age})" : '');
		$pattern = "/^({$page_pattern})_((?:[0-9A-F]{2})+){$age_pattern}$/";

		$matches = [];

		while (($file = readdir($dir)) !== false) {
			if (!preg_match($pattern, $file, $matches)) {
				continue;
			}

			$_page = decode($matches[1]);
			$_file = decode($matches[2]);
			$_age = isset($matches[3]) ? $matches[3] : 0;

			if (!isset($this->pages[$_page])) {
				$this->pages[$_page] = new AttachFiles($_page);
			}
			$this->pages[$_page]->add($_file, $_age);
		}
		closedir($dir);
	}

	public function toString($page = '', $flat = false)
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
