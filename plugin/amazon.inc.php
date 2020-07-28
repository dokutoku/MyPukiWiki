<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// amazon.inc.php
// Copyright
//	2004-2017 PukiWiki Development Team
//	2003 閑舎 <raku@rakunet.org> (Original author)
// License: GPL v2 or (at your option) any later version
//
// Amazon plugin: Book-review maker via amazon.com/amazon.jp

// ChangeLog:
// * 2004/04/03 PukiWiki Developer Team (arino <arino@users.osdn.me>)
//        - replace plugin_amazon_get_page().
//        - PLUGIN_AMAZON_XML 'xml.amazon.com' -> 'xml.amazon.co.jp'
// * 0.6  URL が存在しない場合、No image を表示、画像配置など修正。
//        インラインプラグインの呼び出し方を修正。
//	  ASIN 番号部分をチェックする。
//	  画像、タイトルのキャッシュによる速度の大幅アップ。
// * 0.7  ブックレビュー生成のデバッグ、認証問題の一応のクリア。
// * 0.8  amazon 全商品の画像を表示。
//	  アソシエイト ID に対応。
// * 0.9  RedHat9+php4.3.2+apache2.0.46 で画像が途中までしか読み込まれない問題に対処。
//        日本語ページの下にブックレビューを作ろうとすると文字化けして作れない問題の解決。
//        書籍でなく CD など、ASIN 部分が長くてもタイトルをうまく拾うようにする。
//        写影のみ取り込むのでなければ、B000002G6J.01 と書かず B000002G6J と書いても写影が出るようにする。
//	  ASIN に対応するキャッシュ画像/キャッシュタイトルをそれぞれ削除する機能追加。
//	  proxy 対応(試験的)。
//	  proxy 実装の過程で一般ユーザのための AID はなくとも自動生成されることがわかり、削除した。
// * 1.0  ブックレビューでなく、レビューとする。
//        画像のキャッシュを削除する期限を設ける。
//        タイトル、写影を Web Services の XML アクセスの方法によって get することで時間を短縮する。
//        レビューページ生成のタイミングについて注を入れる。
// * 1.1  編集制限をかけている場合、部外者がレビューを作ろうとして、ページはできないが ASIN4774110655.tit などのキャッシュができるのを解決。
//        画像の最後が 01 の場合、image を削除すると noimage.jpg となってしまうバグを修正。
//        1.0 で導入した XML アクセスは高速だが、返す画像情報がウソなので、09 がだめなら 01 をトライする、で暫定的に解決。
//
// Caution!:
// * 著作権が関連する為、www.amazon.co.jp のアソシエイトプログラムを確認の上ご利用下さい。
// * レビューは、amazon プラグインが呼び出す編集画面はもう出来て PukiWiki に登録されているので、
//   中止するなら全文を削除してページの更新ボタンを押すこと。
// * 下の PLUGIN_AMAZON_AID、PROXY サーバの部分、expire の部分を適当に編集して使用してください(他はそのままでも Ok)。
//
// Thanks to: Reimy and PukiWiki Developers Team
//

/////////////////////////////////////////////////
// Settings

// Amazon associate ID
// None
//define('PLUGIN_AMAZON_AID','');

define('PLUGIN_AMAZON_AID', '');

// Expire caches per ? days
define('PLUGIN_AMAZON_EXPIRE_IMAGECACHE', 1);
define('PLUGIN_AMAZON_EXPIRE_TITLECACHE', 356);

// Alternative image for 'Image not found'
define('PLUGIN_AMAZON_NO_IMAGE', IMAGE_DIR.'noimage.png');

// URI prefixes
switch (LANG) {
	case 'ja':
		// Amazon shop
		define('PLUGIN_AMAZON_SHOP_URI', 'http://www.amazon.co.jp/exec/obidos/ASIN/');

		// Amazon information inquiry (dev-t = default value in the manual)
		define('PLUGIN_AMAZON_XML', 'http://xml.amazon.co.jp/onca/xml3?t=webservices-20&dev-t=GTYDRES564THU&type=lite&page=1&f=xml&locale=jp&AsinSearch=');

		break;

	default:
		// Amazon shop
		define('PLUGIN_AMAZON_SHOP_URI', 'http://www.amazon.com/exec/obidos/ASIN/');

		// Amazon information inquiry (dev-t = default value in the manual)
		define('PLUGIN_AMAZON_XML', 'http://xml.amazon.com/onca/xml3?t=webservices-20&dev-t=GTYDRES564THU&type=lite&page=1&f=xml&locale=us&AsinSearch=');

		break;
}

/////////////////////////////////////////////////

function plugin_amazon_init() : void
{
	global $amazon_aid;
	global $amazon_body;

	if (PLUGIN_AMAZON_AID == '') {
		$amazon_aid = '';
	} else {
		$amazon_aid = PLUGIN_AMAZON_AID.'/';
	}

	$amazon_body = <<<'EOD'
-作者: [[ここ編集のこと]]
-評者: お名前
-日付: &date;
**お薦め対象
[[ここ編集のこと]]

#amazon(,clear)
**感想
[[ここ編集のこと]]

// まず、このレビューを止める場合、全文を削除し、ページの[更新ボタン]を押してください！(PukiWiki にはもう登録されています)
// 続けるなら、上の、[[ここ編集のこと]]部分を括弧を含めて削除し、書き直してください。
// お名前、部分はご自分の名前に変更してください。私だと、閑舎、です。
// **お薦め対象、より上は、新しい行を追加しないでください。目次作成に使用するので。
// //で始まるコメント行は、最終的に全部カットしてください。目次が正常に作成できない可能性があります。
#comment
EOD;
}

function plugin_amazon_convert(string ...$aryargs)
{
	global $vars;
	global $asin;
	global $asin_all;

	$script = get_base_uri();

	if (func_num_args() > 3) {
		if (PKWK_READONLY) {
			// Show nothing
			return '';
		}

		return '#amazon([ASIN-number][,left|,right][,book-title|,image|,delimage|,deltitle|,delete])';
	} elseif (func_num_args() == 0) {
		// レビュー作成
		if (PKWK_READONLY) {
			// Show nothing
			return '';
		}

		$s_page = htmlspecialchars($vars['page'], ENT_COMPAT, 'UTF-8');

		if ($s_page == '') {
			$s_page = (isset($vars['refer'])) ? ($vars['refer']) : ('');
		}

		$ret = <<<EOD
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="amazon" />
		<input type="hidden" name="refer" value="{$s_page}" />
		ASIN:
		<input type="text" name="asin" size="30" value="" />
		<input type="submit" value="レビュー編集" /> (ISBN 10 桁 or ASIN 12 桁)
	</div>
</form>
EOD;

		return $ret;
	}

	$align = strtolower($aryargs[1]);

	if ($align == 'clear') {
		// 改行挿入
		return '<div style="clear:both"></div>';
	}

	if ($align != 'left') {
		$align = 'right';
	} // 配置決定

	// for XSS
	$asin_all = htmlspecialchars($aryargs[0], ENT_COMPAT, 'UTF-8');

	if ((is_asin() == false) && ($align != 'clear')) {
		return false;
	}

	if ($aryargs[2] != '') {
		// タイトル指定

		// for XSS
		$alt = htmlspecialchars($aryargs[2], ENT_COMPAT, 'UTF-8');

		$title = $alt;

		if ($alt == 'image') {
			$alt = plugin_amazon_get_asin_title();

			if ($alt == '') {
				return false;
			}

			$title = '';
		} elseif ($alt == 'delimage') {
			if (unlink(CACHE_DIR.'ASIN'.$asin.'.jpg')) {
				return 'Image of '.$asin.' deleted...';
			} else {
				return 'Image of '.$asin.' NOT DELETED...';
			}
		} elseif ($alt == 'deltitle') {
			if (unlink(CACHE_DIR.'ASIN'.$asin.'.tit')) {
				return 'Title of '.$asin.' deleted...';
			} else {
				return 'Title of '.$asin.' NOT DELETED...';
			}
		} elseif ($alt == 'delete') {
			if (((unlink(CACHE_DIR.'ASIN'.$asin.'.jpg')) && (unlink(CACHE_DIR.'ASIN'.$asin.'.tit')))) {
				return 'Title and Image of '.$asin.' deleted...';
			} else {
				return 'Title and Image of '.$asin.' NOT DELETED...';
			}
		}
	} else {
		// タイトル自動取得
		$title = plugin_amazon_get_asin_title();
		$alt = $title;

		if ($alt == '') {
			return false;
		}
	}

	return plugin_amazon_print_object($align, $alt, $title);
}

function plugin_amazon_action() : array
{
	global $vars;
	global $edit_auth;
	global $edit_auth_users;
	global $amazon_body;
	global $asin;
	global $asin_all;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	$s_page = (isset($vars['refer'])) ? ($vars['refer']) : ('');
	$asin_all = (isset($vars['asin'])) ? (htmlspecialchars(rawurlencode(strip_bracket($vars['asin'])), ENT_COMPAT, 'UTF-8')) : ('');

	if (is_asin()) {
		$r_page = $s_page.'/'.$asin;
		$r_page_url = rawurlencode($r_page);
		$auth_user = (isset($_SERVER['PHP_AUTH_USER'])) ? ($_SERVER['PHP_AUTH_USER']) : ('');

		pkwk_headers_sent();

		if (($edit_auth) && (($auth_user == '') || (!isset($edit_auth_users[$auth_user])) || ($edit_auth_users[$auth_user] != $_SERVER['PHP_AUTH_PW']))) {
			// Edit-auth failed. Just look the page
			header('Location: '.get_page_uri($r_page, PKWK_URI_ROOT));
		} else {
			$title = plugin_amazon_get_asin_title();

			if (($title == '') || (preg_match('#^/#', $s_page))) {
				// Invalid page name
				header('Location: '.get_page_uri($s_page, PKWK_URI_ROOT));
			} else {
				$body = '#amazon('.$asin_all.',,image)'."\n".'*'.$title."\n".$amazon_body;
				plugin_amazon_review_save($r_page, $body);
				header('Location: '.get_base_uri(PKWK_URI_ROOT).'?cmd=edit&page='.$r_page_url);
			}
		}

		exit;
	}

	$retvars['msg'] = 'ブックレビュー編集';
	$retvars['refer'] = &$s_page;
	$retvars['body'] = plugin_amazon_convert();

	return $retvars;
}

function plugin_amazon_inline(string ...$args)
{
	global $amazon_aid;
	global $asin;
	global $asin_all;

	assert(func_num_args() >= 1);

	$asin_all = $args[0];

	// for XSS
	$asin_all = htmlspecialchars($asin_all, ENT_COMPAT, 'UTF-8');

	if (!is_asin()) {
		return false;
	}

	$title = plugin_amazon_get_asin_title();

	if ($title == '') {
		return false;
	} else {
		return '<a href="'.PLUGIN_AMAZON_SHOP_URI.$asin.'/'.$amazon_aid.'ref=nosim">'.$title.'</a>'."\n";
	}
}

function plugin_amazon_print_object($align, $alt, $title) : string
{
	global $amazon_aid;
	global $asin;
	global $asin_ext;
	global $asin_all;

	$url = plugin_amazon_cache_image_fetch(CACHE_DIR);
	$url_shop = PLUGIN_AMAZON_SHOP_URI.$asin.'/'.$amazon_aid.'ref=nosim';
	$center = 'text-align:center';

	if ($title == '') {
		// Show image only
		$div = '<div style="float:'.$align.';margin:16px 16px 16px 16px;'.$center.'">'."\n";
		$div .= "\t".'<a href="'.$url_shop.'"><img src="'.$url.'" alt="'.$alt.'" decoding="async" /></a>'."\n";
		$div .= '</div>'."\n";
	} else {
		// Show image and title
		$div = '<div style="float:'.$align.';padding:.5em 1.5em .5em 1.5em;'.$center.'">'."\n";
		$div .= "\t".'<table style="width:110px;border:0;'.$center.'">'."\n";
		$div .= "\t\t".'<tr><td style="'.$center.'">'."\n";
		$div .= "\t\t\t".'<a href="'.$url_shop.'"><img src="'.$url.'" alt="'.$alt.'" decoding="async" /></a></td></tr>'."\n";
		$div .= "\t\t".'<tr><td style="'.$center.'"><a href="'.$url_shop.'">'.$title.'</a></td></tr>'."\n";
		$div .= "\t".'</table>'."\n";
		$div .= '</div>'."\n";
	}

	return $div;
}

function plugin_amazon_get_asin_title() : string
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	if ($asin_all == '') {
		return '';
	}

	$nocachable = 0;
	$nocache = 0;

	$url = PLUGIN_AMAZON_XML.$asin;

	if ((file_exists(CACHE_DIR) === false) || (is_writable(CACHE_DIR) === false)) {
		// キャッシュ不可の場合
		$nocachable = 1;
	}

	if (($title = plugin_amazon_cache_title_fetch(CACHE_DIR)) == false) {
		// キャッシュ見つからず
		$nocache = 1;

		// しかたないので取りにいく
		$body = plugin_amazon_get_page($url);

		$tmpary = [];
		$body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
		preg_match('/<ProductName>([^<]*)</', $body, $tmpary);
		$title = trim($tmpary[1]);
//		$tmpary[1] = '';
//		preg_match('#<ImageUrlMedium>https://images-na.ssl-images-amazon.com/images/P/[^.]+\.(..)\.#',
//			$body, $tmpary);
//		if ($tmpary[1] != '') {
//			$asin_ext = $tmpary[1];
//			$asin_all = $asin . $asin_ext;
//		}
	}

	if ($title == '') {
		return '';
	} else {
		if (($nocache == 1) && ($nocachable != 1)) {
			plugin_amazon_cache_title_save($title, CACHE_DIR);
		}

		return $title;
	}
}

// タイトルキャッシュがあるか調べる
function plugin_amazon_cache_title_fetch(string $dir)
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$filename = $dir.'ASIN'.$asin.'.tit';

	$get_tit = 0;

	if (!is_readable($filename)) {
		$get_tit = 1;
	} elseif ((PLUGIN_AMAZON_EXPIRE_TITLECACHE * 3600 * 24) < (time() - filemtime($filename))) {
		$get_tit = 1;
	}

	if ($get_tit) {
		return false;
	}

	if (($fp = @fopen($filename, 'r')) === false) {
		return false;
	}

	$title = fgets($fp, 4096);
//	$tmp_ext = fgets($fp, 4096);
//	if ($tmp_ext != '') $asin_ext = $tmp_ext;
	fclose($fp);

	if (strlen($title) > 0) {
		return $title;
	} else {
		return false;
	}
}

// 画像キャッシュがあるか調べる
function plugin_amazon_cache_image_fetch(string $dir)
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$filename = $dir.'ASIN'.$asin.'.jpg';

	$get_img = 0;

	if (!is_readable($filename)) {
		$get_img = 1;
	} elseif ((PLUGIN_AMAZON_EXPIRE_IMAGECACHE * 3600 * 24) < (time() - filemtime($filename))) {
		$get_img = 1;
	}

	if ($get_img) {
		$url = 'https://images-na.ssl-images-amazon.com/images/P/'.$asin.'.'.$asin_ext.'.MZZZZZZZ.jpg';

		if (!is_url($url)) {
			return false;
		}

		$body = plugin_amazon_get_page($url);

		if ($body != '') {
			$tmpfile = $dir.'ASIN'.$asin.'.jpg.0';
			$fp = fopen($tmpfile, 'wb');
			fwrite($fp, $body);
			fclose($fp);
			$size = getimagesize($tmpfile);
			unlink($tmpfile);
		}

		// 通常は1が返るが念のため0の場合も(reimy)
		if (($body == '') || ($size[1] <= 1)) {
			// キャッシュを PLUGIN_AMAZON_NO_IMAGE のコピーとする
			if ($asin_ext == '09') {
				$url = 'https://images-na.ssl-images-amazon.com/images/P/'.$asin.'.01.MZZZZZZZ.jpg';
				$body = plugin_amazon_get_page($url);

				if ($body != '') {
					$tmpfile = $dir.'ASIN'.$asin.'.jpg.0';
					$fp = fopen($tmpfile, 'wb');
					fwrite($fp, $body);
					fclose($fp);
					$size = getimagesize($tmpfile);
					unlink($tmpfile);
				}
			}

			if (($body == '') || ($size[1] <= 1)) {
				$fp = fopen(PLUGIN_AMAZON_NO_IMAGE, 'rb');

				if (!$fp) {
					return false;
				}

				$body = '';

				while (!feof($fp)) {
					$body .= fread($fp, 4096);
				}

				fclose($fp);
			}
		}

		plugin_amazon_cache_image_save($body, CACHE_DIR);
	}

	return $filename;
}

// Save title cache
function plugin_amazon_cache_title_save(string $data, string $dir) : string
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$filename = $dir.'ASIN'.$asin.'.tit';
	$fp = fopen($filename, 'w');
	fwrite($fp, $data);
	fclose($fp);

	return $filename;
}

// Save image cache
function plugin_amazon_cache_image_save(string $data, string $dir) : string
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$filename = $dir.'ASIN'.$asin.'.jpg';
	$fp = fopen($filename, 'wb');
	fwrite($fp, $data);
	fclose($fp);

	return $filename;
}

// Save book data
function plugin_amazon_review_save(string $page, string $data) : bool
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$filename = DATA_DIR.encode($page).'.txt';

	if (!is_readable($filename)) {
		$fp = fopen($filename, 'w');
		fwrite($fp, $data);
		fclose($fp);

		return true;
	} else {
		return false;
	}
}

function plugin_amazon_get_page(string $url) : string
{
	$data = pkwk_http_request($url);

	return ($data['rc'] == 200) ? ($data['data']) : ('');
}

// is ASIN?
function is_asin() : bool
{
	global $asin;
	global $asin_ext;
	global $asin_all;

	$tmpary = [];

	if (preg_match('/^([A-Z0-9]{10}).?([0-9][0-9])?$/', $asin_all, $tmpary) == false) {
		return false;
	} else {
		$asin = $tmpary[1];
		$asin_ext = (isset($tmpary[2])) ? ($tmpary[2]) : ('');

		if ($asin_ext == '') {
			$asin_ext = '09';
		}

		$asin_all = $asin.$asin_ext;

		return true;
	}
}
