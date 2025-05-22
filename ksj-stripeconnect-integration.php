<?php
// Placeholder for Stripe secret key
if (!defined('SCM_STRIPE_SECRET_KEY')) {
    define('SCM_STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY');
}

/**
 * Plugin Name: SCM StripeConnect Engine
 * Description: Stripe Connect onboarding + donation forms for nonprofits (custom plugin by Screechy Cat Media).
 * Version: 0.4.0
 * Author: Screechy Cat Media
 * Text Domain: ksj-sci
 * GitHub Plugin URI: 3JsandaK/scm-stripeconnect-engine
 * GitHub Branch: main
 */
file_put_contents( WP_CONTENT_DIR . '/ksj-test.log', 
    "KSJ TEST WRITE: " . date('c') . "\n", 
    FILE_APPEND 
);


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload Stripe SDK + our classes
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
} else {
    add_action( 'admin_notices', function(){
        echo '<div class="notice notice-error">';
        echo '<p><strong>KSJ StripeConnect Integration:</strong> Missing <code>vendor/autoload.php</code>. ';
        echo 'Please run <code>composer install</code> in the plugin folder.</p>';
        echo '</div>';
    } );
    return;
}

define( 'KSJ_SCI_DIR',      __DIR__ );
define( 'KSJ_SCI_INCLUDES', __DIR__ . '/includes/' );

// Include our classes
require_once KSJ_SCI_INCLUDES . 'class-ksj-sci-admin.php';
require_once KSJ_SCI_INCLUDES . 'class-ksj-sci-public.php';
require_once KSJ_SCI_INCLUDES . 'class-ksj-sci-webhook.php';

// Bootstrap
add_action( 'plugins_loaded', function() {
    new KSJ_SCI_Admin();
    new KSJ_SCI_Public();
    new KSJ_SCI_Webhook();
} );
