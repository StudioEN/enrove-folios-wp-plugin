<?php
require_once dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/wp-load.php';

$post_id = 58; // From user's URL
$post = get_post($post_id);

echo "Post found: " . ($post ? 'yes' : 'no') . "\n";
if ($post) {
    echo "Post Type: " . $post->post_type . "\n";
    $folio_id = get_post_meta($post->ID, 'folio_id', true);
    echo "Folio ID meta: " . var_export($folio_id, true) . "\n";
    
    if ($folio_id) {
        $folio_post = get_post($folio_id);
        echo "Folio Post found: " . ($folio_post ? 'yes' : 'no') . "\n";
        if ($folio_post) {
            echo "Folio Title: " . $folio_post->post_title . "\n";
        }
    }
}
