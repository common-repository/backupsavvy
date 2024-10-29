<?php

// new class will be created with the every new job

class backUpSavvyScheduler {
	const DEBUG = false;
	public $id;
	public static $opts;

	/**
	 * @var \backUpSavvyBackup
	 */
	private $bcInstance;
// todo: remove scheduler job when deactivation
	public function __construct( $args ) {

		$opt_def = array(
			'id'     => '',
			'events' => array(
				// hook name => $data
				'creating_sites_backups' => array(
					'interval_key'  => 'two_minutes',
					'interval_name' => 'Every 2 min',
					'interval'      => 60 * 60 / 30,
					'event_name'    => 'Create backup',
					'method'        => 'create_backup', // callback function
					'args'          => array()
				)
//				'creating_sites_backup1' => array(
//					'interval_key'  => 'half_an_hover',
//					'interval_name' => 'Every hour',
//					'interval'      => 60*60,
//					'event_name'    => 'Create backup',
//					'method'        => 'create_backup', // callback funtion
//					'args'          => array(),
//				),
			),
			// teh tasks for removing
			'clear'  => array(//'hook_name' => array( 'args' => array() ),
			),
		);

		$opts = array_merge($opt_def, $args);
		update_option('wpbiu_schedules', $opts);

		$opts = (object) $opts;

		if ( ! $this->id = $opts->id ) {
			wp_die( 'The wrong cron ID' );
		}

		self::$opts[ $this->id ] = $opts;

		add_filter( 'cron_schedules', array( & $this, 'add_intervals' ) );

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			foreach ( self::$opts[ $this->id ]->events as $key => $data ) {
//				error_log(print_r($data['method'],true));
				if ( method_exists( $this, $data['method'] ) ) {
					$func = array( & $this, $data['method'] );
				} elseif ( function_exists( $data['method'] ) ) {
					$func = $data['method'];
				}

				if ( isset( $func ) ) {
					add_action( $key, $func );
				}
			}
		}

		$this->bcInstance = new backUpSavvyBackup();

	}

	public function create_backup() {
		global $wpdb;

		if( self::DEBUG )
			die( '<br><br>next '. date('d M Y H:i', wp_next_scheduled('backupsavvy_cron_job') ) . ' and now ' . date('d M Y H:i') );

		$sites = backUpSavvySites::get_sites();
		if($sites)
			foreach ( $sites as $site ) {
				$status = $this->bcInstance->backup_one($site);
				if($status && $status != 'error') {
				  $this->bcInstance->upload_one($site);
        }
			}

	}

	public function create_backup_after() {
		// get all sites for the prevent scheduled time which with the error
		$sites = backUpSavvySites::get_iterat_sites();
		if($sites)
			foreach ( $sites as $site ) {
				$this->bcInstance->backup_one($site);
			}
	}

	public function add_intervals( $schedules ){
		foreach( self::$opts[$this->id]->events as $key => $data ){
			$schedules[ $data['interval_key'] ] = array(
				'interval' => $data['interval'],
				'display'  => $data['interval_name'],
			);
		}

		return $schedules;
	}

	static function activation(){
		self::deactivation(); // remove all jobs
		// add new
		foreach( self::$opts as $opt ){
			foreach( $opt->events as $key => $data )
				wp_schedule_event( (@ $data['start_time']?:time()), $data['interval_key'], $key );
		}
	}

	static function deactivation(){
		foreach( self::$opts as $opt ){
			// delete jobs with the same tasks
			foreach( $opt->events as $key => $data ) wp_clear_scheduled_hook( $key, $data['args'] );

			// delete special jobs which we want
			foreach( $opt->clear as $key => $data )  wp_clear_scheduled_hook( $key, $data['args'] );
		}
	}


}