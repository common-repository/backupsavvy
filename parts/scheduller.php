<h2>Scheduler</h2>
<div class="container">
	<div id="add-new-job">
		<h3 class="title">Add new job</h3>
		<form>
			<ul class="form-style-1">
				<li>
                    <span class="task-name">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" class="field1" placeholder="First" />
                    </span>
                    <span class="time">
                        <label>Schedule execution time</label>
                        <select name="field4" class="field-select">
                            <option value="manually">Manually</option>
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="daily">Daily</option>
                            <option value="hourly">Hourly</option>
                            <option value="test">Test</option>
                        </select>
                    </span>
                </li>
				<li>
					<label>Short comment <span class="required">*</span></label>
					<textarea name="field5" id="field5" class="field-long field-textarea"></textarea>
				</li>
				<li>
					<input type="submit" class="btn" value="Add job" />
					<div class="spinner"></div>
				</li>
			</ul>
		</form>
	</div>
	<table id="scheduller-jobs">
		<thead>
		<tr>
			<th>ID</th>
			<th>Title</th>
			<th>Time</th>
			<th>Action</th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<?php
			$title = $time = $action = $num = '&nbsp;';
            $job_title = isset($job_title) ? $job_title : '';
			if($sch_jobs) {
				$title = $job_title;
				$time = 'The next execution time: '.date('d-m-Y H:i:s', wp_next_scheduled('creating_sites_backups'));
				$action = '<a href="" class="run btn">Run</a> <a href="" class="remove btn">Delete</a>';
				$num = 1;
			}
			?>
			<td><?php echo $num; ?></td>
			<td><?php echo $title; ?></td>
			<td><?php echo $time; ?></td>
			<td><?php echo $action; ?></td>
		</tr>
		</tbody>
		<tfoot>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		</tfoot>
	</table>
</div>
	