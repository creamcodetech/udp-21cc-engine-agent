<?php

global $this_agent_ver;

$engine_url           = 'http://localhost/woocommerce';
$root_dir             = dirname( __DIR__, 1 );
$this_agent_ver       = 1.0;
$all_installed_agents = get_option( 'udp_installed_agents', array() );
$this_agent_is_latest = true;

// make sure this agent is the latest.
foreach ( $all_installed_agents as $agent_name => $agent_ver ) {
    if ( $this_agent_ver < $agent_ver ) {
        $this_agent_is_latest = false;
        break;
    }
}

// load this agent, only if it is the latest version.
if ( $this_agent_is_latest ) {

    if ( ! class_exists( 'Udp_Agent' ) ) {
        require_once __DIR__ . '/class-udp-agent.php';
        new Udp_Agent( $root_dir, $engine_url );
    }

}

// activation hook for plugin.
register_activation_hook( $root_dir . DIRECTORY_SEPARATOR .  basename( $root_dir ) . '.php', 'udp_agent_is_activated_v1' );

// activation hook for theme.
add_action( 'after_switch_theme', 'udp_agent_is_activated_v1' );

if ( ! function_exists( 'udp_agent_is_activated_v1' ) ) {
    
    // activation hook callback
    // suffix is intentional.
    function udp_agent_is_activated_v1() {

        global $this_agent_ver;

        $root_dir = dirname( __DIR__, 1 );
        $installed_agents = get_option( 'udp_installed_agents', array() );

        $installed_agents[ basename( $root_dir ) ] = $this_agent_ver;

        update_option( 'udp_installed_agents', $installed_agents );

    }

}
