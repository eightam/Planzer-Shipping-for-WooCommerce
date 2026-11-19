<?php

/**
 * Fork data fixes (eightam). Each fix runs once per site, in wp-admin, and records itself in an option.
 *
 * Kept in its own file so upstream merges rarely touch it.
 */

defined('ABSPATH') || exit;

/*
 * Before 1.0.36 the plugin registered `wc-planzer-transmit` too late (or not at all) on many requests.
 * WooCommerce only adds the `wc-` prefix when the status is registered on the request that saves the
 * order, so those orders were stored as `planzer-transmit`. The order list counts and filters by the
 * prefixed key, so they never appear under the "Transmitted to Planzer" filter. This rewrites the key.
 */
add_action('admin_init', function () {
    if (get_option('planzer_fork_migration_status_prefix') || ! current_user_can('manage_woocommerce')) {
        return;
    }

    global $wpdb;

    $fixed = 0;
    $tables = [
        "{$wpdb->prefix}wc_orders" => 'status',
        $wpdb->posts => 'post_status',
        "{$wpdb->prefix}wc_order_stats" => 'status',
    ];

    foreach ($tables as $table => $column) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            continue;
        }

        $typeFilter = $table === $wpdb->posts ? " AND post_type = 'shop_order'" : '';
        $typeFilter = $table === "{$wpdb->prefix}wc_orders" ? " AND type = 'shop_order'" : $typeFilter;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names are fixed above.
        $result = $wpdb->query("UPDATE {$table} SET {$column} = 'wc-planzer-transmit' WHERE {$column} = 'planzer-transmit'{$typeFilter}");
        $fixed += is_int($result) ? $result : 0;
    }

    if (class_exists(\Automattic\WooCommerce\Caches\OrderCountCache::class)) {
        (new \Automattic\WooCommerce\Caches\OrderCountCache())->flush('shop_order');
    }
    wp_cache_delete(_count_posts_cache_key('shop_order'), 'counts');
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('orders');
    }

    update_option('planzer_fork_migration_status_prefix', gmdate('c') . " rows={$fixed}", false);
});
