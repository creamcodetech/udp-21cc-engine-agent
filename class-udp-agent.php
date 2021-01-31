<?php

class Udp_Agent {

	private $engine_url = 'http://localhost/woocommerce';

	public function __construct() {

		$this->hooks();


		// $data = $this->get_data();
		// $data_to_send = array();

		// foreach( $data['data'] as $row ) {

		// 	foreach( $row['fields'] as $key => $value ) {

		// 		$val = isset( $value['value'] ) ? $value['value'] : '';
		// 		if ( 'no' === strtolower( $val ) ) {
		// 			$val = 0;
		// 		} elseif ( 'yes' === strtolower( $val ) ) {
		// 			$val = 1;
		// 		}

		// 		$data_to_send[ strtolower( $key ) ] = $val;

		// 	}

		// }

		// $data_to_send['wp_url'] = $data['wp_url'];
		// $data_to_send['site_url'] = $data['site_url'];

	}
	

	// ----------------------------------------------
	// public callable functions
	// ----------------------------------------------

	public function on_init() {
		$this->ask_permission_for_usage_tracking();

		if ( isset( $_GET['test']) ) {
			$this->send_data_to_engine();

			// echo '<pre>';
			// var_dump( $this->get_data() );
			// echo '</pre>';
			// die;


			// get column names

			// global $wpdb;
			// $response = $wpdb->get_col( "DESC udp_agent_data", 0 ); 

			// echo '<pre>';
			// var_dump( $response );
			// echo '</pre>';
			// die;

		}
	}


	public function ask_permission_for_usage_tracking() {
		$content = '<p>Allow Anonymous Tracking ?</p><p>';
		$content .= sprintf(
			__( '<a href="%s" class="button button-primary udp-agent-access_tracking-yes" style="margin-right: 10px" >%s</a>', 'udp-agent' ),
			add_query_arg( 'udp-agent-allow-access', 1 ),
			'Allow'
		);

		$content .= sprintf(
			__( '<a href="%s" class="button button-secondary udp-agent-access_tracking-no" style="margin-right: 10px" >%s</a>', 'udp-agent' ),
			add_query_arg( 'udp-agent-allow-access', 0 ),
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

	public function override_load_textdomain( $override, $domain ) {

		return;

		// Check if the domain is our framework domain.
		if ( 'jt-framework' === $domain ) {
			global $l10n;

			// If the theme's textdomain is loaded, assign the theme's translations
			// to the framework's textdomain.
			if ( isset( $l10n[ 'jt-theme' ] ) )
				$l10n[ $domain ] = $l10n[ 'jt-theme' ];

			// Always override.  We only want the theme to handle translations.
			$override = true;
		}

		return $override;
	}


	// ----------------------------------------------
	// private functions
	// ----------------------------------------------

	private function hooks() {
		add_action( 'init', array( $this, 'on_init' ) );
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