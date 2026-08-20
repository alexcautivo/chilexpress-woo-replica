<?php

class Chilexpress_Woo_Oficial_Utils {
    /**
     * Set cached quote response.
     *
     * @param string $md5_payload The MD5 hash of the payload.
     * @param mixed  $response_quote The response quote to cache.
     */
    public function set_cached_cotizacion($md5_payload, $response_quote) {
        // Store the response in cache with the MD5 hash as the key
        wp_cache_set($md5_payload, $response_quote, 'chilexpress_cotizaciones', 6000); // Cache for 10 minutes
    }   

    /**
     * Get cached quote response.
     *
     * @param string $md5_payload The MD5 hash of the payload.
     * @return mixed Cached response or false if not found.
     */
    public function get_cached_cotizacion($md5_payload) {
        // Retrieve the cached response using the MD5 hash as the key
        return wp_cache_get($md5_payload, 'chilexpress_cotizaciones');
    }

    /**
     * Log messages for debugging.
     *
     * @param string $message The message to log.
     */
    public function write_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}
