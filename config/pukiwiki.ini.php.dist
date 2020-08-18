<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// pukiwiki.ini.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// PukiWiki main setting file

/////////////////////////////////////////////////
// Functionality settings

// PKWK_OPTIMISE - Ignore verbose but understandable checking and warning
//   If you end testing this PukiWiki, set '1'.
//   If you feel in trouble about this PukiWiki, set '0'.
if (!defined('PKWK_OPTIMISE')) {
	define('PKWK_OPTIMISE', 0);
}

/////////////////////////////////////////////////
// Security settings

// PKWK_READONLY - Prohibits editing and maintain via WWW
//   NOTE: Counter-related functions will work now (counter, attach count, etc)
if (!defined('PKWK_READONLY')) {
	// 0 or 1
	define('PKWK_READONLY', 0);
}

// PKWK_SAFE_MODE - Prohibits some unsafe(but compatible) functions
if (!defined('PKWK_SAFE_MODE')) {
	define('PKWK_SAFE_MODE', 0);
}

// PKWK_DISABLE_INLINE_IMAGE_FROM_URI - Disallow using inline-image-tag for URIs
//   Inline-image-tag for URIs may allow leakage of Wiki readers' information
//   (in short, 'Web bug') or external malicious CGI (looks like an image's URL)
//   attack to Wiki readers, but easy way to show images.
if (!defined('PKWK_DISABLE_INLINE_IMAGE_FROM_URI')) {
	define('PKWK_DISABLE_INLINE_IMAGE_FROM_URI', 0);
}

// PKWK_QUERY_STRING_MAX
//   Max length of GET method, prohibits some worm attack ASAP
//   NOTE: Keep (page-name + attach-file-name) <= PKWK_QUERY_STRING_MAX
// Bytes, 0 = OFF
define('PKWK_QUERY_STRING_MAX', 640);

/////////////////////////////////////////////////
// Experimental features

// Multiline plugin hack
// EXAMPLE(with a known BUG):
//   #plugin(args1,args2,...,argsN){{
//   argsN+1
//   argsN+1
//   #memo(foo)
//   argsN+1
//   }}
//   #memo(This makes '#memo(foo)' to this)
// 1 = Disabled
define('PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK', 1);

/////////////////////////////////////////////////
// Language / Encoding settings

// LANG - Internal content encoding ('en', 'ja', or ...)
define('LANG', 'ja');

// UI_LANG - Content encoding for buttons, menus,  etc
// 'en' for Internationalized wikisite
define('UI_LANG', LANG);

/////////////////////////////////////////////////
// Directory settings I (ended with '/', permission '777')

// You may hide these directories (from web browsers)
// by setting DATA_HOME at index.php.

// Latest wiki texts
define('DATA_DIR', DATA_HOME.'wiki/');

// Latest diffs
define('DIFF_DIR', DATA_HOME.'diff/');

// Backups
define('BACKUP_DIR', DATA_HOME.'backup/');

// Some sort of caches
define('CACHE_DIR', DATA_HOME.'cache/');

// Attached files and logs
define('UPLOAD_DIR', DATA_HOME.'attach/');

// Counter plugin's counts
define('COUNTER_DIR', DATA_HOME.'counter/');

// Plugin directory
define('PLUGIN_DIR', DATA_HOME.'plugin/');

/////////////////////////////////////////////////
// Directory settings II (ended with '/')

// Skins / Stylesheets
define('SKIN_DIR', 'skin/');
// Skin files (SKIN_DIR/*.skin.php) are needed at
// ./DATAHOME/SKIN_DIR from index.php, but
// CSSs(*.css) and JavaScripts(*.js) are needed at
// ./SKIN_DIR from index.php.

// Static image files
define('IMAGE_DIR', 'image/');
// Keep this directory shown via web browsers like
// ./IMAGE_DIR from index.php.

/////////////////////////////////////////////////
// Local time setting

// or specifiy one
switch (LANG) {
	case 'ja':
		define('ZONE', 'JST');

		// JST = GMT + 9
		define('ZONETIME', 9 * 3600);

		break;

	default:
		define('ZONE', 'GMT');
		define('ZONETIME', 0);

		break;
}

/////////////////////////////////////////////////
// Title of your Wikisite (Name this)
// Also used as RSS feed's channel name etc
$page_title = 'PukiWiki';

// Specify PukiWiki URL (default: auto)
//$script = 'http://example.com/pukiwiki/';

// Shorten $script: Cut its file name (default: not cut)
//$script_directory_index = 'index.php';

// Site admin's name (CHANGE THIS)
$modifier = 'anonymous';

// Site admin's Web page (CHANGE THIS)
$modifierlink = 'http://pukiwiki.example.com/';

// Default page name

// Top / Default page
$defaultpage = 'FrontPage';

// Modified page list
$whatsnew = 'RecentChanges';

// Removeed page list
$whatsdeleted = 'RecentDeleted';

// Set InterWiki definition here
$interwiki = 'InterWikiName';

// Set AutoAlias definition here
$aliaspage = 'AutoAliasName';

// Menu
$menubar = 'MenuBar';

// RightBar
$rightbar_name = 'RightBar';

/////////////////////////////////////////////////
// Change default Document Type Definition

// Some web browser's bug, and / or Java apprets may needs not-Strict DTD.
// Some plugin (e.g. paint) set this PKWK_DTD_XHTML_1_0_TRANSITIONAL.

// Default
//$pkwk_dtd = PKWK_DTD_XHTML_1_1;

//$pkwk_dtd = PKWK_DTD_XHTML_1_0_STRICT;
//$pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
//$pkwk_dtd = PKWK_DTD_HTML_4_01_STRICT;
//$pkwk_dtd = PKWK_DTD_HTML_4_01_TRANSITIONAL;

/////////////////////////////////////////////////
// Always output "nofollow,noindex" attribute

// 1 = Try hiding from search engines
$nofollow = 0;

/////////////////////////////////////////////////

// PKWK_ALLOW_JAVASCRIPT - Must be 1 only for compatibility
define('PKWK_ALLOW_JAVASCRIPT', 1);

/////////////////////////////////////////////////
// _Disable_ WikiName auto-linking
$nowikiname = 0;

/////////////////////////////////////////////////
// AutoLink feature
// Automatic link to existing pages

// AutoLink minimum length of page name
// Bytes, 0 = OFF (try 8)
$autolink = 0;

/////////////////////////////////////////////////
// AutoAlias feature
// Automatic link from specified word, to specifiled URI, page or InterWiki

// AutoAlias minimum length of alias "from" word
// Bytes, 0 = OFF (try 8)
$autoalias = 0;

// Limit loading valid alias pairs
// pairs
$autoalias_max_words = 50;

/////////////////////////////////////////////////
// Enable Freeze / Unfreeze feature
$function_freeze = 1;

/////////////////////////////////////////////////
// Allow to use 'Do not change timestamp' checkbox
// (0:Disable, 1:For everyone,  2:Only for the administrator)
$notimeupdate = 1;

/////////////////////////////////////////////////
// Admin password for this Wikisite

// Default: always fail
$adminpass = '{x-php-md5}!';

// Sample:

// Cleartext
//$adminpass = 'pass';

// PHP md5()  'pass'
//$adminpass = '{x-php-md5}1a1dc91c907325c69271ddf0c944bc72';

// PHP sha256  'pass'
//$adminpass = '{x-php-sha256}d74ff0ee8da3b9806b18c877dbf29bbde50b5bd8e4dad7a3a725000feb82e8f1';

// LDAP CRYPT 'pass'
//$adminpass = '{CRYPT}$1$AR.Gk94x$uCe8fUUGMfxAPH83psCZG/';

// LDAP MD5   'pass'
//$adminpass = '{MD5}Gh3JHJBzJcaScd3wyUS8cg==';

// LDAP SMD5  'pass'
//$adminpass = '{SMD5}o7lTdtHFJDqxFOVX09C8QnlmYmZnd2Qx';

// LDAP SHA256 'pass'
//$adminpass = '{SHA256}10/w7o2juYBrGMh32/KbveULW9jk2tejpyUAD+uC6PE='

/////////////////////////////////////////////////
// Page-reading feature settings
// (Automatically creating pronounce datas, for Kanji-included page names,
//  to show sorted page-list correctly)

// Enable page-reading feature by calling ChaSen or KAKASHI command
// (1:Enable, 0:Disable)
$pagereading_enable = 0;

// Specify converter as ChaSen('chasen') or KAKASI('kakasi') or None('none')
$pagereading_kanji2kana_converter = 'none';

// Specify Kanji encoding to pass data between PukiWiki and the converter

// Default for Unix
$pagereading_kanji2kana_encoding = 'EUC';

// Default for Windows
//$pagereading_kanji2kana_encoding = 'SJIS';

// Absolute path of the converter (ChaSen)
$pagereading_chasen_path = '/usr/local/bin/chasen';
//$pagereading_chasen_path = 'c:\progra~1\chasen21\chasen.exe';

// Absolute path of the converter (KAKASI)
$pagereading_kakasi_path = '/usr/local/bin/kakasi';
//$pagereading_kakasi_path = 'c:\kakasi\bin\kakasi.exe';

// Page name contains pronounce data (written by the converter)
$pagereading_config_page = ':config/PageReading';

// Page name of default pronouncing dictionary, used when converter = 'none'
$pagereading_config_dict = ':config/PageReading/dict';

/////////////////////////////////////////////////
// Authentication type
// AUTH_TYPE_NONE, AUTH_TYPE_FORM, AUTH_TYPE_BASIC, AUTH_TYPE_EXTERNAL, ...
// $auth_type = AUTH_TYPE_FORM;
// $auth_external_login_url_base = './exlogin.php';

/////////////////////////////////////////////////
// LDAP

// (0: Disabled, 1: Enabled)
$ldap_user_account = 0;

// $ldap_server = 'ldap://ldapserver:389';
// $ldap_base_dn = 'ou=Users,dc=ldap,dc=example,dc=com';
// $ldap_bind_dn = 'uid=$login,dc=example,dc=com';
// $ldap_bind_password = '';

/////////////////////////////////////////////////
// User prefix that shows its auth provider
$auth_provider_user_prefix_default = 'default:';
$auth_provider_user_prefix_ldap = 'ldap:';
$auth_provider_user_prefix_external = 'external:';
$auth_provider_user_prefix_saml = 'saml:';

/////////////////////////////////////////////////
// User definition
$auth_users =
[
	// Username=>password

	// Cleartext
	'foo'=>'foo_passwd',

	// PHP md5() 'bar_passwd'
	'bar'=>'{x-php-md5}f53ae779077e987718cc285b14dfbe86',

	// LDAP SMD5 'hoge_passwd'
	'hoge'=>'{SMD5}OzJo/boHwM4q5R+g7LCOx2xGMkFKRVEx',
];

// Group definition
$auth_groups =
[
	// Groupname=>group members(users)

	// Reserved 'valid-user' group contains all authenticated users
	'valid-user'=>'',

	'groupfoobar'=>'foo,bar',
];

/////////////////////////////////////////////////
// Authentication method

// By Page name
$auth_method_type = 'pagename';

// By Page contents
//$auth_method_type	= 'contents';

/////////////////////////////////////////////////
// Read auth (0:Disable, 1:Enable)
$read_auth = 0;

$read_auth_pages =
[
	// Regex		   Groupname or Username
	'#PageForAllValidUsers#'=>'valid-user',
	'#HogeHoge#'=>'hoge',
	'#(NETABARE|NetaBare)#'=>'foo,bar,hoge',
];

/////////////////////////////////////////////////
// Edit auth (0:Disable, 1:Enable)
$edit_auth = 0;

$edit_auth_pages =
[
	// Regex		   Username
	'#BarDiary#'=>'bar',
	'#HogeHoge#'=>'hoge',
	'#(NETABARE|NetaBare)#'=>'foo,bar,hoge',
];

/////////////////////////////////////////////////
// Search auth
// 0: Disabled (Search read-prohibited page contents)
// 1: Enabled  (Search only permitted pages for the user)
$search_auth = 0;

/////////////////////////////////////////////////
// AutoTicketLink
$ticket_link_sites =
[
	/*
	[
		'key'=>'phpbug',

		// type: redmine, jira or git
		'type'=>'redmine',

		'title'=>'PHP :: Bug #$1',
		'base_url'=>'https://bugs.php.net/bug.php?id=',
	],
	[
		'key'=>'asfjira',
		'type'=>'jira',
		'title'=>'ASF JIRA [$1]',
		'base_url'=>'https://issues.apache.org/jira/browse/',
	],
	[
		'key'=>'pukiwiki-commit',
		'type'=>'git',
		'title'=>'PukiWiki revision $1',
		'base_url'=>'https://ja.osdn.net/projects/pukiwiki/scm/git/pukiwiki/commits/',
	],
*/
];

// AutoTicketLink - JIRA Default site
/*
$ticket_jira_default_site =
[
	'title'=>'My JIRA - $1',
	'base_url'=>'https://issues.example.com/jira/browse/',
];

//*/

/////////////////////////////////////////////////
// Show External Link Cushion Page
// 0: Disabled
// 1: Enabled
$external_link_cushion_page = 0;

$external_link_cushion =
[
	// Wait N seconds before jumping to an external site
	'wait_seconds'=>5,

	// Internal site domain list
	'internal_domains'=>
	[
		'localhost',
		// '*.example.com',
	],

	// Don't show extenal link icons on these domains
	'silent_external_domains'=>
	[
		'pukiwiki.osdn.jp',
		'pukiwiki.example.com',
	],
];

/////////////////////////////////////////////////
// Show Topicpath title
// 0: Disabled
// 1: Enabled
$topicpath_title = 1;

/////////////////////////////////////////////////
// Output HTML meta Referrer Policy
// Value: '' (default), no-referrer, origin, same-origin, ...
// Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy
$html_meta_referrer_policy = '';

/////////////////////////////////////////////////
// Output custom HTTP response headers
$http_response_custom_headers =
[
	// 'Strict-Transport-Security: max-age=86400',
	// 'X-Content-Type-Options: nosniff',
];

/////////////////////////////////////////////////
// $whatsnew: Max number of RecentChanges
$maxshow = 500;

// $whatsdeleted: Max number of RecentDeleted
// (0 = Disabled)
$maxshow_deleted = 200;

/////////////////////////////////////////////////
// Page names can't be edit via PukiWiki
$cantedit = [$whatsnew, $whatsdeleted];

/////////////////////////////////////////////////
// HTTP: Output Last-Modified header
$lastmod = 0;

/////////////////////////////////////////////////
// Date format
$date_format = 'Y-m-d';

// Time format
$time_format = 'H:i:s';

/////////////////////////////////////////////////
// Max number of RSS feed
$rss_max = 15;

/////////////////////////////////////////////////
// Backup related settings

// Enable backup
$do_backup = 1;

// When a page had been removed, remove its backup too?
$del_backup = 0;

// Bacukp interval and generation

// Wait N hours between backup (0 = no wait)
$cycle = 3;

// Stock latest N backups
$maxage = 120;

// NOTE: $cycle x $maxage / 24 = Minimum days to lost your data
//          3   x   120   / 24 = 15

// Splitter of backup data (NOTE: Too dangerous to change)
define('PKWK_SPLITTER', '>>>>>>>>>>');

/////////////////////////////////////////////////
// Command execution per update

define('PKWK_UPDATE_EXEC', '');

// Sample: Namazu (Search engine)
//$target     = '/var/www/wiki/';
//$mknmz      = '/usr/bin/mknmz';
//$output_dir = '/var/lib/namazu/index/';
//define('PKWK_UPDATE_EXEC',
//	$mknmz . ' --media-type=text/pukiwiki' .
//	' -O ' . $output_dir . ' -L ja -c -K ' . $target);

/////////////////////////////////////////////////
// HTTP proxy setting

// Use HTTP proxy server to get remote data
$use_proxy = 0;

$proxy_host = 'proxy.example.com';
$proxy_port = 8080;

// Do Basic authentication
$need_proxy_auth = 0;
$proxy_auth_user = 'username';
$proxy_auth_pass = 'password';

// Hosts that proxy server will not be needed
$no_proxy =
[
	// localhost
	'localhost',

	// loopback
	'127.0.0.0/8',

	// private class A
	//	'10.0.0.0/8'

	// private class B
	//	'172.16.0.0/12'

	// private class C
	//	'192.168.0.0/16'

	//	'no-proxy.com',
];

////////////////////////////////////////////////
// Mail related settings

// Send mail per update of pages
$notify = 0;

// Send diff only
$notify_diff_only = 1;

// SMTP server (Windows only. Usually specified at php.ini)
$smtp_server = 'localhost';

// Mail recipient (To:) and sender (From:)

// To:
$notify_to = 'to@example.com';

// From:
$notify_from = 'from@example.com';

// Subject: ($page = Page name wll be replaced)
$notify_subject = '[PukiWiki] $page';

// Mail header
// NOTE: Multiple items must be divided by "\r\n", not "\n".
$notify_header = '';

/////////////////////////////////////////////////
// Mail: POP / APOP Before SMTP

// Do POP/APOP authentication before send mail
$smtp_auth = 0;

$pop_server = 'localhost';
$pop_port = 110;
$pop_userid = '';
$pop_passwd = '';

// Use APOP instead of POP (If server uses)
//   Default = Auto (Use APOP if possible)
//   1       = Always use APOP
//   0       = Always use POP
// $pop_auth_use_apop = 1;

/////////////////////////////////////////////////
// Ignore list

// Regex of ignore pages
$non_list = '^\:';

// Search ignored pages
$search_non_list = 1;

// Page redirect rules
$page_redirect_rules =
[
	//'#^FromProject($|(/(.+)$))#'=>'ToProject$1',
	//'#^FromProject($|(/(.+)$))#'=>function($matches) { return 'ToProject' . $matches[1]; },
];

/////////////////////////////////////////////////
// Template setting

$auto_template_func = 1;
$auto_template_rules =
[
	'((.+)\/([^\/]+))'=>'\2/template',
];

/////////////////////////////////////////////////
// Automatically add fixed heading anchor
$fixed_heading_anchor = 1;

/////////////////////////////////////////////////
// Remove the first spaces from Preformatted text
$preformat_ltrim = 1;

/////////////////////////////////////////////////
// Convert linebreaks into <br />
$line_break = 0;

/////////////////////////////////////////////////
// Use date-time rules (See rules.ini.php)
$usedatetime = 1;

/////////////////////////////////////////////////
// Logging updates (0 or 1)
$logging_updates = 0;
$logging_updates_log_dir = '/var/log/pukiwiki';

/////////////////////////////////////////////////
// User-Agent settings
//
// If you want to ignore embedded browsers for rich-content-wikisite,
// remove (or comment-out) all 'keitai' settings.
//
// If you want to to ignore desktop-PC browsers for simple wikisite,
// copy keitai.ini.php to default.ini.php and customize it.

$agents =
[
	// pattern: A regular-expression that matches device(browser)'s name and version
	// profile: A group of browsers

	// Embedded browsers (Rich-clients for PukiWiki)

	// Windows CE (Microsoft(R) Internet Explorer 5.5 for Windows(R) CE)
	// Sample: "Mozilla/4.0 (compatible; MSIE 5.5; Windows CE; sigmarion3)" (sigmarion, Hand-held PC)
	['pattern'=>'#\b(?:MSIE [5-9]).*\b(Windows CE)\b#', 'profile'=>'default'],

	// ACCESS "NetFront" / "Compact NetFront" and thier OEM, expects to be "Mozilla/4.0"
	// Sample: "Mozilla/4.0 (PS2; PlayStation BB Navigator 1.0) NetFront/3.0" (PlayStation BB Navigator, for SONY PlayStation 2)
	// Sample: "Mozilla/4.0 (PDA; PalmOS/sony/model crdb/Revision:1.1.19) NetFront/3.0" (SONY Clie series)
	// Sample: "Mozilla/4.0 (PDA; SL-A300/1.0,Embedix/Qtopia/1.1.0) NetFront/3.0" (SHARP Zaurus)
	['pattern'=>'#^(?:Mozilla/4).*\b(NetFront)/([0-9\.]+)#',	'profile'=>'default'],

	// Desktop-PC browsers

	// Opera (for desktop PC, not embedded)
	// NOTE: Keep this pattern above MSIE and Mozilla
	// Sample: "Opera/7.0 (OS; U)" (not disguise)
	// Sample: "Mozilla/4.0 (compatible; MSIE 5.0; OS) Opera 6.0" (disguise)
	['pattern'=>'#\b(Opera)[/ ]([0-9\.]+)\b#',	'profile'=>'default'],

	// MSIE: Microsoft Internet Explorer (or something disguised as MSIE)
	// Sample: "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)"
	['pattern'=>'#\b(MSIE) ([0-9\.]+)\b#',	'profile'=>'default'],

	// Mozilla Firefox
	// NOTE: Keep this pattern above Mozilla
	// Sample: "Mozilla/5.0 (Windows; U; Windows NT 5.0; ja-JP; rv:1.7) Gecko/20040803 Firefox/0.9.3"
	['pattern'=>'#\b(Firefox)/([0-9\.]+)\b#',	'profile'=>'default'],

	// Loose default: Including something Mozilla
	['pattern'=>'#^([a-zA-z0-9 ]+)/([0-9\.]+)\b#',	'profile'=>'default'],

	// Sentinel
	['pattern'=>'#^#',	'profile'=>'default'],
];
