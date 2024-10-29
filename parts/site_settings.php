<?php

    $site_unique_id = $url['query']['unique'];

    $unique_sets = get_option('backupsavvy_unique_'.$site_unique_id, false);

    $ind_settings = '';
    if($unique_sets) {
	    $connected = '<span class="yes">Connetcted</span> to: ' . $unique_sets['host'];
	    $dir_name  = '<b>Remote direcory: ' . $unique_sets['dir'] . '</b>';
	    $ind_settings = '<h3 class="red">This site has an Individual settings</h3>';

	    if(!empty($unique_sets['exclude_d']))
		    $exclude_d = implode(',', $unique_sets['exclude_d']);

	    if(!empty($unique_sets['exclude_f']))
		    $exclude_f = implode(',', $unique_sets['exclude_f']);

    }


    $site = backUpSavvySites::get_sites($url['query']['unique']);
?>
<div id="backupsavvy-settings" class="single">
    <div class="overlay"></div>

	<h2>
        <b><?php echo mb_strtoupper($site[0]->title); ?></b> settings
    </h2>
    <a href="admin.php?page=wp-back-it-up-settings#list">Return to list</a><br />
    <?php echo $ind_settings; ?>
	<p>
		<button name="make_default" class="btn little" id="make-default" value="">Set default settings</button>
	</p>
	<div class="t4 storage-settings" style="display:block;" >
		<?php include_once 'ftp_settings.php'; ?>
	</div>
</div>