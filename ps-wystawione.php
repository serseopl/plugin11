<?php
/*
Plugin Name: Portalsluchu - My Account Extras 2
Description: Dodaje endpoint "wystawione" do My Account i wyświetla listę produktów użytkownika.
Version: 0.1
Author: Portalsluchu
*/

// 1) zarejestruj endpoint
add_action('init', function() {
    add_rewrite_endpoint('wystawione', EP_PAGES);
});

// 2) dodaj pozycję do menu My Account (po 'orders')
add_filter('woocommerce_account_menu_items', function($items){
    $new = array();
    foreach($items as $key => $label) {
        $new[$key] = $label;
        if($key === 'orders') {
            $new['wystawione'] = 'Wystawione';
        }
    }
    return $new;
}, 40);

// 3) treść endpointu: lista produktów danego użytkownika z prostym oznaczeniem statusu
add_action('woocommerce_account_wystawione_endpoint', function(){
    $user_id = get_current_user_id();
    if(!$user_id){
        echo '<p>Musisz być zalogowany, aby zobaczyć swoje wystawione ogłoszenia.</p>';
        return;
    }

    $args = array(
        'post_type' => 'product',
        'author' => $user_id,
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    );

    $products = get_posts($args);

    if(empty($products)){
        echo '<p>Nie masz jeszcze wystawionych produktów.</p>';
        return;
    }

    echo '<ul class="ps-wystawione-list">';
    foreach($products as $p){
        $status = $p->post_status; // 'publish', 'draft', 'pending' itd.
        $status_label = 'Oczekuje na akceptację';
        if($status === 'publish') $status_label = 'Zatwierdzony';
        if($status === 'private') $status_label = 'Ukryty';
        $edit_link = get_edit_post_link($p->ID);
        $view_link = get_permalink($p->ID);
        echo '<li>';
        echo '<strong><a href="'.esc_url($view_link).'">'.esc_html(get_the_title($p)).'</a></strong> — ';
        echo esc_html($status_label);
        if($edit_link) {
            echo ' (<a href="'.esc_url($edit_link).'">Edytuj</a>)';
        }
        echo '</li>';
    }
    echo '</ul>';
});