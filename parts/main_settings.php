<?php

	$sch_jobs = get_option( 'wpbiu_schedules', array() );
	//echo '<pre>'.print_r($sch_jobs,true).'</pre>';
	if ( $sch_jobs ) {
		$job_title = $sch_jobs['events']['creating_sites_backups']['event_name'];
	}

	$sites = backUpSavvySites::get_sites();


?>

<div id="backupsavvy-settings">
    <div class="overlay"><div class="popup">
            <h3></h3>
            <div class="info"></div>
            <div class="progressbar">
                <div id="progresssteps" class="bwpu-progress" style="width:1%;">1%</div>
            </div>
            <div class="stop"">Stop</div>
        </div>
    </div>
	<h2>Backupsavvy Dashboard</h2>
	<ul class="tabs">
		<li class="t1 tab tab-current add-new"><a href="#add-new">Add new site</a></li>
		<li class="t2 tab list"><a href="#list">Sites list</a></li>
		<li class="t3 tab scheduller"><a href="#scheduller">Scheduller</a></li>
		<li class="t4 tab storage"><a href="#storage">Storage</a></li>
		<li class="t5 tab storage"><a href="#backups">Existing backups</a></li>
		<li class="t6 tab update"><a href="#update">Update to premium</a></li>
	</ul>
	<div class="t t1">
		<?php include_once 'settings_add_site.php'; ?>
	</div>
	<div class="t t2">
		<?php include_once 'sites_list.php'; ?>
	</div>
	<div class="t t3">
		<?php include_once 'scheduller.php'; ?>
	</div>
	<div class="t4 t storage-settings">
		<?php include_once 'ftp_settings.php'; ?>
	</div>
	<div class="t5 t backups-settings">
		<?php include_once 'existing-backups.php'; ?>
	</div>
	<div class="t6 t premium">
		<?php include_once 'premium.php'; ?>
	</div>
</div>
