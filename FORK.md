# eightam fork

Fork of [Webwirkung/Planzer-Shipping-for-WooCommerce](https://github.com/Webwirkung/Planzer-Shipping-for-WooCommerce).
Sites running this fork update from this repo's GitHub releases only, never from wordpress.org.

## How updates work

- `Update URI` in `wc-planzer-shipping.php` makes WordPress (5.8+) skip wordpress.org for this plugin.
- `inc/updater.php` answers the update check with the latest release that has a `wc-planzer-shipping.zip` asset.
  It also removes the plugin from the wordpress.org request in case that site still offers an update.
- `.github/workflows/release.yml` builds that ZIP (composer `--no-dev` + mix build) when a `v*` tag is pushed.

## Versioning

Upstream version plus a fourth segment: `1.0.36.1`, `1.0.36.2`, ... After merging upstream `1.0.37`, use `1.0.37.1`.
The tag must equal the header `Version` with a `v` prefix, otherwise the workflow fails.

## Release

1. Bump `Version:` in `wc-planzer-shipping.php`, merge to `master`.
2. `git tag v<version> && git push origin v<version>`
3. Sites see the update within 12 h, or immediately via Dashboard → Updates → "Check again".

## Syncing upstream

`git fetch https://github.com/Webwirkung/Planzer-Shipping-for-WooCommerce.git master && git merge FETCH_HEAD`
Expect a conflict on the plugin header (`Version`, `Update URI`). Keep `Update URI` and the four-part version.

## Install requirement

The plugin folder on the site must be `wc-planzer-shipping/`. A site installed from a GitHub source ZIP
(folder `Planzer-Shipping-for-WooCommerce-master/`) also lacks `vendor/` and `dist/`: reinstall from a release ZIP.
