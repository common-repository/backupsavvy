<?php
/**
 * Plugin Name: BackUpSavvy Free
 * Plugin URI: https://backupsavvy.com
 * Description: WordPress Backup Plugin
 * Author: Backupsavvy.com
 * Version: 1.0.6
 * Domain Path: https://backupsavvy.com
 * Network: true
 * License: Backupsavvy
 */

if (!defined('ABSPATH')) exit;

// If this file is called directly, abort.
if (!defined('WPINC')) die;

define('BACKUPSAVVY_PLUGIN_PATH', plugin_dir_path(__FILE__));
// if BACKUPSAVVY_TEST_MODE == 1, will be used btest branch
if(!defined('BACKUPSAVVY_TEST_MODE')) define('BACKUPSAVVY_TEST_MODE', 0);
if(!defined('BACKUPSAVVY_BASE_PATH')) define('BACKUPSAVVY_BASE_PATH', __DIR__);

if (!class_exists('BackUpSavvy', false)) {
  /**
   * Main BackUpSavvy Plugin Class
   */
  final class BackUpSavvy
  {
    private static $instance = null;

    public function __construct()
    {
      // Nothing else matters if we're not on the main site
      if (!is_main_network() && !is_main_site()) {
        return;
      }

      include_once 'inc/BackUpSavvyActivation.php';

      // Deactivation hook
      register_deactivation_hook(__FILE__, array('backUpSavvyActivation', 'deactivate'));

      $this->activate();

    }


    public function activate()
    {

      // todo:: add user role protection when ajax requests
      add_action('admin_menu', array(&$this, 'register_backup_savvy_custom_menu'), 10);

      /*Custom Hooks for style and js files*/
      add_action('admin_enqueue_scripts', array(&$this, 'register_backup_savvy_scripts'));
      add_action('wp_ajax_backupsavvy_add_new_job', array(&$this, 'scheduler_job_creator'));
      add_action( 'admin_init', array(&$this, 'update_database') );

      include_once 'inc/BackupSavvyCrud.php';
      include_once 'inc/BackUpSavvyBackup.php';
      include_once 'inc/pagination.php';
      include_once 'inc/BackupSavvyRestore.php';
      include_once 'inc/BackUpSavvySites.php';
      include_once 'inc/BackUpSavvyScheduler.php';
      include_once 'inc/BackUpSavvyReports.php';
      include_once 'inc/BackupSavvyStorage.php';
      include_once 'inc/BackupSavvyReader.php';

      new backUpSavvySites();
      new backupSavvyStorage();
      new backUpSavvyReports();
      new backupSavvyRestore();

      $scheduler_jobs = get_option('wpbiu_schedules', array());
      if ($scheduler_jobs)
        new backUpSavvyScheduler($scheduler_jobs);


    }


    public function update_database() {
      $db_version = get_option('backupsavvy_db_version', 0);
      if($db_version !== 0) {
        if (version_compare($db_version, '1.1.0', '<')) {
          require_once 'inc/BackUpSavvyActivation.php';
          backUpSavvyActivation::plugin_update($db_version);
        }
      } else {
        require_once 'inc/BackUpSavvyActivation.php';
        backUpSavvyActivation::plugin_update($db_version);
      }

    }

    public function scheduler_job_creator()
    {

      check_ajax_referer('set-wpbckup_sets', 'nonce');
      if (!wp_verify_nonce($_POST['nonce'], 'set-wpbckup_sets')) {
        echo json_encode((object)array('status' => 'nonce'));
        wp_die();
      }

      $data = array();
      parse_str($_POST['data'], $data);
      $event_name = $data['title'];

      $days = date("t");
      switch ($data['field4']):
        case 'monthly':
          $interval = 60 * 60 * 24 * $days;
          $interval_name = 'Every month';
          $interval_key = 'every_month';
          break;
        case 'weekly':
          $interval = 60 * 60 * 24 * 7;
          $interval_name = 'Every week';
          $interval_key = 'every_week';
          break;
        case 'daily':
          $interval = 60 * 60 * 24;
          $interval_name = 'Once in a day';
          $interval_key = 'every_day';
          break;
        case 'hourly':
          $interval = 60 * 60;
          $interval_name = 'Once in a hour';
          $interval_key = 'every_hour';
          break;
        case 'test':
          $interval = 60 * 2;
          $interval_name = 'Once in the two minutes';
          $interval_key = 'every_tow_min';
          break; // for the test every 2 menutes
        default:
          $interval = 0;
      endswitch;

      if ($interval) {
        $job = array(
          'id' => 'backupsavvy_cron_job1',
          'events' => array(
            // hook name => $data
            'creating_sites_backups' => array(
              'interval_key' => $interval_key,
              'interval_name' => $interval_name,
              'interval' => $interval,
              'event_name' => $event_name,
              'method' => 'create_backup',// callback function
              'args' => array()
            ),
            'create_backups_after' => array(
              'interval_key' => 'every_tw_min',
              'interval_name' => 'Every twenty minutes',
              'interval' => 60 * 20,
              'event_name' => 'repeat wrong backups',
              'method' => 'create_backup_after',// callback function
              'args' => array()
            )
          ),
          'clear' => array( // tasks to delation
            'hook_name' => array('args' => array())
          )
        );

        new backUpSavvyScheduler($job);

        backUpSavvyScheduler::activation();
      }

      echo json_encode((object)array('status' => 'success'));

      wp_die();
    }

    public function register_backup_savvy_custom_menu()
    {
      add_menu_page('Wp BackUpSavvy', 'Wp BackUpSavvy', 'manage_options', 'backup-savvy-settings', array(&$this, 'backup_savvy_settings'), 'dashicons-welcome-widgets-menus', '30.21');

    }


    public function backup_savvy_settings()
    {
      include_once 'parts/settings-page.php';
    }

    public static function get_instance()
    {
      if (null === self::$instance) {
        self::$instance = new self;
      }

      return self::$instance;
    }


    private static function get_vars()
    {
      return (object)array(
        'version' => '1.0.6',
        'status' => 'free',
        'php' => '7.2'
      );
    }

    /*
Include JS and CSS in Admin Panel
*/

    public function register_backup_savvy_scripts($hook)
    {
//            wp_register_style( 'backupsavvy_admin_bootstrap', plugins_url('assets/bootstrap.min.css', __FILE__));
//            wp_enqueue_style('backupsavvy_admin_bootstrap');

      wp_register_style('backupsavvy_style', plugins_url('assets/backupsavvy_style.css', __FILE__));
      wp_register_style('jbox_style', plugins_url('assets/jBox.all.min.css', __FILE__));
      wp_enqueue_style('backupsavvy_style');
      wp_enqueue_style('jbox_style');

      wp_enqueue_script('backupsavvy_tablesorter_js', plugins_url('assets/jquery.tablesorter.min.js', __FILE__));
      wp_enqueue_script('backupsavvy_render_js', plugins_url('assets/backupsavvy-render.js', __FILE__));
      wp_enqueue_script('backupsavvy_js', plugins_url('assets/backupsavvy.js', __FILE__));
      wp_enqueue_script('backupsavvy_oauth_js', plugins_url('assets/backupsavvy_aouth.js', __FILE__));
      wp_enqueue_script('jbox_js', plugins_url('assets/jBox.all.min.js', __FILE__));
      wp_enqueue_script('backupsavvy_restore_js', plugins_url('assets/backupsavvy-restore.js', __FILE__));
      wp_enqueue_script('sweetalert2_js', plugins_url('assets/sweetalert2.all.min.js', __FILE__));

      $params = array(
        'nonce' => wp_create_nonce('set-wpbckup_sets'),
        'ajax_url' => admin_url('admin-ajax.php'),
      );
      wp_localize_script('backupsavvy_js', 'localVars', $params);
      wp_localize_script('backupsavvy_oauth_js', 'localVars', $params);
      wp_localize_script('backupsavvy_restore_js', 'localVars', $params);
      wp_localize_script('sweetalert2_js', 'localVars', $params);
    }

  }

  //Start Plugin
  add_action('plugins_loaded', array('BackUpSavvy', 'get_instance'));

  function backupsavvy_activation_hook()
  {
    include_once 'inc/BackUpSavvyActivation.php';
    backUpSavvyActivation::activate();
  }

  register_activation_hook(__FILE__, 'backupsavvy_activation_hook');
}
