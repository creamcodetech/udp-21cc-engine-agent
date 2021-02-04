<?PHP
require_once __DIR__ . '/class-udp-agent.php';

// define constants here
if ( ! defined( 'UDP_API_URL' ) ) {
    define( 'UDP_API_URL', 'http://localhost/woocommerce' );
}

if ( ! defined( 'UDP_AGENT_VERSION' ) ) {
    define( 'UDP_AGENT_VERSION', 1.0 );
}

register_activation_hook( __DIR__ . DIRECTORY_SEPARATOR .  basename( __DIR__ ) . '.php', function(){

    // this will work, only if agent is plugin.
    $agent = new Udp_Agent();
    $agent->udp_agent_is_activated(  );
} );