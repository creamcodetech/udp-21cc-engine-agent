<?php

class Udp_Agent {

	private $curl_in_progress = false;
	private $version;
	private $agent_name;
	private $engine_url;
	private $agent_root_dir;

	public function __construct( $ver, $agent_root_dir, $engine_url ) {

		$this->version        = $ver;
		$this->engine_url     = $engine_url;
		$this->agent_root_dir = $agent_root_dir;

		$this->hooks();

	}


	// ----------------------------------------------
	// Hooks.
	// ----------------------------------------------

	// all hooks will be called from this method.
	private function hooks() {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// custom cron.
		add_action( 'init', array( $this, 'udp_schedule_cron' ) );
	}

	public function on_init() {

		// process user tracking actions.
		if ( isset( $_GET['udp-agent-allow-access'] ) ) {
			$this->process_user_tracking_choice();
		}

	}
	
	public function on_admin_init() {

		$this->show_user_tracking_admin_notice();

		// register and save settings data.
		register_setting(
			'general', 
			'udp_agent_allow_tracking',
			array(
				'sanitize_callback' => array( $this, 'get_settings_field_val' ),
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


	public function get_settings_field_val( $data ) {
		if ( '1' === $data ) {
			return 'yes';
		} else {
			return 'no';
		}

	}


	// ----------------------------------------------
	// Settings page UI.
	// ----------------------------------------------

	public function show_settings_ui() {
		echo '<p>';
		echo "<input type='checkbox' name='udp_agent_allow_tracking' id='udp_agent_allow_tracking' value='1'";
		if ( 'yes' === get_option('udp_agent_allow_tracking') ) {
			echo ' checked';
		}
		echo '/>';
		echo __( 'Become a contributor by opting in to our anonymous data tracking. We guarantee no sensitive data is collected. <a href="#" target="_blank" >What do we track?</a>' ) . ' </p>';
	}


	// ----------------------------------------------
	// Show admin notice, for collecting user data.
	// ----------------------------------------------

	// show admin notice to collect user data.
	public function show_user_tracking_admin_notice() {

		$show_admin_notice = true;
		$users_choice = get_option( 'udp_agent_allow_tracking' );

		if ( 'later' !== $users_choice && ! empty( $users_choice ) ) {

			// user has already clicked "yes" or "no" in admin notice.
			// do not show this notice.
			$show_admin_notice = false;

		} else {

			$tracking_msg_last_shown_at = intval( get_option( 'udp_agent_tracking_msg_last_shown_at' ) );

			if ( $tracking_msg_last_shown_at > ( time() - DAY_IN_SECONDS ) ) {
				// do not show,
				// if last admin notice was shown less than 1 day ago.
				$show_admin_notice = false;
			}

		}

		if ( ! $show_admin_notice ) {
			return;
		}

		$content = '<p>' . sprintf( 
			__( '%s is asking to allow anonymous tracking ?', 'udp-agent' ), 
			$this->find_agent_name( $this->agent_root_dir )
		) . '</p><p>';

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



	// user has decided to allow or not allow user tracking.
	// process it.
	private function process_user_tracking_choice() {
		$users_choice = isset( $_GET['udp-agent-allow-access'] ) ? sanitize_text_field( wp_unslash( $_GET['udp-agent-allow-access'] ) ) : '';

		if ( empty( $users_choice ) ) {
			return;
		}

		// add data into database.
		update_option( 'udp_agent_allow_tracking', $users_choice );
		update_option( 'udp_agent_tracking_msg_last_shown_at', time() );
		// update_option( 'udp_active_agent_basename', basename( $this->agent_root_dir ) );

		// redirect back.
		wp_redirect( remove_query_arg( 'udp-agent-allow-access' ) );
		exit;

	}

	// a little helper function to show admin notice.
	private function show_admin_notice( $error_class, $msg ) {
		
		add_action(
			'admin_notices',
			function() use( $error_class, $msg ) {
				$class = 'is-dismissible  notice notice-' . $error_class;
				printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), $msg );
			}
		);
	}


	// ----------------------------------------------
	// Data collection and authentication with engine.
	// ----------------------------------------------

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


	// authenticate with engine server.
	// get secret key from engine.
	// run only once.
	public function do_handshake() {

		// secret key will be same for all agents.
		$secret_key = get_option( 'udp_agent_secret_key' );

		if ( ! empty( $secret_key ) ) {

			// secret_key already exists.
			// do nothing.
			return true;
		}

		// authenticate with engine.

		$data['site_url'] = get_bloginfo( 'url' );
		$url = $this->engine_url . '/wp-json/udp-engine/v1/handshake';

		// get secret key from engine.
		$secret_key = json_decode( $this->do_curl( $url, $data ) );

		if ( empty( $secret_key ) ) {
			error_log( __FUNCTION__ . ' : Cannot get secret key from engine.' );
			return false;
		}

		// save secret_key into db.
		update_option( 'udp_agent_secret_key', $secret_key );

		return true;

	}


	// get agent name
    private function find_agent_name( $root_dir ) {

		if ( ! empty( $this->agent_name ) ) {
			return $this->agent_name;
		}

		$agent_name = '';

        if ( file_exists( $root_dir . '/functions.php' ) ) {
            // it is a theme
            // return get_style

            $my_theme = wp_get_theme( basename( $root_dir ) );
            if ( $my_theme->exists() ) {
                $agent_name = $my_theme->get( 'Name' );
				
            }
			
        } else {
			// it is a plugin.
			$plugin_file = $this->agent_root_dir . DIRECTORY_SEPARATOR .  basename( $this->agent_root_dir ) . '.php';
			$plugin_data = get_file_data(
				$plugin_file,
				array(
					'name' => 'Plugin Name',
				)
			);

			$agent_name = $plugin_data['name'];
        }
		
		$this->agent_name = $agent_name;
		return $agent_name;
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
	 * @return void
	 */
	public function send_data_to_engine() {

		$track_user = get_option( 'udp_agent_allow_tracking' );

		if ( 'yes' !== $track_user ) { 
			// do not send data.
			return;
		}

		$data_to_send['udp_data'] = serialize( $this->get_data() );
		$data_to_send['secret_key'] = get_option( 'udp_agent_secret_key' );
		$url = $this->engine_url . '/wp-json/udp-engine/v1/process-agent-data';

		$this->do_curl( $url, $data_to_send );
		exit;

	}


	// A little helper function to do curl request.	
	private function do_curl( $url, $data_to_send ) {
		// open connection.
		$ch = curl_init();

		// set the url, POST data.
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
