<?php

class backUpSavvyActivation {

	public function __construct() {
	}


	public static function deactivate() {
		global $wpdb;

//		$table_name = $wpdb->prefix . "backup_savvy_records";
//		$sql = "DROP TABLE IF EXISTS $table_name";
//		$wpdb->query($sql);

		$table_name = $wpdb->prefix . "backup_savvy_reports";
		$sql_rep = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query($sql_rep);

		delete_option('backupsavvy_storage');
		delete_option('backupsavvy_backup_settings');
		delete_option('backupsavvy_apikey');
		delete_option('backupsavvy_db_version');
	}

	public static function activate() {
		global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// creating new table
		$table_name = $wpdb->prefix . "backup_savvy_records";
		$sqls = array();

		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

			$sql = "CREATE TABLE " . $table_name . " (
				  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
				  activated INT(9) NULL,
				  title TEXT NOT NULL,
				  url VARCHAR(55) NOT NULL,
				  unique_settings TINYINT(1) DEFAULT '0',
				  protection VARCHAR(55) NOT NULL,
				  unique_id VARCHAR(55) NOT NULL,
				  time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				  UNIQUE KEY id (id),
				  UNIQUE KEY unique_id (unique_id)
				);";
      dbDelta( $sql );
		}

		$table_name = $wpdb->prefix . "backup_savvy_reports";
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			$sql_rep = "CREATE TABLE " . $table_name . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  url VARCHAR(55) NOT NULL,
				  action VARCHAR(55) NOT NULL,
				  status VARCHAR(55) NOT NULL,
				  unique_id VARCHAR(55) NOT NULL,
				  backup_name VARCHAR(55) NOT NULL,
				  time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				  UNIQUE KEY id (id)
				);";

      dbDelta( $sql_rep );
		}

		update_option('backupsavvy_apikey', '53t181Hf3Xe8f80we472aye7695167V9Q08a4c');

		// default backup settings
        $settings = array(
            'amount' => 4,
            'method' => 'tarGz',
            'compr' => 'norm'
        );
		update_option('backupsavvy_backup_settings', $settings);
	}

  public static function plugin_update($new_db_version) {
	  global $wpdb;

    $table_name = $wpdb->prefix . 'backup_savvy_reports';
    $check_action = (array) $wpdb->get_results(  "SELECT count(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = '{$wpdb->prefix}backup_savvy_reports' AND COLUMN_NAME = 'action'"  )[0];
    $check_backup_name = (array) $wpdb->get_results(  "SELECT count(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = '{$wpdb->prefix}backup_savvy_reports' AND column_name = 'backup_name'"  )[0];
    $check_data = (array) $wpdb->get_results(  "SELECT count(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = '{$wpdb->prefix}backup_savvy_reports' AND column_name = 'data'"  )[0];

    $check_action = (int) array_shift($check_action);
    $check_backup_name = (int) array_shift($check_backup_name);
    $check_data = (int) array_shift($check_data);

    if($check_action == 0) {
      $wpdb->query(
        "ALTER TABLE $table_name
           ADD COLUMN `action` VARCHAR(55) NOT NULL
          ");
    }

    if($check_backup_name == 0)
      $wpdb->query(
        "ALTER TABLE $table_name
           ADD COLUMN `backup_name` VARCHAR(55) NOT NULL
          ");

    if($check_data == 0)
      $wpdb->query(
        "ALTER TABLE $table_name
           ADD COLUMN `data` VARCHAR(128) DEFAULT NULL
          ");

    update_option('backupsavvy_db_version', $new_db_version);
  }
}