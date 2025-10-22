<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ecv_sanitize_variation_data( $data ) {
    // Recursively sanitize array
    if ( is_array( $data ) ) {
        foreach ( $data as $k => $v ) {
            $data[$k] = ecv_sanitize_variation_data( $v );
        }
        return $data;
    }
    return sanitize_text_field( $data );
}

function ecv_get_image_url( $attachment_id ) {
    $img = wp_get_attachment_image_src( $attachment_id, 'medium' );
    return $img ? $img[0] : '';
} 