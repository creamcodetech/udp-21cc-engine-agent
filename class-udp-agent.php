<?php

class Udp_Agent {

	public function __construct() {

		$this->hooks();

		$data = $this->get_data();
		$data_to_send = array();


		echo '<pre>';
		var_dump( $data['data'] );
		echo '</pre>';


		foreach( $data['data'] as $row ) {

			foreach( $row['fields'] as $key => $value ) {

				$val = isset( $value['value'] ) ? $value['value'] : '';
				if ( 'no' === strtolower( $val ) ) {
					$val = 0;
				} elseif ( 'yes' === strtolower( $val ) ) {
					$val = 1;
				}

				$data_to_send[ strtolower( $key ) ] = $val;

			}

		}


		// $prev_key = 'wp_url';

		// echo '<pre>';
		// echo "ALTER TABLE `udp_agent_data` "; 
		// foreach( $data_to_send as $key => $value ) {
		// 	echo "ADD `" .strtolower($key) . "` INT NULL AFTER `{$prev_key}`, \n";
		// 	$prev_key = strtolower( $key );
		// }

		// // var_dump( $data_to_send );

		// echo '</pre>';
		// die;
	}
	

	// ----------------------------------------------
	// public callable functions
	// ----------------------------------------------

	public function on_init() {
		$this->ask_permission_for_usage_tracking();
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
		// add_filter( 'override_load_textdomain', 'override_load_textdomain', 10, 2 );
	}


	private function send_data_to_engine() {

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