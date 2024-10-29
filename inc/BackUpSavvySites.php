<?php

use altayalp\FtpClient\Servers\FtpServer;
use altayalp\FtpClient\FileFactory;

class backUpSavvySites
{
  private $url;
  public static $records_table;
  public static $report_table;
  private $server; // ftp server
  const per_batch = 1;
  private $unique_id;
  protected $apikey;
  private $dropbox_settings; // dropbox storage settings
  private $google_drive_settings;
  private $aws_cloud_settings;

  public function __construct()
  {
    global $wpdb;

    add_action('wp_ajax_backupsavvy_add_new_site', array(&$this, 'update_site'));

    self::$records_table = $wpdb->prefix . "backup_savvy_records";
    self::$report_table = $wpdb->prefix . "backup_savvy_reports";

    // storage process
    add_action('wp_ajax_backupsavvy_load_sites', array(&$this, 'load_sites'));
    add_action('wp_ajax_backupsavvy_filter', array(&$this, 'filter_sites'));
    add_action('wp_ajax_backupsavvy_set_default', array(&$this, 'set_default_settings'));
    add_action('wp_ajax_backupsavvy_sync_one', array(&$this, 'sync_one'));
    add_action('wp_ajax_backupsavvy_sync_process', array(&$this, 'sync_progress'));
    add_action('wp_ajax_backupsavvy_backup_one', array(&$this, 'backup_one'));
    add_action('wp_ajax_backupsavvy_upload_one', array(&$this, 'upload_one'));
    add_action('wp_ajax_backupsavvy_backup_process', array(&$this, 'backup_progress'));
    add_action('wp_ajax_backupsavvy_backup_process_op', array(&$this, 'backup_progress_options'));
    add_action('wp_ajax_backupsavvy_remove_site', array(&$this, 'remove_site'));
    add_action('wp_ajax_backupsavvy_test_con', array(&$this, 'test_ftp_connection'));
    add_action('wp_ajax_backupsavvy_download_backup', array(&$this, 'download_backup'));
    add_action('wp_ajax_backupsavvy_save_ftp_unique', array(&$this, 'save_ftp_unique'));
    add_action('wp_ajax_backupsavvy_save_premium_settings', array(&$this, 'save_premium_settings'));
    add_action('wp_ajax_backupsavvy_compare', array(&$this, 'compare'));
    add_action('wp_ajax_backupsavvy_count_sites', array(&$this, 'get_num_sites'));
    add_action('wp_ajax_backupsavvy_load_report_list', array(&$this, 'load_page_report'));
    add_action('wp_ajax_backupsavvy_import_mainwp', array(&$this, 'import_mainwp'));

    $this->apikey = get_option('backupsavvy_apikey', FALSE);

    if (isset($_GET['backup-file'])) {
      $this->download_file($_GET['backup-file']);
    }

  }


  public function import_mainwp() {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $start = $_POST['start'];
    $db = new backupSavvyCrud();
    $sites = $db->getMainWpSites($start, 5);

    if(!empty($sites) && is_array($sites)) {
      // write to db and send log
      $db->saveMainWp($sites);
      $number = count($sites);
      if($number == 5)
        $next = $number + $start;
      else
        $next = 'end';

      foreach ($sites as $site) {
        $info[] = $site->name;
      }

      $total = self::count_mainwp_sites();

      $result = array(
        'info' => $info,
        'next' => $next,
        'total' => $total
      );

      $this->json_exit($result);
    }

    $this->json_exit(array('next' => 'none'));
  }

  public static function count_mainwp_sites()
  {
    global $wpdb;
    $count = $wpdb->get_var("select COUNT(*) from " . $wpdb->prefix."mainwp_wp");

    return $count ? $count : 0;
  }

  // todo: check individual settings when sync progress !!!!!!!!!!!!!!!!!

  /**
   * compare sites lists
   */
  public function compare()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    if (!$_POST['data'])
      $this->json_exit(array('status' => 'empty'));

    $data = $_POST['data'];
    foreach ($data as $key => $item)
      $data[$key] = basename(str_replace('www.', '', trim($item)));

    $sites = self::get_sites();
    if (!$sites)
      $this->json_exit(array('status' => 'sites'));

    foreach ($sites as $key => $site) {
      $parts = parse_url($site->url);
      $site_name = $this->clean_domain($parts['host']);
      $sites[$key] = $site_name;
    }


    foreach ($data as $site) {
      $site_name = $this->clean_domain($site);
      if (!in_array($site_name, $sites))
        $result[] = $site_name;
    }


    $this->json_exit($result);

  }

  private function clean_domain($url)
  {
    $site_name = str_replace('www.', '', $url);
    $site_name = str_replace('http://', '', $site_name);
    $site_name = str_replace('https://', '', $site_name);

    return $site_name;
  }

  public function filter_sites()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $option = $_POST['option'];

    $tr = '';
    $name = $_POST['data'] ? trim($_POST['data']) : false;

    if ($option == 'sites') {
      $sites = $this->get_sites_by_name($name);
      $tr = $this->create_table_tr($sites, 1);
    }

    if ($option == 'report') {
      $reports = $this->get_report_by_name($name);
      $tr = $reports['tbody'];
    }
    $result = array(
      'sites' => $tr
    );

    $this->json_exit($result);

  }

  public function load_sites()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $number = (int)trim($_POST['number']);
    $page = (int)trim($_POST['page']);

    $pager = new pagination(new self);
    $pager->set_limit($number);
    $pager->set_page($page);
    $pager->target('?page=backup-savvy-settings');
    $pager->set_hash('#list');

    $sites_list = $pager->get_sites();

    $tr = $this->create_table_tr($sites_list, $page);

    $result = array(
      'sites' => $tr,
      'pager' => $pager->show()
    );

    $this->json_exit($result);
  }

  private function create_table_tr($sites_list, $page)
  {
    $tr = '';
    if($sites_list) {
      $src = plugin_dir_url(__DIR__) . 'assets/arrow-more.png';
      $number = (int)trim($_POST['number']);
      foreach ($sites_list as $key => $site) {
        $unique_url = '/wp-admin/admin.php?page=backup-savvy-settings&unique=' . $site->unique_id;
        $unique_status = '';
        if ($site->unique_settings == 1)
          $unique_status = '<span class="red"><b>Unique settings</b></span>';

        $key_out = ($number*$page - $number) + $key + 1;
        $tr .= "<tr>
                  <td class='choice'>$key_out <input type='checkbox'>
                      <div></div>
                  </td>
                  <td class='title'>$site->title</td>
                  <td class='links'>
                      <a href='$unique_url'>$site->url</a>
                      <a alt='Unique settings' href='$unique_url'><img src='$src'> </a>
                    $unique_status
                  </td>
                  <td class='action'>
                      <a class='backup btn little' href=''>Backup now</a>
                      <a class='sync btn little' href=''>Sync</a>
                      <a class='remove btn little' href=''>Del</a>
                      <div class='spinner little'></div>
                      <div class='hidden little' id='$site->unique_id'></div>
                  </td>
              </tr>";
      }
    }

    return $tr;
  }

  public function load_page_report() {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $number = (int)trim($_POST['number']); // 2
    $page = (int)trim($_POST['page']); // 2

    // get report table's data
    $result = $this->get_report_result($number, $page);

    $this->json_exit($result);

  }

  private function get_report_result($number, $page) {
    $pager = new pagination(new BackUpSavvyReports);

    $pager->set_limit($number);
    $pager->set_page($page);
    $pager->target('?page=backup-savvy-settings');
    $pager->set_hash('#existing');

    $backups =  $pager->get_sites();
    $tbody = '';
    foreach ($backups as $group) {
      if ($group) {
        $tbody .= '<tbody class="group">';
        foreach ($group as $backup) {
          $error = '';
          $status = 'backup';
          if ($backup->status != 1) {
            $status = 'error';
            $error = '<span> upload error</span>';
          }
          $backup_name = isset($backup->backup_name) ? $backup->backup_name : '';
          $download_button = " <div class='btn little download' data-id='$backup->id'
                                   data-unique='$backup->unique_id'>Download
                              </div>";
          // todo:: temporary remove download button, not working with the google drive
          $download_button = '';
          $tbody .= "<tr class='$status'>
                          <td>$backup->url $error</td>
                          <td>$backup->time</td>
                          <td>
                              <span>$backup_name</span>
                          </td>
                          <td class='action'>
                             $download_button
                              <div class='btn little restore files' data-id='$backup->id' data-unique='$backup->unique_id'>
                                Restore
                              </div>
                          </td>
                      </tr>";
        }
        $tbody .= '</tbody>';
      }
    }

    $result = array(
      'tbody' => $tbody,
      'pager' => $pager->show()
    );

    return $result;
  }


  // reset settings to default for individual site
  public function set_default_settings()
  {

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $storage = get_option('backupsavvy_storage', false);

    if (!$storage)
      $this->json_exit(array('status' => 'storage'));

    $unique_id = trim($_POST['unique']);
    $site = self::get_sites($unique_id);

    if (!$site)
      $this->json_exit(array('status' => 'site'));

    delete_option('backupsavvy_unique_' . $unique_id);

    $this->update_db_unique($site[0]->unique_id, 0);

    $status = $this->send_settings($site[0]);

    $this->json_exit(array('status' => $status));

  }

  public function test_ftp_connection()
  {

    $data = array();
    parse_str($_POST['data'], $data);

    $data = $this->clean_ftp($data);

    if (!$data) {
      $this->json_exit(array('status' => 'fields'));
    }

    $status = $this->test_ftp_con($data);

    $this->json_exit(array('status' => $status));
  }

  public function test_ftp_con($data)
  {
    if($data['connection'] == 'ftp') {
      $con = ftp_connect($data['host'], $data['port']) or die("Couldn't connect");
      if(!ftp_login($con, $data['login'], $data['pass'])) {
        ftp_close($con);
        return 'error';
      }

      if ($data['mode'] == 'pasv')
        ftp_pasv($con, true);

      if (!ftp_nlist($con, "."))
        return 'error';
      if (!ftp_chdir($con, $data['dir']))
        return 'nodir';
      ftp_close($con);
    }
    elseif($data['connection'] == 'sftp') {
//      error_log(print_r($data,1));
      $con = ssh2_connect($data['host'], $data['port']);
      $auth_result = ssh2_auth_password($con, $data['login'], $data['pass']);
      if(!$auth_result)
        return 'error';
      $stream = ssh2_exec($con, "cd /home/".$data['dir']. ";ls");
      $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
      stream_set_blocking($errorStream, true);
      stream_set_blocking($stream, true);

      $result = stream_get_contents($stream);
      $error = stream_get_contents($errorStream);
      if(!empty($error))
        return $errorStream;

      if(empty($result))
        return 'nodir';
    }
    else
      return 'error';

    return 'success';
  }

  private function check_access() {
      $access = get_option('backupsavvy_premium_object', false);

      if(!$access || !is_object($access))
          return false;
error_log('access '.print_r($access,1));
      $postUrl = 'https://backupsavvy.com/wp-json/backupsavvystoreapi/serial';
      if($access) {
          $access = (array) $access;
          $args = array(
              'timeout'     => 120, // seconds
              'redirection' => 5,
              'httpversion' => '1.0',
              'blocking'    => true,
              'headers'     => array(),
              'body'        => $access,
              'cookies'     => array(),
              'sslverify'   => false
          );

          $response = wp_remote_post( $postUrl, $args );

          if ( is_wp_error( $response ) ) {
              $error_message = $response->get_error_message();
              return false;
          } else {
              $res =(object) json_decode($response['body']);
              if($res->status == 'ok')
                  return true;
          }
      }

      return false;
  }

  public function backup_one()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

//    if(!$this->check_access()) // should be another way
//        $this->json_exit(array('status' => 'access', 'site' => ''));


    //require_once 'BackupSavvyReader.php';
    $features = new commonFeatures();

    $id = $_POST['data'];
    $site = $features->get_site(trim($id));

    if(!$site) {
        $this->json_exit(array('status' => 'reader', 'url' => ''));
    }

//    $site = self::get_sites(trim($id));
    $status = 'no sites to backup';
    if (isset($site[0]))
      $status = (new backUpSavvyBackup)->backup_one($site[0]);

    $this->json_exit(array('status' => $status, 'url' => $site[0]->url));
  }

  // todo:: explode download by methods, add other storages
  public function download_backup()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    $id = $_POST['id'];
    if (empty($id))
      $this->json_exit(array('status' => 'id', 'site' => ''));

    $this->unique_id = $_POST['unique'];

    $storage = self::get_storage($this->unique_id);

    $site = self::get_sites($id);
    $status = 'success';

    if ($storage['connection'] == 'dropbox') {
      include_once 'DropboxClient.php';

      $token = $storage['token'];
      $projectFolder = $storage['folder'];
      $secret = $storage['secret'];
      $access_key = $storage['access_key'];

      $dropbox = new DropboxClient(array(
        'app_key' => $access_key,
        'app_secret' => $secret,
        'app_full_access' => false, // if the app has full or folder only access),
        'en'
      ));

      $dropbox->SetBearerToken(array(
        't' => $token,
        's' => ''
      ));

      $file_name = self::get_report_backup_name($id);
      if (!$file_name)
        $this->json_exit(array('status' => 'name', 'site' => ''));

      $backup_list = $dropbox->Search("/", $file_name);

      $path = plugin_dir_path(__DIR__);
      if(isset($backup_list[0])) {
        $dropbox->DownloadFile($backup_list[0], $path . $file_name);
      } else {
        $status = 'error';
      }

    }

    if ($storage['connection'] == 'ftp') {
      $server = $this->get_ftp_server();
      $file = FileFactory::build($server);
      $file_name = self::get_report_backup_name($id);
      $path = plugin_dir_path(__DIR__);
      $file->download($storage['dir'] . '/' . $file_name, $path . $file_name);
    }

    if($storage['connection'] == 'google_drive') {
      $storage = self::get_storage($id);
      $storage = unserialize($storage['storage']);

      $vault = $storage->getStorage('google_drive');
      $file_name = self::get_report_backup_name($id);
      $data = self::get_report_data($id);
      if($data == NULL)
        $this->json_exit(array('status' => 'error', 'name' => $file_name, 'code' => 'fileId'));

      $data = unserialize($data);
      $fileId = $data->fileId;
      // download from google_drive
      require_once __DIR__ . '/../vendor/autoload.php';
      $client = new Google_Client();

      $client->addScope(Google_Service_Drive::DRIVE);
      $client->setRedirectUri($vault['redirect_uri']);
      $client->setAccessToken($vault['token']);

      $service = new Google_Service_Drive($client);
      $file = $service->files->get($fileId, array( 'alt' => 'media' ));

      $downloadUrl = $file->getDownloadUrl();
      // todo:: organize download process

      $out_dir = get_temp_dir();
      if(!is_writable($out_dir))
        $out_dir = BACKUPSAVVY_PLUGIN_PATH;

    }

    $this->json_exit(array('status' => $status, 'name' => $file_name));

  }

    /**
     * update option with new serial key
     */
  public function save_premium_settings() {
      check_ajax_referer('set-wpbckup_sets', 'nonce');
      if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
          $this->json_exit(array('status' => 'nonce', 'site' => ''));

      $data = array();
      parse_str($_POST['data'], $data);

      if (!$data)
          $this->json_exit(array('status' => 'field'));

      commonFeatures::updateLicense(array(
          'apikey' => trim($data['license'])
      ));

      $this->json_exit(array('status' => 'success'));
  }
  /**
   * Save ftp for unique site when need to restore it with restore button
   */
  public function save_ftp_unique() {

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    if(empty($_POST['unique']) || empty($_POST['data']))
      $this->json_exit(array('status' => 'error'));

    parse_str($_POST['data'], $data);

    $data = $this->clean_ftp($data);
    if(!$data)
      $this->json_exit(array('status' => 'error'));

    $status = $this->test_ftp_con($data);

    if ($status != 'success')
      $this->json_exit(array('status' => $status));

//    error_log('ftp data '.print_r($data,1));
    $unique = trim($_POST['unique']);

    update_option('backupsavvy_unique_ftp'.$unique, $data);

    $this->json_exit(array('status' => 'success'));

  }

  private function get_ftp_server()
  {
    $storage = self::get_storage($this->unique_id);

    if (!$storage)
      return false;

    if (!$this->server) {
      $server = new FtpServer($storage['host']);
      $server->login($storage['login'], $storage['pass']);

      if (empty($storage['active'])) {
        $server->turnPassive();
      }

      $this->server = $server;
    }

    return $this->server;
  }

  /**
   * 'backupsavvy_unique_' . $id - unique storage settings for site, if it has special settings
   * 'backupsavvy_storage' - common storage
   * 'backupsavvy_unique_ftp'.$unique - unique ftp settings if site need to restore. FTP must exists in this case
   *
   * @param bool $id
   * @return array|mixed|void
   */
  public static function get_storage($id = false)
  {
    if(!empty($_GET['unique']))
      $id = trim($_GET['unique']);

    $storage = array();

    if ($id)
      $storage = get_option('backupsavvy_unique_' . $id, array());

    if (!$storage)
      $storage = get_option('backupsavvy_storage', array());

    return $storage;
  }

  public function upload_one()
  {

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    $id = $_POST['data'];
    $site = self::get_sites(trim($id));
    $status = 'no sites to upload';
    if (isset($site[0]))
      $status = (new backUpSavvyBackup)->upload_one($site[0]);

    if ($status != 'success')
      $status = 'error';

    $exit = array('status' => $status, 'site' => $site[0]->title);

    $this->json_exit($exit);
  }

  public function sync_one()
  {

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $id = $_POST['id'];
    $site = self::get_sites(trim($id));
    $status = 'error';

    if ($site)
      $status = $this->send_settings($site[0]);

    $this->json_exit(array('status' => $status, 'site' => $site[0]->title));
  }

  public function sync_progress()
  {

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    ignore_user_abort(TRUE);

    if (!ini_get('safe_mode')) {
      @set_time_limit(0);
    }

    $license = get_option('backupsavvy_license', FALSE);

    $step = isset($_POST['step']) ? absint($_POST['step']) : 1;
//		$total   = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : FALSE;
    $start = $step == 1 ? 0 : ($step - 1) * 1;

    // get all sites
    $sites = self::get_sites(false, $start, 1);
    $current_site = '';
    if ($sites)
      foreach ($sites as $site) {
        $current_site = $site->title;
        if ($site->unique_settings != 1) {
          // sync settings
          try {
            $this->send_settings($site);
          } catch (Exception $e) {
            throw new Exception($e->getMessage());
          }
          $status = 'success';
        } else {
          $status = 'unique';
        }
      }
    else
      $status = 'empty';

    $step++;

    $count = self::count_sites();
    $total = $count;

    if ($step > $count || empty($count)) {
      $total = 'completed';
    }

    $this->json_exit(array(
      'step' => $step,
      'total' => $total,
      'site' => $current_site,
      'status' => $status,
      'count' => $count,
    ));
  }

  public function clean_ftp($data)
  {
    if (!is_array($data))
      return false;

    if (!$data['host'] || !$data['pass'] || !$data['login'] || !$data['dir'] || !$data['port'])
      return false;

    if (!is_numeric($data['port']))
      return false;

    $data['host'] = untrailingslashit(sanitize_text_field($data['host']));
    $data['login'] = sanitize_text_field($data['login']);
    $data['dir'] = untrailingslashit(sanitize_text_field($data['dir']));
    $data['port'] = (int)$data['port'];

    if (isset($data['exclude_d'])) {
      $data['exclude_d'] = explode(',', trim($data['exclude_d']));
    }

    if (isset($data['exclude_f'])) {
      $data['exclude_f'] = explode(',', trim($data['exclude_f']));
    }

    return $data;
  }


  /**
   * @param $data array
   */
  private function json_exit($data)
  {
    echo json_encode((object)$data);
    wp_die();
  }

  public function send_settings($site, $storage = false)
  {

    $storage = self::get_storage($site->unique_id);

    if (!$storage)
      return 'error';

    $protection = md5($site->protection.'.bcssvy');

    $postUrl = $site->url . '/wp-json/backupsavvyapi/settings';
    $args = array(
      'timeout' => 120, // seconds
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array(),
      'body' => array(
        'apikey' => $this->apikey,
        'secret' => $protection,
        'action' => 'storage',
        'data' => $storage
      ),
      'cookies' => array(),
      'sslverify' => false
    );

    $response = wp_remote_post($postUrl, $args);

    if (is_wp_error($response)) {
      $error_message = $response->get_error_message();
      return $error_message;
    } else {
      $res = (object)json_decode($response['body']);
      if(!isset($res->status))
        return 'error';
    }


    return $res->status;
  }


  public function update_site()
  {
    global $wpdb;

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $data = array();
    parse_str($_POST['data'], $data);

    $secret = trim($data['code']);
    $protection = md5($secret.'.bcssvy');
    $this->url = untrailingslashit(esc_url_raw($data['url']));

    $status = 'error_exists';
    if (!$this->site_exists($this->url)) {
      $status = 'error_protecion';
      $unique_id = $this->check_child_protection($protection);
      if ($unique_id) {
        $status = 'error';

        $title = sanitize_text_field($data['title']);
        $url = $this->url;
        $time = date("Y-m-d");
        $acitvated = 1;

        $sql = "INSERT INTO " . self::$records_table . " (title,activated,url,protection,unique_id,time) VALUES (%s,%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE title=%s, activated=%d, url=%s";
        $sql = $wpdb->prepare($sql, $title, $acitvated, $url, $secret, $unique_id, $time, $title, $acitvated, $url);
        if ($wpdb->query($sql)) {
          $status = 'success';
        }
      }
    }

    $this->json_exit(array('status' => $status));
  }

  /**
   * update db unique_settings col
   * @param $unique_id
   * @param $action
   * @return string
   */
  public function update_db_unique($unique_id, $action)
  {
    global $wpdb;
    $status = 'error';
    $sql = "UPDATE " . self::$records_table . " SET unique_settings=%s WHERE unique_id=%s";
    $sql = $wpdb->prepare($sql, $action, $unique_id);
    $query = $wpdb->query($sql);

    if ($query) {
      $status = 'success';
    }

    return $status;
  }

  private function site_exists($url)
  {
    global $wpdb;

    $sql = $wpdb->get_results('SELECT unique_id FROM ' . self::$records_table . ' WHERE url="' . $url . '"');

    if (empty($sql)) {
      return false;
    }

    return true;
  }

  public static function get_sites($id = FALSE, $start = false, $limit = false, $pager = false)
  {
    global $wpdb;

    $sites = (new commonFeatures())->get_sites($id, $start, $limit, $pager);
//		if($pager && isset($_GET['num'])) {
//		    $page = (int) trim($_GET['num']);
//		    $start = !$page ? 0 : $limit * $page;
//        }

    return $sites;
  }

  private function get_sites_by_name($name)
  {
    global $wpdb;

    if(!$name)
      $sites = $wpdb->get_results("SELECT * FROM " . self::$records_table);
    else
      $sites = $wpdb->get_results("SELECT * FROM " . self::$records_table . " WHERE url LIKE '%" . $name . "%'");

    return $sites;
  }

  private function get_report_by_name($name)
  {
    global $wpdb;

    $storage = self::get_storage();
    $limit = $storage['amount'];
    if(!$name) {
      $result = $this->get_report_result(99999999, 1);
    }
    else {
      $site = $wpdb->get_results('SELECT title,unique_id FROM ' . self::$records_table . ' WHERE url LIKE "%' . $name . '%"');

      foreach ($site as $rep) {
        $backups[] = $wpdb->get_results('SELECT * FROM ' . self::$report_table . ' WHERE unique_id="' . $rep->unique_id . '" and action="upload" ORDER BY ID DESC LIMIT ' . $limit);
      }

      $tbody = "";
      foreach ($backups as $group) {
        if ($group) {
          $tbody .= '<tbody class="group">';
          foreach ($group as $backup) {
            $error = '';
            $status = 'backup';
            if ($backup->status != 1) {
              $status = 'error';
              $error = '<span> upload error</span>';
            }
            $backup_name = isset($backup->backup_name) ? $backup->backup_name : '';
            $download_button = '<div class=\'btn little\' data-id=\'$backup->id\'
                                   data-unique=\'$backup->unique_id\'>Download
                              </div>';
            $tbody .= "<tr class='$status'>
                          <td>$backup->url $error</td>
                          <td>$backup->time</td>
                          <td>
                              <span>$backup_name</span>
                          </td>
                          <td class='action'>
                              $download_button
                              <div class='btn little restore db' data-id='$backup->id' data-unique='$backup->unique_id'>
                                Restore db
                              </div>
                          </td>
                      </tr>";
          }
          $tbody .= '</tbody>';
        }
      }

      $result['tbody'] = $tbody;
    }

    return $result;
  }

  public static function get_iterat_sites()
  {
    global $wpdb;
    $exclude = array();
    $sites = $wpdb->get_results("SELECT unique_id FROM " . self::$report_table . " WHERE status <> 1");

    if (!$sites)
      return false;

    foreach ($sites as $site) {
      $exclude[] = "'$site->unique_id'";
    }

    $exclude_sql = implode(', ', $exclude);

    $sites = $wpdb->get_results("SELECT * FROM " . self::$records_table . " WHERE unique_id IN (" . $exclude_sql . ")");

    return $sites;
  }

  public static function get_report_backup_name($id)
  {
    global $wpdb;
    $site = $wpdb->get_results("SELECT backup_name FROM " . self::$report_table . " WHERE id = '" . $id . "'");

    if (!$site)
      return false;

    return $site[0]->backup_name;
  }

  public static function get_report_data($id)
  {
    global $wpdb;
    $site = $wpdb->get_results("SELECT data FROM " . self::$report_table . " WHERE id = '" . $id . "'");

    if (!$site)
      return false;

    return $site[0]->data;
  }

  public static function count_sites()
  {
    global $wpdb;
    $count = $wpdb->get_var("select COUNT(*) from " . self::$records_table);

    return $count ? $count : 0;
  }

  public function get_num_sites()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $number = array('number' => self::count_sites());

    $this->json_exit($number);
  }

  public function remove_site()
  {
    global $wpdb;

    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce'));

    $status = 'success';
    $id = $_POST['id'];

    if (!$id)
      $status = 'error';
    else {
      delete_option('backupsavvy_unique_' . $id);
      $wpdb->delete(self::$records_table, array('unique_id' => $id));
    }

    $this->json_exit(array('status' => $status));

  }

  public function backup_progress()
  {
    // start balk operations
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    ignore_user_abort(TRUE);

    if (!ini_get('safe_mode')) {
      @set_time_limit(0);
    }

    // create log file
    if(!file_exists(plugin_dir_path(__DIR__).'/logs'));
      mkdir(plugin_dir_path(__DIR__).'/logs', '0755');

    $step = isset($_POST['step']) ? absint($_POST['step']) : 1;
    $start = ($step == 1 || $step == 0) ? 0 : ($step - 1) * 1;

    if($start == 0) {
      if(file_exists(plugin_dir_path(__DIR__).'/logs/backup-all.log'))
        unlink(plugin_dir_path(__DIR__).'/logs/backup-all.log');
    }

//		error_log($start . ' '.self::per_batch);
    // get all sites
    $site = self::get_sites(false, $start, 1);
    $site = $site[0];

//		error_log($step . print_r($sites,true));

    $curent_site = $status = '';
    if ($site) {
      $curent_site = $site->title;
      try {
        $status = (new backUpSavvyBackup)->backup_one($site);
      } catch (Exception $e) {
        throw new Exception($e->getMessage());
      }
    }

    $step++;
    $total = self::count_sites();
    $completed = 'no';
    if ($step > $total || !$total) $completed = 'completed';

    $this->json_exit(array('step' => $step,
      'total' => $total,
      'site' => $curent_site,
      'status' => $status,
      'id' => $site->unique_id,
      'completed' => $completed
    ));
  }

  public function backup_progress_options()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    ignore_user_abort(TRUE);

    if (!ini_get('safe_mode')) {
      @set_time_limit(0);
    }

  }

  private function check_child_protection($protection_code)
  {
    // realization of the curl request to child site and compare the code

    $postUrl = $this->url . '/wp-json/backupsavvyapi/addsite';

    $args = array(
      'timeout' => 45, // seconds
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array(),
      'body' => array(
        'apikey' => $this->apikey,
        'secret' => $protection_code
      ),
      'cookies' => array(),
      'sslverify' => false
    );

    $response = wp_remote_post($postUrl, $args);

    if (is_wp_error($response)) {
      $error_message = $response->get_error_message();
      error_log($error_message);
      return false;
    } else {
      $res = (object)json_decode($response['body']);
      if ($res->status != 'success')
        return false;
    }

    $unique_id = self::get_unique($protection_code);

    return $unique_id;
//    return $protection_code;
  }

  // generate unique
  public static function get_unique($input, $len = 7)
  {
    $time = round(microtime(true) * 1000);
    $hex = md5($input . $time);

    $pack = pack('H*', $hex);
    $tmp = base64_encode($pack);

    $uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);

    $len = max(4, min(128, $len));

    while (strlen($uid) < $len)
      $uid .= self::get_unique(22);

    return substr($uid, 0, $len);
  }

  public static function get_current_url()
  {

    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $escaped_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    return $escaped_url;
  }

  public static function backupsavvy_parse_current_url()
  {

    $url = self::get_current_url();

    $url = wp_parse_url($url);

    parse_str(html_entity_decode($url['query']), $url['query']);

    return $url;
  }

  private function download_file($file_name)
  {

//    $file_path = plugin_dir_path(__DIR__);
    $file_path = get_temp_dir();
    if(!is_writable($file_path))
      $file_path = BACKUPSAVVY_PLUGIN_PATH;

    $path = $file_path . "/" . $file_name;


    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    $browser = $_SERVER['HTTP_USER_AGENT'];

    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: zip');
    header('Content-Disposition: attachment; filename=' . $file_name);
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));

    header('Content-Type: application/zip');

    ob_clean();
    flush();
    readfile($path);
//        unlink($file);
    return;

//        if ($file = fopen($path, 'rb'))
//        {
//            while(!feof($file) and (connection_status()==0))
//            {
//                print(fread($file, filesize($path)));
//                flush();
//            }
//            fclose($file);
//        }
  }

}

if (!class_exists('storageSettings', false)) {
  class storageSettings
  {
    private $name;
    private $vaults = [];

    public function __construct($name, array $args)
    {
      $this->name = $name;
      $args['name'] = $name;
      $this->vaults[$name] = $args;
    }

    public function getStorage($name)
    {
      return !empty($this->vaults[$name]) ? $this->vaults[$name] : false;
    }

    /**
     * @param storageSettings $storage current storage
     * @param storageSettings $new_storage new storage to add it
     * @return storageSettings $storage
     */
    public function addStorage(storageSettings $storage, $new_storage)
    {

      $storage->vaults[$this->name] = $new_storage->getStorage($this->name); // array

      return $storage;

    }

    // will be removed storage from the saved storage object
    public function cleanStorage()
    {

    }

  }
}