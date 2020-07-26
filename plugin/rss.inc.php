<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// rss.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// RSS plugin: Publishing RSS of RecentChanges

// Usage: plugin=rss[&ver=[0.91|1.0|2.0]] (Default: 0.91)
//
// NOTE for acronyms
//   RSS 0.9,  1.0  : RSS means 'RDF Site Summary'
//   RSS 0.91, 0.92 : RSS means 'Rich Site Summary'
//   RSS 2.0        : RSS means 'Really Simple Syndication' (born from RSS 0.92)

function plugin_rss_action() : void
{
	global $vars;
	global $rss_max;
	global $page_title;
	global $whatsnew;

	$version = (isset($vars['ver'])) ? ($vars['ver']) : ('');

	switch ($version) {
		case '':
			$version = '0.91';

			// Default
			break;

		case '1':
			$version = '1.0';

			// Sugar
			break;

		case '2':
			$version = '2.0';

			// Sugar
			break;

		case '0.91':
		case '1.0':
		case '2.0':
			break;

		default:
			die('Invalid RSS version!!');
	}

	$recent = CACHE_DIR.'recent.dat';

	if (!file_exists($recent)) {
		die('recent.dat is not found');
	}

	$lang = LANG;
	$page_title_utf8 = mb_convert_encoding($page_title, 'UTF-8', SOURCE_ENCODING);
	$self = get_base_uri(PKWK_URI_ABSOLUTE);

	// Creating <item>
	$rdf_li = '';
	$items = '';

	foreach (file_head($recent, $rss_max) as $line) {
		[$time, $page] = explode("\t", rtrim($line));
		$r_page = pagename_urlencode($page);
		$title = mb_convert_encoding($page, 'UTF-8', SOURCE_ENCODING);

		switch ($version) {
			case '0.91':
			case '2.0':
				$date = get_date('D, d M Y H:i:s T', (int) ($time));
				$date = ($version == '0.91') ? ('<description>'.$date.'</description>') : ('<pubDate>'.$date.'</pubDate>');
				$items .= <<<EOD
<item>
	<title>{$title}</title>
	<link>{$self}?{$r_page}</link>
	{$date}
</item>

EOD;

			break;

		case '1.0':
			// Add <item> into <items>
			$rdf_li .= "\t\t\t\t".'<rdf:li rdf:resource="'.$self.'?'.$r_page.'" />'."\n";

			$date = substr_replace(get_date('Y-m-d\TH:i:sO', (int) ($time)), ':', -2, 0);
			$items .= <<<EOD
<item rdf:about="{$self}?{$r_page}">
	<title>{$title}</title>
	<link>{$self}?{$r_page}</link>
	<dc:date>{$date}</dc:date>
	<dc:identifier>{$self}?{$r_page}</dc:identifier>
</item>

EOD;

			break;

			default:
				break;
		}
	}

	// Feeding start
	pkwk_common_headers();
	header('Content-type: application/xml');
	echo '<?xml version="1.0" encoding="UTF-8"?>'."\n\n";

	$r_whatsnew = pagename_urlencode($whatsnew);
	$items = rtrim($items);

	switch ($version) {
		case '0.91':
			print '<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://my.netscape.com/publish/formats/rss-0.91.dtd">'."\n";
			 // FALLTHROUGH

		case '2.0':
			$items = str_replace("\n", "\n\t\t", $items);
			print <<<EOD
<rss version="{$version}">
	<channel>
		<title>{$page_title_utf8}</title>
		<link>{$self}?{$r_whatsnew}</link>
		<description>PukiWiki RecentChanges</description>
		{$items}
	</channel>
</rss>
EOD;

		break;

		case '1.0':
			$rdf_li = rtrim($rdf_li);
			$items = str_replace("\n", "\n\t", $items);
			print <<<EOD
<rdf:RDF xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns="http://purl.org/rss/1.0/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xml:lang="{$lang}">
	<channel rdf:about="{$self}?{$r_whatsnew}">
		<title>{$page_title_utf8}</title>
		<link>{$self}?{$r_whatsnew}</link>
		<description>PukiWiki RecentChanges</description>
		<items>
			<rdf:Seq>
{$rdf_li}
			</rdf:Seq>
		</items>
	</channel>
	{$items}
</rdf:RDF>
EOD;

		break;

		default:
			break;
	}

	exit;
}
