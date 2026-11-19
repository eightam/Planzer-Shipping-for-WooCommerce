<?php

/**
 * Fork updater (eightam): updates come from GitHub releases of the fork, never from wordpress.org.
 *
 * The `Update URI` header in wc-planzer-shipping.php makes WordPress core (5.8+) leave this plugin
 * out of the wordpress.org update request and ask the `update_plugins_github.com` filter instead.
 * The filter below answers with the latest release of PLANZER_UPDATE_REPO that carries a
 * `wc-planzer-shipping.zip` asset (built by .github/workflows/release.yml).
 *
 * Kept in its own file and outside src/ so upstream merges rarely touch it.
 */

defined('ABSPATH') || exit;

if (! defined('PLANZER_UPDATE_REPO')) {
    define('PLANZER_UPDATE_REPO', 'eightam/Planzer-Shipping-for-WooCommerce');
}

if (! function_exists('planzerFetchLatestRelease')) {
    /**
     * @return array{version: string, package: string, url: string}|null
     */
    function planzerFetchLatestRelease(): ?array
    {
        $cacheKey = 'planzer_fork_latest_release';
        $forceCheck = is_admin() && isset($_GET['force-check']);
        $cached = get_site_transient($cacheKey);

        if (! $forceCheck && is_array($cached)) {
            return $cached['release'];
        }

        $response = wp_remote_get('https://api.github.com/repos/' . PLANZER_UPDATE_REPO . '/releases/latest', [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'planzer-fork-updater',
            ],
        ]);

        $release = null;

        if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);

            foreach ((array) ($data['assets'] ?? []) as $asset) {
                if (($asset['name'] ?? '') === 'wc-planzer-shipping.zip') {
                    $release = [
                        'version' => ltrim((string) $data['tag_name'], 'vV'),
                        'package' => (string) $asset['browser_download_url'],
                        'url' => (string) $data['html_url'],
                    ];
                    break;
                }
            }
        }

        // Cache failures for 1 h, successes for 6 h (GitHub allows 60 unauthenticated requests/h per IP).
        set_site_transient($cacheKey, ['release' => $release], $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);

        return $release;
    }
}

add_filter('update_plugins_github.com', function ($update, array $pluginData, string $pluginFile) {
    if ($pluginFile !== plugin_basename(PLANZER_PLUGIN_FILE)) {
        return $update;
    }

    $release = planzerFetchLatestRelease();

    if (! $release) {
        return $update;
    }

    return [
        'id' => $pluginData['UpdateURI'],
        'slug' => dirname($pluginFile),
        'version' => $release['version'],
        'url' => $release['url'],
        'package' => $release['package'],
    ];
}, 10, 3);

// Belt and braces: never send this plugin to the wordpress.org update check, so a wordpress.org
// release with the same slug can't win over the fork (core skips the filter above if it does).
add_filter('http_request_args', function (array $args, string $url) {
    if (strpos($url, '://api.wordpress.org/plugins/update-check/') === false || empty($args['body']['plugins'])) {
        return $args;
    }

    $plugins = json_decode($args['body']['plugins'], true);
    $file = plugin_basename(PLANZER_PLUGIN_FILE);

    if (! is_array($plugins)) {
        return $args;
    }

    unset($plugins['plugins'][$file]);
    $plugins['active'] = array_values(array_diff((array) ($plugins['active'] ?? []), [$file]));
    $args['body']['plugins'] = wp_json_encode($plugins);

    return $args;
}, 10, 2);

add_action('upgrader_process_complete', function ($upgrader, array $options) {
    if (($options['type'] ?? '') === 'plugin') {
        delete_site_transient('planzer_fork_latest_release');
    }
}, 10, 2);
