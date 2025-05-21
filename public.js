jQuery(function($) {
    $('#ksj-sci-donation-form').on('submit', function(e) {
        e.preventDefault();
        var data = {
            action: 'ksj_sci_create_checkout_session',
            amount: $('input[name="amount"]').val(),
            name:   $('input[name="name"]').val(),
            email:  $('input[name="email"]').val()
        };
        $.post(ksjSciData.ajax_url, data, function(response) {
            if (response.success && response.data.session_id) {
                var stripe = Stripe(response.data.publishable_key);
                stripe.redirectToCheckout({ sessionId: response.data.session_id });
            } else {
                alert(response.data.error || 'Unable to start checkout.');
            }
        });
    });
});
