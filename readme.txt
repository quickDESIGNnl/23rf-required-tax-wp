=== Required Taxonomies ===
Contributors: openai-assistant
Tags: taxonomy, publishing, editor, gutenberg, validation
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enforce at least one taxonomy term on publish for selected post types in both the block and classic editors.

== Description ==

Required Taxonomies lets you define a matrix of post types and taxonomies from the WordPress admin. Any combination you enable becomes mandatory when a user attempts to publish (or schedule/pending) a post, page, or custom post type. Drafts can still be saved, but publishing is blocked until all required taxonomies contain at least one term.

* Works with the Block Editor (Gutenberg) and Classic/Quick Edit.
* Compatible with JetEngine and other plugins that register public, UI-enabled post types or taxonomies.
* Highlights taxonomy panels when validation fails and displays a clear error notice.
* Includes a live-searchable settings matrix for a better admin experience.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/required-taxonomies` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings → Required Taxonomies** to configure which taxonomy columns are required per post type.

== Frequently Asked Questions ==

= Does this work with custom post types or taxonomies registered by other plugins? =

Yes. Any public post type or taxonomy that exposes a UI will appear in the configuration matrix.

= Can drafts be saved without the required terms? =

Yes. Validation only runs when a post is being published, scheduled, or moved to pending review.

== Screenshots ==

1. Configuration matrix where you can select required taxonomies for each post type.

== Changelog ==

= 1.0.0 =
* Initial release.
