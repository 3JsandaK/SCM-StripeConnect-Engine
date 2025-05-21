<?php
/**
 * Public donation form & Checkout Session (connected account)
 */
class KSJ_SCI_Public {

    public function __construct() {
        add_shortcode( 'ksj_sci_donation_form', [ $this, 'render_form' ] );
        add_action( 'wp_enqueue_scripts',           [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_ksj_sci_create_checkout_session',        [ $this, 'create_checkout_session' ] );
        add_action( 'wp_ajax_nopriv_ksj_sci_create_checkout_session',[ $this, 'create_checkout_session' ] );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/' );
        wp_enqueue_script(
            'ksj-sci-public',
            plugins_url( 'public.js', __FILE__ ),
            [ 'jquery', 'stripe-js' ],
            null,
            true
        );
        wp_localize_script( 'ksj-sci-public', 'ksjSciData', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'publishable_key' => get_option( 'ksj_sci_client_id' ),
        ]);
    }

    public function render_form() {
        ob_start(); ?>
        <form id="ksj-sci-donation-form">
            <p><label>Amount<br>
                <input type="number" name="amount" min="1" step="0.01" required>
            </label></p>
            <p><label>Name<br>
                <input type="text" name="name" required>
            </label></p>
            <p><label>Email<br>
                <input type="email" name="email" required>
            </label></p>
            <p><button type="submit">Donate</button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function create_checkout_session() {
        $secret     = get_option( 'ksj_sci_client_secret' );
        $account_id = get_option( 'ksj_sci_stripe_account_id' );

        \Stripe\Stripe::setApiKey( $secret );

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => intval( floatval( $_POST['amount'] ) * 100 ),
                        'product_data' => [ 'name' => 'Donation' ],
                    ],
                    'quantity' => 1,
                ]],
                'mode'                 => 'payment',
                'success_url'          => get_option( 'ksj_sci_redirect_uri' ),
                'cancel_url'           => get_option( 'ksj_sci_redirect_uri' ),
            ], [
                'stripe_account' => $account_id,
            ]);

            wp_send_json_success([
                'session_id'      => $session->id,
                'publishable_key' => get_option( 'ksj_sci_client_id' ),
            ]);
        } catch ( Exception $e ) {
            wp_send_json_error([ 'error' => $e->getMessage() ]);
        }
    }
}
