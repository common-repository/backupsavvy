<?php
/**
 * Page of all backup settings.
 */

global $wpdb;

$st        = get_option( 'backupsavvy_storage', NULL );
$connected = '<span class="no">No ftp added</span>';
$dir_name  = $exclude_d = $exclude_f = $site_unique_id = '';

if ( $st && isset($st['host']) ) {
	$connected = '<span class="yes">Connetcted</span> to: ' . $st['host'];
	$dir_name  = '<b>Remote direcory: ' . $st['dir'] . '</b>';

	if(!empty($st['exclude_d']))
		$exclude_d = implode(',', $st['exclude_d']);

	if(!empty($st['exclude_f']))
		$exclude_f = implode(',', $st['exclude_f']);

}

$url = backUpSavvySites::backupsavvy_parse_current_url();


if ( ! isset( $url['query']['unique'] ) ) {

	include_once 'main_settings.php';

} else {

	include_once 'site_settings.php';

}