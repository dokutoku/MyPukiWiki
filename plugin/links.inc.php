<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// links.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Update link cache plugin

// Message setting
function plugin_links_init() : void
{
	$messages =
	[
		'_links_messages'=>
		[
			'title_update'=>'キャッシュ更新',
			'msg_adminpass'=>'管理者パスワード',
			'btn_submit'=>'実行',
			'msg_done'=>'キャッシュの更新が完了しました。',
			'msg_usage'=>'
* 処理内容

:キャッシュを更新|
全てのページをスキャンし、あるページがどのページからリンクされているかを調査して、キャッシュに記録します。

* 注意
実行には数分かかる場合もあります。実行ボタンを押したあと、しばらくお待ちください。

* 実行
管理者パスワードを入力して、[実行]ボタンをクリックしてください。
',
		],
	];

	set_plugin_messages($messages);
}

function plugin_links_action() : array
{
	global $post;
	global $vars;
	global $foot_explain;
	global $_links_messages;

	$script = get_base_uri();

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits this');
	}

	$body = '';
	$msg = '';

	if ((empty($vars['action'])) || (empty($post['adminpass'])) || (!pkwk_login($post['adminpass']))) {
		$msg = &$_links_messages['title_update'];
		$body = convert_html($_links_messages['msg_usage']);
		$body .= <<<EOD
<form method="post" action="{$script}">
 <div>
  <input type="hidden" name="plugin" value="links" />
  <input type="hidden" name="action" value="update" />
  <label for="_p_links_adminpass">{$_links_messages['msg_adminpass']}</label>
  <input type="password" name="adminpass" id="_p_links_adminpass" size="20" value="" />
  <input type="submit" value="{$_links_messages['btn_submit']}" />
 </div>
</form>
EOD;
	} elseif ($vars['action'] == 'update') {
		links_init();

		// Exhaust footnotes
		$foot_explain = [];

		$msg = &$_links_messages['title_update'];
		$body = &$_links_messages['msg_done'];
	} else {
		$msg = &$_links_messages['title_update'];
		$body = &$_links_messages['err_invalid'];
	}

	return ['msg'=>$msg, 'body'=>$body];
}
