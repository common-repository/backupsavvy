<?php

class backUpSavvyReports
{

  public function __construct()
  {
    add_action('wp_ajax_backupsavvy_log_process', array(&$this, 'log_process'));
  }

  public static function get_report($start = false, $per_page = false)
  {
    global $wpdb;

    $table = $wpdb->prefix . "backup_savvy_reports";
    $table_sites = $wpdb->prefix . "backup_savvy_records";

    // get all sites
    if ($start !== false && $per_page) {
      $sites = $wpdb->get_results('SELECT title,unique_id FROM ' . $table_sites . ' LIMIT ' . $start . ', ' . $per_page);
    } else
      $sites = $wpdb->get_results('SELECT title,unique_id FROM ' . $table_sites);

    $storage = maybe_unserialize(get_option('backupsavvy_storage', false));
    $limit = $storage['amount'] ? $storage['amount'] : 2;

    foreach ($sites as $site) {
      $backups[] = $wpdb->get_results('SELECT * FROM ' . $table . ' WHERE unique_id="' . $site->unique_id . '" and action="upload" ORDER BY ID DESC LIMIT ' . $limit);
    }
    return $backups;
  }

  public function log_process()
  {
    check_ajax_referer('set-wpbckup_sets', 'nonce');
    if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets'))
      $this->json_exit(array('status' => 'nonce', 'site' => ''));

    $data = array();
    parse_str($_POST['data'], $data);

    switch ($data['logs']) {
      case 'export_csv':
        $result = $this->export_csv();
        break;
      case 'export_txt':
        $result = $this->export_txt();
        break;
      case 'last_backup_log':
        $result = $this->get_last_log();
        break;
      default:
        $result = $this->load_php_info();
        break;
    }


    $this->json_exit(array('status' => 'success', 'result' => $result));
  }

  private function export_csv()
  {

    return 1;
  }

  private function export_txt()
  {

    return 1;
  }

  private function get_last_log() {
    global $wpdb;
    $storage = get_option('backupsavvy_storage');

    $amount = $storage['amount'];


  }

  private function load_php_info()
  {
    ob_start();
    phpinfo(INFO_MODULES);
    $s = ob_get_contents();
    ob_end_clean();
    $s = strip_tags($s, '<h2><th><td>');
    $s = preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $s);
    $s = preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $s);
    $t = preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    $r = array();
    $count = count($t);
    $p1 = '<info>([^<]+)<\/info>';
    $p2 = '/' . $p1 . '\s*' . $p1 . '\s*' . $p1 . '/';
    $p3 = '/' . $p1 . '\s*' . $p1 . '/';
    for ($i = 1; $i < $count; $i++) {
      if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $t[$i], $matchs)) {
        $name = trim($matchs[1]);
        $vals = explode("\n", $t[$i + 1]);
        foreach ($vals AS $val) {
          if (preg_match($p2, $val, $matchs)) { // 3cols
            $r[$name][trim($matchs[1])] = array(trim($matchs[2]), trim($matchs[3]));
          } elseif (preg_match($p3, $val, $matchs)) { // 2cols
            $r[$name][trim($matchs[1])] = trim($matchs[2]);
          }
        }
      }
    }

    $result = '';
    foreach ($r as $key => $items) {
      $result .= "<h3>$key</h3>";
      $table = '';
      if (is_array($items)) {
        $table = '<table>';
        foreach ($items as $key1 => $item) {
          if (!is_array($item))
            $table .= "<tr><td>$key1</td><td>$item</td></tr>";
        }
        $table .= "</table>";
      }
      $result .= $table;
    }

    return $result;
  }

  /**
   * @param $data array
   */
  private function json_exit($data)
  {
    echo json_encode((object)$data);
    wp_die();
  }


}