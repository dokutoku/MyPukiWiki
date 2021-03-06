<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// md5.inc.php
// Copyright 2001-2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
//  MD5 plugin: Allow to convert password/passphrase
//	* PHP sha1() -- If you have sha1() or mhash extension
//	* PHP md5()
//	* PHP hash('sha256')
//	* PHP hash('sha512')
//	* LDAP SHA / SSHA -- If you have sha1() or mhash extension
//	* LDAP MD5 / SMD5

// User interface of pkwk_hash_compute() for system admin
function plugin_md5_action() : array
{
	global $get;
	global $post;

	if ((PKWK_SAFE_MODE) || (PKWK_READONLY)) {
		die_message('Prohibited by admin');
	}

	// Wait POST
	$phrase = (isset($post['phrase'])) ? ($post['phrase']) : ('');

	if ($phrase == '') {
		// Show the form
		// If plugin=md5&md5=password, only set it (Don't compute)
		$value = (isset($get['md5'])) ? ($get['md5']) : ('');

		return ['msg'=>'Compute userPassword', 'body'=>plugin_md5_show_form(isset($post['phrase']), $value)];
	} else {
		// Compute (Don't show its $phrase at the same time)
		$is_output_prefix = isset($post['prefix']);
		$salt = (isset($post['salt'])) ? ($post['salt']) : ('');
		$scheme = (isset($post['scheme'])) ? ($post['scheme']) : ('');
		$algos_enabled = plugin_md5_get_algos_enabled();
		$scheme_list = ['x-php-md5', 'MD5', 'SMD5'];

		if ($algos_enabled->sha1) {
			array_push($scheme_list, 'x-php-sha1', 'SHA', 'SSHA');
		}

		if ($algos_enabled->sha256) {
			array_push($scheme_list, 'x-php-sha256', 'SHA256', 'SSHA256');
		}

		if ($algos_enabled->sha512) {
			array_push($scheme_list, 'x-php-sha512', 'SHA512', 'SSHA512');
		}

		if (!in_array($scheme, $scheme_list, true)) {
			return ['msg'=>'Error', 'body'=>'Invalid scheme: '.htmlspecialchars($scheme, ENT_COMPAT, 'UTF-8')];
		}

		$scheme_with_salt = '{'.$scheme.'}'.$salt;

		return ['msg'=>'Result', 'body'=>pkwk_hash_compute($phrase, $scheme_with_salt, $is_output_prefix, true)];
	}
}

// $nophrase = Passphrase is (submitted but) empty
// $value    = Default passphrase value
function plugin_md5_show_form(bool $nophrase = false, string $value = '') : string
{
	if ((PKWK_SAFE_MODE) || (PKWK_READONLY)) {
		die_message('Prohibited');
	}

	if (strlen($value) > PKWK_PASSPHRASE_LIMIT_LENGTH) {
		die_message('Limit: malicious message length');
	}

	if ($value != '') {
		$value = 'value="'.htmlspecialchars($value, ENT_COMPAT, 'UTF-8').'" ';
	}

	$algos_enabled = plugin_md5_get_algos_enabled();
	$md5_checked = '';
	$sha1_checked = '';

	if ($algos_enabled->sha1) {
		$sha1_checked = 'checked="checked" ';
	} else {
		$md5_checked = 'checked="checked" ';
	}

	$self = get_base_uri();
	$form = <<<'EOD'
<p><strong>NOTICE: Don't use this feature via untrustful or unsure network</strong></p>
<hr />
EOD;

	if ($nophrase) {
		$form .= '<strong>NO PHRASE</strong><br />';
	}

	$form .= <<<EOD
<form action="{$self}" method="post">
	<div>
		<input type="hidden" name="plugin" value="md5" />
		<label for="_p_md5_phrase">Phrase:</label>
		<input type="text" name="phrase" id="_p_md5_phrase" size="60" {$value}/><br />
EOD;
	$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_md5" value="x-php-md5" />
		<label for="_p_md5_md5">PHP md5</label><br />
EOD;

	if ($algos_enabled->sha1) {
		$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_sha1" value="x-php-sha1" />
		<label for="_p_md5_sha1">PHP sha1</label><br />
EOD;
	}

	if ($algos_enabled->sha256) {
		$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_sha256" value="x-php-sha256" />
		<label for="_p_md5_sha256">PHP sha256</label><br />
EOD;
	}

	if ($algos_enabled->sha512) {
		$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_sha512" value="x-php-sha512" />
		<label for="_p_md5_sha512">PHP sha512</label><br />
EOD;
	}

	if ($algos_enabled->sha1) {
		$form .= <<<EOD
		<input type="radio" name="scheme" id="_p_md5_lssha" value="SSHA" {$sha1_checked}/>
		<label for="_p_md5_lssha">LDAP SSHA (sha-1 with a seed) *</label><br />
		<input type="radio" name="scheme" id="_p_md5_lsha" value="SHA" />
		<label for="_p_md5_lsha">LDAP SHA (sha-1)</label><br />
EOD;
	}

	$form .= <<<EOD
		<input type="radio" name="scheme" id="_p_md5_lsmd5" value="SMD5" {$md5_checked}/>
		<label for="_p_md5_lsmd5">LDAP SMD5 (md5 with a seed) *</label><br />
		<input type="radio" name="scheme" id="_p_md5_lmd5" value="MD5" />
		<label for="_p_md5_lmd5">LDAP MD5</label><br />
EOD;

	if ($algos_enabled->sha256) {
		$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_lssha256" value="SSHA256" />
		<label for="_p_md5_lssha256">LDAP SSHA256 (sha256 with a seed) *</label><br />
		<input type="radio" name="scheme" id="_p_md5_lsha256" value="SHA256" />
		<label for="_p_md5_lsha256">LDAP SHA256</label><br />
EOD;
	}

	if ($algos_enabled->sha512) {
		$form .= <<<'EOD'
		<input type="radio" name="scheme" id="_p_md5_lssha512" value="SSHA512" />
		<label for="_p_md5_lssha512">LDAP SSHA512 (sha512 with a seed) *</label><br />
		<input type="radio" name="scheme" id="_p_md5_lsha512" value="SHA512" />
		<label for="_p_md5_lsha512">LDAP SHA512</label><br />
EOD;
	}

	$form .= <<<'EOD'
		<input type="checkbox" name="prefix" id="_p_md5_prefix" checked="checked" />
		<label for="_p_md5_prefix">Add scheme prefix (RFC2307, Using LDAP as NIS)</label><br />

		<label for="_p_md5_salt">Salt:</label>
		<input type="text" name="salt" id="_p_md5_salt" size="60" /><br />

		<input type="submit" value="Compute" /><br />
		<hr />
		<p>* = Salt enabled</p>
	</div>
</form>
EOD;

	return $form;
}

/**
 * Get availabilites of algos.
 */
function plugin_md5_get_algos_enabled() : object
{
	return (object)
	[
		'sha1'=>true,
		'sha256'=>true,
		'sha512'=>true,
	];
}
