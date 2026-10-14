=== WP GraphQL Redirection ===
Contributors: superhuit, kuuak
Tags: WPGraphQL, GraphQL, API, Redirection
Requires at least: 5.0
Tested up to: 6.1
Requires PHP: 7.4
Requires Plugins: redirection, wp-graphql
Stable tag: 2.0.4
license: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Exposes Redirection plugin in the GraphQL schema.


== Changelog ==

= 2.0.4 =
* Require a pinned Release Belt host key in the release pipeline.

= 2.0.3 =
* Security: only expose active redirects (enabled, in enabled groups of the WordPress module) in redirections and isRedirected. Disabled redirects and redirects in disabled or Apache/Nginx groups are no longer listed, and isRedirected ignores them.
* GraphQL lookups no longer record hits on hidden redirects.
* Fix unbounded WPGraphQL Smart Cache growth caused by redirections lookups, and skip the object cache for those queries so new redirects are picked up on the next lookup.
* Fix the plugin not loading when Redirection runs in refactor mode (Red_Item class detection was case-sensitive).
* Harden the release pipeline.

= 2.0.2 =
* Fix empty release notes and add Dependabot for dependency and security updates.

= 2.0.1 =
* Fix PHP 8.1+ deprecation notice when resolving isRedirected on a node without a link.

= 2.0.0 =
* Add regex support on redirection items.
* Improve release and changelog automation for maintainers.
* Package dependencies in release artifacts instead of committing the vendor directory.

= 1.1.0 =
* fix: 🐛 isRedirected with false positive
* chore: ➕ add "Requires Plugins" in plugin Header fields
* chore: 🚀 v1.1.1

= 1.1.0 =
* Add isRedirected to UniformResourceIdentifiable type

= 1.0.1 =
* Update Readme & Composer info to release to Packagist
* Track vendor folder

= 1.0.0 =
* Initial internal release.
