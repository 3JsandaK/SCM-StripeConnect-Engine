<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin: OAuth onboarding & settings for Stripe Connect + Default Brevo List
 */
class KSJ_SCI_Admin {

    public function __construct() {
        add_action( 'admin_menu',   [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',   [ $this, 'register_settings' ] );
        add_action( 'admin_init',   [ $this, 'maybe_handle_stripe_oauth' ], 20 );
    }

    public function add_settings_page() {
        add_options_page(
            'KSJ StripeConnect Settings',
            'StripeConnect',
            'manage_options',
            'ksj-sci-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        // OAuth / Connect
        register_setting( 'ksj_sci_options', 'ksj_sci_client_id',         'sanitize_text_field' );
        register_setting( 'ksj_sci_options', 'ksj_sci_client_secret',     'sanitize_text_field' );
        register_setting( 'ksj_sci_options', 'ksj_sci_redirect_uri',      'sanitize_text_field' );
        register_setting( 'ksj_sci_options', 'ksj_sci_stripe_account_id', 'sanitize_text_field' );
        // Webhook & Checkout
        register_setting( 'ksj_sci_options', 'ksj_sci_webhook_secret',    'sanitize_text_field' );
        register_setting( 'ksj_sci_options', 'ksj_sci_success_url',       'esc_url_raw' );
        register_setting( 'ksj_sci_options', 'ksj_sci_cancel_url',        'esc_url_raw' );
        // Default Brevo List
        register_setting( 'ksj_sci_options', 'ksj_sci_default_list',      'intval' );
    }

    public function render_settings_page() {
        // Fetch Brevo lists
        $brevo_key = get_option( 'ksj_brevo_api_key' );
        $lists     = [];
        if ( $brevo_key ) {
            $resp = wp_remote_get( 'https://api.brevo.com/v3/contacts/lists', [
                'headers' => [ 'api-key' => $brevo_key ],
                'timeout' => 15,
            ] );
            if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                $data = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( ! empty( $data['lists'] ) ) {
                    $lists = $data['lists'];
                }
            }
        }
        $current_list = get_option( 'ksj_sci_default_list' );
        ?>
        <div class="wrap">
            <h1>KSJ StripeConnect Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'ksj_sci_options' ); ?>
                <table class="form-table">

                    <!-- OAuth Connect Settings -->
                    <tr>
                        <th><label for="ksj_sci_client_id">Client ID</label></th>
                        <td><input type="text" id="ksj_sci_client_id" name="ksj_sci_client_id"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_client_id' ) ); ?>"
                                   class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="ksj_sci_client_secret">Client Secret</label></th>
                        <td><input type="text" id="ksj_sci_client_secret" name="ksj_sci_client_secret"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_client_secret' ) ); ?>"
                                   class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="ksj_sci_redirect_uri">Redirect URI</label></th>
                        <td>
                            <input type="text" id="ksj_sci_redirect_uri" name="ksj_sci_redirect_uri"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_redirect_uri' ) ); ?>"
                                   class="regular-text" />
                            <p class="description">Must match your Stripe Connect app’s Redirect URI.</p>
                        </td>
                    </tr>

                    <!-- Webhook & Checkout Settings -->
                    <tr>
                        <th><label for="ksj_sci_webhook_secret">Webhook Secret</label></th>
                        <td>
                            <input type="text" id="ksj_sci_webhook_secret" name="ksj_sci_webhook_secret"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_webhook_secret' ) ); ?>"
                                   class="regular-text" />
                            <p class="description">Your Stripe CLI webhook signing secret (starts with <code>whsec_</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ksj_sci_success_url">Success URL</label></th>
                        <td>
                            <input type="url" id="ksj_sci_success_url" name="ksj_sci_success_url"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_success_url' ) ); ?>"
                                   class="regular-text" />
                            <p class="description">Where donors are sent after successful checkout.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ksj_sci_cancel_url">Cancel URL</label></th>
                        <td>
                            <input type="url" id="ksj_sci_cancel_url" name="ksj_sci_cancel_url"
                                   value="<?php echo esc_attr( get_option( 'ksj_sci_cancel_url' ) ); ?>"
                                   class="regular-text" />
                            <p class="description">Where donors are sent if they cancel checkout.</p>
                        </td>
                    </tr>

                    <!-- Default Brevo List -->
                    <tr>
                        <th><label for="ksj_sci_default_list">Default Brevo List</label></th>
                        <td>
                            <select id="ksj_sci_default_list" name="ksj_sci_default_list">
                                <option value="">— Select a Brevo list —</option>
                                <?php foreach ( $lists as $list ) : ?>
                                    <option value="<?php echo esc_attr( $list['id'] ); ?>"
                                        <?php selected( $current_list, $list['id'] ); ?>>
                                        <?php echo esc_html( $list['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Contacts will be added to this Brevo list by default.</p>
                        </td>
                    </tr>

                </table>
                <?php submit_button(); ?>
            </form>

            <?php
            // Connect / Disconnect UI
            $acct = get_option( 'ksj_sci_stripe_account_id' );
            if ( $acct ) : ?>
                <p>
                    Connected Stripe Account: <strong><?php echo esc_html( $acct ); ?></strong>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ksj-sci-settings&sc_action=disconnect' ) ) ;?>"
                       class="button">Disconnect</a>
                </p>
            <?php else :
                $params = [
                    'response_type' => 'code',
                    'client_id'     => get_option( 'ksj_sci_client_id' ),
                    'scope'         => 'read_write',
                    'redirect_uri'  => get_option( 'ksj_sci_redirect_uri' ),
                ];
                $url = 'https://connect.stripe.com/oauth/authorize?' . http_build_query( $params );
            ?>
                <p><a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
                    Connect with Stripe
                </a></p>
            <?php endif; ?>

        </div>
        <?php
    }

    public function maybe_handle_stripe_oauth() {
        if ( empty( $_GET['page'] ) || $_GET['page'] !== 'ksj-sci-settings' ) {
            return;
        }

        // Disconnect
        if ( isset( $_GET['sc_action'] ) && $_GET['sc_action'] === 'disconnect' ) {
            delete_option( 'ksj_sci_stripe_account_id' );
            add_action( 'admin_notices', function(){
                echo '<div class="notice notice-success"><p>Stripe account disconnected.</p></div>';
            } );
            return;
        }

        // OAuth callback
        if ( empty( $_GET['code'] ) ) {
            return;
        }

        $code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $secret = get_option( 'ksj_sci_client_secret' );

        try {
            \Stripe\Stripe::setApiKey( $secret );
            $resp = \Stripe\OAuth::token([
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_secret' => $secret,
            ]);
            update_option( 'ksj_sci_stripe_account_id', $resp->stripe_user_id );
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success"><p>Stripe account connected successfully!</p></div>';
            } );
        } catch ( \Exception $e ) {
            add_action( 'admin_notices', function() use ( $e ) {
                printf(
                    '<div class="notice notice-error"><p>Stripe Connect error: %s</p></div>',
                    esc_html( $e->getMessage() )
                );
            } );
        }
    }
}
