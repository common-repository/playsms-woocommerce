<?php
/**
 * Plugin Name: PlaySMS - Woocommerce
 * Version: 1.0
 * Description: Integracja Woocommerce z PlaySMS.pl
 * Author: App World Sp. z o.o.
 * Author URI: http://playsms.pl/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * PlaySMS - Woocommerce is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 2 of the License, or
   any later version.

   PlaySMS - Woocommerce is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PlaySMS - Woocommerce - in file PlaySMS_LICENSE.
 */

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

require_once( dirname( __FILE__ ) . '/classes/PlaySMS_SendSms.php' );
require_once( dirname( __FILE__ ) . '/classes/PlaySMS_SenderFields.php' );

$playSMS_availableAdminTabs = array('playSMS-settings', 'playSMS-events', 'playSMS-errorlog');
$playSMS_timezone = 'Europe/Warsaw';

$playSMS_hooksToAssociate = array(
    'woocommerce_customer_save_address',
    'woocommerce_new_order',
    'woocommerce_order_status_pending',
    'woocommerce_payment_complete',
    'woocommerce_order_status_failed',
    'woocommerce_order_status_on-hold',
    'woocommerce_order_status_completed',
    'woocommerce_order_status_refunded',
    'woocommerce_order_status_processing',
    'woocommerce_order_status_cancelled',
    'woocommerce_before_checkout_process'
);

$playSMS_actionTypes = playSMS_linkActionTypeToHook();


function playSMS_onPluginActivation() {
    global $wpdb;

    if ( !current_user_can( 'activate_plugins' ) ) {
        return false;
    }

    $plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field($_REQUEST['plugin']) : '';
    check_admin_referer( "activate-plugin_{$plugin}" );

    $charset_collate = $wpdb->get_charset_collate();

    $eventsTableName = $wpdb->prefix . "playsms_events";
    $sqlEvents = "CREATE TABLE IF NOT EXISTS $eventsTableName (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name  varchar(55) NOT NULL,
      associated_hook  varchar(55) NOT NULL,
      sms_message  varchar(600) NOT NULL,
      action_type  varchar(255) NOT NULL,
      convert_characters  tinyint(1) NULL,
      message_header  varchar(11) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    $errorsTableName = $wpdb->prefix . "playsms_errors";
    $sqlErrors = "CREATE TABLE IF NOT EXISTS $errorsTableName (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      error_date  TIMESTAMP NULL DEFAULT now(),
      error_message  varchar(255) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    $wpdb->query($sqlEvents);
    $wpdb->query($sqlErrors);
}
register_activation_hook( __FILE__, 'playSMS_onPluginActivation' );

function playSMS_onPluginDeactivation() {
    global $wpdb;
    
    if ( !current_user_can( 'activate_plugins' ) ) {
        return;
    }
    
    $plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field($_REQUEST['plugin']) : '';
    
    if (!empty($plugin)){
        check_admin_referer( "deactivate-plugin_{$plugin}" );
    }
}
register_deactivation_hook( __FILE__, 'playSMS_onPluginDeactivation' );

function playSMS_enqueue_assets() {
    $url = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    
    if ( strpos( $url, 'playSMS-menu' ) !== false ) {

        wp_register_script( 'app', plugin_dir_url( __FILE__ ) . '/js/PlaySMS_app.js', array( 'jquery' ), '0.0.51', true );

        wp_enqueue_script( 'app' );
        wp_localize_script( "app",
            'psAjaxObject',
            array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ), //url for php file that process ajax request to WP
            )
        );

    }
}
add_action( 'admin_enqueue_scripts', 'playSMS_enqueue_assets' );



function playSMS_registerSubmenuPage() {
    add_submenu_page(
        'woocommerce',
        __('PlaySMS Zarządzanie'),
        'PlaySMS',
        'manage_options',
        'playSMS-menu',
        'playSMS_menuCallback'
    );
}
add_action( 'admin_menu', 'playSMS_registerSubmenuPage' );

function playSMS_menuCallback() {
    global $wpdb, $playSMS_availableAdminTabs;

    if (isset($_GET['tab'])){
        $tab = sanitize_text_field($_GET['tab']);

        if (in_array($tab, $playSMS_availableAdminTabs)){
            $active_tab = $tab;
        }else{
            $active_tab = 'playSMS-settings';
        }
    
    }else{
        $active_tab = 'playSMS-settings';
    }
?>
    <div class="wrap">
        <div id="icon-themes" class="icon32"></div>
        <h1 id="plugin-title"><?php _e('Ustawienia integracji WooCommerce z PlaySMS'); ?></h1>
            <?php settings_errors(); ?>
        <h2 class="nav-tab-wrapper">
            <a href="admin.php?page=playSMS-menu&tab=playSMS-settings"
               class="nav-tab <?php echo $active_tab === 'playSMS-settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Ogólne'); ?></a>
            <a href="admin.php?page=playSMS-menu&tab=playSMS-events"
               class="nav-tab <?php echo $active_tab === 'playSMS-events' ? 'nav-tab-active' : ''; ?>"><?php _e('Eventy'); ?></a>
            <a href="admin.php?page=playSMS-menu&tab=playSMS-errorlog"
               class="nav-tab <?php echo $active_tab === 'playSMS-errorlog' ? 'nav-tab-active' : ''; ?>"><?php _e('Błędy'); ?></a>
        </h2>

            <?php 
                if ( $active_tab === 'playSMS-settings' ) {
                    echo '<form method="post" action="' . esc_url( "options.php" ) . '">';
                        settings_fields( 'playSMS-settings' );
                        do_settings_sections( 'playSMS-settings' );
                        submit_button();
                    echo '</form>';

                } else if ( $active_tab === 'playSMS-events' ) {
                    settings_fields( 'PlaySMS_events_options' );
                    do_settings_sections( 'PlaySMS_events_options' );
                } else if ( $active_tab === 'playSMS-errorlog' ) {
                    echo "<div>";
                    
                    $errors = playSMS_getAllErrors();

                    if ( $errors === false ) {
                        echo "<p>" . __( 'Brak błędów' ) . "</p>";
                    } else {
                        playSMS_deleteOldErrors();

                        echo '<ul>';
                        if (!empty($errors)){
                            foreach ( $errors as $error ) {
                                echo '<li>'.__('Data').': ' . esc_html($error['error_date']) . ' '.__('Wiadomość').': ' . esc_html($error['error_message']) . ' </li>';
                            }
                        }
                        echo '</ul>';
                    }
                    echo "</div>";
                }

            ?>

    </div>

<?php }

function playSMS_deleteOldErrors() {
    global $wpdb, $playSMS_timezone;
    
    $now = new DateTime( $playSMS_timezone );
    $errors = playSMS_getAllErrors();

    if (!empty($errors) && $errors !== false){
        
        foreach ( $errors as $error ) {

            $errorDate = esc_attr($error['error_date']);
            $errorId = (int)$error['id'];

            $errorDate = new DateTime ( $errorDate );
            $errorDueDate = $errorDate->modify( '+1 month' );

            if ( $now >= $errorDueDate ) {
                $wpdb->delete( $wpdb->prefix . "playsms_errors", array(
                        'id' => $errorId
                    )
                );
            }
        }
    }
}

function playSMS_getAllErrors() {
    global $wpdb;
    
    $errors = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix . "playsms_errors", ARRAY_A );

    if (!empty($errors)){
        return $errors;
    }else{
        return false;
    }
}

/** ----------------------------------------------------------------------- *
 * Settings
 * ------------------------------------------------------------------------ */

function playSMS_initializeThemeOptions() {

	add_settings_section(
            'playSMS-settings',
            __('Ustawienia ogólne'),
            'playSMS_generalOptionsCallback',
            'playSMS-settings'
	);

	if ( get_option( 'playSMS-settings' ) === false ) {
            add_option( 'playSMS-settings' );
	}

	add_settings_field(
            'playSMS-apiKey',
            __('Klucz API'),
            'playSMS_apiKeyCallback',
            'playSMS-settings',
            'playSMS-settings',
            array(
                __('Wprowadź klucz api dostępny w panelu konta na stronie playsms.pl')
            )
	);

	add_settings_field(
            'playSMS_apiPass',
            __('Hasło'),
            'playSMS_apiPasswordCallback',
            'playSMS-settings',
            'playSMS-settings',
            array(
                __('Wprowadź hasło powiązane z kluczem API, domyślnie jest to hasło nadane przy tworzeniu konta.')
            )
	);

	add_settings_field(
            'playSMS-apiHeader',
            __('Wybierz domyślne pole nadawcy'),
            'playSMS_apiHeaderCallback',
            'playSMS-settings',
            'playSMS-settings'
	);

	register_setting(
            'playSMS-settings',
            'playSMS-apiKey',
            'playSMS_validateApiKey'
	);
        
        register_setting(
            'playSMS-settings',
            'playSMS-apiPass',
            'playSMS_validateApiPass'
	);
        
        register_setting(
            'playSMS-settings',
            'playSMS-apiHeader'
	);
}
add_action( 'admin_init', 'playSMS_initializeThemeOptions' );

function playSMS_custom_admin_notice() {
    $screen = get_current_screen();

    if ( $screen->base === 'woocommerce_page_playSMS-menu' ) {
        $cleanURL = esc_url( 'https://panel.playsms.pl/login' );
?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e( 'W przypadku chęci masowej wysyłki SMSów prosimy przejść na stronę <a href="' . $cleanURL . '" target="_blank">PlaySMS.pl</a>!' ); ?></p>
            </div>
<?php
    }
}
add_action( 'admin_notices', 'playSMS_custom_admin_notice' );

function playSMS_initializeEventsOptions() {

    if ( get_option( 'PlaySMS_events_options' ) === false ) {
        add_option( 'PlaySMS_events_options' );
    }

    add_settings_section(
        'events_settings_section',
        __('Eventy'),
        'playSMS_eventsOptionsCallback',
        'PlaySMS_events_options'
    );

    add_settings_field(
        'playSMS_apiPass',
        __('Hasło'),
        'playSMS_apiPasswordCallback',
        'playSMS-settings',
        'playSMS-settings',
        array(
            __('Wprowadź hasło powiązane z kluczem API, domyślnie jest to hasło nadane przy tworzeniu konta.')
        )
    );

}
add_action( 'admin_init', 'playSMS_initializeEventsOptions' );


/** ------------------------------------------------------------------------*
 * Section Callbacks
 * ------------------------------------------------------------------------ */

function playSMS_eventsOptionsCallback() { ?>

    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>
            <th id="cb" class="manage-column column-cb check-column" scope="col"></th>
            <th id="columnname" class="manage-column column-columnname" scope="col"><?php _e('Nazwa'); ?></th>
            <th id="columnname" class="manage-column column-columnname" scope="col"><?php _e('Wiadomość SMS'); ?></th>
            <th id="columnname" class="manage-column column-columnname" scope="col"><?php _e('Typ akcji'); ?></th>
            <th id="columnname" class="manage-column column-columnname" scope="col"><?php _e('Pole nadawcy'); ?></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th class="manage-column column-cb check-column" scope="col"></th>
            <th class="manage-column column-columnname" scope="col"><?php _e('Nazwa'); ?></th>
            <th class="manage-column column-columnname" scope="col"><?php _e('Wiadomość SMS'); ?></th>
            <th class="manage-column column-columnname" scope="col"><?php _e('Typ akcji'); ?></th>
            <th class="manage-column column-columnname" scope="col"><?php _e('Pole nadawcy'); ?></th>
        </tr>
        </tfoot>

        <tbody id="playSMS-allEvents">
            <?php
                playSMS_get_events();
            ?>
        </tbody>
    </table>

    <h2><?php _e('Dodaj nowy event'); ?></h2>
    
    <form id="playSMS-addNewEventForm">
        <table class="form-table">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="playSMS-eventName"><?php _e('Nazwa'); ?>:</label>
                </th>
                <td>
                    <input type="text" id="playSMS-eventName" name="playSMS-eventName">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="playSMS-eventMessage"><?php _e('Treść wiadomości sms'); ?>:</label>
                </th>
                <td>
                    <div style="margin-bottom: 20px;">
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="IMIE"
                               id="addCustomerNameToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="NAZWISKO"
                               id="addCustomerSecondNameToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="ULICA"
                               id="addCustomerStreetToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="KOD"
                               id="addCustomerPostalCodeToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="MIEJSCOWOSC"
                               id="addCustomerCityToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="NR_ZAMOWIENIA"
                               id="addCustomerOrderNumberToMessageButton"/>
                        <input type="button" class="button-secondary playSMS-addVariableToMessage" value="WARTOSC_ZAMOWIENIA"
                               id="addCustomerOrderValueToMessageButton"/>
                    </div>
                    
                    <textarea rows="7" cols="50" id="playSMS-eventMessage" name="playSMS-eventMessage"></textarea>
                    
                    <div class="playsms_checkbox">
                        <input type="checkbox" id="playSMS-changePolishLettersCheckbox"
                               name="playSMS-changePolishLettersCheckbox"
                               value="1" checked>
                        <label for="playSMS-changePolishLettersCheckbox"><?php _e('Zamiana polskich znaków na ich odpowiedniki'); ?></label>
                    </div>
                    
                    <div class="playSMS-countCharacters">
                        <h6 id="playSMS-countMessage"></h6>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="playSMS-apiHeader"><?php _e('Pole nadawcy'); ?>:</label>
                </th>
                <td>
                    
                    <?php 
                        $defaultHeader = get_option( 'playSMS-apiHeader' );
                        $senderHeaders = get_option( 'smsApi-senderHeaders' );
                    ?>
                    
                    <select id="playSMS-apiHeader" name="playSMS-apiHeader">
                        
                        <?php if ( ! empty( $senderHeaders ) ) {

                                $senderHeaders = unserialize( $senderHeaders );

                                    if (!empty($senderHeaders)){

                                        $selected = '';
                                        
                                        foreach ( $senderHeaders as $senderHeader ) {
                                                if ( ! empty( $defaultHeader && $defaultHeader === $senderHeader ) ) {
                                                        $selected = 'selected';
                                                } 
                        ?>

                                                    <option value="<?php echo $senderHeader; ?>" <?php echo $selected; ?>><?php echo $senderHeader; ?></option>

                        <?php
                                        }
                                        
                                    }else{
                                        echo '<option value="noConnection">'.__('Uzupełnij klucz API oraz hasło').'</option>';
                                    }
                                    
                            } else { ?>
                                <option value="noConnection"><?php _e('Uzupełnij klucz API oraz hasło'); ?></option>
                        <?php 
                            } 
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="playSMS-eventActionType"><?php _e('Rodzaj akcji'); ?>:</label>
                </th>
                <td>
                    <select id="playSMS-eventActionType">
                        <?php 
                        $actionTypes = playSMS_linkActionTypeToHook();
                        
                        if (!empty($actionTypes)){
                            foreach ( $actionTypes as $actionType => $actionName ) {
                                echo '<option value="' . $actionType . '">' . $actionName . '</option>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <input class="button-primary" type="submit" value="<?php _e('Dodaj nowy event'); ?>">
                </th>
            </tr>

            </tbody>
        </table>
    </form>
    
	<?php
}

function playSMS_apiKeyCallback( $args ) {
	$apiKey = get_option( 'playSMS-apiKey' );

	$html = '<input type="text" id="playSMS-apiKey" name="playSMS-apiKey" value="' . esc_attr($apiKey) . '" style="width: 300px;"/>';
        
        if (isset($args[0])){
            $html .= '<label for="playSMS-apiKey"> ' . esc_html($args[0]) . '</label>';
        }
        
	echo $html;
}


function playSMS_apiPasswordCallback( $args ) {
	$apiPass = get_option( 'playSMS-apiPass' );

	$html = '<input type="password" id="playSMS-apiPass" name="playSMS-apiPass" value="' . esc_attr($apiPass) . '" style="width: 300px;"/>';
        
        if (isset($args[0])){
            $html .= '<label for="playSMS-apiPass"> ' . esc_html($args[0]) . '</label>';
        }
        
	echo $html;
}

function playSMS_apiHeaderCallback() {

	$defaultHeader = get_option( 'playSMS-apiHeader' );
        
	if ( empty( $defaultHeader ) ) {
            $html = '<select id="playSMS-apiHeader" name="playSMS-apiHeader" disabled><option value="noConnection" ' . selected( $defaultHeader, __("Uzupełnij klucz API oraz hasło") ) . '>'.__('Uzupełnij klucz API oraz hasło').'</option></select>';
	} else {
            $html = '';
            $senderHeaders = get_option( 'smsApi-senderHeaders' );
	?>
    
            <select id="playSMS-apiHeader" name="playSMS-apiHeader">
                <?php 

                    if ( ! empty( $senderHeaders ) ) {
                        
                        $senderHeaders = unserialize( $senderHeaders );
                        
                        if (!empty($senderHeaders)){

                            foreach ( $senderHeaders as $senderHeader ) {
                                $selected = '';

                                if ( ! empty( $defaultHeader && $defaultHeader == $senderHeader ) ) {
                                    $selected = 'selected';
                                } ?>
                                    <option value="<?php echo $senderHeader; ?>" <?php echo $selected; ?>><?php echo $senderHeader; ?></option>
                                <?php
                            }
                        
                        }else{
                            echo '<option value="noConnection">'.__('Uzupełnij klucz API oraz hasło').'</option>';
                        }

                    } else { ?>
                        <option value="noConnection"><?php _e('Uzupełnij klucz API oraz hasło'); ?></option>
                <?php 
                    } 
                ?>
            </select>

    <?php
	}
        
	$html .= '<input type="button" id="playSMS-testApiConnection" style="margin-top: 20px;" class="button-primary" value="'.__('Aktualizuj listę pól nadawcy').'">';

	echo $html;

}

function playSMS_generalOptionsCallback() {
    echo '<p>'.__('Klucz API oraz hasło powinno być dostępne w panelu Twojego konta na stronie playsms.pl').'</p>';
}


/**
 * EVENT HOOKS
 */

function playSMS_sendSMS($billingPhone, $type, $eventsWithAssociatedHook, $user_id = 0, $order_id = 0){
    global $playSMS_timezone;
    
    $apiKey = get_option( 'playSMS-apiKey' );
    $apiPass = get_option( 'playSMS-apiPass' );

    if (!empty($apiKey) && !empty($apiPass) && playSMS_validateApiKey($apiKey)){

        $test = 0;
        $format = 'json';

        foreach ( $eventsWithAssociatedHook as $event ) {
            $smsMessage = esc_html($event['sms_message']);
            
            $smsMessageConverted = playSMS_convertSMSMessage( $smsMessage, $user_id, $order_id, $type );
            $convertCharacters = (int)$event['convert_characters'];
            
            $senderHeader = esc_html($event['message_header']);

            $smsMessage = new PlaySMS_SendSms( $apiKey, $apiPass, $billingPhone, $smsMessageConverted, $test, $senderHeader, $convertCharacters, $format );
            $sendSMSMessage = $smsMessage->send();
            
            $response = playSMS_getStringBetween( (string) $sendSMSMessage, '<result>', '</result>' );
        
            if ( $response === 'ERROR' ) {

                $date = new DateTime( $playSMS_timezone );
                $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
                $errorMessage = playSMS_getStringBetween( $sendSMSMessage, '<errorMsg>', '</errorMsg>' );

                playSMS_insertError( $datetimeFormatted, $errorMessage );
            }
        }

    }else{

        $date = new DateTime( $playSMS_timezone );
        $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
        $errorMessage = __('Brak klucza API/hasła lub błędne dane - nie wysłano wiadomości SMS do Klienta ID: '.$user_id);

        playSMS_insertError( $datetimeFormatted, $errorMessage );
    }
}

function playSMS_doActionOnChangingOrderStatusEvent($hookToAssociate, $order_id = 0){
    global $wpdb, $playSMS_hooksToAssociate, $playSMS_timezone;
    
    if (in_array($hookToAssociate, $playSMS_hooksToAssociate)){
        
	$eventsTable = $wpdb->prefix . 'playsms_events';
	$exist = $wpdb->get_var( "SELECT COUNT(id) FROM $eventsTable WHERE associated_hook = '".$hookToAssociate."'" );
        
	if ( $exist > 0 ) {
            $eventsWithAssociatedHook = $wpdb->get_results( "SELECT * FROM $eventsTable WHERE associated_hook = '".$hookToAssociate."'", ARRAY_A );
            $orderData = get_post_meta( $order_id );
            
            if (isset($orderData['_billing_phone'][0]) && !empty($orderData['_billing_phone'][0])){
            
                $billingPhone = $orderData['_billing_phone'][0];

                playSMS_sendSMS($billingPhone, 'order', $eventsWithAssociatedHook, 0, $order_id);

            }else{

                $date = new DateTime( $playSMS_timezone );
                $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
                $errorMessage = __('Klient z zamówienia ID: '.$order_id.__(' nie ma podanego numeru telefonu - nie wysłano wiadomości SMS po zmianie statusu.'));

                playSMS_insertError( $datetimeFormatted, $errorMessage );
            }
            
	}
        
    }else{
        $date = new DateTime( $playSMS_timezone );
        $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
        $errorMessage = __('Klient z zamówienia ID: '.$order_id.__(' nieprawidłowy status: '.$eventType));

        playSMS_insertError( $datetimeFormatted, $errorMessage );
    }
}

function playSMS_woocommerceCustomerSaveAddress( $user_id ) {
    global $wpdb, $playSMS_timezone;
    
    $eventsTable = $wpdb->prefix . 'playsms_events';
    $exist = $wpdb->get_var( "SELECT COUNT(id) FROM $eventsTable WHERE associated_hook = 'woocommerce_customer_save_address'" );
    
    if ( $exist > 0 ) {
        $eventsWithAssociatedHook = $wpdb->get_results( "SELECT * FROM $eventsTable WHERE associated_hook = 'woocommerce_customer_save_address'", ARRAY_A );
        $userdata = get_user_meta( $user_id );
        
        if (isset($userdata['billing_phone'][0]) && !empty($userdata['billing_phone'][0])){
            
            $billingPhone = $userdata['billing_phone'][0];

            playSMS_sendSMS($billingPhone, 'userdata', $eventsWithAssociatedHook, $user_id, 0);
        
        }else{
            
            $date = new DateTime( $playSMS_timezone );
            $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
            $errorMessage = __('Klient ID: '.$user_id.__(' nie ma podanego numeru telefonu - nie wysłano wiadomości SMS po zapisaniu adresu.'));

            playSMS_insertError( $datetimeFormatted, $errorMessage );
        }
    }
}
add_action( 'woocommerce_customer_save_address', 'playSMS_woocommerceCustomerSaveAddress', 10, 1 );

function playSMS_insertError( $datetime, $errorMessage ) {
    $insertError = false;
    
    if ( ! empty( $datetime ) && ! empty( $errorMessage ) ) {
        global $wpdb;
        
        $errorTableName = $wpdb->prefix . 'playsms_errors';
        $insertError = $wpdb->insert( $errorTableName,
            array(
                'error_date'    => $datetime,
                'error_message' => $errorMessage
            )
        );
    }

    return $insertError;
}

/**
 * WC Order status changes
 */

function playSMS_orderCreated( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_new_order', $order_id);
}
add_action( 'woocommerce_new_order', 'playSMS_orderCreated', 1, 1 );

function playSMS_woocommerceOrderStatusPending( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_pending', $order_id);
}
add_action( 'woocommerce_order_status_pending', 'playSMS_woocommerceOrderStatusPending' );

function playSMS_woocommercePaymentComplete( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_payment_complete', $order_id);
}
add_action( 'woocommerce_payment_complete', 'playSMS_woocommercePaymentComplete', 10, 1 );

function playSMS_orderFailed( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_failed', $order_id);
}
add_action( 'woocommerce_order_status_failed', 'playSMS_orderFailed' );

function playSMS_orderOnHold( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_on-hold', $order_id);
}
add_action( 'woocommerce_order_status_on-hold', 'playSMS_orderOnHold' );

function playSMS_orderCompleted( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_completed', $order_id);
}
add_action( 'woocommerce_order_status_completed', 'playSMS_orderCompleted' );

function playSMS_orderRefunded( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_refunded', $order_id);
}
add_action( 'woocommerce_order_status_refunded', 'playSMS_orderRefunded' );

function playSMS_woocommerceOrderStatusProcessing( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_processing', $order_id);
}
add_action( 'woocommerce_order_status_processing', 'playSMS_woocommerceOrderStatusProcessing' );

function playSMS_orderCancelled( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_order_status_cancelled', $order_id);
}
add_action( 'woocommerce_order_status_cancelled', 'playSMS_orderCancelled' );

function playSMS_woocommerceBeforeCheckoutProccess( $order_id ) {
    playSMS_doActionOnChangingOrderStatusEvent('woocommerce_before_checkout_process', $order_id);
}
add_action( 'woocommerce_before_checkout_process', 'playSMS_woocommerceBeforeCheckoutProccess', 10, 1 );

function playSMS_get_events() {
    global $wpdb;

    $eventsTable = $wpdb->prefix.'playsms_events';
    $eventsSql = "SELECT * FROM ".$eventsTable;
    $events = $wpdb->get_results( $eventsSql, 'ARRAY_A' );

    if (!empty($events)){

        foreach ( $events as $event ) { 
?>
            <tr id="playSMS-singleEvent-<?php echo (int)$event['id']; ?>" class="alternate" valign="top">
                <th class="check-column" scope="row"></th>
                <td class="column-columnname">
                    <span><?php echo esc_html($event['name']); ?></span>
                    <div class="row-actions">
                        <span><a id="playSMS-editEvent-<?php echo (int)$event['id']; ?>" class="playSMS-editEvent" href="#">Edytuj</a> |</span>
                        <span><a id="playSMS-removeEvent-<?php echo (int)$event['id']; ?>" class="playSMS-removeEvent" href="#">Usuń</a></span>
                    </div>
                </td>
                <td class="column-columnname"><span><?php echo esc_html($event['sms_message']); ?></span></td>
                <td class="column-columnname"><?php echo esc_html($event['action_type']); ?></td>
                <td class="column-columnname"><?php echo esc_html($event['message_header']); ?></td>
            </tr>
<?php         
        }

    }
}

function playSMS_loadEventsAjax() {
    playSMS_get_events();
    wp_die();
}
add_action( 'wp_ajax_playSMS_loadEventsAjax', 'playSMS_loadEventsAjax' ); 


function playSMS_addNewEventAjax() {
    global $wpdb;
    
    $eventsTableName = $wpdb->prefix . "playsms_events";
    
    $eventName = sanitize_text_field($_POST['eventName']);
    $associatedHook = sanitize_text_field($_POST['associatedHook']);
    $eventMessage = sanitize_text_field($_POST['eventMessage']);
    $actionType = sanitize_text_field($_POST['eventActionType']);
    $convertCharacters = (int)$_POST['convertCharacters'];
    $eventHeader = sanitize_text_field($_POST['eventHeader']);
    
    if ( !playSMS_validateEventName($eventName) ) {
        $status = __( 'Uzupełnij nazwę eventu (min. 5 znaków, litery aflabetu, cyfry 0-9).' );
        echo $status;
        wp_die();
    }

    if ( !playSMS_validateAssociatedHook($associatedHook) ) {
        $status = __( 'Przypisz odpowiedni hook (z dostępnej listy).' );
        echo $status;
        wp_die();
    }

    if ( !playSMS_validateActionType($actionType) ) {
        $status = __( 'Wybierz prawidłowy typ eventu.' );
        echo $status;
        wp_die();
    }

    if ( !playSMS_validateEventMessage($eventMessage) ) {
        $status = __( 'Uzupełnij wiadomość (min. 5 znaków).' );
        echo $status;
        wp_die();
    }

    if ( !playSMS_validateConvertCharacters($convertCharacters) ) {
        $status = __( 'Błędna wartość konwersji na polskie znaki.' );
        echo $status;
        wp_die();
    }

    if ( !playSMS_validateEventHeader($eventHeader) ) {
        $status = __( 'Brak wybranego nagłówka lub niepoprawny format nagłówka (do 11 znaków).' );
        echo $status;
        wp_die();
    }
    
    $status = $wpdb->insert( $eventsTableName, array(
        'name'               => $eventName,
        'associated_hook'    => $associatedHook,
        'sms_message'        => $eventMessage,
        'action_type'        => $actionType,
        'convert_characters' => $convertCharacters,
        'message_header'     => $eventHeader
    ) );

    echo $status;

    wp_die();
}
add_action( 'wp_ajax_playSMS_addNewEventAjax', 'playSMS_addNewEventAjax' ); 

function playSMS_editEventAjax() {
	global $wpdb;
	$eventID = (int)$_POST['editedEventID'];

        $eventsTable = $wpdb->prefix.'playsms_events';
	$editedEvent = $wpdb->get_row( "SELECT * FROM ".$eventsTable." WHERE id = $eventID", ARRAY_A );
        
        if (!empty($editedEvent)){
?>

            <tr id="playSMS-singleEvent-<?php echo (int)$editedEvent['id']; ?>" class="alternate" valign="top">
                <th class="check-column" scope="row"></th>
                <td class="column-columnname">
                    <span><input type="text"
                                 id="playSMS-eventName-<?php echo (int)$editedEvent['id']; ?>"
                                 value="<?php echo esc_attr($editedEvent['name']); ?>"
                                 name="playSMS-eventName"></span>
                    <div class="row-actions">
                        <span><a id="playSMS-saveEvent-<?php echo (int)$editedEvent['id']; ?>" class="playSMS-saveEvent" href="#">Zapisz</a></span>
                    </div>
                </td>
                <td class="column-columnname"><span><textarea style="width: 100%;"
                                                              id="playSMS-eventMessage-<?php echo (int)$editedEvent['id']; ?>"
                                                              rows="5"
                        ><?php echo esc_attr($editedEvent['sms_message']); ?></textarea></span>
                    
                    <div class="playSMS-checkbox">
                        
                        <?php 
                            if ( (int)$editedEvent['convert_characters'] === 1 ) {
                                $checked = 'checked';
                            } else {
                                $checked = '';
                            }
                        ?>
                        
                        <input type="checkbox" id="playSMS-changePolishLettersCheckbox-<?php echo (int)$editedEvent['id']; ?>"
                               name="playSMS-changePolishLettersCheckbox-<?php echo (int)$editedEvent['id']; ?>"
                               value="1" <?php echo $checked; ?>>
                        
                        <label for="playSMS-changePolishLettersCheckbox-<?php echo (int)$editedEvent['id']; ?>"><?php _e('Zamiana polskich znaków na ich odpowiedniki'); ?></label>
                    </div>
                    <div id="playSMS-count_message_edit-<?php echo (int)$editedEvent['id']; ?>"></div>
                </td>
                <td class="column-columnname">
                    <select id="playSMS-eventActionType-<?php echo (int)$editedEvent['id']; ?>">
                        
                    <?php 
                    $actionTypes = playSMS_linkActionTypeToHook();

                    foreach ( $actionTypes as $actionType => $actionName ) {

                        if ( $editedEvent['action_type'] === $actionName ) {
                            $selected = 'selected';
                        } else {
                            $selected = '';
                        }

                        echo '<option value="' . $actionType . '"' . $selected . '>' . $actionName . '</option>';
                    }
                    ?>
                        
                    </select>
                </td>
                <td class="column-columnname">
                    <?php 
                        $chosenHeader = $editedEvent['message_header'];
                        $senderHeaders = get_option( 'smsApi-senderHeaders' );
                    ?>
                    <select id="playSMS-eventHeader-<?php echo $editedEvent['id']; ?>">
                        <?php 

                        if ( ! empty( $senderHeaders ) ) {
                            
                            $senderHeaders = unserialize( $senderHeaders );
                            
                            if (!empty($senderHeaders)){
                                
                                foreach ( $senderHeaders as $senderHeader ) {
                                    $selected = '';

                                    if ( ! empty( $chosenHeader && $chosenHeader === $senderHeader ) ) {
                                        $selected = 'selected';
                                    } 

                            ?>

                                    <option value="<?php echo $senderHeader; ?>" <?php echo $selected; ?>><?php echo $senderHeader; ?></option>

                            <?php
                                }
                                
                            }else{
                                echo '<option value="noConnection">'.__('Uzupełnij klucz API oraz hasło').'</option>';
                            }

                        } else { ?>
                                
                            <option value="noConnection"><?php _e('Uzupełnij klucz API oraz hasło'); ?></option>
                            
                    <?php 
                        } 
                    ?>
                    </select>
                </td>
            </tr>

	<?php 
    
        }else{
            
        ?>
            
            <tr>
                <td colspan="4">
                    <?php _e('Nieprawidłowe ID eventu.'); ?>
                </td>
            </tr>
            
        <?php
            
        }
        
        wp_die();
}
add_action( 'wp_ajax_playSMS_editEventAjax', 'playSMS_editEventAjax' ); 

function playSMS_saveEventAjax() {
	global $wpdb;
	$eventID = (int)$_POST['eventID'];
        
        if (!empty($eventID)){
            
            $eventsTable = $wpdb->prefix.'playsms_events';
            $editedEvent = $wpdb->get_row( "SELECT id FROM ".$eventsTable." WHERE id = $eventID", ARRAY_A );

            if (!empty($editedEvent)){
            
                $eventName = sanitize_text_field( $_POST['eventName'] );
                $associatedHook = sanitize_text_field( $_POST['associatedHook'] );
                $eventMessage = sanitize_text_field( $_POST['eventMessage'] );
                $actionType = sanitize_text_field( $_POST['eventActionType'] );
                $eventHeader = sanitize_text_field( $_POST['eventHeader'] );
                $convertCharacters = (int)$_POST['convertCharacters'];

                if ( !playSMS_validateEventName($eventName) ) {
                    $status = __( 'Uzupełnij nazwę eventu (min. 5 znaków, litery aflabetu, cyfry 0-9).' );
                    echo $status;
                    wp_die();
                }

                if ( !playSMS_validateAssociatedHook($associatedHook) ) {
                    $status = __( 'Przypisz odpowiedni hook (z dostępnej listy).' );
                    echo $status;
                    wp_die();
                }
                
                if ( !playSMS_validateActionType($actionType) ) {
                    $status = __( 'Wybierz prawidłowy typ eventu.' );
                    echo $status;
                    wp_die();
                }

                if ( !playSMS_validateEventMessage($eventMessage) ) {
                    $status = __( 'Uzupełnij wiadomość (min. 5 znaków).' );
                    echo $status;
                    wp_die();
                }

                if ( !playSMS_validateConvertCharacters($convertCharacters) ) {
                    $status = __( 'Błędna wartość konwersji na polskie znaki.' );
                    echo $status;
                    wp_die();
                }

                if ( !playSMS_validateEventHeader($eventHeader) ) {
                    $status = __( 'Brak wybranego nagłówka lub niepoprawny format nagłówka (do 11 znaków).' );
                    echo $status;
                    wp_die();
                }

                $updateEvent = $wpdb->update( $wpdb->prefix . 'playsms_events', array(
                    'name' => $eventName,
                    'associated_hook' => $associatedHook,
                    'sms_message' => $eventMessage,
                    'action_type' => $actionType,
                    'convert_characters' => $convertCharacters,
                    'message_header' => $eventHeader
                ), array( 'id' => $eventID ) );

                if ( is_numeric( $updateEvent ) ) {
                    $response = 1;
                } else {
                    $response = 0;
                }
            
            }else{
                $response = 0;
            }
        
        }else{
            $response = 0;
        }

	echo $response;

	wp_die();
}
add_action( 'wp_ajax_playSMS_saveEventAjax', 'playSMS_saveEventAjax' ); 


function playSMS_validateEventName($eventName){
    if (!empty($eventName) && preg_match("/[a-zA-Z0-9]+/i", $eventName)){
        return true;
    }else{
        return false;
    }
}

function playSMS_validateAssociatedHook($associatedHook){
    global $playSMS_hooksToAssociate;
    
    if (!empty($associatedHook) && in_array($associatedHook, $playSMS_hooksToAssociate)){
        return true;
    }else{
        return false;
    }
}

function playSMS_validateActionType($actionType){
    global $playSMS_actionTypes;
    
    if (!empty($actionType) && in_array($actionType, $playSMS_actionTypes)){
        return true;
    }else{
        return false;
    }
}

function playSMS_validateEventMessage($eventMessage){
    if (!empty($eventMessage) && strlen($eventMessage) >= 5){
        return true;
    }else{
        return false;
    }
}

function playSMS_validateConvertCharacters($convertCharacters){
    if (in_array($convertCharacters, array(0, 1))){
        return true;
    }else{
        return false;
    }
}

function playSMS_validateEventHeader($eventHeader){
    if (!empty($eventHeader) && strlen($eventHeader) <= 11){
        return true;
    }else{
        return false;
    }
}



function playSMS_removeEventAjax() {
	global $wpdb;
	$eventID = (int)$_POST['eventID'];
        
        if (!empty($eventID)){
            
            $eventsTable = $wpdb->prefix . 'playsms_events';
            $editedEvent = $wpdb->get_row( "SELECT id FROM ".$eventsTable." WHERE id = $eventID", ARRAY_A );

            if (!empty($editedEvent)){
                
                $removeEvent = $wpdb->delete( $eventsTable, array( 'id' => $eventID ) );

                if ( is_numeric( $removeEvent ) ) {
                    $response = 1;
                } else {
                    $response = 0;
                }
                
            }else{
                $response = 0;
            }
            
        }else{
            $response = 0;
        }

	echo $response;

	wp_die();
}
add_action( 'wp_ajax_playSMS_removeEventAjax', 'playSMS_removeEventAjax' ); 

function playSMS_linkActionTypeToHook() {
    return $actionTypes = [
        'none'                                => __('Wybierz'),
        'woocommerce_customer_save_address'   => __('W momencie zmiany danych adresowych'),
        'woocommerce_new_order'               => __('Utworzenie zamówienia'),
        'woocommerce_payment_complete'        => __('Zamówienie opłacone'),
        'woocommerce_order_status_failed'     => __('Zamówienie nieudane (płatność nieudana)'),
        'woocommerce_order_status_on-hold'    => __('Zamówienie wstrzymane'),
        'woocommerce_order_status_cancelled'  => __('Zamówienie anulowane'),
        'woocommerce_order_status_refunded'   => __('Zamówienie zwrócone'),
        'woocommerce_order_status_completed'  => __('Zamówienie zrealizowane'),
        'woocommerce_order_status_pending'    => __('Zamówienie oczekujące na płatność'),
        'woocommerce_order_status_processing' => __('W trakcie realizacji')
    ];
}

function playSMS_testApiConnectionAjax() {
    global $playSMS_timezone;
    
    $apiKey = get_option( 'playSMS-apiKey' );
    $apiPassword = get_option( 'playSMS-apiPass' );
    
    if (!empty($apiKey) && !empty($apiPassword) && playSMS_validateApiKey($apiKey)){

        $senderFieldsObject = new PlaySMS_SenderFields( $apiKey, $apiPassword );
        $senderFieldsString = $senderFieldsObject->getSenderFields();

        if ( ! empty( $senderFieldsString ) && $senderFieldsString !== 'ERROR' ) {
            $senderFieldsString = str_replace( "[", "", $senderFieldsString );
            $senderFieldsString = str_replace( "]", "", $senderFieldsString );
            $senderFieldsString = str_replace( '"', "", $senderFieldsString );
            $senderFieldsArray  = explode( ",", $senderFieldsString );
            $senderFieldsArraySerialized = serialize( $senderFieldsArray );
            
            add_option( 'smsApi-senderHeaders', $senderFieldsArraySerialized, '', '' );
            $response = json_encode( $senderFieldsArray );
            
        } else {
            $response = 'ERROR';
            
            $date = new DateTime( $playSMS_timezone );
            $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
            $errorMessage = __( "Błąd pobierania nagłówków" );
            
            playSMS_insertError( $datetimeFormatted, $errorMessage );
        }
    
    }else{
        $response = 'ERROR';
        
        $date = new DateTime( $playSMS_timezone );
        $datetimeFormatted = $date->format( 'Y-m-d H:i:s' );
        $errorMessage = __( "Błędne dane do API - nie pobrano dostępnych nagłówków wiadomości SMS." );

        playSMS_insertError( $datetimeFormatted, $errorMessage );
    }

    echo $response;
    wp_die();
}
add_action( 'wp_ajax_playSMS_testApiConnectionAjax', 'playSMS_testApiConnectionAjax' ); 

function playSMS_validateApiKey($apiKey){
    if (!empty($apiKey) && preg_match("/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i", $apiKey)){
        return $apiKey;
    }else{
        
        add_settings_error(
            'playSMS-apiKey',
            'playSMS-apiKey',
            'Nieprawidłowy klucz API. Spróbuj ponownie.',
            'error'
        );
        
        return false;
    }
}

function playSMS_validateApiPass($apiPass){
    if (!empty($apiPass) && strlen($apiPass) > 3){
        return $apiPass;
    }else{
        
        add_settings_error(
            'playSMS-apiPass',
            'playSMS-apiPass',
            'Nieprawidłowe hasło do API. Spróbuj ponownie.',
            'error'
        );
        
        return false;
    }
}

function playSMS_convertSMSMessage( $smsMessage, $userId, $orderId, $type ) {
    if ( $type === 'order' ) {
        
        $orderData = get_post_meta( $orderId );
        
        if (!empty($orderData)){
            $customerFirstname = esc_html($orderData['_billing_first_name'][0]);
            $customerLastname = esc_html($orderData['_billing_last_name'][0]);
            $customerStreetname = esc_html($orderData['_billing_address_1'][0]);
            $customerCityPostcode = esc_html($orderData['_billing_postcode'][0]);
            $customerCity  = esc_html($orderData['_billing_city'][0]);
            $orderTotal = (float)$orderData['_order_total'][0];
            $orderNumber = (int)$orderId;
            
            if (!empty($customerFirstname)){
                $convertedMessage = str_replace( "{{IMIE}}", $customerFirstname, $smsMessage );
            }
            
            if (!empty($customerLastname)){
                $convertedMessage = str_replace( "{{NAZWISKO}}", $customerLastname, $convertedMessage );
            }
            
            if (!empty($customerStreetname)){
                $convertedMessage = str_replace( "{{ULICA}}", $customerStreetname, $convertedMessage );
            }
            
            if (!empty($customerCityPostcode)){
                $convertedMessage = str_replace( "{{KOD}}", $customerCityPostcode, $convertedMessage );
            }
            
            if (!empty($customerCity)){
                $convertedMessage = str_replace( "{{MIEJSCOWOSC}}", $customerCity, $convertedMessage );
            }
            
            if (!empty($orderNumber)){
                $convertedMessage = str_replace( "{{NR_ZAMOWIENIA}}", $orderNumber, $convertedMessage );
            }
            
            if (!empty($orderTotal)){
                $convertedMessage = str_replace( "{{WARTOSC_ZAMOWIENIA}}", $orderTotal, $convertedMessage );
            }
            
        }else{
            return false;
        }
        
    } else {
        
        $userdata = get_user_meta( $userId );
        
        if (!empty($userdata)){
            $customerFirstname = esc_html($userdata['billing_first_name'][0]);
            $customerLastname = esc_html($userdata['billing_last_name'][0]);
            $customerStreetname = esc_html($userdata['billing_address_1'][0]);
            $customerCityPostcode = esc_html($userdata['billing_postcode'][0]);
            $customerCity = esc_html($userdata['billing_city'][0]);
            
            if (!empty($customerFirstname)){
                $convertedMessage = str_replace( "{{IMIE}}", $customerFirstname, $smsMessage );
            }
            
            if (!empty($customerLastname)){
                $convertedMessage = str_replace( "{{NAZWISKO}}", $customerLastname, $convertedMessage );
            }
            
            if (!empty($customerStreetname)){
                $convertedMessage = str_replace( "{{ULICA}}", $customerStreetname, $convertedMessage );
            }
            
            if (!empty($customerCityPostcode)){
                $convertedMessage = str_replace( "{{KOD}}", $customerCityPostcode, $convertedMessage );
            }
            
            if (!empty($customerCity)){
                $convertedMessage = str_replace( "{{MIEJSCOWOSC}}", $customerCity, $convertedMessage );
            }
        
        }else{
            return false;
        }
    }

    return $convertedMessage;
}

function playSMS_getStringBetween( $string, $start, $end ) {
    $string = ' ' . $string;
    $ini = strpos( $string, $start );
    if ( $ini == 0 ) {
        return '';
    }
    $ini += strlen( $start );
    $len = strpos( $string, $end, $ini ) - $ini;

    return substr( $string, $ini, $len );
}
