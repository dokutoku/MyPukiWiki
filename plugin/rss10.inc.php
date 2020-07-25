<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// rss10.inc.php
// Copyright 2003-2017 PukiWiki Development Team
//
// RSS 1.0 plugin - had been merged into rss plugin

function plugin_rss10_action() : void
{
	pkwk_headers_sent();
	http_response_code(301);

	// HTTP
	header('Location: '.get_base_uri(PKWK_URI_ROOT).'?cmd=rss&ver=1.0');

	exit;
}
