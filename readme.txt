=== Docs to WordPress ===
Contributors: wpdavis
Donate link: http://wpdavis.com/
Tags: docs,google,google docs,syndication
Requires at least: 3.0
Tested up to: 3.2
Stable tag: 0.1-beta

Easily move posts from Google Docs to WordPress

== Description ==
This plugin will grab docs out of a collection in Google Docs, create or update a post in WordPress and then move the doc to a new collection. Google Docs no longer supports xmlrpc, so this is perhaps the easiest way to move content from your Google Docs account to your self-hosted WordPress install.

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. If you wish, activate the extender plugin that removes formatting from Google Docs and removes comments, placing them in a separate meta box
4. Create a file to run cron against, and put the following code in it:

<?php
include('./wp-load.php');
$docs_to_wp = new Docs_To_WP();
$gdClient = $docs_to_wp->docs_to_wp_init( 'example@gmail.com', 'password' );
$docs_to_wp->retrieve_docs_for_web( $gdClient, Source folder ID, Destination folder ID );

== Changelog ==

= 1.0 =
* A change since the previous version.
* Another change.

= 0.5 =
* List versions from most recent at top to oldest at bottom.

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.
