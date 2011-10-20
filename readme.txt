=== Docs to WordPress ===
Contributors: wpdavis
Donate link: http://wpdavis.com/
Tags: docs,google,google docs,syndication
Requires at least: 3.0
Tested up to: 3.2
Stable tag: 0.4-beta

Easily move posts from Google Docs to WordPress

== Description ==
This plugin will grab docs out of a collection in Google Docs, create or update a post in WordPress and then move the doc to a new collection. Google Docs no longer supports xmlrpc, so this is perhaps the easiest way to move content from your Google Docs account to your self-hosted WordPress install.

You can see more details at http://dev.bangordailynews.com/2011/06/16/marrying-google-docs-and-wordpress-or-really-any-cms/

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. If you wish, activate the extender plugin that removes formatting from Google Docs and removes comments, placing them in a separate meta box
4. To run the plugin, you will need to either activate the included Cron Extender or put code in a separate file and point a cron job to it.

= To run using WP CRON =
Activate the cron extender and define, in your wp-config DOCSTOWP_USER, DOCSTOWP_PASS, DOCSTOWP_ORIGIN and DOCSTOWP_DESTINATION like so:
`define( 'DOCSTOWP_USER', 'example@gmail.com' );
define( 'DOCSTOWP_PASS', 'mypassword' );`

= To run using real cron =
Create a file to run cron against, and put the following code in it:

`<?php
include('./wp-load.php');
$docs_to_wp = new Docs_To_WP();
$gdClient = $docs_to_wp->docs_to_wp_init( 'example@gmail.com', 'password' );
$docs_to_wp->retrieve_docs_for_web( $gdClient, Source folder ID, Destination folder ID );`

You will need to have a folder to draw the docs from and an optional folder to put the docs in after they've been processed.

In docs, the ID looks a little something like this in the URL: #folders/folder.0.!--ID STARTS HERE, after the 0 and the period --!

== Changelog ==

= 0.4-beta =
Use HTTPS instead of HTTP, per new Google API spec.

Fix a few bugs with carrying over bold and italic

= 0.3-beta =
In extend-clean.php, extract the styles and apply them so bolding and italicizing goes through. Also, don't strip heading styles. Props nacin and  Rob Flaherty.

= 0.2-beta =
Added the cron extender

= 0.1-beta =
Initial release