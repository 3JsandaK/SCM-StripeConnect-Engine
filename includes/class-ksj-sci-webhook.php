<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook endpoint for Stripe events → sync to Brevo & add to default list
 */
class KSJ_SCI_Webhook {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
    }

    public function register_endpoint() {
        register_rest_route( 'ksj-sci/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_webhook( WP_REST_Request $request ) {
        $payload = $request->get_body();
        $sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret  = get_option( 'ksj_sci_webhook_secret' );

        try {
            $event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
        } catch ( \Exception $e ) {
            return new WP_Error( 'invalid_signature', $e->getMessage(), [ 'status' => 400 ] );
        }

        if ( 'checkout.session.completed' !== $event->type ) {
            return rest_ensure_response( [ 'success' => true ] );
        }

        $session      = $event->data->object;
        $email        = $session->customer_details->email ?? '';
        $fullName     = $session->customer_details->name  ?? '';
        $amount       = isset( $session->amount_total ) ? ( $session->amount_total / 100 ) : 0;
        $currency     = strtoupper( $session->currency ?? '' );

        // Prepare Brevo sync
        $brevo_key    = get_option( 'ksj_brevo_api_key' );
        $list_id      = get_option( 'ksj_sci_default_list' );
        if ( empty( $brevo_key ) || empty( $email ) ) {
            return rest_ensure_response( [ 'success' => false ] );
        }

        // Split name into first/last
        list( $first, $last ) = array_pad( explode( ' ', $fullName, 2 ), 2, '' );
        $first = sanitize_text_field( $first );
        $last  = sanitize_text_field( $last );

        $attributes = [
            'FIRSTNAME'         => $first,
            'LASTNAME'          => $last,
            'DONATION_AMOUNT'   => floatval( $amount ),
            'DONATION_CURRENCY' => $currency,
            'DONATION_DATE'     => current_time( 'Y-m-d H:i:s' ),
        ];

        $payload_body = [
            'email'          => sanitize_email( $email ),
            'attributes'     => $attributes,
            'updateEnabled'  => true,
        ];
        if ( $list_id ) {
            $payload_body['listIds'] = [ intval( $list_id ) ];
        }

        $response = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
            'headers' => [
                'api-key'      => $brevo_key,
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode( $payload_body ),
        ] );

        // We ignore errors here, but you could add logging if desired

        return rest_ensure_response( [ 'success' => true ] );
    }
}
