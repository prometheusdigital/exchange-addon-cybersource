<?php
/**
 * The following file contains utility functions specific to our CyberSource add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for CyberSource, etc.
*/

/**
 * Adds actions to the plugins page for the iThemes Exchange CyberSource plugin
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

    $actions['setup_addon'] = '<a href="' . get_admin_url( NULL, 'admin.php?page=it-exchange-addons&add-on-settings=cybersource' ) . '">' . __( 'Setup Add-on', 'it-l10n-exchange-addon-cybersource' ) . '</a>';

    return $actions;

}
add_filter( 'plugin_action_links_exchange-addon-cybersource/exchange-addon-cybersource.php', 'it_exchange_cybersource_plugin_row_actions', 10, 4 );

/**
 * Enqueues any scripts we need on the frontend during a CyberSource checkout
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_cybersource_addon_enqueue_script() {
    wp_enqueue_script( 'cybersource-addon-js', ITUtility::get_url_from_file( dirname( __FILE__ ) ) . '/js/cybersource-addon.js', array( 'jquery' ) );
    wp_localize_script( 'cybersource-addon-js', 'CyberSourceAddonL10n', array(
            'processing_payment_text' => __( 'Processing payment, please wait...', 'it-l10n-exchange-addon-cybersource' ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'it_exchange_cybersource_addon_enqueue_script' );

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
 * @param $transaction_object
 * @param $args
 *
 * @return array
 * @throws Exception
 */
function it_exchange_cybersource_addon_do_payment( $it_exchange_customer, $transaction_object, $args ) {

	$general_settings = it_exchange_get_option( 'settings_general' );
	$settings = it_exchange_get_option( 'addon_cybersource' );

	$charge = array(
		'customer' => $it_exchange_customer->id,
		'amount' => number_format( $transaction_object->total, 2, '', '' ),
		'currency' => $general_settings[ 'default-currency' ],
		'description' => $transaction_object->description,
	);

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

	$expiration = $_POST[ 'it-exchange-purchase-dialog-cc-expiration-month' ];

	if ( $expiration < 10 ) {
		$expiration = '0' . $expiration;
	}

	if ( $_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ] < 100 ) {
		$expiration .= '20' . $_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ];
	}
	else {
		$expiration .= $_POST[ 'it-exchange-purchase-dialog-cc-expiration-year' ];
	}

	$default_address = array(
		'first-name'   => empty( $it_exchange_customer->data->first_name ) ? '' : $it_exchange_customer->data->first_name,
		'last-name'    => empty( $it_exchange_customer->data->last_name ) ? '' : $it_exchange_customer->data->last_name,
		'company-name' => '',
		'address-1'    => '',
		'address-2'    => '',
		'city'         => '',
		'state'        => '',
		'zip'          => '',
		'country'      => '',
		'email'        => empty( $it_exchange_customer->data->user_email ) ? '' : $it_exchange_customer->data->user_email,
		'phone'        => ''
	);

	$billing_address = $shipping_address = it_exchange_get_customer_billing_address( $it_exchange_customer->id );

	if ( function_exists( 'it_exchange_get_customer_shipping_address' ) ) {
		$shipping_address = it_exchange_get_customer_shipping_address( $it_exchange_customer->id );
	}

	$billing_address = array_merge( $default_address, $billing_address );
	$shipping_address = array_merge( $default_address, $shipping_address );

	$post_data = array(
		// Base Cart
		'AMT' => $total,
		'CURRENCYCODE' => $transaction_object->currency,

		// Credit Card information
		'CREDITCARDTYPE' => $card_type,
		'ACCT' => $_POST[ 'it-exchange-purchase-dialog-cc-number' ],
		'EXPDATE' => $expiration,
		'CVV2' => $_POST[ 'it-exchange-purchase-dialog-cc-code' ],

		// Customer information
		'EMAIL' => $billing_address[ 'email' ],
		'FIRSTNAME' => $billing_address[ 'first-name' ],
		'LASTNAME' => $billing_address[ 'last-name' ],
		'STREET' => $billing_address[ 'address-1' ],
		'STREET2' => $billing_address[ 'address-2' ],
		'CITY' => $billing_address[ 'city' ],
		'STATE' => $billing_address[ 'state' ],
		'ZIP' => $billing_address[ 'zip' ],
		'COUNTRYCODE' => $billing_address[ 'country' ],

		// Shipping information
		'SHIPTONAME' => $shipping_address[ 'first-name' ] . ' ' . $shipping_address[ 'last-name' ],
		'SHIPTOSTREET' => $shipping_address[ 'address-1' ],
		'SHIPTOSTREET2' => $shipping_address[ 'address-2' ],
		'SHIPTOCITY' => $shipping_address[ 'city' ],
		'SHIPTOSTATE' => $shipping_address[ 'state' ],
		'SHIPTOZIP' => $shipping_address[ 'zip' ],
		'SHIPTOCOUNTRYCODE' => $shipping_address[ 'country' ],

		// API settings
		'METHOD' => 'DoDirectPayment',
		'PAYMENTACTION' => $paymentaction,
		'USER' => $settings[ 'cybersource_api_username' ],
		'PWD' => $settings[ 'cybersource_api_password' ],
		'SIGNATURE' => $settings[ 'cybersource_api_signature' ],

		// Additional info
		'IPADDRESS' => $_SERVER[ 'REMOTE_ADDR' ],
		'VERSION' => '59.0',
	);

	$item_count = 0;

	// Basic cart (one line item for all products)
	/*$post_data[ 'L_NUMBER' . $item_count ] = $item_count;
	$post_data[ 'L_NAME' . $item_count ] = $transaction_object->description;
	$post_data[ 'L_AMT' . $item_count ] = it_exchange_format_price( $total, false );
	$post_data[ 'L_QTY' . $item_count ] = 1;

	// @todo Handle taxes?
	// $post_data[ 'L_TAXAMT' . $item_count ] = 0;*/

	foreach ( $transaction_object->products as $product ) {
		$price = $product[ 'product_subtotal' ]; // base price * quantity, w/ any changes by plugins
		$price = $price / $product[ 'count' ]; // get final base price (possibly different from $product[ 'product_base_price' ])

		// @todo handle product discounts
		//$price -= ( ( ( ( $total * $price ) / $total_pre_discount ) / 100 ) * $price ); // get discounted item price

		$price = it_exchange_format_price( $price, false );

		$post_data[ 'L_NUMBER' . $item_count ] = $item_count;
		$post_data[ 'L_NAME' . $item_count ] = $product[ 'product_name' ];
		$post_data[ 'L_AMT' . $item_count ] = $price;
		$post_data[ 'L_QTY' . $item_count ] = $product[ 'count' ];

		// @todo Handle taxes?
		// $post_data[ 'L_TAXAMT' . $item_count ] = 0;

		$item_count++;
	}

	$request = new stdClass();

	$request->merchantID = $this->get_merchant_id();

	$request->merchantReferenceCode = ltrim( $order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ) );

	$request->clientLibrary = "PHP";
	$request->clientLibraryVersion = phpversion();
	$request->clientEnvironment = php_uname();

	// always authorize
	$cc_auth_service = new stdClass();
	$cc_auth_service->run = "true";
	$request->ccAuthService = $cc_auth_service;

	// capture?
	if ( 'AUTH_CAPTURE' == $this->salemethod ) {
		$cc_capture_service = new stdClass();
		$cc_capture_service->run = "true";
		$request->ccCaptureService = $cc_capture_service;
	}

	$bill_to = new stdClass();
	$bill_to->firstName   = $order->billing_first_name;
	$bill_to->lastName    = $order->billing_last_name;
	$bill_to->company     = $order->billing_company;
	$bill_to->street1     = $order->billing_address_1;
	$bill_to->street2     = $order->billing_address_2;
	$bill_to->city        = $order->billing_city;
	$bill_to->state       = $order->billing_state;
	$bill_to->postalCode  = $order->billing_postcode;
	$bill_to->country     = $order->billing_country;
	$bill_to->phoneNumber = $order->billing_phone;
	$bill_to->email       = $order->billing_email;
	if ( $order->user_id ) $bill_to->customerID = $order->user_id;
	$request->billTo = $bill_to;

	$card = new stdClass();
	$card->accountNumber   = $this->get_post( 'cybersource_accountNumber' );
	$card->expirationMonth = $this->get_post( 'cybersource_expirationMonth' );
	$card->expirationYear  = $this->get_post( 'cybersource_expirationYear' );
	$card->cvNumber        = $this->get_post( 'cybersource_cvNumber' );
	$card->cardType        = $this->get_post( 'cybersource_cardType' );
	$request->card = $card;

	$purchase_totals = new stdClass();
	$purchase_totals->currency = get_woocommerce_currency();
	$purchase_totals->grandTotalAmount = $order->get_total();
	$request->purchaseTotals  = $purchase_totals;

	$items = array();
	foreach ( $order->get_items() as $order_item ) {
		$item = new stdClass();
		$item->unitPrice = $order_item['line_subtotal'] / $order_item['qty'];  // TODO: best way to get the item price?
		$item->quantity  = $order_item['qty'];
		$item->id        = count( $items );

		$items[] = $item;
	}

	if ( ! empty( $items ) ) $request->item = $items;

	try {
		// Setup client
		$cybersource_soap = @new CyberSource_SoapClient( $url );

		// Set credentials
		$cybersource_soap->set_credentials( $settings[ 'cybersource_merchant_id' ], $security_key );

		// Make request
		$response = $cybersource_soap->runTransaction( $request );
	}
	catch ( SoapFault $e ) {
		// $e->getMessage();
	}

	// store the payment information in the order, regardless of success or failure
	update_post_meta( $order->id, '_cybersource_request_id',            $response->requestID );
	update_post_meta( $order->id, '_cybersource_orderpage_environment', $this->is_test_mode() ? 'TEST' : 'PRODUCTION' );
	update_post_meta( $order->id, '_cybersource_card_type',             isset( $request->card->cardType ) ? $this->card_type_options[ $request->card->cardType ] : '' );
	update_post_meta( $order->id, '_cybersource_card_last4',            isset( $request->card->accountNumber ) ? substr( $request->card->accountNumber, -4 ) : '' );
	update_post_meta( $order->id, '_cybersource_card_expiration',       isset( $request->card->expirationMonth ) && isset( $request->card->expirationYear ) ? $request->card->expirationMonth . '/' . $request->card->expirationYear : '' );

	// needed for creating the transaction URL
	$order->order_custom_fields['_cybersource_request_id'][0]            = $response->requestID;
	$order->order_custom_fields['_cybersource_orderpage_environment'][0] = $this->is_test_mode() ? 'TEST' : 'PRODUCTION';

	if ( 'ACCEPT' == $response->decision ) {

		// Successful payment:
		$order_note = $this->is_test_mode() ?
						__( 'TEST MODE Credit Card Transaction Approved: %s ending in %s (%s)', WC_Cybersource::TEXT_DOMAIN ) :
						__( 'Credit Card Transaction Approved: %s ending in %s (%s)', WC_Cybersource::TEXT_DOMAIN );
		$order->add_order_note( sprintf( $order_note,
										$this->card_type_options[ $request->card->cardType ], substr( $request->card->accountNumber, -4 ), $request->card->expirationMonth . '/' . $request->card->expirationYear ) );
		$order->payment_complete();

	} elseif ( 'REVIEW' == $response->decision ) {

		// Transaction requires review:

		// admin message
		$error_message = "";
		if ( 230 == $response->reasonCode ) $error_message = __( "The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the CVN check.  You must log into your CyberSource account and decline or settle the transaction.", WC_Cybersource::TEXT_DOMAIN );
		if ( $error_message ) $error_message = " - " . $error_message;

		// Mark on-hold
		$order_note = sprintf( __( 'Transaction requires review: code %s%s', WC_Cybersource::TEXT_DOMAIN ), $response->reasonCode, $error_message );
		if ( 'on-hold' != $order->status ) {
			$order->update_status( 'on-hold', $order_note );
		} else {
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			$order->add_order_note( $order_note );
		}

		// user message:
		// specific messages based on reason code
		if ( 230 == $response->reasonCode ) $woocommerce->add_error( __( "This order is being placed on hold for review due to an incorrect card verification number.  You may contact the store to complete the transaction.", WC_Cybersource::TEXT_DOMAIN ) );

		// provide some default error message as needed
		if ( 0 == $woocommerce->error_count() ) {
			$woocommerce->add_error( __( "This order is being placed on hold for review.  You may contact the store to complete the transaction.", WC_Cybersource::TEXT_DOMAIN ) );
		}

	} else {

		// Failure:
		// admin error message, and set status to 'failed'
		$order_note = __( 'CyberSource Credit Card payment failed', WC_Cybersource::TEXT_DOMAIN ) . ' (Reason Code: ' . $response->reasonCode . ').';
		$order_note .= ' <a href="' . esc_url( $this->get_transaction_url( $order ) ) . '" target="_blank">' . __( 'View in CyberSource', WC_Cybersource::TEXT_DOMAIN ) . '</a>';

		$this->order_failed( $order, $order_note );

		// user error message
		if ( 202 == $response->reasonCode ) $woocommerce->add_error( __( "The provided card is expired, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 203 == $response->reasonCode ) $woocommerce->add_error( __( "The provided card was declined, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 204 == $response->reasonCode ) $woocommerce->add_error( __( "Insufficient funds in account, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 208 == $response->reasonCode ) $woocommerce->add_error( __( "The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 210 == $response->reasonCode ) $woocommerce->add_error( __( "The credit limit for the card has been reached, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 211 == $response->reasonCode ) $woocommerce->add_error( __( "The card verification number is invalid, please try again.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 231 == $response->reasonCode ) $woocommerce->add_error( __( "The provided card number was invalid, or card type was incorrect.  Please try again.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 232 == $response->reasonCode ) $woocommerce->add_error( __( "That card type is not accepted, please use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );
		if ( 240 == $response->reasonCode ) $woocommerce->add_error( __( "The card type is invalid or does not correlate with the credit card number.  Please try again or use an alternate card or other form of payment.", WC_Cybersource::TEXT_DOMAIN ) );

		// provide some default error message
		if ( 0 == $woocommerce->error_count() ) {
			// decision will be ERROR or REJECT
			if ( 'ERROR' == $response->decision ) $woocommerce->add_error( __( "An error occurred, please try again or try an alternate form of payment", WC_Cybersource::TEXT_DOMAIN ) );
			else $woocommerce->add_error( __( "We cannot process your order with the payment information that you provided.  Please use a different payment account or an alternate payment method.", WC_Cybersource::TEXT_DOMAIN ) );
		}

		// done, stay on page and display any messages
		return;
	}

	$status = strtolower( $api_response[ 'ACK' ] );

	switch ( $status ) {
		case 'success':
		case 'successwithwarning':
			// @todo Set message

			break;

		case 'failure':
		default:
			$messages = array();

			$message_count = 0;

			while ( isset( $api_response[ 'L_LONGMESSAGE' . $message_count ] ) ) {
				$messages[] = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ': ' . $api_response[ 'L_LONGMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

				$message_count++;
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SHORTMESSAGE' . $message_count ] ) ) {
					$messages[] = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$message_count++;
				}
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SEVERITYCODE' . $message_count ] ) ) {
					$messages[] = $api_response[ 'L_SEVERITYCODE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$message_count++;
				}
			}

			// @todo Set message
			// 'Correlation ID: ' . $api_response[ 'CORRELATIONID' ]

			// @todo Return errors

			break;
	}

	return array( 'id' => $api_response[ 'TRANSACTIONID' ], 'status' => 'success' );
}

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

function it_exchange_cybersource_addon_get_card_type( $number ) {

	//removing spaces from number
	$number = str_replace( ' ', '', $number );

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