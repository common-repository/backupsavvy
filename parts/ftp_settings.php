<div class="container">
    <div class="connected">
        <?php echo $connected .'<br>'. $dir_name; ?>
    </div>
	<h2>Ftp settings</h2>
	<form action="">
		<div class="field">
			<span><label for="host">Host:</label> <input type="text" name="host" id="host"></span>
			<span><label for="port">Port:</label> <input type="number" name="port" id="port" value="21"></span>
		</div>
		<div class="field">
			<fieldset>
				<legend>FTP/SFTP</legend>
				<label for="ftp">FTP</label>
				<input type="radio" name="connection" id="ftp" value="ftp" checked>
				<label for="sftp">SFTP</label>
				<input type="radio" name="connection" id="sftp" value="sftp">
			</fieldset>
		</div>
		<div class="field">
			<fieldset>
				<legend>Compression</legend>
				<label for="targz">Tar.gz</label>
				<input type="radio" name="method" id="targz" value="tarGz" checked>
				<label for="zip">Zip</label>
				<input type="radio" name="method" id="zip" value="zip">
				<label for="bz2">Bz2</label>
				<input type="radio" name="method" id="bz2" value="bz2">
				<label for="tar">Tar</label>
				<input type="radio" name="method" id="tar" value="tar">
			</fieldset>
        </div>
        <div class="field num">
            <label for="amount">Amount of backups</label>
            <input type="number" name="amount" id="amount" value="4">
        </div>
        <div class="field">
			<fieldset>
				<legend>Extended settings</legend>
                <label for="norm">Normal compression</label>
                <input type="radio" name="compr" id="norm" value="norm" checked><br>
				<label for="phar">Phar compression</label>
				<input type="radio" name="compr" id="phar" value="phar"><br><br>
                <label for="exclude-d">Exclude folders</label>
                <textarea name="exclude_d" id="exclude-d" cols="60" rows="10" placeholder="dir1,dir2..."><?php echo $exclude_d; ?></textarea>
                <label for="exclude-f">Exclude files</label>
                <textarea name="exclude_f" id="exclude-f" cols="60" rows="10" placeholder="file1,file2..."><?php echo $exclude_f; ?></textarea>
			</fieldset>
		</div>
		<div class="field">
			<label for="login">Login</label>
			<input type="text" name="login" id="login" value="" placeholder="Ftp Login">
		</div>
		<div class="field">
			<label for="pass">Password</label>
			<input type="password" name="pass" id="pass" value="" placeholder="Password">
		</div>
		<div class="field">
			<label for="dir">Remote directory</label>
			<input type="text" id="dir" name="dir" id="dir" value="" placeholder="directory name">
		</div>
		<div class="field">
            <input type="hidden" name="unique_id" value="<?php echo $site_unique_id; ?>">
			<input type="submit" class="btn" name="ftp_save" value="Save">
			<button id="test-con" class="btn">Test connection</button>
		</div>
	</form>
</div>
    