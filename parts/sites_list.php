<div class="container">
	<div id="bulk-op">
		<a href="#" class="btn bulk-sync">Sync all</a>
		<a href="#" class="btn bulk-backup">Backup all</a>
	</div>
	<table id="sites-list">
		<thead>
		<tr>
			<th>&nbsp;</th>
			<th>Title</th>
			<th>Site Url</th>
<!--			<th>Activation code</th>-->
<!--			<th>Login</th>-->
			<th>Action</th>
		</tr>
		</thead>
		<tbody>

		<?php
		$title = $time = $action = $num = '&nbsp;';
		if($sch_jobs) {
			$title = $job_title;
			$time = 'The next execution time: '.date('d-m-Y H:i:s', wp_next_scheduled('creating_sites_backup'));
			$action = '<a href="" class="run btn">Run</a> <a href="" class="remove btn">Delete</a>';
			$num = 1;
		}
		if($sites):
			foreach ($sites as $key => $site ):
				$unique_url = backUpSavvySites::get_current_url() . '&unique=' . $site->unique_id;
				?>
				<tr>
					<td><?php echo $key + 1; ?></td>
					<td class="title"><?php echo $site->title; ?></td>
					<td><a href="<?php echo $unique_url; ?>"><?php echo $site->url; ?></a></td>
<!--					<td>--><?php //// echo $site->protection; ?><!--</td>-->
<!--					<td>--><?php //echo // $site->login; ?><!--</td>-->
					<td class="action">
                        <a class="backup btn little" href="">Backup now</a>
                        <a class="sync btn" href="">Sync</a>
                        <a class="remove btn" href="">Del</a>
                        <div class="spinner"></div>
                        <div class="hidden" id="<?php echo $site->unique_id ?>"></div>
                    </td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
		<tfoot>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
<!--			<td>&nbsp;</td>-->
		</tr>
		</tfoot>
	</table>
</div>
		