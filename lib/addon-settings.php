<?php
/**
 * Exchange will build your add-on's settings page for you and link to it from our add-on
 * screen. You are free to link from it elsewhere as well if you'd like... or to not use our API
 * at all. This file has all the functions related to registering the page, printing the form, and saving
 * the options. This includes the wizard settings. Additionally, we use the Exchange storage API to
 * save / retreive options. Add-ons are not required to do this.
*/

/**
 * This is the function registered in the options array when it_exchange_register_addon was called for CyberSource
 *
 * It tells Exchange where to find the settings page
 *
 * @return void
*/
function it_exchange_cybersource_addon_settings_callback() {
    $IT_Exchange_CyberSource_Add_On = new IT_Exchange_CyberSource_Add_On();
    $IT_Exchange_CyberSource_Add_On->print_settings_page();
}

/**
 * Outputs wizard settings for CyberSource
 *
 * Exchange allows add-ons to add a small amount of settings to the wizard.
 * You can add these settings to the wizard by hooking into the following action:
 * - it_exchange_print_[addon-slug]_wizard_settings
 * Exchange exspects you to print your fields here.
 *
 * @since 1.0.0
 * @todo make this better, probably
 * @param object $form Current IT Form object
 * @return void
*/
function it_exchange_print_cybersource_wizard_settings( $form ) {
    $IT_Exchange_CyberSource_Add_On = new IT_Exchange_CyberSource_Add_On();
    $settings = it_exchange_get_option( 'addon_cybersource', true );
    $form_values = ITUtility::merge_defaults( ITForm::get_post_data(), $settings );
    $hide_if_js =  it_exchange_is_addon_enabled( 'cybersource' ) ? '' : 'hide-if-js';
    ?>
    <div class="field cybersource-wizard <?php echo $hide_if_js; ?>">
    <?php if ( empty( $hide_if_js ) ) { ?>
        <input class="enable-cybersource" type="hidden" name="it-exchange-transaction-methods[]" value="cybersource" />
    <?php } ?>
    <?php $IT_Exchange_CyberSource_Add_On->get_form_table( $form, $form_values ); ?>
    </div>
    <?php
}
add_action( 'it_exchange_print_cybersource_wizard_settings', 'it_exchange_print_cybersource_wizard_settings' );

/**
 * Saves CyberSource settings when the Wizard is saved
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_save_cybersource_wizard_settings( $errors ) {
    if ( ! empty( $errors ) )
        return $errors;

    $IT_Exchange_CyberSource_Add_On = new IT_Exchange_CyberSource_Add_On();
    return $IT_Exchange_CyberSource_Add_On->save_wizard_settings();
}
// add_action( 'it_exchange_save_cybersource_wizard_settings', 'it_exchange_save_cybersource_wizard_settings' );

/**
 * Default settings for CyberSource
 *
 * @since 1.0.0
 *
 * @param array $values
 * @return array
*/
function it_exchange_cybersource_addon_default_settings( $values ) {
    $defaults = array(
        'cybersource_merchant_id'                => '',
        'cybersource_live_security_key'                => '',
        'cybersource_test_security_key'                => '',
		'cybersource_sale_method'                 => 'auth_capture',
        'cybersource_sandbox_mode'                => false,
        'cybersource_purchase_button_label' => __( 'Purchase', 'LION' ),
    );
    $values = ITUtility::merge_defaults( $values, $defaults );
    return $values;
}
add_filter( 'it_storage_get_defaults_exchange_addon_cybersource', 'it_exchange_cybersource_addon_default_settings' );

/**
 * Class for CyberSource
 * @since 1.0.0
*/
class IT_Exchange_CyberSource_Add_On {

    /**
     * @var boolean $_is_admin true or false
     * @since 1.0.0
    */
    var $_is_admin;

    /**
     * @var string $_current_page Current $_GET['page'] value
     * @since 1.0.0
    */
    var $_current_page;

    /**
     * @var string $_current_add_on Current $_GET['add-on-settings'] value
     * @since 1.0.0
    */
    var $_current_add_on;

    /**
     * @var string $status_message will be displayed if not empty
     * @since 1.0.0
    */
    var $status_message;

    /**
     * @var string $error_message will be displayed if not empty
     * @since 1.0.0
    */
    var $error_message;

    /**
     * Set up the class
     *
     * @since 1.0.0
    */
    function __construct() {
        $this->_is_admin       = is_admin();
        $this->_current_page   = empty( $_GET['page'] ) ? false : $_GET['page'];
        $this->_current_add_on = empty( $_GET['add-on-settings'] ) ? false : $_GET['add-on-settings'];

        if ( ! empty( $_POST ) && $this->_is_admin && 'it-exchange-addons' == $this->_current_page && 'cybersource' == $this->_current_add_on ) {
            add_action( 'it_exchange_save_add_on_settings_cybersource', array( $this, 'save_settings' ) );
            do_action( 'it_exchange_save_add_on_settings_cybersource' );
        }
    }

    /**
     * Prints settings page
     *
     * @since 1.0.0
    */
    function print_settings_page() {
        $settings = it_exchange_get_option( 'addon_cybersource', true );
        $form_values  = empty( $this->error_message ) ? $settings : ITForm::get_post_data();
        $form_options = array(
            'id'      => apply_filters( 'it_exchange_add_on_cybersource', 'it-exchange-add-on-cybersource-settings' ),
            'enctype' => apply_filters( 'it_exchange_add_on_cybersource_settings_form_enctype', false ),
            'action'  => 'admin.php?page=it-exchange-addons&add-on-settings=cybersource',
        );
        $form         = new ITForm( $form_values, array( 'prefix' => 'it-exchange-add-on-cybersource' ) );

        if ( ! empty ( $this->status_message ) )
            ITUtility::show_status_message( $this->status_message );
        if ( ! empty( $this->error_message ) )
            ITUtility::show_error_message( $this->error_message );

        ?>
        <div class="wrap">
            <?php screen_icon( 'it-exchange' ); ?>
            <h2><?php _e( 'CyberSource Settings', 'LION' ); ?></h2>

            <?php do_action( 'it_exchange_paypa-pro_settings_page_top' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_top' ); ?>
            <?php $form->start_form( $form_options, 'it-exchange-cybersource-settings' ); ?>
                <?php do_action( 'it_exchange_cybersource_settings_form_top' ); ?>
                <?php $this->get_form_table( $form, $form_values ); ?>
                <?php do_action( 'it_exchange_cybersource_settings_form_bottom' ); ?>
                <p class="submit">
                    <?php $form->add_submit( 'submit', array( 'value' => __( 'Save Changes', 'LION' ), 'class' => 'button button-primary button-large' ) ); ?>
                </p>
            <?php $form->end_form(); ?>
            <?php do_action( 'it_exchange_cybersource_settings_page_bottom' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_bottom' ); ?>
        </div>
        <?php
    }

    /**
     * Builds Settings Form Table
     *
     * @since 1.0.0
     */
    function get_form_table( $form, $settings = array() ) {

        $general_settings = it_exchange_get_option( 'settings_general' );

        if ( !empty( $settings ) ) {
            foreach ( $settings as $key => $var ) {
                $form->set_option( $key, $var );
			}
		}

        if ( ! empty( $_GET['page'] ) && 'it-exchange-setup' == $_GET['page'] ) : ?>
            <h3><?php _e( 'CyberSource', 'LION' ); ?></h3>
        <?php endif; ?>
        <div class="it-exchange-addon-settings it-exchange-cybersource-addon-settings">
            <p>
                <?php _e( 'To get CyberSource set up for use with Exchange, you\'ll need to add the following information from your CyberSource account.', 'LION' ); ?>
            </p>
            <p>
                <?php _e( 'Don\'t have a CyberSource account yet?', 'LION' ); ?> <a href="http://www.cybersource.com/" target="_blank"><?php _e( 'Go set one up here', 'LION' ); ?></a>.
            </p>
            <h4><?php _e( 'Fill out your CyberSource API Credentials', 'LION' ); ?></h4>
            <p>
                <label for="cybersource_merchant_id"><?php _e( 'Merchant ID', 'LION' ); ?>
					<span class="tip" title="<?php _e( 'This is the same merchant ID you use to log into the CyberSource Business Center.', 'LION' ); ?>">i</span></label>
                <?php $form->add_text_box( 'cybersource_merchant_id' ); ?>
            </p>
            <p>
                <label for="cybersource_live_security_key"><?php _e( 'Live Transaction Security Key', 'LION' ); ?>
					<span class="tip" title="<?php _e( 'You can find this by logging into your "Live" CyberSource Business Center, going to Account Management &gt; Transaction Security Keys &gt; Security Keys for the SOAP Toolkit API, and then click \'Generate\'.', 'LION' ); ?>">i</span></label>
                <?php $form->add_password( 'cybersource_live_security_key' ); ?>
            </p>
            <p>
                <label for="cybersource_test_security_key"><?php _e( 'Test Transaction Security Key', 'LION' ); ?>
					<span class="tip" title="<?php _e( 'You can find this by logging into your "Test" CyberSource Business Center, going to Account Management &gt; Transaction Security Keys &gt; Security Keys for the SOAP Toolkit API, and then click \'Generate\'.', 'LION' ); ?>">i</span></label>
                <?php $form->add_password( 'cybersource_test_security_key' ); ?>
            </p>
            <p>
                <label for="cybersource_sale_method"><?php _e( 'Transaction Sale Method', 'LION' ); ?></label>
				<?php
					$sale_methods = array(
						'auth_capture' => __( 'Authorize and Capture - Charge the Credit Card for the total amount', 'LION' ),
						'auth' => __( 'Authorize - Only authorize the Credit Card for the total amount', 'LION' )
					);

					$form->add_drop_down( 'cybersource_sale_method', $sale_methods );
				?>
            </p>

            <h4 class="hide-if-wizard"><?php _e( 'Optional: Enable CyberSource Sandbox Mode', 'LION' ); ?></h4>
            <p class="hide-if-wizard">
                <?php $form->add_check_box( 'cybersource_sandbox_mode', array( 'class' => 'show-test-mode-options' ) ); ?>
                <label for="cybersource_sandbox_mode"><?php _e( 'Enable CyberSource Sandbox Mode?', 'LION' ); ?>
					<span class="tip" title="<?php _e( 'Use this mode for testing your store. This mode will need to be disabled when the store is ready to process customer payments.', 'LION' ); ?>">i</span></label>
            </p>

            <h4><?php _e( 'Optional: Edit Purchase Button Label', 'LION' ); ?></h4>
            <p>
                <label for="cybersource_purchase_button_label"><?php _e( 'Purchase Button Label', 'LION' ); ?>
					<span class="tip" title="<?php _e( 'This is the text inside the button your customers will press to purchase with CyberSource', 'LION' ); ?>">i</span></label>
                <?php $form->add_text_box( 'cybersource_purchase_button_label' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save settings
     *
     * @since 1.0.0
     * @return void
    */
    function save_settings() {
        $defaults = it_exchange_get_option( 'addon_cybersource' );
        $new_values = wp_parse_args( ITForm::get_post_data(), $defaults );

        // Check nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'it-exchange-cybersource-settings' ) ) {
            $this->error_message = __( 'Error. Please try again', 'LION' );
            return;
        }

        $errors = apply_filters( 'it_exchange_add_on_cybersource_validate_settings', $this->get_form_errors( $new_values ), $new_values );
        if ( ! $errors && it_exchange_save_option( 'addon_cybersource', $new_values ) ) {
            ITUtility::show_status_message( __( 'Settings saved.', 'LION' ) );
        } else if ( $errors ) {
            $errors = implode( '<br />', $errors );
            $this->error_message = $errors;
        } else {
            $this->status_message = __( 'Settings not saved.', 'LION' );
        }
    }

    /**
     * Save wizard settings
     *
     * @since 1.0.0
     * @return void|array Void or Error message array
    */
    function save_wizard_settings() {
        if ( empty( $_REQUEST['it_exchange_settings-wizard-submitted'] ) )
            return;

		$defaults = it_exchange_cybersource_addon_default_settings( array() );

        $cybersource_settings = array(
			'cybersource_sale_method' => $defaults[ 'cybersource_sale_method' ],
			'cybersource_purchase_button_label' => $defaults[ 'cybersource_purchase_button_label' ]
		);

        // Fields to save
        $fields = array_keys( $defaults );

        $default_wizard_cybersource_settings = apply_filters( 'default_wizard_cybersource_settings', $fields );

        foreach( $default_wizard_cybersource_settings as $var ) {
            if ( isset( $_REQUEST['it_exchange_settings-' . $var] ) ) {
                $cybersource_settings[$var] = $_REQUEST['it_exchange_settings-' . $var];
            }
        }

        $settings = wp_parse_args( $cybersource_settings, it_exchange_get_option( 'addon_cybersource' ) );

        if ( $error_msg = $this->get_form_errors( $settings ) ) {

            return $error_msg;

        } else {
            it_exchange_save_option( 'addon_cybersource', $settings );
            $this->status_message = __( 'Settings Saved.', 'LION' );
        }

        return;
    }

    /**
     * Validates for values
     *
     * Returns string of errors if anything is invalid
     *
     * @since 1.0.0
     * @return array
    */
    public function get_form_errors( $values ) {

        $errors = array();

		if ( empty( $values['cybersource_merchant_id'] ) )
            $errors[] = __( 'Please include your CyberSource Merchant ID', 'LION' );

		if ( empty( $values[ 'cybersource_live_security_key' ] ) && empty( $values[ 'cybersource_sandbox_mode' ] ) ) {
            $errors[] = __( 'Please include your CyberSource Live Transaction Security Key', 'LION' );
		}
		elseif ( empty( $values[ 'cybersource_test_security_key' ] ) && !empty( $values[ 'cybersource_sandbox_mode' ] ) ) {
            $errors[] = __( 'Please include your CyberSource Test Transaction Security Key', 'LION' );
		}

        return $errors;

    }

}
