<?php
// Updated code for managing PRAME and LEWE audiograms

// Function to display admin profile forms for audiogram
function displayAdminProfile() {
    global $audiogram_prawe, $audiogram_lewe;
    
    // Accessing new separate forms for PRAWE and LEWE
    echo '<input type="text" name="portalsluchu_admin_hz_{hz}_prawe_od"/>';
    echo '<input type="text" name="portalsluchu_admin_hz_{hz}_prawe_do"/>';
    echo '<input type="text" name="portalsluchu_admin_hz_{hz}_lewe_od"/>';
    echo '<input type="text" name="portalsluchu_admin_hz_{hz}_lewe_do"/>';
}

// Adjusting save profile fields to remove unused assignments
function portalsluchu_moj_audiogram_save_profile_fields() {
    // Previous assignments removed
    
    // Logic for saving profile fields
}

// Validate form submissions for audiograms
function portalsluchu_moj_audiogram_render_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if both PRAWE and LEWE are empty
        if (empty($_POST['portalsluchu_admin_hz_{hz}_prawe_od']) && empty($_POST['portalsluchu_admin_hz_{hz}_lewe_od'])) {
            echo 'Error: Please select a service or upload an image.';
            return;
        }
        
        // Logic to handle form saving
    }
}
?>