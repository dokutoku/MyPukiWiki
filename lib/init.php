<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// init.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Init PukiWiki here

// PukiWiki version / Copyright / Licence
define('S_VERSION', '1.5.3');
define('S_COPYRIGHT', '<a href="https://gitlab.com/dokutoku/mypukiwiki" rel="external,nofollow">MyPukiWiki</a> developed by <a href="https://twitter.com/dokutoku3" rel="external,nofollow">dokutoku</a>');

/////////////////////////////////////////////////
// Session security options

ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');

/////////////////////////////////////////////////
// Init server variables

// Comapat and suppress notices
if (!isset($HTTP_SERVER_VARS)) {
	$HTTP_SERVER_VARS = [];
}

define('SCRIPT_NAME', (isset($_SERVER['SCRIPT_NAME'])) ? ($_SERVER['SCRIPT_NAME']) : (''));
define('SERVER_ADMIN', (isset($_SERVER['SERVER_ADMIN'])) ? ($_SERVER['SERVER_ADMIN']) : (''));
define('SERVER_NAME', (isset($_SERVER['SERVER_NAME'])) ? ($_SERVER['SERVER_NAME']) : (''));
define('SERVER_PORT', (isset($_SERVER['SERVER_PORT'])) ? ($_SERVER['SERVER_PORT']) : (''));
define('SERVER_SOFTWARE', (isset($_SERVER['SERVER_SOFTWARE'])) ? ($_SERVER['SERVER_SOFTWARE']) : (''));
unset($SCRIPT_NAME, $_SERVER['SCRIPT_NAME'], $HTTP_SERVER_VARS['SCRIPT_NAME']);
unset($SERVER_ADMIN, $_SERVER['SERVER_ADMIN'], $HTTP_SERVER_VARS['SERVER_ADMIN']);
unset($SERVER_NAME, $_SERVER['SERVER_NAME'], $HTTP_SERVER_VARS['SERVER_NAME']);
unset($SERVER_PORT, $_SERVER['SERVER_PORT'], $HTTP_SERVER_VARS['SERVER_PORT']);
unset($SERVER_SOFTWARE, $_SERVER['SERVER_SOFTWARE'], $HTTP_SERVER_VARS['SERVER_SOFTWARE']);


/////////////////////////////////////////////////
// Init grobal variables

// Footnotes
$foot_explain = [];

// Related pages
$related = [];

// XHTML tags in <head></head>
$head_tags = [];

/////////////////////////////////////////////////
// Time settings

define('LOCALZONE', date('Z'));
define('UTIME', time() - LOCALZONE);
define('MUTIME', getmicrotime());

/////////////////////////////////////////////////
// Require INI_FILE

define('INI_FILE', DATA_HOME.'config/pukiwiki.ini.php');
$die = '';

if ((!file_exists(INI_FILE)) || (!is_readable(INI_FILE))) {
	$die .= 'File is not found. (INI_FILE)'."\n";
} else {
	require INI_FILE;
}

if ($die) {
	die_message(nl2br("\n\n".$die));
}

/////////////////////////////////////////////////
// INI_FILE: LANG に基づくエンコーディング設定

// MB_LANGUAGE: mb_language (for mbstring extension)
//   'uni'(means UTF-8), 'English', or 'Japanese'

switch (LANG) {
	case 'en':
		define('MB_LANGUAGE', 'English');

		break;

	case 'ja':
		define('MB_LANGUAGE', 'Japanese');

		break;

	case 'ko':
		define('MB_LANGUAGE', 'Korean');

		break;

	default:
		die_message('No such language "'.LANG.'"');
}

mb_language(MB_LANGUAGE);
mb_internal_encoding('UTF-8');
mb_http_output('pass');
mb_detect_order('auto');

/////////////////////////////////////////////////
// INI_FILE: Require LANG_FILE

// For encoding hint
define('LANG_FILE_HINT', DATA_HOME.'lang/'.LANG.'.lng.php');

// For UI resource
define('LANG_FILE', DATA_HOME.'lang/'.UI_LANG.'.lng.php');

$die = '';

foreach (['LANG_FILE_HINT', 'LANG_FILE'] as $langfile) {
	if ((!file_exists(constant($langfile))) || (!is_readable(constant($langfile)))) {
		$die .= 'File is not found or not readable. ('.$langfile.')'."\n";
	} else {
		require_once constant($langfile);
	}
}

if ($die) {
	die_message(nl2br("\n\n".$die));
}

/////////////////////////////////////////////////
// LANG_FILE: Init encoding hint

define('PKWK_ENCODING_HINT', (isset($_LANG['encode_hint'][LANG])) ? ($_LANG['encode_hint'][LANG]) : (''));
unset($_LANG['encode_hint']);

/////////////////////////////////////////////////
// LANG_FILE: Init severn days of the week

$weeklabels = $_msg_week;

/////////////////////////////////////////////////
// INI_FILE: Init $script

if (isset($script)) {
	// Init manually
	pkwk_script_uri_base(PKWK_URI_ABSOLUTE, true, $script);
} else {
	// Init automatically
	$script = pkwk_script_uri_base(PKWK_URI_ABSOLUTE, true);
}

// INI_FILE: Auth settings
if ((isset($auth_type)) && ($auth_type === AUTH_TYPE_SAML)) {
	$auth_external_login_url_base = get_base_uri().'?//cmd.saml//sso';
}

/////////////////////////////////////////////////
// INI_FILE: $agents:  UserAgentの識別

$ua = 'HTTP_USER_AGENT';
$matches = [];
$user_agent = [];

$user_agent['agent'] = (isset($_SERVER['HTTP_USER_AGENT'])) ? ($_SERVER['HTTP_USER_AGENT']) : ('');

foreach ($agents as $agent) {
	if (preg_match($agent['pattern'], $user_agent['agent'], $matches)) {
		$user_agent['profile'] = (isset($agent['profile'])) ? ($agent['profile']) : ('');

		// device or browser name
		$user_agent['name'] = (isset($matches[1])) ? ($matches[1]) : ('');

		// 's version
		$user_agent['vers'] = (isset($matches[2])) ? ($matches[2]) : ('');

		break;
	}
}

unset($agents, $matches);

// Profile-related init and setting
define('UA_PROFILE', (isset($user_agent['profile'])) ? ($user_agent['profile']) : (''));

define('UA_INI_FILE', DATA_HOME.'config/user_agent/'.UA_PROFILE.'.ini.php');

if ((!file_exists(UA_INI_FILE)) || (!is_readable(UA_INI_FILE))) {
	die_message('UA_INI_FILE for "'.UA_PROFILE.'" not found.');
} else {
	// Also manually
	require UA_INI_FILE;
}

define('UA_NAME', (isset($user_agent['name'])) ? ($user_agent['name']) : (''));
define('UA_VERS', (isset($user_agent['vers'])) ? ($user_agent['vers']) : (''));

// Unset after reading UA_INI_FILE
unset($user_agent);

/////////////////////////////////////////////////
// ディレクトリのチェック

$die = '';

foreach (['DATA_DIR', 'DIFF_DIR', 'BACKUP_DIR', 'CACHE_DIR'] as $dir) {
	if (!is_writable(constant($dir))) {
		$die .= 'Directory is not found or not writable ('.$dir.')'."\n";
	}
}

// 設定ファイルの変数チェック
$temp = '';

$temp .= (!isset($rss_max)) ? ('$rss_max'."\n") : ('');
$temp .= (!isset($page_title)) ? ('$page_title'."\n") : ('');
$temp .= (!isset($note_hr)) ? ('$note_hr'."\n") : ('');
$temp .= (!isset($related_link)) ? ('$related_link'."\n") : ('');
$temp .= (!isset($show_passage)) ? ('$show_passage'."\n") : ('');
$temp .= (!isset($rule_related_str)) ? ('$rule_related_str'."\n") : ('');
$temp .= (!isset($load_template_func)) ? ('$load_template_func'."\n") : ('');

if ($temp) {
	if ($die) {
		$die .= "\n";
	}	// A breath

	$die .= 'Variable(s) not found: (Maybe the old *.ini.php?)'."\n".$temp;
}

$temp = '';

foreach (['LANG', 'PLUGIN_DIR'] as $def) {
	if (!defined($def)) {
		$temp .= $def."\n";
	}
}

if ($temp) {
	if ($die) {
		$die .= "\n";
	}	// A breath

	$die .= 'Define(s) not found: (Maybe the old *.ini.php?)'."\n".$temp;
}

if ($die) {
	die_message(nl2br("\n\n".$die));
}

unset($die, $temp);

/////////////////////////////////////////////////
// 必須のページが存在しなければ、空のファイルを作成する

foreach ([$defaultpage, $whatsnew, $interwiki] as $page) {
	if (!is_page($page)) {
		pkwk_touch_file(get_filename($page));
	}
}

/////////////////////////////////////////////////
// 外部からくる変数のチェック

// Prohibit $_GET attack
foreach (['msg', 'pass'] as $key) {
	if (isset($_GET[$key])) {
		die_message('Sorry, already reserved: '.$key.'=');
	}
}

// Expire risk

//, 'SERVER', 'ENV', 'SESSION', ...
unset($HTTP_GET_VARS, $HTTP_POST_VARS);

// Considered harmful
unset($_REQUEST);

// Remove null character etc.
$_GET = input_filter($_GET);
$_POST = input_filter($_POST);
$_COOKIE = input_filter($_COOKIE);

// 文字コード変換 ($_POST)
// <form> で送信された文字 (ブラウザがエンコードしたデータ) のコードを変換
// POST method は常に form 経由なので、必ず変換する
//
if ((isset($_POST['charset'])) && ($_POST['charset'] != '')) {
	// TrackBack Ping で指定されていることがある
	// うまくいかない場合は自動検出に切り替え
	if (mb_convert_variables('UTF-8', $_POST['charset'], $_POST) !== $_POST['charset']) {
		mb_convert_variables('UTF-8', 'auto', $_POST);
	}
} elseif (!empty($_POST)) {
	// 全部まとめて、自動検出／変換
	mb_convert_variables('UTF-8', 'UTF-8', $_POST);
}

/////////////////////////////////////////////////
// QUERY_STRINGを取得

// cmdもpluginも指定されていない場合は、QUERY_STRINGを
// ページ名かInterWikiNameであるとみなす
$arg = '';

if ((isset($_SERVER['QUERY_STRING'])) && ($_SERVER['QUERY_STRING'] != '')) {
	global $g_query_string;

	$g_query_string = $_SERVER['QUERY_STRING'];
	$arg = &$_SERVER['QUERY_STRING'];
} elseif ((isset($_SERVER['argv'])) && (!empty($_SERVER['argv']))) {
	$arg = &$_SERVER['argv'][0];
}

if ((PKWK_QUERY_STRING_MAX) && (strlen($arg) > PKWK_QUERY_STRING_MAX)) {
	// Something nasty attack?
	pkwk_common_headers();

	// Fake processing, and/or process other threads
	sleep(1);

	echo 'Query string too long';

	exit;
}

// \0 除去
$arg = input_filter($arg);

// unset QUERY_STRINGs
unset($QUERY_STRING, $_SERVER['QUERY_STRING'], $HTTP_SERVER_VARS['QUERY_STRING']);
unset($argv, $_SERVER['argv'], $HTTP_SERVER_VARS['argv']);
unset($argc, $_SERVER['argc'], $HTTP_SERVER_VARS['argc']);

// $_SERVER['REQUEST_URI'] is used at func.php NOW
unset($REQUEST_URI, $HTTP_SERVER_VARS['REQUEST_URI']);

// mb_convert_variablesのバグ(?)対策: 配列で渡さないと落ちる
$arg = [$arg];
mb_convert_variables('UTF-8', 'auto', $arg);
$arg = $arg[0];

/////////////////////////////////////////////////
// QUERY_STRINGを分解してコード変換し、$_GET に上書き

// URI を urlencode せずに入力した場合に対処する
$matches = [];

foreach (explode('&', $arg) as $key_and_value) {
	if ((preg_match('/^([^=]+)=(.+)/', $key_and_value, $matches)) && (mb_detect_encoding($matches[2], mb_detect_order(), true) != 'ASCII')) {
		$_GET[$matches[1]] = $matches[2];
	}
}

unset($matches);

/////////////////////////////////////////////////
// GET, POST, COOKIE

$get = &$_GET;
$post = &$_POST;
$cookie = &$_COOKIE;

// GET + POST = $vars
if (empty($_POST)) {
	// Major pattern: Read-only access via GET
	$vars = &$_GET;
} elseif (empty($_GET)) {
	// Minor pattern: Write access via POST etc.
	$vars = &$_POST;
} else {
	// Considered reliable than $_REQUEST
	$vars = array_merge($_GET, $_POST);
}

/**
 * Parse specified format query_string as params.
 *
 * For example: ?//key1.value2//key2.value2
 */
function parse_query_string_ext(string $query_string) : array
{
	$vars = [];
	$m = null;

	if (preg_match('#^//[^&]*#', $query_string, $m)) {
		foreach (explode('//', $m[0]) as $item) {
			$sp = explode('.', $item, 2);

			if (isset($sp[0])) {
				if (isset($sp[1])) {
					$vars[$sp[0]] = $sp[1];
				} else {
					$vars[$sp[0]] = '';
				}
			}
		}
	}

	return $vars;
}

if ((isset($g_query_string)) && ($g_query_string)) {
	if (substr($g_query_string, 0, 2) === '//') {
		// Parse ?//key.value//key.value format query string
		$vars = array_merge($vars, parse_query_string_ext($g_query_string));
	}
}

// 入力チェック: 'cmd=' and 'plugin=' can't live together
if ((isset($vars['cmd'])) && (isset($vars['plugin']))) {
	die('Using both cmd= and plugin= is not allowed');
}

// 入力チェック: cmd, plugin の文字列は英数字以外ありえない
if ((isset($vars['cmd'])) && (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $vars['cmd']))) {
	unset($get['cmd'], $post['cmd'], $vars['cmd']);
}

if ((isset($vars['plugin'])) && (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $vars['plugin']))) {
	unset($get['plugin'], $post['plugin'], $vars['plugin']);
}

// 整形: page, strip_bracket()
if (isset($vars['page'])) {
	$vars['page'] = strip_bracket($vars['page']);
	$post['page'] = $vars['page'];
	$get['page'] = $vars['page'];
} else {
	$vars['page'] = '';
	$post['page'] = '';
	$get['page'] = '';
}

// 整形: msg, 改行を取り除く
if (isset($vars['msg'])) {
	$vars['msg'] = str_replace("\r", '', $vars['msg']);
	$post['msg'] = $vars['msg'];
	$get['msg'] = $vars['msg'];
}

// 後方互換性 (?md5=...)
if ((isset($get['md5'])) && ($get['md5'] != '') && (!isset($vars['cmd'])) && (!isset($vars['plugin']))) {
	$vars['cmd'] = 'md5';
	$post['cmd'] = 'md5';
	$get['cmd'] = 'md5';
}

// cmdもpluginも指定されていない場合は、QUERY_STRINGをページ名かInterWikiNameであるとみなす
if ((!isset($vars['cmd'])) && (!isset($vars['plugin']))) {
	$vars['cmd'] = 'read';
	$post['cmd'] = 'read';
	$get['cmd'] = 'read';

	$arg = preg_replace('#^([^&]*)&.*$#', '$1', $arg);

	if ($arg == '') {
		$arg = $defaultpage;
	}

	if (strpos($arg, '=') !== false) {
		$arg = $defaultpage;
	} // Found '/?key=value'
	$arg = urldecode($arg);
	$arg = strip_bracket($arg);
	$arg = input_filter($arg);
	$vars['page'] = $arg;
	$post['page'] = $arg;
	$get['page'] = $arg;
}

/////////////////////////////////////////////////
// 初期設定($WikiName,$BracketNameなど)
// $WikiName = '[A-Z][a-z]+(?:[A-Z][a-z]+)+';
// $WikiName = '\b[A-Z][a-z]+(?:[A-Z][a-z]+)+\b';
// $WikiName = '(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])';
// $WikiName = '(?<!\w)(?:[A-Z][a-z]+){2,}(?!\w)';

// 暫定対処
$WikiName = '(?:[A-Z][a-z]+){2,}(?!\w)';

// $BracketName = ':?[^\s\]#&<>":]+:?';
$BracketName = '(?!\s):?[^\r\n\t\f\[\]<>#&":]+:?(?<!\s)';

// InterWiki
$InterWikiName = '(\[\[)?((?:(?!\s|:|\]\]).)+):(.+)(?(1)\]\])';

// 注釈
$NotePattern = '/\(\(((?:(?>(?:(?!\(\()(?!\)\)(?:[^\)]|$)).)+)|(?R))*)\)\)/x';

/////////////////////////////////////////////////
// 初期設定(ユーザ定義ルール読み込み)
require DATA_HOME.'config/rules.ini.php';

/////////////////////////////////////////////////
// Load HTML Entity pattern
// This pattern is created by 'plugin/update_entities.inc.php'
require LIB_DIR.'html_entities.php';

/////////////////////////////////////////////////
// 初期設定(その他のグローバル変数)

// 現在時刻
$now = format_date(UTIME);

// 日時置換ルールを$line_rulesに加える
if ($usedatetime) {
	$line_rules += $datetime_rules;
}

unset($datetime_rules);

// フェイスマークを$line_rulesに加える
if ($usefacemark) {
	$line_rules += $facemark_rules;
}

unset($facemark_rules);

// 実体参照パターンおよびシステムで使用するパターンを$line_rulesに加える
$line_rules = array_merge(
	[
		'&amp;(#[0-9]+|#x[0-9a-f]+|'.get_html_entity_pattern().');'=>'&$1;',

		// 行末にチルダは改行
		"\r"=>'<br />'."\n",
	],
	$line_rules);
