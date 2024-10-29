<?php

class backUpSavvyBackup
{
  protected $apikey;

  public function __construct()
  {
    $this->apikey = get_option('backupsavvy_apikey', FALSE);
  }

  public function backup_one($site)
  {
    if (!ini_get('safe_mode')) {
      @set_time_limit(0);
    }
    $protection = md5($site->protection.'.bcssvy');

    $postUrl = $site->url . '/wp-json/backupsavvyapi/backup';
    $args = array(
      'timeout' => 500, // seconds
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array(),
      'body' => array(
        'apikey' => $this->apikey,
        'secret' => $protection,
      ),
      'cookies' => array(),
      'sslverify' => false
    );

    $response = wp_remote_post($postUrl, $args);

    if (is_wp_error($response)) {
      $error_message = $response->get_error_message();
      $data = array(
        'url' => $site->url,
        'status' => $error_message,
        'unique_id' => $site->unique_id,
        'action' => 'backup'
      );

      $this->save_report($data);
      $this->save_log_file($data);
      return $error_message;
    }

    if (!json_last_error() == JSON_ERROR_NONE) {
      $data = array(
        'url' => $site->url,
        'status' => 'error',
        'unique_id' => $site->unique_id,
        'action' => 'backup'
      );

      $this->save_report($data);
      $this->save_log_file($data);
      return false;
    }

    $res = (object)json_decode($response['body']);

    $status = 'success';
    if (!isset($res->status))
      $status = array('backup_error');

    $backup_name = '';
    if(isset($res->backup))
      $backup_name = basename($res->backup);
    $data = array(
      'url' => $site->url,
      'status' => $status,
      'unique_id' => $site->unique_id,
      'action' => 'backup',
      'backup_name' => $backup_name
    );

    $this->save_report($data);
    $this->save_log_file($data);

    return $status;
  }

  public function upload_one($site)
  {
    $postUrl = $site->url . '/wp-json/backupsavvyapi/upload';
    $protection = md5($site->protection.'.bcssvy');
//        error_log('upload '.print_r($site, true));
    $args = array(
      'timeout' => 1800, // seconds
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array(),
      'body' => array(
        'apikey' => $this->apikey,
        'secret' => $protection
      ),
      'cookies' => array(),
      'sslverify' => false
    );

    $response = wp_remote_post($postUrl, $args);

    if (is_wp_error($response)) {
      $error_message = $response->get_error_message();
      error_log('error_message ' . print_r($error_message, true));
      $data = array(
        'url' => $site->url,
        'status' => 'error: upload error',
        'unique_id' => $site->unique_id,
        'action' => 'upload'
      );
//            error_log('before_save_report');
//            error_log('data '.print_r($data, true));
      $this->save_report($data);
      $this->save_log_file($data, 'append');
//            error_log('after_save_report');
      return 'error';
    } else {
      $res = (object)json_decode($response['body']);

      if (!isset($res->status)) {
        $status = array('upload error');
        $backup_name = '';
      } else {
        $status = $res->status;
        $backup_name = $res->backup_name;
        if(!empty($res->result))
          $result_data = serialize($res->result);
      }

      $data = array(
        'url' => $site->url,
        'status' => $status,
        'unique_id' => $site->unique_id,
        'backup_name' => $backup_name,
        'action' => 'upload'
      );

      if(isset($result_data))
        $data['data'] = $result_data;

      $this->save_report($data);
      $this->save_log_file($data, 'append');
      return $status;
    }

  }

  private function save_report($data)
  {
    global $wpdb;
    $url = $data['url'];
    $status = $data['status'] == 'success' ? 1 : serialize($data['status']);
    $action = $data['action'];
    $backup_name = $data['backup_name'];

    $unique_id = $data['unique_id'];
    $additiona_data = isset($data['data']) ? $data['data'] : NULL;
    $time = time();
    $date = date('Y-m-d');

    $table_name = $wpdb->prefix . "backup_savvy_reports";
    $sql = "INSERT INTO " . $table_name . " (url,action,status,unique_id,backup_name,time,data) VALUES (%s,%s,%s,%s,%s,%s,%s)";
    $sql = $wpdb->prepare($sql, $url, $action, $status, $unique_id, $backup_name,$date,$additiona_data);
    $wpdb->query($sql);

  }

  private function save_log_file($data, $options = false)
  {
    if (!file_exists(plugin_dir_path(__DIR__) . '/logs'))
      mkdir(plugin_dir_path(__DIR__) . '/logs', 0755);

    if (!$options || (isset($options['write']) && $options['write'] == 1))
      $write = 'w+';
    else
      $write = 'a+';

    $log = '[' . date('d.m.Y H:i:s') . ']' . "\n";
    foreach ($data as $item) {
      if (is_array($item))
        $log .= print_r($item, 1) . "\n";
      else
        $log .= $item . "\n";
    }
    $log .= "\n";

    $f = fopen(plugin_dir_path(__DIR__) . '/logs/last-backups.log', $write);
    fwrite($f, $log);
    fclose($f);
  }
}