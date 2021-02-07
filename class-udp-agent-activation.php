<?php

class Udp_Agent_Activation {

	private static $version;
	private static $agent_root_dir;

	public function __construct( $ver ) {

		self::$version        = $ver;
		self::$agent_root_dir = dirname( __DIR__, 1 );

		// -----------------------------------------
		// activation
		// -----------------------------------------

		// activation hook for plugin.
		register_activation_hook(
			self::$agent_root_dir . DIRECTORY_SEPARATOR .  basename( self::$agent_root_dir ) . '.php',
			array( 'Udp_Agent_Activation', 'udp_agent_is_activated' )
		);

		// activation for theme.
		add_action( 'after_switch_theme', array( 'Udp_Agent_Activation', 'udp_agent_is_activated' ) );

		// -----------------------------------------
		// De-activation
		// -----------------------------------------

		// de-activation hook for plugin
		register_deactivation_hook(
			self::$agent_root_dir . DIRECTORY_SEPARATOR .  basename( self::$agent_root_dir ) . '.php',
			array( 'Udp_Agent_Activation', 'udp_agent_is_deactivated' )
		);
	}


	public static function udp_agent_is_activated() {

        $installed_agents = get_option( 'udp_installed_agents', array() );

        $installed_agents[ basename( self::$agent_root_dir ) ] = self::$version;

        update_option( 'udp_installed_agents', $installed_agents );
    }


	public static function udp_agent_is_deactivated() {

        $installed_agents = get_option( 'udp_installed_agents', array() );

        $installed_agents[ basename( self::$agent_root_dir ) ] = self::$version;

        update_option( 'udp_installed_agents', $installed_agents );
    }

}

