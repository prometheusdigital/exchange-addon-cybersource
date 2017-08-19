<?php
/**
 * The following file contains utility functions specific to our CyberSource add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for CyberSource, etc.
*/

/**
 * Adds actions to the plugins page for the ExchangeWP CyberSource plugin
 *
 * @since 1.0.0
 *
 * @param array $meta Existing meta
 * @param string $plugin_file the wp plugin slug (path)
 * @param array $plugin_data the data WP harvested from the plugin header
 * @param string $context
 * @return array
*/
function it_exchange_cybersource_plugin_row_actions( $actions, $plugin_file, $plugin_data, $context ) {

    $actions['setup_addon'] = '<a href="' . get_admin_url( NULL, 'admin.php?page=it-exchange-addons&add-on-settings=cybersource' ) . '">' . __( 'Setup Add-on', 'LION' ) . '</a>';

    return $actions;

}
add_filter( 'plugin_action_links_exchange-addon-cybersource/exchange-addon-cybersource.php', 'it_exchange_cybersource_plugin_row_actions', 10, 4 );

/**
 * Grab the CyberSource customer ID for a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP customer ID
 * @return integer
*/
function it_exchange_cybersource_addon_get_customer_id( $customer_id ) {
    $settings = it_exchange_get_option( 'addon_cybersource' );
    $mode     = ( $settings['cybersource_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return get_user_meta( $customer_id, '_it_exchange_cybersource_id' . $mode, true );
}

/**
 * Add the CyberSource customer ID as user meta on a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP user ID
 * @param integer $cybersource_id the CyberSource customer ID
 * @return boolean
*/
function it_exchange_cybersource_addon_set_customer_id( $customer_id, $cybersource_id ) {
    $settings = it_exchange_get_option( 'addon_cybersource' );
    $mode     = ( $settings['cybersource_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return update_user_meta( $customer_id, '_it_exchange_cybersource_id' . $mode, $cybersource_id );
}

/**
 * Grab a transaction from the CyberSource transaction ID
 *
 * @since 1.0.0
 *
 * @param integer $cybersource_id id of CyberSource transaction
 * @return transaction object
*/
function it_exchange_cybersource_addon_get_transaction_id( $cybersource_id ) {
    $args = array(
        'meta_key'    => '_it_exchange_transaction_method_id',
        'meta_value'  => $cybersource_id,
        'numberposts' => 1, //we should only have one, so limit to 1
    );
    return it_exchange_get_transactions( $args );
}

/**
 * Updates a CyberSource transaction status based on CyberSource ID
 *
 * @since 1.0.0
 *
 * @param integer $cybersource_id id of CyberSource transaction
 * @param string $new_status new status
 * @return void
*/
function it_exchange_cybersource_addon_update_transaction_status( $cybersource_id, $new_status ) {
    $transactions = it_exchange_cybersource_addon_get_transaction_id( $cybersource_id );
    foreach( $transactions as $transaction ) { //really only one
        $current_status = it_exchange_get_transaction_status( $transaction );
        if ( $new_status !== $current_status )
            it_exchange_update_transaction_status( $transaction, $new_status );
    }
}

/**
 * Adds a refund to post_meta for a CyberSource transaction
 *
 * @since 1.0.0
*/
function it_exchange_cybersource_addon_add_refund_to_transaction( $cybersource_id, $refund ) {

    // CyberSource money format comes in as cents. Divide by 100.
    $refund = ( $refund / 100 );

    // Grab transaction
    $transactions = it_exchange_cybersource_addon_get_transaction_id( $cybersource_id );
    foreach( $transactions as $transaction ) { //really only one

        $refunds = it_exchange_get_transaction_refunds( $transaction );

        $refunded_amount = 0;
        foreach( ( array) $refunds as $refund_meta ) {
            $refunded_amount += $refund_meta['amount'];
        }

        // In CyberSource the Refund is the total amount that has been refunded, not just this transaction
        $this_refund = $refund - $refunded_amount;

        // This refund is already formated on the way in. Don't reformat.
        it_exchange_add_refund_to_transaction( $transaction, $this_refund );
    }
}

/**
 * Removes a CyberSource Customer ID from a WP user
 *
 * @since 1.0.0
 *
 * @param integer $cybersource_id the id of the CyberSource transaction
*/
function it_exchange_cybersource_addon_delete_id_from_customer( $cybersource_id ) {
    $settings = it_exchange_get_option( 'addon_cybersource' );
    $mode     = ( $settings['cybersource_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    $transactions = it_exchange_cybersource_addon_get_transaction_id( $cybersource_id );
    foreach( $transactions as $transaction ) { //really only one
        $customer_id = get_post_meta( $transaction->ID, '_it_exchange_customer_id', true );
        if ( false !== $current_cybersource_id = it_exchange_cybersource_addon_get_customer_id( $customer_id ) ) {

            if ( $current_cybersource_id === $cybersource_id )
                delete_user_meta( $customer_id, '_it_exchange_cybersource_id' . $mode );

        }
    }
}

/**
 * @param IT_Exchange_Customer $it_exchange_customer
 * @param object $transaction_object
 * @param array $args
 *
 * @return array
 * @throws Exception
 */
function it_exchange_cybersource_addon_do_payment( $it_exchange_customer, $transaction_object, $args ) {

	$general_settings = it_exchange_get_option( 'settings_general' );
	$settings = it_exchange_get_option( 'addon_cybersource' );

	$url = 'https://ics2ws.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.26.wsdl';
	$security_key = $settings[ 'cybersource_live_security_key' ];

	if ( $settings[ 'cybersource_sandbox_mode' ] ) {
		$url = 'https://ics2wstest.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.26.wsdl';
		$security_key = $settings[ 'cybersource_test_security_key' ];
	}

	$paymentaction = 'Sale';

	if ( 'auth' == $settings[ 'cybersource_sale_method' ] ) {
		$paymentaction = 'Authorization';
	}

	$total = $transaction_object->total;
	$discount = 0;

	if ( isset( $transaction_object->coupons_total_discounts ) ) {
		$discount = $transaction_object->coupons_total_discounts;
	}

	$total_pre_discount = $total + $discount;
	$taxes = '0.00';

	$card_type = it_exchange_cybersource_addon_get_card_type( $_POST[ 'it-exchange-purchase-dialog-cc-number' ] );

	if ( empty( $card_type ) ) {
		throw new Exception( 'Invalid Credit Card' );
	}

	$card_type = $card_type[ 'name' ];


	$_POST[ 'it-exchange-purchase-dialog-cc-expiration-month' ] = (int) $_POST[ 'it-exchange-purchase-dialog-cc-expiration-month' ];
	$_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ] = (int) $_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ];

	$expiration = array(
		'month' => $_POST[ 'it-exchange-purchase-dialog-cc-expiration-month' ],
		'year' => $_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ]
	);

	if ( $expiration[ 'month' ] < 10 ) {
		$expiration[ 'month' ] = '0' . $expiration[ 'month' ];
	}

	if ( $expiration[ 'year' ] < 100 ) {
		$expiration[ 'year' ] = '20' . $expiration[ 'year' ];
	}

	$default_address = array(
		'first-name' => empty( $it_exchange_customer->data->first_name ) ? '' : $it_exchange_customer->data->first_name,
		'last-name' => empty( $it_exchange_customer->data->last_name ) ? '' : $it_exchange_customer->data->last_name,
		'company-name' => '',
		'address1' => '',
		'address2' => '',
		'city' => '',
		'state' => '',
		'zip' => '',
		'country' => '',
		'email' => empty( $it_exchange_customer->data->user_email ) ? '' : $it_exchange_customer->data->user_email,
		'phone' => ''
	);

	$billing_address = $shipping_address = it_exchange_get_customer_billing_address( $it_exchange_customer->id );

	if ( function_exists( 'it_exchange_get_customer_shipping_address' ) ) {
		$shipping_address = it_exchange_get_customer_shipping_address( $it_exchange_customer->id );
	}

	$billing_address = array_merge( $default_address, $billing_address );
	$shipping_address = array_merge( $default_address, $shipping_address );

	if ( empty( $billing_address[ 'email' ] ) ) {
		$billing_address[ 'email' ] = $it_exchange_customer->data->user_email;
	}

	if ( empty( $shipping_address[ 'email' ] ) ) {
		$shipping_address[ 'email' ] = $it_exchange_customer->data->user_email;
	}

	$request = new stdClass();

	$request->merchantID = $settings[ 'cybersource_merchant_id' ];

	$request->merchantReferenceCode = $it_exchange_customer->id . '00' . microtime();

	// Authorize
	$request->ccAuthService = (object) array( 'run' => 'true' );

	// Capture
	if ( 'Sale' == $paymentaction ) {
		$request->ccCaptureService = (object) array( 'run' => 'true' );
	}

	$request->billTo = (object) array(
		'email' => $billing_address[ 'email' ],
		'firstName' => $billing_address[ 'first-name' ],
		'lastName' => $billing_address[ 'last-name' ],
		'company' => '',
		'street1' => $billing_address[ 'address1' ],
		'street2' => $billing_address[ 'address2' ],
		'city' => $billing_address[ 'city' ],
		'state' => $billing_address[ 'state' ],
		'postalCode' => $billing_address[ 'zip' ],
		'country' => $billing_address[ 'country' ],
		'phoneNumber' => '',
		'customerID' => $it_exchange_customer->id
	);

	$request->card = (object) array(
		'accountNumber' => $_POST[ 'it-exchange-purchase-dialog-cc-number' ],
		'expirationMonth' => $expiration[ 'month' ],
		'expirationYear' => $expiration[ 'year' ],
		'cvNumber' => $_POST[ 'it-exchange-purchase-dialog-cc-code' ]
	);

	$request->purchaseTotals = (object) array(
		'grandTotalAmount' => $total,
		'currency' => $transaction_object->currency
	);

	$items = array();

	$item_count = 0;

	foreach ( $transaction_object->products as $product ) {
		$price = $product[ 'product_subtotal' ]; // base price * quantity, w/ any changes by plugins
		$price = $price / $product[ 'count' ]; // get final base price (possibly different from $product[ 'product_base_price' ])

		// @todo handle product discounts
		//$price -= ( ( ( ( $total * $price ) / $total_pre_discount ) / 100 ) * $price ); // get discounted item price

		$price = it_exchange_format_price( $price, false );

		// @todo Handle taxes?

		// @todo Product name? $product[ 'product_name' ]

		$items[] = (object) array(
			'id' => $item_count,
			'unitPrice' => $price,
			'quantity' => $product[ 'count' ]
		);

		$item_count++;
	}

	if ( !empty( $items ) ) {
		$request->item = $items;
	}

	$request->clientLibrary = "PHP";
	$request->clientLibraryVersion = phpversion();
	$request->clientEnvironment = php_uname();

	/*ob_start();
	echo '<pre>';
	var_dump( $request );
	echo '</pre>';
	error_log( ob_get_clean() );*/

	// Setup client
	require_once 'soap.cybersource.php';

	$cybersource_soap = new CyberSource_SoapClient( $url );

	// Set credentials
	$cybersource_soap->set_credentials( $settings[ 'cybersource_merchant_id' ], $security_key );

	// Make request
	$response = $cybersource_soap->runTransaction( $request );

	/*ob_start();
	echo '<pre>';
	var_dump( $response );
	echo '</pre>';
	error_log( ob_get_clean() );*/

	$status = strtolower( $response->decision );

	switch ( $status ) {
		case 'accept':
			// @todo Set message

			break;

		case 'review':
			if ( 230 == $response->reasonCode ) {
				$messages = __( "The authorization request was approved by the issuing bank but declined by our merchant because it did not pass the CVN check.", 'LION' );
			}
			else {
				$messages = __( "This order is being placed on hold for review. You may contact the store to complete the transaction.", 'LION' );
			}

			throw new Exception( $messages );

			break;

		case 'failure':
		default:
			if ( 202 == $response->reasonCode ) {
				$messages = __( 'The provided card is expired, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 203 == $response->reasonCode ) {
				$messages = __( 'The provided card was declined, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 204 == $response->reasonCode ) {
				$messages = __( 'Insufficient funds in account, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 208 == $response->reasonCode ) {
				$messages = __( 'The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 210 == $response->reasonCode ) {
				$messages = __( 'The credit limit for the card has been reached, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 211 == $response->reasonCode ) {
				$messages = __( 'The card verification number is invalid, please try again.', 'LION' );
			}
			elseif ( 231 == $response->reasonCode ) {
				$messages = __( 'The provided card number was invalid, or card type was incorrect. Please try again.', 'LION' );
			}
			elseif ( 232 == $response->reasonCode ) {
				$messages = __( 'That card type is not accepted, please use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 240 == $response->reasonCode ) {
				$messages = __( 'The card type is invalid or does not correlate with the credit card number. Please try again or use an alternate card or other form of payment.', 'LION' );
			}
			elseif ( 'ERROR' == $response->decision ) {
				$messages = __( 'An error occurred, please try again or try an alternate form of payment', 'LION' );
			}
			else {
				$messages = __( 'We cannot process your order with the payment information that you provided. Please use a different payment account or an alternate payment method.', 'LION' );
			}

			throw new Exception( $messages );

			break;
	}

	return array( 'id' => $response->requestID, 'status' => 'success' );
}

/**
 * Get card types and their settings
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @return array
 */
function it_exchange_cybersource_addon_get_card_types() {

	$cards = array(

		array(
			'name' => 'Amex',
			'slug' => 'amex',
			'lengths' => '15',
			'prefixes' => '34,37',
			'checksum' => true
		),
		array(
			'name' => 'Discover',
			'slug' => 'discover',
			'lengths' => '16',
			'prefixes' => '6011,622,64,65',
			'checksum' => true
		),
		array(
			'name' => 'MasterCard',
			'slug' => 'mastercard',
			'lengths' => '16',
			'prefixes' => '51,52,53,54,55',
			'checksum' => true
		),
		array(
			'name' => 'Visa',
			'slug' => 'visa',
			'lengths' => '13,16',
			'prefixes' => '4,417500,4917,4913,4508,4844',
			'checksum' => true
		),
		array(
			'name' => 'JCB',
			'slug' => 'jcb',
			'lengths' => '16',
			'prefixes' => '35',
			'checksum' => true
		),
		array(
			'name' => 'Maestro',
			'slug' => 'maestro',
			'lengths' => '12,13,14,15,16,18,19',
			'prefixes' => '5018,5020,5038,6304,6759,6761',
			'checksum' => true
		)

	);

	return $cards;
}

/**
 * Get the Card Type from a Card Number
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int|string $number
 *
 * @return bool
 */
function it_exchange_cybersource_addon_get_card_type( $number ) {

	//removing spaces from number
	$number = str_replace( array( '-', ' ' ), '', $number );

	if ( empty( $number ) ) {
		return false;
	}

	$cards = it_exchange_cybersource_addon_get_card_types();

	$matched_card = false;

	foreach ( $cards as $card ) {
		if ( it_exchange_cybersource_addon_matches_card_type( $number, $card ) ) {
			$matched_card = $card;

			break;
		}
	}

	if ( $matched_card && $matched_card[ 'checksum' ] && !it_exchange_cybersource_addon_is_valid_card_checksum( $number ) ) {
		$matched_card = false;
	}

	return $matched_card ? $matched_card : false;

}

/**
 * Match the Card Number to a Card Type
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int $number
 * @param array $card
 *
 * @return bool
 */
function it_exchange_cybersource_addon_matches_card_type( $number, $card ) {

	//checking prefix
	$prefixes = explode( ',', $card[ 'prefixes' ] );
	$matches_prefix = false;
	foreach ( $prefixes as $prefix ) {
		if ( preg_match( "|^{$prefix}|", $number ) ) {
			$matches_prefix = true;
			break;
		}
	}

	//checking length
	$lengths = explode( ',', $card[ 'lengths' ] );
	$matches_length = false;
	foreach ( $lengths as $length ) {
		if ( strlen( $number ) == absint( $length ) ) {
			$matches_length = true;
			break;
		}
	}

	return $matches_prefix && $matches_length;

}

/**
 * Check Credit Card number checksum
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int $number
 *
 * @return bool
 */
function it_exchange_cybersource_addon_is_valid_card_checksum( $number ) {

	$checksum = 0;
	$num = 0;
	$multiplier = 1;

	// Process each character starting at the right
	for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {

		//Multiply current digit by multiplier (1 or 2)
		$num = $number{$i} * $multiplier;

		// If the result is in greater than 9, add 1 to the checksum total
		if ( $num >= 10 ) {
			$checksum++;
			$num -= 10;
		}

		//Update checksum
		$checksum += $num;

		//Update multiplier
		$multiplier = $multiplier == 1 ? 2 : 1;
	}

	return $checksum % 10 == 0;

}
