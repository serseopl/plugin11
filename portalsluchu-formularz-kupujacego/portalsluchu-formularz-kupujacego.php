<?php
// Updated function to remove dojazd fee calculation block and maintain existing session data
function portalsluchu_kup_add_fees() {
    // Remove dojazd fee calculation
    // Existing fees calculation logic for courier, pakiet, warranty remains intact
    // Ensure session data for delivery_method=dojazd continues to store:
    // delivery_price=0, delivery_label='', delivery_postcode=''
    if(isset($_SESSION['delivery_method']) && $_SESSION['delivery_method'] == 'dojazd') {
        $_SESSION['delivery_price'] = 0;
        $_SESSION['delivery_label'] = '';
        $_SESSION['delivery_postcode'] = '';
    }
    // ... other calculations
}
?>