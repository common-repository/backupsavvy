<?php
// creating license process:
// 1. make a mess in the code with random variables and methods..........?????????

if(!$ftp_dir)
    $ftp_dir = '/public_html';

?>

<div class="container existing-list">
    <h2>Existing backups</h2>
    <table class="top-options">
        <tr>
            <td class="first">
                <div class="spinner little" style="display: block; float:left;"></div>
            </td>
            <td class="per-page">
                <span class="per-page"><b>Per page:</b></span>
                <span data-num="2">2</span>
                <span data-num="10">10</span>
                <span data-num="25">25</span>
                <span data-num="50" class="current">50</span>
                <span data-num="75">75</span>
                <span data-num="100">100</span>
                <span data-num="99999999">All</span>
            </td>
            <td>
                <form action="#" method="post" class="filter" class="left">
                    <label for="site-name">Site name: </label>
                    <input type="text" name="site" value="" />
                    <input type="submit" name="filter" class="filter-submit" value="Find" class="btn little">
                </form>
            </td>
        </tr>
    </table>
      <table id="existing-backups" cellpadding="1" cellspacing="1">
          <thead>
          <tr>
              <th>Site</th>
              <th>Date</th>
              <th>Backup name</th>
              <th>Action</th>
          </tr>
          </thead>

      </table>

      <div class="tablenav">
          <div class='tablenav-pages'>
            <?php // $pager->show();  // Echo out the list of paging. ?>
          </div>
      </div>

  <?php add_thickbox(); ?>
    <div id="ftp-unique-settings" style="display:none;">
        <div class="ftp-inner-box">
            <form action="#" method="post" id="ftp-site-settings" class="unique-settings">
                <div class="field">
                    <span><label for="host">Host:</label> <input type="text" name="host" id="host" value="<?php echo $st_host; ?>"></span><br>
                    <span><label for="port">Port:</label> <input type="number" name="port" id="port" value="<?php echo $st_port; ?>"></span>
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
                        <legend>Mode</legend>
                        <label for="pass">Passive</label>
                        <input type="radio" name="mode" id="pasv" value="pasv" checked>
                        <label for="sftp">Active</label>
                        <input type="radio" name="mode" id="actv" value="act">
                    </fieldset>
                </div>

                <div class="field">
                    <label for="login">Login</label>
                    <input type="text" name="login" id="login" value="<?php echo $st_login; ?>" placeholder="Ftp Login">
                </div>
                <div class="field">
                    <label for="pass">Password</label>
                    <input type="password" name="pass" id="pass" value="" placeholder="Password" autocomplete="on">
                </div>
                <div class="field">
                    <label for="dirr">Remote directory</label>
                    <input type="text" id="dirr" name="dir" value="<?php echo $ftp_dir; ?>" placeholder="directory name">
                </div>
                <div class="field">
                    <input type="hidden" name="unique_id" value="<?php echo $site_unique_id; ?>">
                    <input type="hidden" name="ident" value="ftp">
                    <input type="submit" class="btn btn-light btn-little" name="ftp_save_restore" value="Save">
                    <button id="test-con-restore" class="btn btn-light btn-little">Test connection</button>
                    <button id="return-restore" class="btn btn-light btn-little">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <div id="restore-admin-panel">
        <div id="restore-admin-panel-inner">
            <div class="field">
                <a href="#" class="restore-files btn-light btn-little">Restore file system</a>
                <a href="#" class="restore-db btn-light btn-little">Restore database</a>
                <a href="#" class="restore-all btn-light btn-little">Full recovery</a>
                <a href="#" class="change-ftp btn-light btn-little">Change Ftp settings</a>
            </div>
            <div class="field">
                Be careful, by this action will be removed all current files or clean your current database. It depends on your choice.
            </div>
            <div class="field">
                <input type="hidden" name="unique_id" value="<?php echo $site_unique_id; ?>">
            </div>

        </div>
    </div>
    <div class="ajax-loader ajax-loader-default" data-text=""></div>
</div>