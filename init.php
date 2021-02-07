<?php

global $this_agent_ver;

// -------------------------------------------
// Config
// -------------------------------------------

$engine_url           = 'http://localhost/woocommerce';
$root_dir             = dirname( __DIR__, 1 );
$this_agent_ver       = 1.0;
$all_installed_agents = get_option( 'udp_installed_agents', array() );
$this_agent_is_latest = true;

echo '<pre>';
var_dump( $all_installed_agents );
echo '</pre>';

// -------------------------------------------
// Do not edit from here
// -------------------------------------------

// make sure this agent is the latest.
foreach ( $all_installed_agents as $agent_ver ) {
    if ( $this_agent_ver < $agent_ver ) {
        $this_agent_is_latest = false;
        break;
    }
}

// load this agent, only if it is the latest version.
if ( $this_agent_is_latest && isset( $all_installed_agents[ basename( $root_dir ) ] ) ) {

    if ( ! class_exists( 'Udp_Agent' ) ) {
        require_once __DIR__ . '/class-udp-agent.php';
        new Udp_Agent( $this_agent_ver, $root_dir, $engine_url );
    }
    
    // -------------------------------------------
    // Agent Activation
    // -------------------------------------------
    
    require_once __DIR__ . '/class-udp-agent-activation.php'; 
    new Udp_Agent_Activation( $this_agent_ver );

}
