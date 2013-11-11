<?php
/**
 * Exchange Transaction Add-ons require several hooks in order to work properly.
 * Most of these hooks are called in api/transactions.php and are named dynamically
 * so that individual add-ons can target them. eg: it_exchange_refund_url_for_cybersource
 * We've placed them all in one file to help add-on devs identify them more easily
*/

/**
 * CyberSource URL to perform refunds
 *
 * The it_exchange_refund_url_for_[addon-slug] filter is
 * used to generate the link for the 'Refund Transaction' button
 * found in the admin under Customer Payments
 *
 * @since 1.0.0
 *
 * @param string $url passed by WP filter.
 * @param string $url transaction URL
*/
function it_exchange_refund_url_for_cybersource( $url ) {
	return 'http://www.cybersource.com/';
}
add_filter( 'it_exchange_refund_url_for_cybersource', 'it_exchange_refund_url_for_cybersource' );

/**
 * This proccesses a CyberSource transaction.
 *
 * The it_exchange_do_transaction_[addon-slug] action is called when
 * the site visitor clicks a specific add-ons 'purchase' button. It is
 * passed the default status of false along with the transaction object
 * The transaction object is a package of data describing what was in the user's cart
 *
 * Exchange expects your add-on to either return false if the transaction failed or to
 * call it_exchange_add_transaction() and return the transaction ID
 *
 * @since 1.0.0
 *
 * @param string $status passed by WP filter.
 * @param object $transaction_object The transaction object
*/
function it_exchange_cybersource_addon_process_transaction( $status, $transaction_object ) {

	// If this has been modified as true already, return.
	if ( $status || !isset( $_REQUEST[ 'ite-cybersource-purchase-dialog-nonce' ] ) ) {

		return $status;
	}

	// Verify nonce
	if ( empty( $_REQUEST[ 'ite-cybersource-purchase-dialog-nonce' ] ) && !wp_verify_nonce( $_REQUEST[ 'ite-cybersource-purchase-dialog-nonce' ], 'cybersource-checkout' ) ) {
		it_exchange_add_message( 'error', __( 'Transaction Failed, unable to verify security token.', 'LION' ) );

		return false;
	}

	$it_exchange_customer = it_exchange_get_current_customer();

	try {
		// Set / pass additional info
		$args = array();

		// Make payment
		$payment = it_exchange_cybersource_addon_do_payment( $it_exchange_customer, $transaction_object, $args );
	}
	catch ( Exception $e ) {
		it_exchange_add_message( 'error', $e->getMessage() );

		return false;
	}

	return it_exchange_add_transaction( 'cybersource', $payment[ 'id' ], 'succeeded', $it_exchange_customer->id, $transaction_object );

}
add_action( 'it_exchange_do_transaction_cybersource', 'it_exchange_cybersource_addon_process_transaction', 10, 2 );

/**
 * Returns the button for making the payment
 *
 * Exchange will loop through activated Payment Methods on the checkout page
 * and ask each transaction method to return a button using the following filter:
 * - it_exchange_get_[addon-slug]_make_payment_button
 * Transaction Method add-ons must return a button hooked to this filter if they
 * want people to be able to make purchases.
 *
 * @since 1.0.0
 *
 * @param array $options
 * @return string HTML button
*/
function it_exchange_cybersource_addon_make_payment_button( $options ) {

    if ( 0 >= it_exchange_get_cart_total( false ) )
        return '';

	$settings = it_exchange_get_option( 'addon_cybersource' );

    return it_exchange_generate_purchase_dialog( 'cybersource', array( 'purchase-label' => $settings[ 'cybersource_purchase_button_label' ] ) );

}
add_filter( 'it_exchange_get_cybersource_make_payment_button', 'it_exchange_cybersource_addon_make_payment_button', 10, 2 );

/**
 * Gets the interpretted transaction status from valid CyberSource transaction statuses
 *
 * Most gateway transaction stati are going to be lowercase, one word strings.
 * Hooking a function to the it_exchange_transaction_status_label_[addon-slug] filter
 * will allow add-ons to return the human readable label for a given transaction status.
 *
 * @since 1.0.0
 *
 * @param string $status the string of the CyberSource transaction
 * @return string translaction transaction status
*/
function it_exchange_cybersource_addon_transaction_status_label( $status ) {
    switch ( $status ) {
        case 'succeeded':
            return __( 'Paid', 'LION' );
        case 'refunded':
            return __( 'Refunded', 'LION' );
        case 'partial-refund':
            return __( 'Partially Refunded', 'LION' );
        case 'needs_response':
            return __( 'Disputed: CyberSource needs a response', 'LION' );
        case 'under_review':
            return __( 'Disputed: Under review', 'LION' );
        case 'won':
            return __( 'Disputed: Won, Paid', 'LION' );
        default:
            return __( 'Unknown', 'LION' );
    }
}
add_filter( 'it_exchange_transaction_status_label_cybersource', 'it_exchange_cybersource_addon_transaction_status_label' );

/**
 * Returns a boolean. Is this transaction a status that warrants delivery of any products attached to it?
 *
 * Just because a transaction gets added to the DB doesn't mean that the admin is ready to give over
 * the goods yet. Each payment gateway will have different transaction stati. Exchange uses the following
 * filter to ask transaction-methods if a current status is cleared for delivery. Return true if the status
 * means its okay to give the download link out, ship the product, etc. Return false if we need to wait.
 * - it_exchange_[addon-slug]_transaction_is_cleared_for_delivery
 *
 * @since 1.0.0
 *
 * @param boolean $cleared passed in through WP filter. Ignored here.
 * @param object $transaction
 * @return boolean
*/
function it_exchange_cybersource_transaction_is_cleared_for_delivery( $cleared, $transaction ) {
    $valid_stati = array( 'succeeded', 'partial-refund', 'won' );

    return in_array( it_exchange_get_transaction_status( $transaction ), $valid_stati );
}
add_filter( 'it_exchange_cybersource_transaction_is_cleared_for_delivery', 'it_exchange_cybersource_transaction_is_cleared_for_delivery', 10, 2 );