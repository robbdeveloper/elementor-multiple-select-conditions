=== Elementor Dynamic Select ===
Contributors: elementor-dynamic-select
Tags: elementor, elementor pro, forms, dynamic select, conditional fields
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a Dynamic Select field to Elementor Pro forms with options driven by JSON rules and multi-select source fields.

== Description ==

Elementor Dynamic Select registers a new Elementor Pro form field type. Paste a JSON ruleset on the field to define which options appear based on the current values of other form fields (multi-select and checkbox fields supported).

Features:

* Custom Elementor Pro form field type
* Per-field JSON rules configuration
* Reusable named option sets
* AND/OR condition groups
* Client-side instant updates
* Server-side validation against the same rules

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/elementor-multiple-select-conditions/`.
2. Activate the plugin through the Plugins screen.
3. Ensure Elementor Pro is active.
4. Edit a form, add source fields with Field IDs, then add a Dynamic Select field and paste your rules JSON.

== Frequently Asked Questions ==

= Does this work without Elementor Pro? =

No. Elementor Pro is required for the Forms module and custom field registration.

= Where do I put the JSON rules? =

In the Dynamic Select field settings inside the Elementor editor, under **Rules JSON**.

= Can I load rules from a file? =

Yes. Use **Rules JSON URL** as a fallback when inline JSON is empty.

== Changelog ==

= 1.0.0 =
* Initial release.
