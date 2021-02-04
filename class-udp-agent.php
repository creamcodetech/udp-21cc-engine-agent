<?php

class Udp_Agent {

	public function __construct() {

		$this->hooks();

	}
	

	// ----------------------------------------------
	// public callable functions
	// ----------------------------------------------

	public function on_init() {

		if ( isset( $_GET['test']) ) {
			// $this->udp_agent_is_activated();
			$this->send_data_to_engine();
		}


		// process user tracking actions.
		if ( isset( $_GET['udp-agent-allow-access'] ) ) {
			$this->process_user_tracking_actions();
		}
	}

	
	public function on_admin_init() {

		$this->show_user_tracking_admin_notice();

		// register and save settings data.
		register_setting(
			'general', 
			'udp_agent_allow_tracking',
			array(
				'type'              => 'string', 
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => NULL,
			)
		); 

		// show ui in settings page.
		add_settings_field(
			'udp_agent_allow_tracking', 
			__( 'Allow Anonymous Tracking', 'udp-agent' ),
			array( $this, 'show_settings_ui' ),
			'general',
			'default', 
			array(
				'label_for' => 'udp_agent_allow_tracking'
			)
		);
	}

	public function show_user_tracking_admin_notice() {

		$users_choice = get_option( 'udp_agent_allow_tracking' );

		if ( 'later' !== $users_choice && ! empty( $users_choice ) ) {
			// do not show this message.
			// user has already chosen to show or not-show this message.
			return;

		} else {

			$tracking_msg_last_shown_at = intval( get_option( 'udp_agent_tracking_msg_last_shown_at' ) );

			if ( $tracking_msg_last_shown_at > ( time() - DAY_IN_SECONDS ) ) {
				// do not show,
				// if last admin notice was shown less than 1 day ago.
				return;
			}
		}

		$content = '<p>Allow Anonymous Tracking ?</p><p>';
		$content .= sprintf(
			__( '<a href="%s" class="button button-primary udp-agent-access_tracking-yes" style="margin-right: 10px" >%s</a>', 'udp-agent' ),
			add_query_arg( 'udp-agent-allow-access', 'yes' ),
			'Allow'
		);

		$content .= sprintf(
			__( '<a href="%s" class="button button-secondary udp-agent-access_tracking-no" style="margin-right: 10px" >%s</a>', 'udp-agent' ),
			add_query_arg( 'udp-agent-allow-access', 'no' ),
			'Do not show again'
		);

		$content .= sprintf(
			__( '<a href="%s" class="button button-secondary udp-agent-access_tracking-yes" style="margin-right: 10px" >%s</a>', 'udp-agent' ),
			add_query_arg( 'udp-agent-allow-access', 'later' ),
			'Later'
		);

		$content .= '</p>';
		$this->show_admin_notice( 'warning', $content );
	}


	public function show_settings_ui() {
		echo '<p>';
		echo "<input type='checkbox' id='udp_agent_allow_tracking' value='1'";
		if ( '1' === get_option('udp_agent_allow_tracking') ) {
			echo ' checked';
		}
		echo '/>';
		echo __( 'Become a contributor by opting in to our anonymous data tracking. We guarantee no sensitive data is collected. <a href="#" target="_blank" >What do we track?</a>' ) . ' </p>';
	}

	
	// our theme is activated.
	public function udp_agent_is_activated() {

		$track_user = get_option( 'udp_agent_allow_tracking' );
		if ( 'yes' !== $track_user ) { 
			// do not collect user data.
			return;
		}

		$secret_key = get_option( 'udp_agent_secret_key' );
		$installed_agent_version = get_option( 'udp_agent_version' );

		if ( ! empty( $secret_key ) && ! empty( $installed_agent_version ) && floatval( UDP_AGENT_VERSION ) === floatval( $installed_agent_version ) ) {

			// secret_key and installed_agent_version already exists.
			// agent version is also same.
			return;
		}

		// handshake with engine.

		$data['site_url'] = get_bloginfo( 'url' );
		$url = UDP_API_URL . '/wp-json/udp-engine/v1/handshake';

		// get secret key from engine.
		$secret_key = json_decode( $this->do_curl( $url, $data ) );

		if ( empty( $secret_key ) ) {
			error_log( __FUNCTION__ . ' : Cannot get secret key from engine.' );
			return;
		}

		// save secret_key into db.
		update_option( 'udp_agent_secret_key', $secret_key );
		update_option( 'udp_agent_version', UDP_AGENT_VERSION );

	}



	// ----------------------------------------------
	// private functions
	// ----------------------------------------------

	private function hooks() {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// if udp agent is theme.
		add_action( 'after_switch_theme', array( $this, 'udp_agent_is_activated' ) );

		// custom cron.
		add_action( 'init', array( $this, 'udp_schedule_cron' ) );
	}



	// user has decided to allow or not allow user tracking.
	// process it.
	private function process_user_tracking_actions() {
		$users_choice = $_GET['udp-agent-allow-access'];

		if ( empty( $users_choice ) ) {
			return;
		}

		// add data into database.
		update_option( 'udp_agent_allow_tracking', $users_choice );
		update_option( 'udp_agent_tracking_msg_last_shown_at', time() );

		if ( 'yes' === $users_choice ) {
			// establish connection with udp-engine ( hand shake ).
		}

		// redirect back.
		wp_redirect( remove_query_arg( 'udp-agent-allow-access' ) );
		exit;

	}


	

	private function get_data() {

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			require_once ABSPATH . 'wp-includes/load.php';
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$data = array();

		$data['data'] = WP_Debug_Data::debug_data();
		$data['root_domain'] = parse_url( get_bloginfo( 'url' ) )['host'];
		$data['admin_email'] = get_bloginfo( 'admin_email' );
		$data['wp_url']      = get_bloginfo( 'wpurl' );
		$data['site_url']    = get_bloginfo( 'url' );
		$data['version']     = get_bloginfo('version');

		return $data;

	}


	private function show_admin_notice( $error_class, $msg ) {
		
		add_action(
			'admin_notices',
			function() use( $error_class, $msg ) {
				$class = 'is-dismissible  notice notice-' . $error_class;
				printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), $msg );
			}
		);
	}


	


	// ------------------------------------------------
	// Cron
	// ------------------------------------------------

	/**
	 * Custom cron job, runs daily
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function udp_schedule_cron() {

		$cron_hook_name = 'udp_agent_cron';
		add_action( $cron_hook_name, array( $this, 'send_data_to_engine' ) );

		if ( ! wp_next_scheduled( $cron_hook_name ) ) {
			wp_schedule_event( time(), 'daily', $cron_hook_name );
		}

	}

	/**
	 * Custom cron job callback function.
	 * Send data collected from agent to engine.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function send_data_to_engine() {

		$track_user = get_option( 'udp_agent_allow_tracking' );

		if ( 'yes' !== $track_user ) { 
			// do not send data.
			return;
		}

		$data_to_send['udp_data'] = serialize( $this->get_data() );
		$data_to_send['secret_key'] = get_option( 'udp_agent_secret_key' );
		$url = UDP_API_URL . '/wp-json/udp-engine/v1/process-agent-data';

		echo '<pre>';
		var_dump( $this->do_curl( $url, $data_to_send ) );
		die;
	}


	// A little helper function to do curl request.	
	private function do_curl( $url, $data_to_send ) {
		// open connection.
		$ch = curl_init();

		// set the url, number of POST vars, POST data.
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_to_send );

		// execute post.
		$response = curl_exec( $ch );

		// close connection
		curl_close( $ch);

		return $response;
	}

}

new Udp_Agent();