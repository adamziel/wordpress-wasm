---
title: Changelog
slug: /changelog
---

# Changelog

All notable changes to this project are documented in this file by a CI job
that runs on every NPM release. The file follows the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
format.

## [v1.0.11] (2024-11-11) 




## [v1.0.10] (2024-11-08) 

### Enhancements

- [CLI] Set debug constants during boot. ([#1983](https://github.com/WordPress/wordpress-playground/pull/1983))

### Bug Fixes

- [CLI] Restore the "login" argument handler. ([#1985](https://github.com/WordPress/wordpress-playground/pull/1985))

### Contributors

The following contributors merged PRs in this release:

@bgrgicak


## [v1.0.9] (2024-11-04) 

### Enhancements

- [Data Liberation] Fork humanmade/WordPress-Importer. ([#1968](https://github.com/WordPress/wordpress-playground/pull/1968))
- [Data Liberation] Merge both XML processors into a single WP_XML_Processor. ([#1960](https://github.com/WordPress/wordpress-playground/pull/1960))
- [Data liberation] Add blueprints-library as a submodule. ([#1967](https://github.com/WordPress/wordpress-playground/pull/1967))

### Tools


#### Import/Export

- [Data Liberation] WP_WXR_Reader. ([#1972](https://github.com/WordPress/wordpress-playground/pull/1972))

### Documentation

- Rewrite clone examples to use HTTPS instead of SSH. ([#1963](https://github.com/WordPress/wordpress-playground/pull/1963))

### Website

- Consistent width of settings, logs, and blueprint gallery sidebars. ([#1964](https://github.com/WordPress/wordpress-playground/pull/1964))

### Bug Fixes

- Fix: Import & Export from Github causes reloading the playground even before accept this step. ([#1908](https://github.com/WordPress/wordpress-playground/pull/1908))
- [WordPress build] Only build the latest patch version of WordPress. ([#1955](https://github.com/WordPress/wordpress-playground/pull/1955))

### Contributors

The following contributors merged PRs in this release:

@adamziel @ajotka @bgrgicak


## [v1.0.8] (2024-10-30) 

### Enhancements

- [Data liberation] wp_rewrite_urls(). ([#1893](https://github.com/WordPress/wordpress-playground/pull/1893))

### PHP WebAssembly

- [PHP.wasm for Node] Fix php.js import path in the published npm package. ([#1958](https://github.com/WordPress/wordpress-playground/pull/1958))

### Website

- Restore .d.ts files missing from the published @wp-playground/remote npm package. ([#1949](https://github.com/WordPress/wordpress-playground/pull/1949))

### Various

- [Data Liberation] Add XML API, Stream API, WXR URL Rewriter API. ([#1952](https://github.com/WordPress/wordpress-playground/pull/1952))

### Contributors

The following contributors merged PRs in this release:

@adamziel @psrpinto


## [v1.0.7] (2024-10-28)

## [v1.0.6] (2024-10-28)

### Website

-   Query API: Preserve multiple ?plugin= query params. ([#1947](https://github.com/WordPress/wordpress-playground/pull/1947))
-   [Remote] Enable releasing @wp-playground/remote by making it public. ([#1948](https://github.com/WordPress/wordpress-playground/pull/1948))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak

## [v1.0.5] (2024-10-25)

### Enhancements

-   [CORS Proxy] Rate-limits IPv6 requests based on /64 subnets, not specific addresses. ([#1923](https://github.com/WordPress/wordpress-playground/pull/1923))

### Blueprints

-   Reload after autologin to set login cookies during boot. ([#1914](https://github.com/WordPress/wordpress-playground/pull/1914))
-   Skip empty lines in the runSql step. ([#1939](https://github.com/WordPress/wordpress-playground/pull/1939))

### Documentation

-   Clarified wp beta to also include rc version. ([#1936](https://github.com/WordPress/wordpress-playground/pull/1936))

### PHP WebAssembly

-   Enable CURL in Playground Web. ([#1935](https://github.com/WordPress/wordpress-playground/pull/1935))
-   PHP: Implement TLS 1.2 to decrypt https:// and ssl:// traffic and translate it into fetch(). ([#1926](https://github.com/WordPress/wordpress-playground/pull/1926))

### Website

-   Hide Settings menu after clicking "Restore from .zip. ([#1904](https://github.com/WordPress/wordpress-playground/pull/1904))
-   Publish @wp-playground/remote (types only). ([#1924](https://github.com/WordPress/wordpress-playground/pull/1924))

### Bug Fixes

-   CORS Proxy: Index update_at column because it is used for lookup. ([#1931](https://github.com/WordPress/wordpress-playground/pull/1931))
-   CORS Proxy: Reject targeting self. ([#1932](https://github.com/WordPress/wordpress-playground/pull/1932))
-   Docs: Fix typo. ([#1934](https://github.com/WordPress/wordpress-playground/pull/1934))
-   Explicitly request no-cache to discourage WP Cloud from edge caching CORS proxy results. ([#1930](https://github.com/WordPress/wordpress-playground/pull/1930))
-   Remove test code added in #1914. ([#1928](https://github.com/WordPress/wordpress-playground/pull/1928))

### Contributors

The following contributors merged PRs in this release:

@adamziel @ajotka @bgrgicak @bph @brandonpayton @ockham @psrpinto

## [v1.0.4] (2024-10-21)

### Enhancements

-   Support CORS proxy rate-limiting. ([#1879](https://github.com/WordPress/wordpress-playground/pull/1879))

### Blueprints

-   Allow multisites to load wp-admin pages with the landingPage attribute. ([#1913](https://github.com/WordPress/wordpress-playground/pull/1913))

### Tools

#### GitHub integration

-   Blueprints: Use `?` instead of `/` to CORS Proxy URLs. ([#1899](https://github.com/WordPress/wordpress-playground/pull/1899))

#### Import/Export

-   Kickoff Data Liberation: Let's Build WordPress-first Data Migration Tools. ([#1888](https://github.com/WordPress/wordpress-playground/pull/1888))

### Experiments

#### File Synchronization

-   [Remote] Preserve PHP constants when saving a temporary site. ([#1911](https://github.com/WordPress/wordpress-playground/pull/1911))

### Website

-   Do not display "You have no Playgrounds" message before loading the site. ([#1912](https://github.com/WordPress/wordpress-playground/pull/1912))
-   Fix build error that only appeared during deployment. ([#1896](https://github.com/WordPress/wordpress-playground/pull/1896))
-   Fix use of secrets on WP Cloud site. ([#1909](https://github.com/WordPress/wordpress-playground/pull/1909))
-   Maintain Query API parameters on temporary Playground settings update. ([#1910](https://github.com/WordPress/wordpress-playground/pull/1910))
-   Stop adding all CORS proxy files to website build. ([#1895](https://github.com/WordPress/wordpress-playground/pull/1895))
-   Stop responding with default MIME type. ([#1897](https://github.com/WordPress/wordpress-playground/pull/1897))
-   Stop short-circuiting web host PHP execution. ([#1898](https://github.com/WordPress/wordpress-playground/pull/1898))
-   Fix progress reporting during Playground load. ([#1915](https://github.com/WordPress/wordpress-playground/pull/1915))
-   Include CORS proxy with website builds. ([#1880](https://github.com/WordPress/wordpress-playground/pull/1880))
-   [Blueprints] Stop escaping landingPage URLs when loading WP Admin. ([#1891](https://github.com/WordPress/wordpress-playground/pull/1891))

### Bug Fixes

#### Boot Flow

-   Prefer pretty permalinks like WP install does. ([#1832](https://github.com/WordPress/wordpress-playground/pull/1832))

### Various

-   Improve self-host documentation. ([#1884](https://github.com/WordPress/wordpress-playground/pull/1884))

### Contributors

The following contributors merged PRs in this release:

@adamziel @ashfame @bgrgicak @brandonpayton

## [v1.0.3] (2024-10-14)

### Enhancements

-   Website: Remove unused React components. ([#1887](https://github.com/WordPress/wordpress-playground/pull/1887))

### Tools

#### Blueprints Builder

-   Blueprints sidebar section for single-click Playground presets. ([#1759](https://github.com/WordPress/wordpress-playground/pull/1759))

### Website

-   Replace 100vh with 100dvh to fix an "unscrollable" state on mobile devices. ([#1883](https://github.com/WordPress/wordpress-playground/pull/1883))
-   Use modal for Site settings form on mobile – mobile Safari l…. ([#1885](https://github.com/WordPress/wordpress-playground/pull/1885))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v1.0.2] (2024-10-09)

### PHP WebAssembly

-   PHP.wasm: Load correct php.wasm paths in the built Node.js packages. ([#1877](https://github.com/WordPress/wordpress-playground/pull/1877))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v1.0.1] (2024-10-09)

## [v1.0.0] (2024-10-09)

### Blueprints

-   Directory Resources. ([#1793](https://github.com/WordPress/wordpress-playground/pull/1793))
-   Login step – handle passwordless autologin via a PHP mu-plugin. ([#1856](https://github.com/WordPress/wordpress-playground/pull/1856))

### Tools

#### Blueprints

-   GitDirectoryResource. ([#1858](https://github.com/WordPress/wordpress-playground/pull/1858))
-   GitDirectoryResource: Accept "/", "", "." as root paths. ([#1860](https://github.com/WordPress/wordpress-playground/pull/1860))

#### GitHub integration

-   Native git support: LsRefs(), sparseCheckout(), GitPathControl. ([#1764](https://github.com/WordPress/wordpress-playground/pull/1764))

#### Website

-   Add core-pr and gutenberg-pr Query API parameters. ([#1761](https://github.com/WordPress/wordpress-playground/pull/1761))

### PHP WebAssembly

-   Refreshless website deployments – load remote.html using the network-first strategy. ([#1849](https://github.com/WordPress/wordpress-playground/pull/1849))
-   JSPI: Pass all unit tests, remove stale PHP builds. ([#1876](https://github.com/WordPress/wordpress-playground/pull/1876))
-   [Remote] Remove the "light" PHP.wasm bundle and only ship the "kitchen-sink" build. ([#1861](https://github.com/WordPress/wordpress-playground/pull/1861))

### Website

-   Restore the single-click "Edit Settings" flow. ([#1854](https://github.com/WordPress/wordpress-playground/pull/1854))
-   Restrict CORS proxy to requests from Playground website origin. ([#1865](https://github.com/WordPress/wordpress-playground/pull/1865))

#### Documentation

-   Add /release redirect to WP beta/RC blueprint. ([#1866](https://github.com/WordPress/wordpress-playground/pull/1866))

### Internal

-   Make isomorphic-git submodule use https, not ssh. ([#1863](https://github.com/WordPress/wordpress-playground/pull/1863))

### Bug Fixes

-   [CLI] Fix `isWordPressInstalled()` in CLI by inlining the auto_login.php in index.ts instead of using import ?raw. ([#1869](https://github.com/WordPress/wordpress-playground/pull/1869))

### Various

-   Add documentation around the GPL license and implications for contribution. ([#1776](https://github.com/WordPress/wordpress-playground/pull/1776))
-   Allow installing Plugins/Themes into an arbitrary folder. ([#1803](https://github.com/WordPress/wordpress-playground/pull/1803))
-   Improve documentation. ([#1862](https://github.com/WordPress/wordpress-playground/pull/1862))
-   [Website] Fix "undefined" as className. ([#1870](https://github.com/WordPress/wordpress-playground/pull/1870))

### Contributors

The following contributors merged PRs in this release:

@adamziel @ajotka @bgrgicak @brandonpayton @dd32 @n8finch

## [v0.9.46] (2024-10-07)

### Enhancements

-   Webapp upgrade protocol: Disable HTTP caching and reload other browser tabs to prevent fatal errors after new deployments. ([#1822](https://github.com/WordPress/wordpress-playground/pull/1822))

### Documentation

-   Docs: Disable localeDropdown until more pages are translated. ([#1824](https://github.com/WordPress/wordpress-playground/pull/1824))
-   Docs: Review playground documentation translations page. ([#1826](https://github.com/WordPress/wordpress-playground/pull/1826))
-   Docs: Playground PR previews through GitHub actions. ([#1825](https://github.com/WordPress/wordpress-playground/pull/1825))

### Website

-   Use site slug as a stable scope. ([#1839](https://github.com/WordPress/wordpress-playground/pull/1839))
-   Close Playground Manager by default. ([#1831](https://github.com/WordPress/wordpress-playground/pull/1831))
-   Fix go-to-site menu items to reveal site view. ([#1833](https://github.com/WordPress/wordpress-playground/pull/1833))

### Various

-   Add Install instructions to the Playwright README. ([#1837](https://github.com/WordPress/wordpress-playground/pull/1837))
-   Resolve end-to-end failures. ([#1844](https://github.com/WordPress/wordpress-playground/pull/1844))

### Contributors

The following contributors merged PRs in this release:

@adamziel @akirk @brandonpayton @juanmaguitar

## [v0.9.45] (2024-09-30)

### Blueprints

-   Translate GitHub.com file URLs into CORS-accessible raw.githubusercontent.com. ([#1810](https://github.com/WordPress/wordpress-playground/pull/1810))

### Tools

-   [UX] Stored Playgrounds (no more data loss), multiple Playgrounds, UI WebApp Redesign. ([#1731](https://github.com/WordPress/wordpress-playground/pull/1731))

### Documentation

-   Docs: Translation i18n messages - JSON files. ([#1807](https://github.com/WordPress/wordpress-playground/pull/1807))

### Website

-   Prevent creation of two temporary sites. ([#1817](https://github.com/WordPress/wordpress-playground/pull/1817))
-   Stop address bar from adding trailing slash to query params. ([#1820](https://github.com/WordPress/wordpress-playground/pull/1820))

### Bug Fixes

-   Fix broken Playwright tests. ([#1819](https://github.com/WordPress/wordpress-playground/pull/1819))

### Various

-   Add Playwright tests for UI redesign changes. ([#1769](https://github.com/WordPress/wordpress-playground/pull/1769))
-   Docs: Contributions to translations. ([#1808](https://github.com/WordPress/wordpress-playground/pull/1808))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @juanmaguitar

## [v0.9.44] (2024-09-24)

### Bug Fixes

-   Docs: Fix links intro fr. ([#1795](https://github.com/WordPress/wordpress-playground/pull/1795))

### Various

-   Add missing functions required to succesfully connect with MySQL DB. ([#1752](https://github.com/WordPress/wordpress-playground/pull/1752))

### Contributors

The following contributors merged PRs in this release:

@jeroenpf @juanmaguitar

## [v0.9.43] (2024-09-23)

### Documentation

-   Docs: Better paths for links. ([#1765](https://github.com/WordPress/wordpress-playground/pull/1765))
-   Docs: I18n setup. ([#1766](https://github.com/WordPress/wordpress-playground/pull/1766))
-   Docs: Remove the outdated "data rependencies" page. ([#1785](https://github.com/WordPress/wordpress-playground/pull/1785))

### Website

-   Fix troubleshoot-and-debug link. ([#1782](https://github.com/WordPress/wordpress-playground/pull/1782))

### Various

-   Update link for contributor day. ([#1775](https://github.com/WordPress/wordpress-playground/pull/1775))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @juanmaguitar @n8finch

## [v0.9.42] (2024-09-17)

### PHP WebAssembly

-   FS: Use the correct rm/rmdir method when moving files between mounts. ([#1770](https://github.com/WordPress/wordpress-playground/pull/1770))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.41] (2024-09-16)

## [v0.9.40] (2024-09-16)

### Enhancements

-   Extend allowable resources available via WordPress/WordPress. ([#1721](https://github.com/WordPress/wordpress-playground/pull/1721))

### Tools

-   Update actions/upload-artifact version to 4. ([#1748](https://github.com/WordPress/wordpress-playground/pull/1748))

### Documentation

-   Docs/Blueprints resources: Grammar and typo fixes. ([#1741](https://github.com/WordPress/wordpress-playground/pull/1741))

### PHP WebAssembly

-   @php-wasm/universal : Add Phar support in php-wasm. ([#1716](https://github.com/WordPress/wordpress-playground/pull/1716))

### Website

-   Add the `components` package with PathMappingControl. ([#1608](https://github.com/WordPress/wordpress-playground/pull/1608))

### Bug Fixes

-   Fix CLI --skipWordPressSetup option. ([#1760](https://github.com/WordPress/wordpress-playground/pull/1760))

### Reliability

-   Improve Playground CLI logging and fix quiet mode. ([#1751](https://github.com/WordPress/wordpress-playground/pull/1751))

### Various

-   Docs/Guides: Guides introductions and some minor adjustments. ([#1754](https://github.com/WordPress/wordpress-playground/pull/1754))
-   Docs/Guides: Normalized and fixed guides links. ([#1756](https://github.com/WordPress/wordpress-playground/pull/1756))
-   Docs/Guides: Providing content for your demo. ([#1747](https://github.com/WordPress/wordpress-playground/pull/1747))
-   Docs/Guides: WordPress Playground for plugin developers. ([#1750](https://github.com/WordPress/wordpress-playground/pull/1750))
-   Docs/Guides: WordPress Playground for theme developers. ([#1732](https://github.com/WordPress/wordpress-playground/pull/1732))
-   Docs: Links redirections. ([#1758](https://github.com/WordPress/wordpress-playground/pull/1758))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @juanmaguitar @mho22 @peterwilsoncc

## [v0.9.39] (2024-09-09)

### Bug Fixes

-   Use the correct imports in the generated .d.ts files. ([#1742](https://github.com/WordPress/wordpress-playground/pull/1742))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.38] (2024-09-09)

### Internal

-   Fix changelog updates for latest doc structure. ([#1734](https://github.com/WordPress/wordpress-playground/pull/1734))

### Various

-   Add support for handling symlinks in the request handler. ([#1724](https://github.com/WordPress/wordpress-playground/pull/1724))
-   Docs: Playground block in launch section. ([#1722](https://github.com/WordPress/wordpress-playground/pull/1722))
-   documentation - Blueprints - Resources: Highlight installPlugin and installTheme steps and most common resouces for them. ([#1733](https://github.com/WordPress/wordpress-playground/pull/1733))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton @jeroenpf @juanmaguitar

## [v0.9.37] (2024-09-05)

### PHP WebAssembly

-   Add more asyncify-listed PHP functions to fix Studio crash. ([#1729](https://github.com/WordPress/wordpress-playground/pull/1729))

### Website

-   Add initial site info view. ([#1701](https://github.com/WordPress/wordpress-playground/pull/1701))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.36] (2024-09-03)

### Bug Fixes

-   Fix logger test for inconsistent short month. ([#1727](https://github.com/WordPress/wordpress-playground/pull/1727))

### Reliability

-   Avoid errors due to log message formatting. ([#1726](https://github.com/WordPress/wordpress-playground/pull/1726))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.35] (2024-08-29)

### Enhancements

-   Allow specifying a WordPres/WordPress branch and pulling from GitHub. ([#1705](https://github.com/WordPress/wordpress-playground/pull/1705))

### PHP WebAssembly

-   Fix: Exit http server on php exit. ([#1714](https://github.com/WordPress/wordpress-playground/pull/1714))

### Various

-   Enable networking for WordPress and Gutenberg PR viewers. ([#1715](https://github.com/WordPress/wordpress-playground/pull/1715))

### Contributors

The following contributors merged PRs in this release:

@ironprogrammer @kozer @peterwilsoncc

## [v0.9.34] (2024-08-28)

### PHP WebAssembly

-   @php-wasm/node: Publish index.d.ts. ([#1713](https://github.com/WordPress/wordpress-playground/pull/1713))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.33] (2024-08-28)

### Internal

-   @php-wasm/util: Publish TypeScript types. ([#1711](https://github.com/WordPress/wordpress-playground/pull/1711))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.32] (2024-08-26)

### Website

-   Make the site switcher work again. ([#1698](https://github.com/WordPress/wordpress-playground/pull/1698))
-   WebApp Redesign: Interact with sites list via redux. ([#1679](https://github.com/WordPress/wordpress-playground/pull/1679))
-   deployment: Fix builder redirect. ([#1696](https://github.com/WordPress/wordpress-playground/pull/1696))

### Bug Fixes

-   Docs: Fix typo and replace en dash with hyphen for consistency. ([#1702](https://github.com/WordPress/wordpress-playground/pull/1702))
-   Fix broken documentation links. ([#1694](https://github.com/WordPress/wordpress-playground/pull/1694))
-   Remove Playground branding from site list. ([#1700](https://github.com/WordPress/wordpress-playground/pull/1700))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton @eliot-akira @mirka

## [v0.9.31] (2024-08-20)

### Enhancements

#### Boot Flow

-   A boot() method to explicitly initialize the PHP worker. ([#1669](https://github.com/WordPress/wordpress-playground/pull/1669))

### Website

-   Fix builder redirect. ([#1693](https://github.com/WordPress/wordpress-playground/pull/1693))

### Internal

-   Avoid GH board automation permissions error. ([#1691](https://github.com/WordPress/wordpress-playground/pull/1691))
-   Refresh sqlite-database-integration from develop branch. ([#1692](https://github.com/WordPress/wordpress-playground/pull/1692))

### Bug Fixes

#### Boot Flow

-   Fix sqlite-database-integration rename fatal. ([#1695](https://github.com/WordPress/wordpress-playground/pull/1695))

#### Documentation

-   Docs: Fix links to proper pages. ([#1690](https://github.com/WordPress/wordpress-playground/pull/1690))

### Various

-   Documentation structure overhaul. ([#1602](https://github.com/WordPress/wordpress-playground/pull/1602))

### Contributors

The following contributors merged PRs in this release:

@adamziel @brandonpayton @juanmaguitar

## [v0.9.30] (2024-08-19)

### Website

-   Ask users to report errors if Playground load fails. ([#1686](https://github.com/WordPress/wordpress-playground/pull/1686))

### Bug Fixes

-   Avoid Blueprint schema formatting changes by build. ([#1685](https://github.com/WordPress/wordpress-playground/pull/1685))

### Various

-   [Website] Improves the messaging around exporting a zip if needed, when connecting to GitHub. ([#1689](https://github.com/WordPress/wordpress-playground/pull/1689))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton @jonathanbossenger

## [v0.9.29] (2024-08-12)

### Tools

-   Add max-len rule. ([#1613](https://github.com/WordPress/wordpress-playground/pull/1613))

### Experiments

#### GitHub integration

-   Add site manager view and sidebar. ([#1661](https://github.com/WordPress/wordpress-playground/pull/1661))
-   Add sites from the site manager. ([#1680](https://github.com/WordPress/wordpress-playground/pull/1680))

### PHP WebAssembly

-   Offline mode end-to-end tests. ([#1648](https://github.com/WordPress/wordpress-playground/pull/1648))

### Website

-   Add nice redirects for the new documentation site. ([#1681](https://github.com/WordPress/wordpress-playground/pull/1681))
-   Fix site manager button styles. ([#1676](https://github.com/WordPress/wordpress-playground/pull/1676))

### Bug Fixes

-   Revert "Offline mode end-to-end tests". ([#1673](https://github.com/WordPress/wordpress-playground/pull/1673))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak

## [v0.9.28] (2024-08-05)

### Blueprints

-   Add support for loading wpCli without running blueprint steps. ([#1629](https://github.com/WordPress/wordpress-playground/pull/1629))

### Documentation

-   Blueprints: Add resetData step to documentation. ([#1658](https://github.com/WordPress/wordpress-playground/pull/1658))
-   Docs: Redirect from /docs to https://wordpress.github.io/wordpress-playground. ([#1671](https://github.com/WordPress/wordpress-playground/pull/1671))

### Website

-   Suppress unavoidable Deprecated notices - Networking. ([#1660](https://github.com/WordPress/wordpress-playground/pull/1660))
-   UI: Explain the Logs modal. ([#1666](https://github.com/WordPress/wordpress-playground/pull/1666))

#### Blueprints

-   Precompile Ajv Blueprint validator to avoid CSP issues. ([#1649](https://github.com/WordPress/wordpress-playground/pull/1649))

### Internal

-   Reinstantiate Changelog generation in GitHub CI. ([#1657](https://github.com/WordPress/wordpress-playground/pull/1657))

### Various

-   Rollback artifact creation to enable downloading a pre-built package …. ([#1624](https://github.com/WordPress/wordpress-playground/pull/1624))
-   Update WordPress packages. ([#1672](https://github.com/WordPress/wordpress-playground/pull/1672))
-   Update `ws` package version to fix DOS vulnerability. ([#1635](https://github.com/WordPress/wordpress-playground/pull/1635))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @PiotrPress

## [v0.9.27] (2024-07-29)

### Enhancements

-   Support offline mode after the first Playground page load. ([#1643](https://github.com/WordPress/wordpress-playground/pull/1643))

### Devrel

-   Remove puzzle app package. ([#1642](https://github.com/WordPress/wordpress-playground/pull/1642))

### PHP WebAssembly

-   Cache Playground assets to enable offline support. ([#1535](https://github.com/WordPress/wordpress-playground/pull/1535))
-   Rotate PHP runtime after runtime crash. ([#1628](https://github.com/WordPress/wordpress-playground/pull/1628))
-   Throw error when PHP run() receives no code to run. ([#1646](https://github.com/WordPress/wordpress-playground/pull/1646))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton

## [v0.9.26] (2024-07-22)

### Blueprints

-   Add missing blueprints library dep. ([#1640](https://github.com/WordPress/wordpress-playground/pull/1640))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.25] (2024-07-22)

### Tools

-   Make sure NPM packages declare dependencies. ([#1639](https://github.com/WordPress/wordpress-playground/pull/1639))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.24] (2024-07-22)

### Bug Fixes

-   Fix plugin-proxy response codes. ([#1636](https://github.com/WordPress/wordpress-playground/pull/1636))
-   Stop publishing @wp-playground/wordpress-builds package. ([#1637](https://github.com/WordPress/wordpress-playground/pull/1637))

### Contributors

The following contributors merged PRs in this release:

@bgrgicak @brandonpayton

## [v0.9.23] (2024-07-22)

### PHP WebAssembly

-   Route requests more like a normal web server. ([#1539](https://github.com/WordPress/wordpress-playground/pull/1539))

### Website

-   Remove old, unused website deployment workflow. ([#1633](https://github.com/WordPress/wordpress-playground/pull/1633))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.22] (2024-07-19)

### Bug Fixes

-   Remove WP 6.2 support after WP 6.6 release. ([#1632](https://github.com/WordPress/wordpress-playground/pull/1632))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.9.21] (2024-07-19)

### Website

-   Fix manifest.json URLs. ([#1615](https://github.com/WordPress/wordpress-playground/pull/1615))

### Internal

-   Fix joinPaths root edge case. ([#1620](https://github.com/WordPress/wordpress-playground/pull/1620))

### Various

-   Disable PHP 7.0 and 7.1 version switcher end-to-end tests. ([#1626](https://github.com/WordPress/wordpress-playground/pull/1626))

### Contributors

The following contributors merged PRs in this release:

@bgrgicak @brandonpayton

## [v0.9.20] (2024-07-16)

### Enhancements

#### Boot Flow

-   Backfill the assets removed from minified WordPress bundles. ([#1604](https://github.com/WordPress/wordpress-playground/pull/1604))
-   Register service worker before spawning the worker thread. ([#1606](https://github.com/WordPress/wordpress-playground/pull/1606))

### Website

-   Disable website features that don't work while offline. ([#1607](https://github.com/WordPress/wordpress-playground/pull/1607))
-   Generate a list of assets to cache for offline support. ([#1573](https://github.com/WordPress/wordpress-playground/pull/1573))

### Internal

-   Build: Ship the default TypeScript .d.ts declaration files, not rollups. ([#1593](https://github.com/WordPress/wordpress-playground/pull/1593))

### Bug Fixes

#### Boot Flow

-   Fix recursive calls to backfillStaticFilesRemovedFromMinifiedBuild. ([#1614](https://github.com/WordPress/wordpress-playground/pull/1614))

### Various

-   Add/allow import site gutenberg pr. ([#1610](https://github.com/WordPress/wordpress-playground/pull/1610))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @smithjw1

## [v0.9.19] (2024-07-15)

### **Breaking Changes**

-   Set web worker startup options with messages instead of query strings. ([#1574](https://github.com/WordPress/wordpress-playground/pull/1574))

### Blueprints

-   Add an Import Theme Starter Content step. ([#1521](https://github.com/WordPress/wordpress-playground/pull/1521))
-   Add setSiteLanguage step to change the language. ([#1538](https://github.com/WordPress/wordpress-playground/pull/1538))
-   Mark shorthand properties as stable, not deprecated. ([#1594](https://github.com/WordPress/wordpress-playground/pull/1594))

### Documentation

-   Add Blueprint 101 to Documentation. ([#1556](https://github.com/WordPress/wordpress-playground/pull/1556))

### PHP WebAssembly

#### Website

-   Download all WordPress assets on boot. ([#1532](https://github.com/WordPress/wordpress-playground/pull/1532))

### Website

-   PHP CORS Proxy. ([#1546](https://github.com/WordPress/wordpress-playground/pull/1546))

### Various

-   Revert "Set web worker startup options with messages instead of query strings". ([#1605](https://github.com/WordPress/wordpress-playground/pull/1605))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @bph @dd32

## [v0.9.18] (2024-07-09)

### Website

-   Remove the unused isSupportedWordPressVersion export. ([#1592](https://github.com/WordPress/wordpress-playground/pull/1592))

### Internal

-   Build: Polyfill \_\_dirname in php-wam/node ESM via banner option. ([#1591](https://github.com/WordPress/wordpress-playground/pull/1591))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.16] (2024-07-09)

### Internal

-   Build: Source external deps from package.json. ([#1590](https://github.com/WordPress/wordpress-playground/pull/1590))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.15] (2024-07-09)

### Internal

-   Build: Use regular expressions to mark packages as external. ([#1589](https://github.com/WordPress/wordpress-playground/pull/1589))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.14] (2024-07-09)

### Devrel

-   Remove Puzzle app from the Playground website. ([#1588](https://github.com/WordPress/wordpress-playground/pull/1588))

### Internal

-   Vite build: Mark all imported modules as external to avoid bundling them with released packages. ([#1586](https://github.com/WordPress/wordpress-playground/pull/1586))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak

## [v0.9.13] (2024-07-08)

### PHP WebAssembly

-   php-wasm/node: Ship as ESM and CJS. ([#1585](https://github.com/WordPress/wordpress-playground/pull/1585))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.12] (2024-07-08)

## [v0.9.11] (2024-07-08)

### PHP WebAssembly

-   Build: Treat all dependencies of php-wasm/node as external. ([#1584](https://github.com/WordPress/wordpress-playground/pull/1584))

### Various

-   Autopublish npm packages every week. ([#1542](https://github.com/WordPress/wordpress-playground/pull/1542))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.10] (2024-07-08)

### Internal

-   Revert "Use NPM for publishing packages instead of Lerna ". ([#1582](https://github.com/WordPress/wordpress-playground/pull/1582))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.9] (2024-07-08)

### Internal

-   Use NPM for publishing packages instead of Lerna. ([#1581](https://github.com/WordPress/wordpress-playground/pull/1581))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.9.4] (2024-07-03)

### Documentation

-   Update the Blueprint data format doc. ([#1510](https://github.com/WordPress/wordpress-playground/pull/1510))

### Contributors

The following contributors merged PRs in this release:

@ndiego

## [v0.9.3] (2024-07-03)

### Tools

#### Blueprints

-   Importing regression fix – support old exported Playground ZIPs. ([#1569](https://github.com/WordPress/wordpress-playground/pull/1569))

### Documentation

-   Add GitHub development instructions. ([#1551](https://github.com/WordPress/wordpress-playground/pull/1551))

### Internal

-   Meta: GitHub Boards Automation. ([#1549](https://github.com/WordPress/wordpress-playground/pull/1549))
-   Meta: GitHub-sourced Mindmap. ([#1559](https://github.com/WordPress/wordpress-playground/pull/1559))

###

-   Add cache version number. ([#1541](https://github.com/WordPress/wordpress-playground/pull/1541))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak

## [v0.9.1] (2024-06-26)

### PHP WebAssembly

-   Networking access: Fix wp_http_supports() to work without the kitchen-sink extension bundle. ([#1504](https://github.com/WordPress/wordpress-playground/pull/1504))
-   Networking: Remove CORS workarounds for WordPress.org API. ([#1511](https://github.com/WordPress/wordpress-playground/pull/1511))
-   Backfill remote asset listing when needed. ([#1531](https://github.com/WordPress/wordpress-playground/pull/1531))

### Website

-   Remove "small window mode". ([#1540](https://github.com/WordPress/wordpress-playground/pull/1540))
-   Detect actual, loaded WP version. ([#1503](https://github.com/WordPress/wordpress-playground/pull/1503))

### Various

-   Remove deprecation note from shorthand steps. ([#1507](https://github.com/WordPress/wordpress-playground/pull/1507))
-   Remove trailing semicolon from example URL for loading playground with network access. ([#1520](https://github.com/WordPress/wordpress-playground/pull/1520))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bph @brandonpayton @dd32 @oskosk

## [v0.7.20] (2024-05-21)

### **Breaking Changes**

-   [Breaking] Refactor PHP.ini management, remove php.setPhpIniPath() and php.setPhpIniEntry(). ([#1423](https://github.com/WordPress/wordpress-playground/pull/1423))

### Enhancements

-   CLI: Distinguish between mount and mountBeforeInstall options. ([#1410](https://github.com/WordPress/wordpress-playground/pull/1410))
-   CLI: Support fetching WordPress zips from custom URLs. ([#1415](https://github.com/WordPress/wordpress-playground/pull/1415))
-   Introduce a new @wp-playground/common package to avoid circular depencies. ([#1387](https://github.com/WordPress/wordpress-playground/pull/1387))
-   Website: Ship the SQLite database integration plugin. ([#1418](https://github.com/WordPress/wordpress-playground/pull/1418))

#### Boot Flow

-   Playground CLI: Don't create /wordpress/wp-config.php on boot. ([#1407](https://github.com/WordPress/wordpress-playground/pull/1407))

### Blueprints

-   Define constants in auto_prepend_file, silence warnings related to redefining those constants. ([#1400](https://github.com/WordPress/wordpress-playground/pull/1400))
-   Detect silent failures when activating plugins and theme. ([#1436](https://github.com/WordPress/wordpress-playground/pull/1436))
-   Re-activate single-file plugins when enabling a multisite. ([#1435](https://github.com/WordPress/wordpress-playground/pull/1435))
-   Throw an error when activating a theme or plugin that doesn't exist. ([#1391](https://github.com/WordPress/wordpress-playground/pull/1391))
-   Write sunrise.php to /internal in enableMultisite step. ([#1401](https://github.com/WordPress/wordpress-playground/pull/1401))

### Tools

-   Add VSCode branch protection. ([#1408](https://github.com/WordPress/wordpress-playground/pull/1408))
-   Show error log if Playground fails to start. ([#1336](https://github.com/WordPress/wordpress-playground/pull/1336))

#### Blueprints

-   Unzip: Only delete a temporary zip file after unzipping, do not delete the original zip. ([#1412](https://github.com/WordPress/wordpress-playground/pull/1412))

#### GitHub integration

-   GitHub export: Create new commits in your fork when writing to the upstream repo isn't allowed. ([#1392](https://github.com/WordPress/wordpress-playground/pull/1392))

#### Import/Export

-   Support wp_crop_image in import wxr. ([#1357](https://github.com/WordPress/wordpress-playground/pull/1357))

### Devrel

-   Add puzzle API. ([#1372](https://github.com/WordPress/wordpress-playground/pull/1372))

### Documentation

-   Docs: Use step function names instead of TypeScript type names. ([#1373](https://github.com/WordPress/wordpress-playground/pull/1373))
-   Updated the GitHub issue link to open in a new tab. ([#1353](https://github.com/WordPress/wordpress-playground/pull/1353))
-   Use step id name. ([#1377](https://github.com/WordPress/wordpress-playground/pull/1377))

### Experiments

-   Explore: Setup SQLite database integration without creating wp-content/db.php. ([#1382](https://github.com/WordPress/wordpress-playground/pull/1382))

### PHP WebAssembly

-   Add shareable extension-to-MIME-type mapping. ([#1355](https://github.com/WordPress/wordpress-playground/pull/1355))
-   Document php ini functions. ([#1430](https://github.com/WordPress/wordpress-playground/pull/1430))
-   JSPI: Enable the origin trial on Chrome. ([#1346](https://github.com/WordPress/wordpress-playground/pull/1346))
-   PHP: Add libjpeg and libwebp support. ([#1393](https://github.com/WordPress/wordpress-playground/pull/1393))
-   PHP: Always set the auto_prepend_file php.ini entry, even when the auto_prepend_file.php file exists. ([#1388](https://github.com/WordPress/wordpress-playground/pull/1388))
-   PHP: Move internal shared directories to /internal/shared. ([#1386](https://github.com/WordPress/wordpress-playground/pull/1386))
-   PHP: Remove mentions of a custom PHP extension. ([#1422](https://github.com/WordPress/wordpress-playground/pull/1422))
-   PHP: Remove the MODE_EVAL_CODE execution mode. ([#1433](https://github.com/WordPress/wordpress-playground/pull/1433))
-   PHP: Support php.mv() between devices via recursive copy. ([#1411](https://github.com/WordPress/wordpress-playground/pull/1411))
-   PHP: Use /internal/shared/php.ini by default. ([#1419](https://github.com/WordPress/wordpress-playground/pull/1419))
-   PHP: Use auto_prepend_file to preload mu-plugins (instead of creating them in wp-content/mu-plugins). ([#1366](https://github.com/WordPress/wordpress-playground/pull/1366))

### Website

-   Improve log modal styles, a11y, error message wording. ([#1369](https://github.com/WordPress/wordpress-playground/pull/1369))
-   Move puzzle app to a Playground package. ([#1385](https://github.com/WordPress/wordpress-playground/pull/1385))
-   Add secrets on-demand for more endpoints. ([#1362](https://github.com/WordPress/wordpress-playground/pull/1362))
-   Boot: Move WordPress zip extraction logic to a common unzipWordPress() utility. ([#1427](https://github.com/WordPress/wordpress-playground/pull/1427))
-   Derive MIME types for PHP served files from shared JSON. ([#1360](https://github.com/WordPress/wordpress-playground/pull/1360))
-   Fix constant names for GH export oauth. ([#1378](https://github.com/WordPress/wordpress-playground/pull/1378))
-   Playground Boot: Align the boot process between remote.html and CLI. ([#1389](https://github.com/WordPress/wordpress-playground/pull/1389))
-   Remote.html: Install WordPress if it isn't installed yet. ([#1425](https://github.com/WordPress/wordpress-playground/pull/1425))
-   Remote.html: Preload the SQLite database plugin, but only execute it if there's no custom db.php inside wp-content. ([#1424](https://github.com/WordPress/wordpress-playground/pull/1424))
-   Simplify website deployment workflows. ([#1404](https://github.com/WordPress/wordpress-playground/pull/1404))
-   Update rsync command to clean up more completely. ([#1361](https://github.com/WordPress/wordpress-playground/pull/1361))

#### Blueprints

-   Provide non-gzipped wp-cli.phar file with website build. ([#1406](https://github.com/WordPress/wordpress-playground/pull/1406))
-   Simplify runPhpWithZipFunctions() setup. ([#1434](https://github.com/WordPress/wordpress-playground/pull/1434))

### Internal

-   Fix changelog automation. ([#1413](https://github.com/WordPress/wordpress-playground/pull/1413))

### Bug Fixes

-   Add name to Puzzle package. ([#1443](https://github.com/WordPress/wordpress-playground/pull/1443))
-   Fixed images not loading on the page. ([#1352](https://github.com/WordPress/wordpress-playground/pull/1352))
-   Restore nightly wordpress build. ([#1437](https://github.com/WordPress/wordpress-playground/pull/1437))

### Reliability

-   Disable console logging when running tests. ([#1368](https://github.com/WordPress/wordpress-playground/pull/1368))

###

-   Lint: Disable console warnings for paths where they're not useful. ([#1421](https://github.com/WordPress/wordpress-playground/pull/1421))

### Various

-   Add links to kitchen sink (PHP extensions), networking. ([#1363](https://github.com/WordPress/wordpress-playground/pull/1363))
-   Reorganize and update documentation. ([#1354](https://github.com/WordPress/wordpress-playground/pull/1354))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @flexseth @ironnysh @josevarghese

## [v0.7.15] (2024-04-30)

### Website

-   Avoid edge-caching conditionally redirected resources. ([#1351](https://github.com/WordPress/wordpress-playground/pull/1351))
-   Fix deploy-time check for file with PHP-handled redirect. ([#1350](https://github.com/WordPress/wordpress-playground/pull/1350))

### Contributors

The following contributors merged PRs in this release:

@brandonpayton

## [v0.7.10] (2024-04-30)

### PHP WebAssembly

-   PHP.wasm Node: Revert a part of #1289, do not import a .wasm file. ([#1348](https://github.com/WordPress/wordpress-playground/pull/1348))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.7.5] (2024-04-30)

### Internal

-   Meta: Move the minified WordPress to the new `@wp-playground/wordpress-builds` package. ([#1343](https://github.com/WordPress/wordpress-playground/pull/1343))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.7.3] (2024-04-29)

### PHP WebAssembly

-   Playground CLI. ([#1289](https://github.com/WordPress/wordpress-playground/pull/1289))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.7.2] (2024-04-29)

### **Breaking Changes**

-   PHP: Remove setSapiName, setPhpIniEntry, setPhpIniPath methods from the remote PHP API client. ([#1321](https://github.com/WordPress/wordpress-playground/pull/1321))
-   Remove the wp-playground/node package. ([#1323](https://github.com/WordPress/wordpress-playground/pull/1323))

#### PHP WebAssembly

-   Breaking: Loopback Request Support. ([#1287](https://github.com/WordPress/wordpress-playground/pull/1287))

### Tools

-   Centralize log storage. ([#1315](https://github.com/WordPress/wordpress-playground/pull/1315))

### Documentation

-   Link to Installing Nx Globally in the README. ([#1325](https://github.com/WordPress/wordpress-playground/pull/1325))

### PHP WebAssembly

-   Add PHPResponse.forHttpCode() shorthand. ([#1322](https://github.com/WordPress/wordpress-playground/pull/1322))
-   Asyncify: List ZEND_FETCH_OBJ_R_SPEC_CV_CV_HANDLER. ([#1342](https://github.com/WordPress/wordpress-playground/pull/1342))
-   Curl extension for the Node.js build of PHP.wasm. ([#1273](https://github.com/WordPress/wordpress-playground/pull/1273))
-   Explore curl support. ([#1133](https://github.com/WordPress/wordpress-playground/pull/1133))
-   PHP Process Manager. ([#1301](https://github.com/WordPress/wordpress-playground/pull/1301))
-   PHPProcessManager: Clear nextInstance when the concurrency limit is exhausted. ([#1324](https://github.com/WordPress/wordpress-playground/pull/1324))
-   Spawn handler: Wrap the program call with try/catch, exit gracefully on error. ([#1320](https://github.com/WordPress/wordpress-playground/pull/1320))

### Website

-   Add initial workflow for deploying the website to WP Cloud. ([#1293](https://github.com/WordPress/wordpress-playground/pull/1293))
-   Eliminate 404s due to nested files-to-serve-via-php dir. ([#1333](https://github.com/WordPress/wordpress-playground/pull/1333))
-   Stop WP rewrite rules from matching files like wp-admin.css. ([#1317](https://github.com/WordPress/wordpress-playground/pull/1317))
-   Stop using PHP to serve most static files on WP Cloud. ([#1331](https://github.com/WordPress/wordpress-playground/pull/1331))
-   WP Cloud: Relay secrets for error logger. ([#1337](https://github.com/WordPress/wordpress-playground/pull/1337))

#### Documentation

-   Document WP Cloud website setup. ([#1338](https://github.com/WordPress/wordpress-playground/pull/1338))

### Reliability

-   Add log methods, log handlers, and separate log collection. ([#1264](https://github.com/WordPress/wordpress-playground/pull/1264))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @juanmaguitar @mho22

## [v0.7.1] (2024-04-24)

## [v0.7.0] (2024-04-24)

### **Breaking Changes**

#### PHP WebAssembly

-   Breaking: Remove PHPBrowser. ([#1302](https://github.com/WordPress/wordpress-playground/pull/1302))

### Enhancements

-   Bump TypeScript to 5.4.5. ([#1299](https://github.com/WordPress/wordpress-playground/pull/1299))
-   Semaphore: Add timeout option. ([#1300](https://github.com/WordPress/wordpress-playground/pull/1300))

### Blueprints

-   Builder: Fix stuck loader bar. ([#1284](https://github.com/WordPress/wordpress-playground/pull/1284))
-   Remove setPhpIniEntry step. ([#1288](https://github.com/WordPress/wordpress-playground/pull/1288))

### Tools

#### GitHub integration

-   GitHub: Don't delete all the files when exporting a theme. ([#1308](https://github.com/WordPress/wordpress-playground/pull/1308))
-   Urlencode branch name. ([#1275](https://github.com/WordPress/wordpress-playground/pull/1275))

#### Blueprints

-   Blueprints builder: Support ?blueprint-url. ([#1309](https://github.com/WordPress/wordpress-playground/pull/1309))

### Documentation

-   Use new learning resources in Playground documentation. ([#1276](https://github.com/WordPress/wordpress-playground/pull/1276))

### PHP WebAssembly

-   Browser: Remove setSpawnHandler function from the public API. ([#1303](https://github.com/WordPress/wordpress-playground/pull/1303))
-   PHP: Add a cwd argument to hotSwapPHPRuntime(). ([#1304](https://github.com/WordPress/wordpress-playground/pull/1304))
-   PHP: Remove addServerGlobalEntry() method, accept $\_SERVER as php.run() property. ([#1286](https://github.com/WordPress/wordpress-playground/pull/1286))
-   PHPRequestHandler: Add a generic PHP argument. ([#1310](https://github.com/WordPress/wordpress-playground/pull/1310))
-   nit: Clean up after node PHP popen() test. ([#1280](https://github.com/WordPress/wordpress-playground/pull/1280))

### Website

-   Add more info to crash reports. ([#1253](https://github.com/WordPress/wordpress-playground/pull/1253))
-   Memoize fetch() responses when requesting php.wasm. ([#1306](https://github.com/WordPress/wordpress-playground/pull/1306))
-   Progress monitoring: Use a custom instantiateWasm handler to avoid monkey-patching WebAssembly.instantiateStreaming. ([#1305](https://github.com/WordPress/wordpress-playground/pull/1305))
-   Remove sandbox attribute from iframe. ([#1313](https://github.com/WordPress/wordpress-playground/pull/1313))
-   Service Worker: Fetch credentialless to play more nicely with server caches (#1311). ([#1311](https://github.com/WordPress/wordpress-playground/pull/1311))

### Internal

-   Automate Changelog generation after each npm release. ([#1312](https://github.com/WordPress/wordpress-playground/pull/1312))
-   CI: Fix intermittent documentation build failures. ([#1307](https://github.com/WordPress/wordpress-playground/pull/1307))

### Bug Fixes

-   Add styles to ensure `iframes` are responsive. ([#1267](https://github.com/WordPress/wordpress-playground/pull/1267))
-   Docs: Fix the Blueprint example of the Gutenberg PR preview. ([#1268](https://github.com/WordPress/wordpress-playground/pull/1268))
-   Docs: Move Steps Shorthands to a separate page to fix Steps TOC. ([#1265](https://github.com/WordPress/wordpress-playground/pull/1265))

### Reliability

-   Add network error message. ([#1281](https://github.com/WordPress/wordpress-playground/pull/1281))
-   Explore logging to a file. ([#1292](https://github.com/WordPress/wordpress-playground/pull/1292))

### Various

-   Add PDF to infer mime type list. ([#1298](https://github.com/WordPress/wordpress-playground/pull/1298))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @ironnysh @peeranat-dan

## [v0.6.16] (2024-04-17)

### Blueprints

-   Replace set_current_user call with wp_set_current_user to fix a PHP notice. ([#1262](https://github.com/WordPress/playground/pull/1262))

### Tools

-   Install themes and plugins using the ReadableStream API. ([#919](https://github.com/WordPress/playground/pull/919))

### Documentation

-   Docs: Update WordPress versions used in the documentation, document using older releases. ([#1235](https://github.com/WordPress/playground/pull/1235))

### PHP WebAssembly

-   Filter Requests library to use the Fetch handler. ([#1048](https://github.com/WordPress/playground/pull/1048))

-   PHP: Handle request errors in PHPRequestHandler, return response code 500. ([#1249](https://github.com/WordPress/playground/pull/1249))
-   PHP: Reset exit code before dispatching a request. ([#1251](https://github.com/WordPress/playground/pull/1251))

### Various

-   Add documentation for `shorthand` alternatives of Blueprint steps. ([#1261](https://github.com/WordPress/playground/pull/1261))

### Contributors

The following contributors merged PRs in this release:

@adamziel @dd32 @ironnysh @kozer

## [v0.6.15] (2024-04-16)

### Blueprints

-   Add ifAlreadyInstalled to installPlugin and installTheme steps. ([#1244](https://github.com/WordPress/playground/pull/1244))
-   Support a landingPage value without the initial slash. ([#1227](https://github.com/WordPress/playground/pull/1227))

### PHP WebAssembly

-   Investigate OOB: Run unit tests with instrumented PHP 8.0 code. ([#1220](https://github.com/WordPress/playground/pull/1220))
-   Unit tests: Restore site-data.spec.ts. ([#1194](https://github.com/WordPress/playground/pull/1194))
-   Web PHP: Increase memory limit to 256 M. ([#1232](https://github.com/WordPress/playground/pull/1232))

### Website

-   Browser: Display PHP output when Fatal Error is trigerred. ([#1234](https://github.com/WordPress/playground/pull/1234))
-   Fix accessibility issues found by Axe. ([#1246](https://github.com/WordPress/playground/pull/1246))
-   Request Handler: Urldecode the requested path. ([#1228](https://github.com/WordPress/playground/pull/1228))

### Bug Fixes

-   fix: Set required engine version to 18.18.0. ([#1214](https://github.com/WordPress/playground/pull/1214))

### Various

-   Blueprints/json example. ([#1188](https://github.com/WordPress/playground/pull/1188))
-   Doc: Update 01-index.md. ([#1216](https://github.com/WordPress/playground/pull/1216))
-   Move DefineSiteUrlStep doc warning so it displays in documentation. ([#1245](https://github.com/WordPress/playground/pull/1245))
-   Updated link to native WordPress importer. ([#1243](https://github.com/WordPress/playground/pull/1243))
-   documentation update proposal: Provide more info on features, extensions?. ([#1208](https://github.com/WordPress/playground/pull/1208))
-   php-wasm/node: Update express to newest version, and move it to devDependencies. ([#1218](https://github.com/WordPress/playground/pull/1218))

### Contributors

The following contributors merged PRs in this release:

@adamziel @artpi @bph @brandonpayton @eliot-akira @flexseth @ironnysh @kirjavascript

## [v0.6.14] (2024-04-11)

### Bug Fixes

-   Revert changes to the documentation build. ([#1226](https://github.com/WordPress/playground/pull/1226))

### Reliability

-   Update error modal description label. ([#1224](https://github.com/WordPress/playground/pull/1224))

### Various

-   Try memory leak workaround with zeroed mem. ([#1229](https://github.com/WordPress/playground/pull/1229))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton

## [v0.6.13] (2024-04-10)

### PHP WebAssembly

-   Try to repro memory out of bounds errors in CI. ([#1199](https://github.com/WordPress/playground/pull/1199))

### Bug Fixes

-   Fix docs-site build. ([#1222](https://github.com/WordPress/playground/pull/1222))

### Contributors

The following contributors merged PRs in this release:

@bgrgicak @brandonpayton

## [v0.6.11] (2024-04-09)

### Tools

-   Avoid Service Worker update issues on localhost. ([#1209](https://github.com/WordPress/playground/pull/1209))

#### Import/Export

-   importWxr: Preserve backslashes in the imported content. ([#1213](https://github.com/WordPress/playground/pull/1213))

### PHP WebAssembly

-   Catch DNS errors to avoid unhandled exceptions. ([#1215](https://github.com/WordPress/playground/pull/1215))

-   Revert "Avoid partial munmap memory leak". ([#1195](https://github.com/WordPress/playground/pull/1195))
-   Try to repro memory out of bounds errors in CI. ([#1198](https://github.com/WordPress/playground/pull/1198))

### Various

-   Adjust link to LICENSE file. ([#1210](https://github.com/WordPress/playground/pull/1210))
-   Try to reproduce the memory access error with files from 096a017. ([#1212](https://github.com/WordPress/playground/pull/1212))

### Contributors

The following contributors merged PRs in this release:

@adamziel @brandonpayton @emmanuel-ferdman @fluiddot

## [v0.6.10] (2024-04-04)

### Blueprints

-   Rename importFile to importWxr, switch to humanmade/WordPress importer. ([#1192](https://github.com/WordPress/playground/pull/1192))

### Tools

#### Blueprints

-   Explorations: Stream API. ([#851](https://github.com/WordPress/playground/pull/851))

### PHP WebAssembly

-   Avoid partial munmap memory leak. ([#1189](https://github.com/WordPress/playground/pull/1189))

### Website

-   Make kitchen sink extension bundle the default. ([#1191](https://github.com/WordPress/playground/pull/1191))

### Bug Fixes

-   Fix cross-device mv by switching to copy. ([#846](https://github.com/WordPress/playground/pull/846))

### Contributors

The following contributors merged PRs in this release:

@adamziel @brandonpayton @seanmorris

## [v0.6.9] (2024-04-03)

### Tools

-   Devex: Expose window.playground for quick testing and debugging. ([#1125](https://github.com/WordPress/playground/pull/1125))

#### GitHub integration

-   Website: Query API options to preconfigure the GitHub export form. ([#1174](https://github.com/WordPress/playground/pull/1174))

### Documentation

-   Update the wp-cli step code example. ([#1140](https://github.com/WordPress/playground/pull/1140))

### PHP WebAssembly

-   Add PHP iterator and yield support. ([#1181](https://github.com/WordPress/playground/pull/1181))
-   Fix fileinfo support. ([#1179](https://github.com/WordPress/playground/pull/1179))
-   Fix mbregex support. ([#1155](https://github.com/WordPress/playground/pull/1155))
-   PHP.run(): Throw JS exception on runtime error, remove throwOnError flag. ([#1137](https://github.com/WordPress/playground/pull/1137))

### Website

-   Add error report modal. ([#1102](https://github.com/WordPress/playground/pull/1102))
-   Ensure PromiseRejectionEvent has reason before logging it. ([#1150](https://github.com/WordPress/playground/pull/1150))
-   Request handler: Remove everything after # from the URL. ([#1126](https://github.com/WordPress/playground/pull/1126))
-   Web: Make the "Apply changes" button work in Playground settings form. ([#1122](https://github.com/WordPress/playground/pull/1122))

#### Plugin proxy

-   Allow requests to WordPress.org. ([#1154](https://github.com/WordPress/playground/pull/1154))

### Internal

-   Refresh WordPress with the latest SQLite integration plugin. ([#1151](https://github.com/WordPress/playground/pull/1151))

### Bug Fixes

-   Fix typo in blueprints/public/schema-readme.md. ([#1134](https://github.com/WordPress/playground/pull/1134))
-   Priority: Fix broken link to VS Code extension. ([#1141](https://github.com/WordPress/playground/pull/1141))

### Various

-   Docs/update - Add implied step. ([#1144](https://github.com/WordPress/playground/pull/1144))
-   Give brandonpayton permission to run Playground GH workflows. ([#1139](https://github.com/WordPress/playground/pull/1139))
-   Logger API: Add rate limiting. ([#1142](https://github.com/WordPress/playground/pull/1142))
-   Remove `--disable-all` configuration option in PHP compile process. ([#1132](https://github.com/WordPress/playground/pull/1132))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @brandonpayton @flexseth @jblz @mho22

## [v0.6.8] (2024-03-21)

### Blueprints

-   Allow optional metadata. ([#1103](https://github.com/WordPress/playground/pull/1103))

### Tools

-   Add VSCode Chrome debugging support. ([#1088](https://github.com/WordPress/playground/pull/1088))
-   Website: Support Base64-encoding Blueprints passed in the URL. ([#1091](https://github.com/WordPress/playground/pull/1091))

### Documentation

-   Docs: Expand Details section. ([#1109](https://github.com/WordPress/playground/pull/1109))
-   Update activate-theme.ts to use `themeFolderName`. ([#1119](https://github.com/WordPress/playground/pull/1119))

### PHP WebAssembly

-   Blueprints: Explore switching to the PHP implementation. ([#1051](https://github.com/WordPress/playground/pull/1051))
-   Explore weird register_shutdown_function behavior. ([#1099](https://github.com/WordPress/playground/pull/1099))
-   Fix post_message_to_js memory out of bounds. ([#1114](https://github.com/WordPress/playground/pull/1114))
-   Fix shutdown errors. ([#1104](https://github.com/WordPress/playground/pull/1104))
-   Fixing build regression [BISON COMPILE]. ([#871](https://github.com/WordPress/playground/pull/871))
-   PHP : Set appropriate SCRIPT variables in $\_SERVER superglobal. ([#1092](https://github.com/WordPress/playground/pull/1092))

### Website

-   Add logger API. ([#1113](https://github.com/WordPress/playground/pull/1113))
-   Add multisite rewrite rules. ([#1083](https://github.com/WordPress/playground/pull/1083))
-   Service worker: Improve error reporting in non-secure contexts. ([#1098](https://github.com/WordPress/playground/pull/1098))

### Bug Fixes

-   Fix experimental notice in FF ESR. ([#1117](https://github.com/WordPress/playground/pull/1117))
-   Fix php bison dep for building on non-arm64 architectures. ([#1115](https://github.com/WordPress/playground/pull/1115))

### Reliability

-   Add fatal errror listener. ([#1095](https://github.com/WordPress/playground/pull/1095))

### Various

-   Update examples and demos in the documentation. ([#1107](https://github.com/WordPress/playground/pull/1107))

### Contributors

The following contributors merged PRs in this release:

@0aveRyan @adamziel @bgrgicak @brandonpayton @ironnysh @mho22 @seanmorris @StevenDufresne

## [v0.6.7] (2024-03-06)

### Website

-   Node polyfills: Only apply them in Node.js, not in web browsers. ([#1089](https://github.com/WordPress/playground/pull/1089))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.6.6] (2024-03-06)

### Website

-   Comlink API: Pass the context argument to windowEndpoint, not wrap. ([#1087](https://github.com/WordPress/playground/pull/1087))
-   Fix: Playground not starting due to a race condition. ([#1084](https://github.com/WordPress/playground/pull/1084))
-   Hide the "This is experimental WordPress" notice on click. ([#1082](https://github.com/WordPress/playground/pull/1082))
-   Set the API context when using Comlink.wrap(). ([#1085](https://github.com/WordPress/playground/pull/1085))

### Contributors

The following contributors merged PRs in this release:

@adamziel

## [v0.6.5] (2024-03-05)

### Tools

#### Plugin proxy

-   Add Sensei to the allowed repositories for plugin proxy. ([#1079](https://github.com/WordPress/playground/pull/1079))

#### Blueprints

-   Snapshot Import Protocol v1. ([#1007](https://github.com/WordPress/playground/pull/1007))

### Internal

-   Build the php-wasm/util package as both ESM and CJS. ([#1081](https://github.com/WordPress/playground/pull/1081))

### Reliability

#### Blueprints

-   Add unit tests to the mkdir step. ([#1029](https://github.com/WordPress/playground/pull/1029))

### Various

-   Website query API: Continue plugin installs on error. ([#605](https://github.com/WordPress/playground/pull/605))

### Contributors

The following contributors merged PRs in this release:

@adamziel @eliot-akira @reimic @renatho

## [v0.6.4] (2024-03-04)

### Enhancements

-   Add logging support to Playground. ([#1035](https://github.com/WordPress/playground/pull/1035))

### Blueprints

-   PHP Blueprints: Display progress. ([#1077](https://github.com/WordPress/playground/pull/1077))
-   Set progress caption and communicate failures in the import file step. ([#1034](https://github.com/WordPress/playground/pull/1034))

### Tools

#### Blueprints

-   PHP Blueprints demo page. ([#1070](https://github.com/WordPress/playground/pull/1070))
-   PHP: Do not prepend a whitespace when encoding body as multipart form data. ([#1033](https://github.com/WordPress/playground/pull/1033))

### PHP WebAssembly

-   Fix response header escaping. ([#1050](https://github.com/WordPress/playground/pull/1050))
-   Fix: Networking broken when extra PHP extensions are enabled. ([#1045](https://github.com/WordPress/playground/pull/1045))
-   PHP.wasm: Yield 0 bytes read on fd_read failure to improve PHP's fread() and feof() behavior. ([#1053](https://github.com/WordPress/playground/pull/1053))
-   PHP: Support $env and $cwd proc_open arguments. ([#1064](https://github.com/WordPress/playground/pull/1064))
-   Parse shell commands in createSpawnHandler. ([#1065](https://github.com/WordPress/playground/pull/1065))
-   Prototype: Spawning PHP sub-processes in Web Workers. ([#1031](https://github.com/WordPress/playground/pull/1031))
-   Spawning PHP sub-processes in Web Workers. ([#1069](https://github.com/WordPress/playground/pull/1069))

### Website

-   Add Google Analytics events to Playground. ([#1040](https://github.com/WordPress/playground/pull/1040))
-   Fix error on reload site click. ([#1041](https://github.com/WordPress/playground/pull/1041))

### Internal

-   Rebuild WordPress every 20 minutes, short-circuit if no new version is found. ([#1061](https://github.com/WordPress/playground/pull/1061))
-   Rebuild WordPress within an hour of a beta release. ([#1059](https://github.com/WordPress/playground/pull/1059))

### Bug Fixes

-   Fix the login message so it doesn't override another. ([#1044](https://github.com/WordPress/playground/pull/1044))

### Various

-   Add arguments to default node spawn method. ([#1037](https://github.com/WordPress/playground/pull/1037))
-   Add bgrgicak to deployment allowlists. ([#1057](https://github.com/WordPress/playground/pull/1057))
-   Allow for CORS requests to api.wordpress.org to pass. ([#1009](https://github.com/WordPress/playground/pull/1009))
-   Default URL rewrites to /index.php. ([#1072](https://github.com/WordPress/playground/pull/1072))
-   Remove repository specific Code of Conduct. ([#1038](https://github.com/WordPress/playground/pull/1038))
-   Ship WordPress 6.5 beta 1. ([#1036](https://github.com/WordPress/playground/pull/1036))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @dd32 @desrosj @johnbillion @mho22

## [v0.6.3] (2024-02-12)

### Blueprints

-   Wp-cli step. ([#1017](https://github.com/WordPress/playground/pull/1017))

### PHP WebAssembly

-   Calls proc_open two times in a row. ([#1012](https://github.com/WordPress/playground/pull/1012))
-   Experiment: Build PHP with OPFS support. ([#1030](https://github.com/WordPress/playground/pull/1030))
-   PHP: Pass request body as UInt8Array. ([#1018](https://github.com/WordPress/playground/pull/1018))

### Contributors

The following contributors merged PRs in this release:

@adamziel @mho22

## [v0.6.2] (2024-02-08)

### PHP WebAssembly

-   Networking: Swap Requests transports using the http_api_transports instead of patching the Requests library. ([#1004](https://github.com/WordPress/playground/pull/1004))
-   Remove `crypto.randomUUID` dependency in favor of a custom function. ([#1016](https://github.com/WordPress/playground/pull/1016))
-   Remove x-request-issuer header on cross-origin requests. ([#1010](https://github.com/WordPress/playground/pull/1010))
-   Update wp_http_fetch.php. ([#1002](https://github.com/WordPress/playground/pull/1002))

### Website

-   Remote.html: Always install the playground mu-plugin. ([#1005](https://github.com/WordPress/playground/pull/1005))

### Various

-   32bit integer workaround. ([#1014](https://github.com/WordPress/playground/pull/1014))
-   Test/hello world blueprint. ([#908](https://github.com/WordPress/playground/pull/908))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bgrgicak @jdevalk @sejas @stoph

## [v0.6.1] (2024-02-05)

### Website

#### Blueprints

-   Remove the applyWordPressPatches step, enable the Site Health Plugin. ([#1001](https://github.com/WordPress/playground/pull/1001))

### Various

-   Add `crypto` to Polyfills improving Blueprint compatibility for Node. ([#1000](https://github.com/WordPress/playground/pull/1000))

### Contributors

The following contributors merged PRs in this release:

@adamziel @sejas

## [v0.6.0] (2024-02-05)

### Enhancements

-   Add wp-cli and code editor examples to the demos page. ([#965](https://github.com/WordPress/playground/pull/965))
-   WordPress: Preserve PHP attributes and wp-config.php whitespace. ([#964](https://github.com/WordPress/playground/pull/964))

### Blueprints

-   Add enableMultisite step. ([#888](https://github.com/WordPress/playground/pull/888))
-   Set_current_user to admin before activating plugins and themes. ([#984](https://github.com/WordPress/playground/pull/984))

### Tools

-   Use .zip files instead of .data files for loading WordPress. ([#978](https://github.com/WordPress/playground/pull/978))

#### Blueprints

-   Throw on failure. ([#982](https://github.com/WordPress/playground/pull/982))

#### PHP WebAssembly

-   Support wp-cli in the browser. ([#957](https://github.com/WordPress/playground/pull/957))

### PHP WebAssembly

-   Correcting OOB & Prevent Crash on Saving Large Post. ([#870](https://github.com/WordPress/playground/pull/870))
-   Memory leak: Add rotatedPHP to kill and recreate PHP instances after a certain number of requests. ([#990](https://github.com/WordPress/playground/pull/990))
-   PHP : Add args and descriptors dynamic arrays in proc open function. ([#969](https://github.com/WordPress/playground/pull/969))
-   PHP.wasm: Fix stack overflow in wasm_set_request_body. ([#993](https://github.com/WordPress/playground/pull/993))

### Website

-   Add .htaccess file to prevent caching of index.html and enable importing the client.js library. ([#989](https://github.com/WordPress/playground/pull/989))
-   Add og meta tags and meta description. ([#980](https://github.com/WordPress/playground/pull/980))
-   CORS headers for client/index.js. ([#893](https://github.com/WordPress/playground/pull/893))
-   wp-cli: Respect quotes when parsing shell commands. ([#966](https://github.com/WordPress/playground/pull/966))

### Internal

-   Remove the interactive block playground. ([#988](https://github.com/WordPress/playground/pull/988))

### Bug Fixes

-   Fix "WP-CLI" typos. ([#971](https://github.com/WordPress/playground/pull/971))
-   Fix footer styling issue in the "Code is Poetry" in wordpress.github.io/wordpress-playground. ([#959](https://github.com/WordPress/playground/pull/959))
-   WordPress build: Add newlines after PHP annotations. ([#986](https://github.com/WordPress/playground/pull/986))

### Various

-   Add a blueprint example. ([#946](https://github.com/WordPress/playground/pull/946))
-   Add terminal to playground site. ([#161](https://github.com/WordPress/playground/pull/161))
-   Match the .nvmrc node version to the changes made in commit ec2605b. ([#972](https://github.com/WordPress/playground/pull/972))
-   PHP : Dispatch available descriptor specs in js_open_process function. ([#963](https://github.com/WordPress/playground/pull/963))
-   PHP : Give access to command arguments if array type is given in php ^7.4 proc_open function. ([#944](https://github.com/WordPress/playground/pull/944))
-   Rebuild WordPress. ([#987](https://github.com/WordPress/playground/pull/987))
-   Update the networking disabled error messages in wp-admin for plugins and themes. ([#936](https://github.com/WordPress/playground/pull/936))

### Contributors

The following contributors merged PRs in this release:

@adamziel @bph @ironnysh @marcarmengou @mho22 @rowasc @seanmorris @swissspidy @tyrann0us

## [v0.5.9] - 2021-09-29

### Changed

– **Breaking:** Remoddsaved the PHPBrowser class ([##1302](https://github.com/WordPress/wordpress-playground/pull/1302))

### Added

– Added CHANGELOG.md to keep track of notable changes ([##1302](https://github.com/WordPress/wordpress-playground/pull/1302))
