# Docs to WordPress #
**Contributors:** wpdavis, anubisthejackle
  
**Donate link:** http://wpdavis.com/
  
**Tags:** docs,google,google docs,syndication
  
**Requires at least:** 3.0
  
**Tested up to:** 4.1.1
  
**Stable tag:** 1.0-beta
  

Easily move posts from Google Docs to WordPress

## Description ##
This plugin will grab docs out of a collection in Google Docs, create or update a post in WordPress and then move the doc to a new collection. Google Docs no longer supports xmlrpc, so this is perhaps the easiest way to move content from your Google Docs account to your self-hosted WordPress install.

You can see more details at http://dev.bangordailynews.com/2011/06/16/marrying-google-docs-and-wordpress-or-really-any-cms/

## Installation ##

1. Upload `docs-to-wordpress` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a [Google App](https://console.developers.google.com/project) for your website.
4. Enter Client ID and Client Secret into settings page.
5. You should be redirected to a Google login page. Grant full permissions.
6. If you wish, activate the extender plugin that removes formatting from Google Docs and removes comments, placing them in a separate meta box
7. To run the plugin, you will need to either activate the included Cron Extender or put code in a separate file and point a cron job to it.

### To run using WP CRON ###
Activate the cron extender.

### To run using real cron ###
Create a file to run cron against, and put the following code in it:

`<?php
include('./wp-load.php');
$docs_to_wp = new Docs_To_WP();
$results = $docs_to_wp->startTransfer();`

You will need to have a folder to draw the docs from and an optional folder to put the docs in after they've been processed. Take the Share link for each folder, and enter it into the settings page for Docs To WP.

## Changelog ##

### 1.0-beta ###
Update to remove deprecated Google API and use Drive API v2

Updates to code to clean it up, remove extraneous loops.

### 0.4-beta ###
Use HTTPS instead of HTTP, per new Google API spec.

Fix a few bugs with carrying over bold and italic

### 0.3-beta ###
In extend-clean.php, extract the styles and apply them so bolding and italicizing goes through. Also, don't strip heading styles. Props nacin and  Rob Flaherty.

### 0.2-beta ###
Added the cron extender

### 0.1-beta ###
Initial release
