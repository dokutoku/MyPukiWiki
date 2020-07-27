<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// func.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// deprecated functions

define('PKWK_UTF8_ENABLE', 1);

/**
 * Internal content encoding (for mbstring extension) 'UTF-8'
 */
define('SOURCE_ENCODING', 'UTF-8');

/**
 * Internal content encoding = Output content charset 'UTF-8'
 */
define('CONTENT_CHARSET', 'UTF-8');

function _mb_convert_kana__enable(string $str, string $option) : string
{
	return mb_convert_kana($str, $option, 'UTF-8');
}

function _mb_convert_kana__none(string $str, string $option) : string
{
	return $str;
}

/**
 * ctype_digit that supports PHP4+.
 *
 * PHP official document says PHP4 has ctype_digit() function.
 * But sometimes it doen't exists on PHP 4.1.
 */
function pkwk_ctype_digit(?string $s) : bool
{
	return ctype_digit($s);
}

// Sugar with default settings
function htmlsc(string $string = '', int $flags = ENT_COMPAT, string $charset = 'UTF-8') : string
{
	return htmlspecialchars($string, $flags, $charset);
}

/**
 * Get JSON string with htmlspecialchars().
 */
function htmlsc_json($obj) : string
{
	return htmlspecialchars(json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_COMPAT, 'UTF-8');
}
