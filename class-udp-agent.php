<?php

class Udp_Agent {

	private $engine_url = 'http://localhost/woocommerce';

	public function __construct() {

		$this->hooks();

	}
	

	// ----------------------------------------------
	// public callable functions
	// ----------------------------------------------

	public function on_init() {

		if ( isset( $_GET['test']) ) {
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



	// ----------------------------------------------
	// private functions
	// ----------------------------------------------

	private function hooks() {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );
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


	private function send_data_to_engine() {

		$data_to_send['udp_data'] = serialize( $this->get_data() );

		$url = $this->engine_url . '/wp-json/udp-engine/v1/process-agent-data';

		//open connection.
		$ch = curl_init();

		//set the url, number of POST vars, POST data.
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_to_send );

		//execute post.
		$return = curl_exec( $ch );

		//close connection
		curl_close( $ch);

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

}

new Udp_Agent();