=== Better WordPress Minify ===
Contributors: OddOneOut
Donate link: http://betterwp.net/wordpress-plugins/bwp-minify/
Tags: minify, minify js, minify css, minify javascript, minify stylesheet, minification, optimization, optimize, stylesheet, css, javascript, js
Requires at least: 3.0
Tested up to: 3.9
Stable tag: 1.3.1
License: GPLv3 or later

Allows you to combine and minify your CSS and JS files to improve page load time.

== Description ==

Allows you to combine and minify your CSS and JS files to improve page load time. This plugin uses the PHP library [Minify](http://code.google.com/p/minify/) and relies on WordPress's enqueueing system rather than the output buffer, which respects the order of CSS and JS files as well as their dependencies. BWP Minify is very customizable and easy to use.

**Useful resources to help you get started and make the most out of BWP Minify**

* [Official Documentation](http://betterwp.net/wordpress-plugins/bwp-minify/#usage)
* [WordPress Minify Best Practices](http://betterwp.net/wordpress-minify-javascript-css/)

**Some Features**

* Uses enqueueing system of WordPress which improves compatibility with other plugins and themes
* Allows you to move enqueued files to desired locations (header, footer, oblivion, etc.) via a dedicated management page
* Allows you to change various Minify settings (cache directory, cache age, debug mode, etc.) directly in admin
* Allows you to use friendly Minify urls, such as `http://example.com/path/to/cache/somestring.js`
* Allows you to use CDN for minified contents, one CDN host for JS and one for CSS with SSL support
* Allows you to split long Minify strings into shorter ones
* Offers various way to add a cache buster to your minify string such as WordPress's version, Theme's version, Cache folder's last modified timestap, etc.
* Supports script localization (`wp_localize_script()`)
* Supports inline styles
* Supports RTL stylesheets
* Supports media-specific stylesheets (e.g. 'screen', 'print', etc.)
* Supports conditional stylesheets (e.g. `<!--[if lt IE 7]>`)
* Provides hooks for further customization
* WordPress Multi-site compatible

Please don't forget to rate this plugin [5 shining stars](http://wordpress.org/support/view/plugin-reviews/bwp-minify?filter=5) if you like it, thanks!

**Get in touch**

* Support is provided via [BetterWP.net Community](http://betterwp.net/community/).
* Follow and contribute to development via [Github](https://github.com/OddOneOut/Better-WordPress-Minify).
* You can also follow me on [Twitter](http://twitter.com/0dd0ne0ut).
* Check out [latest WordPress Tips and Ideas](http://feeds.feedburner.com/BetterWPnet) from BetterWP.net.

**Languages**

* English (default)
* Romanian (ro_RO) - Thanks to [Luke Tyler, International Calling Cards](www.enjoyprepaid.com)!
* Turkish (tr_TR) - Thanks to Hakan E
* French (fr_FR) - Thanks to Sebastien
* Italian (it_IT) - Thanks to Gabriele - http://cookspot.it
* Spanish (es_ES) -  Thanks to Ruben Hernandez - http://usitility.com/
* Dutch (nl_NL) - Thanks to Martijn van Egmond
* German (de_DE) - Thanks to Matthias
* Serbo-Croatian (sr_RS) - Thanks to Borisa Djuraskovic - [Web Hosting Hub](http://www.webhostinghub.com/)
* Indonesian (id_ID) - Thanks to Nasrulhaq Muiz - http://al-badar.net
* Russian (ru_RU) - Thanks to Эдуард Валеев

Please [help translate](http://betterwp.net/wordpress-tips/create-pot-file-using-poedit/) this plugin!

== Installation ==

1. Upload the `bwp-minify` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the Plugins menu in WordPress. After activation, you should see three menus of this plugin on your left.
3. Configure the plugin.
4. Make sure the `cache` folder is writable, by `CHMOD` it to either `755` or `777`, depending on which one works for you.
5. For Advanced Configuration, take a look at [BWP Minify Advanced Usage](http://betterwp.net/wordpress-plugins/bwp-minify/#advanced_usage).

**Enjoy!**

== Frequently Asked Questions ==

[Check plugin news and ask questions](http://betterwp.net/topic/bwp-minify/).

== Screenshots ==

1. Basic Functionality
2. Advanced Settings
3. Enqueued files management
4. Minify in action!

== Changelog ==

= 1.3.1 =
* **Enhancements**
    * Added an option to leave external enqueued files at their original positions.
    * Added compatibility for Maintenance plugin (BWP Minify will be inactive when Maintenance mode is on).
    * Improved settings on `Manage enqueued files` admin page to allow better control over enqueued files. Take a look at [this updated section](http://betterwp.net/wordpress-plugins/bwp-minify/#manage_enqueued_files) of official documentation for more details.
    * Improved friendly minify url feature to work better with cache plugins: friendly urls should show up on first load.
* **Bugs fixed**
    * Fixed an issue where CDN hosts don't replace the original host properly
    * Fixed an issue with SSL on `wp-login.php` page

**Important Note**: After updating to 1.3.1, users of `Simple Google Maps Short Code` plugin (or similar ones) and `Avada` theme should go to *BWP Minify > General Options* and turn on `Leave external files at their original positions?` setting to make map shortcodes work.

= 1.3.0 =
* **New Features**
    * Added support for Friendly Minify strings, e.g. `http://example.com/path/to/script.js` (best used with CDN). This feature should work well on **nginx** server.
    * Provides a much better method to capture and print JS, CSS files.
    * Dependencies are more intelligently handled. This should fix many incompatibility issues with other plugins. **Note**: users of **Leaflet Map Markers** plugin will still need to add `leafletmapsmarker` handle to `Script to be ignored (not minified)`.
    * Provides full support for conditional and alternate CSS files.
    * Auto-detects CSS, JS files used on site. Added a new admin page to easily add CSS, JS files to desired positions.
    * Added a new position called `oblivion`, admin can put CSS, JS files to this position to remove them completely from the site, this is useful when you want to remove duplicate files.
    * You can now control Minify Library's settings via `wp-admin` setting page.
    * Basic CDN support with SSL.
* **Bugs fixed**
    * Fixed issues with certain installations where WordPress is installed into a sub-directory or `wp-content` folder is moved.
    * Fixed incompatibility issues with protocol-relative URLs.
    * Fixed possible incompatibility issues with forced-SSL URLs.
    * Other minor fixes.
* **Enhancements**
    * BWP Minify is WordPress 3.9 compatible.
    * BWP Minify should now be able to handle **very late** JS, CSS files that are queued/printed directly using `wp_print_scripts` and `wp_print_styles`.
    * Changed Minify URL setting to contain a relative URL (from site root) instead of an absolute URL. This should be useful when switching between staging and live site, or between mirror sites.
    * Changed Minify strings' default length to `10` to avoid errors on certain servers. Users are encouraged to increase/decrease the length when needed, or enable Pretty Minify String instead.
    * Disable `Minify bloginfo()` setting by default. Modern themes should always use the enqueue system for any stylesheet.
    * `admin-bar`, `jquery-core`, and `jquery-migrate` are now ignored by default.
    * `admin-bar` and `dashicon` are now ignored by default
    * Changed default cache age to 1 day instead of 2 hours.
    * Users can now use `BWP_MINIFY_CACHE_DIR` and `BWP_MINIFY_MIN_PATH` constants to override the Cache Directory and Min Path setting in admin. This can become useful when mirroring a site.
    * Other minor enhancements.
* **Misc**
    * Added a Serbo-Croatian translation - Thanks to Borisa Djuraskovic!
    * Added an Indonesian translation - Thanks to Nasrulhaq Muiz!
    * Added a Russian translation - Thanks to Эдуард Валеев!

**Migration from 1.2.x**

* Minify URL setting has been replaced with Minify Path setting and you will have to manually update this setting if you're using a non-default one. **Note to Developers**: the setting's key has been changed from `input_minurl` to `input_minpath`. The hook `bwp_minify_min_dir` is still available but deprecated in favor of `bwp_minify_min_path`
* Minify Path setting and Cache Directory setting are now default to empty value, which means they're automatically detected.

Enjoy this release, and please don't forget to [rate BWP Minify](http://wordpress.org/support/view/plugin-reviews/bwp-minify?filter=5) **5 shining stars** if you like it, thanks!

**Bonus**: Check out these [Minifying Best Practices](http://betterwp.net/wordpress-minify-javascript-css/) to apply to your site today!

= 1.2.3 =
* BWP Minify is now WordPress 3.7 compatible (compatibility issues with WordPress 3.5 and 3.6 have been fixed).
* Updated Minify library to version 2.1.7 (security fix). This updated version of Minify also comes with an updated version of CSSMin library, which solves relative path issues in some plugins' CSS files (such as TablePress).
* Added support for protocol-relative media sources.
* Added partial support for bbPress forum plugin (`quicktags` script must be ignored for the Text editor to work, more info [here](http://betterwp.net/community/topic/144/wordpress-plugins-minify-compatibility-report/)).
* Added a filter (`bwp_minify_is_loadable`) to allow other plugins to disable BWP Minify based on some criteria.
* Added a Spanish translation - Thanks to Ruben Hernandez!
* Added a Dutch translation - Thanks to Martijn van Egmond!
* Added a German translation - Thanks to Matthias!
* Disabled BWP Minify on Ajax Edit Comment plugin's pages (for now).
* Disabled BWP Minify on SimplePress forum page.
* Updated BWP Framework to fix a possible bug that causes BWP setting pages to go blank.
* Fixed a bug that makes BWP Minify fails to split long minify string into shorter ones.
* **Good news**: I have created [an official Github repository for BWP Minify](https://github.com/OddOneOut/Better-WordPress-Minify), awaiting coders worldwide.
* **Good news**: ManageWP.com has become the official sponsor for BWP Minify - [Read more](http://betterwp.net/319-better-wordpress-plugins-updates-2013/).

**Notes:** If you're using All-in-one Calendar plugin and still having issues with it after update, please try this [suggestion](http://betterwp.net/community/post/426/#p426).

= 1.2.2 =
* Fixed a possible fatal error on certain installations.

= 1.2.1 =
* Added support for inline styles (available since WordPress 3.3).
* Cache directory is now hidden from normal admins when used on a Multi-site environment.
* Added additional hooks allowing developers to control Minify Tags and Minify Src, more info:  http://betterwp.net/community/topic/226/additional-hooks-filters-to-getminifytag/ (`bwp_get_minify_src`, `bwp_get_minify_tag`)
* Added a new hook for buster allowing developers to dynamically work with this variable (`bwp_minify_get_buster`)
* Fixed the pass-by-references fatal error when activating the plugin on a host using PHP 5.4 or higher.
* Fixed a possible bug where dependencies are ignored, more info: http://betterwp.net/community/topic/153/dependencies-ignored/
* Fixed a possible bug where scripts are echoed twice.
* Updated Turkish (tr_TR) translation - Thanks to Hakan E!

= 1.2.0 =
* New Features:
	* Added a Flush cache button
	* Split auto minify option into two options, auto minify js and auto minify css
	* Added a new buster: theme version - thanks to Jon for the patch!
* Bugs fixed:
	* Fixed a bug that broke minify strings if current port is not 80
	* Fixed two bugs that broke minify strings when `HTTPS` is enabled
	* Fixed `wp_localize_script` duplication issue
	* Fixed media style duplication issue
	* Fixed a deprecate notice caused by the use of `print_scripts_l10n`
	* Fixed some script dependency issue when you choose to ignore certain scripts
* Enhancements:
	* Increased default cache time to something longer (2 hours)
	* Cache directory can now be edited in admin area. Please note that changing the cache directory is still a two-step process, which has been described in great details [here](http://betterwp.net/wordpress-plugins/bwp-minify/#cache_directory).
* Misc:
	* Added a Turkish translation - Thanks to Hakan E.
	* Added a French translation - Thanks to Sebastien
	* Added an Italian translation - Thanks to Gabriele

= 1.0.10 =
* Fixed two possible PHP notices when using root-relative paths as Minify URL. Thanks to [Marcus](http://marcuspope.com/)!
* Fixed wrongly closed HTML `<link>` tags.
* Fixed a bug that breaks the dynamic JS file enqueued by Mingle plugin.
* Fixed an incompatibility issue with WP Download Monitor.
* Fixed an incompatibility issue with Geo-Mashup, thanks to JeremyCherfas for reporting!
* Added support for the new script localization function introduced in WordPress 3.3. Thanks to **workshopshed** for reporting!
* Added Romanian translation, thanks to Luke Tyler!

= 1.0.9 =
* Fixed a possible PHP warning about an argument not being an array.

= 1.0.8 =
* Hot fix for 1.0.7, which resolves the broken CSS issues for the wp-login page when you install WordPress in a sub-directory.

= 1.0.7 =
* Hot fix for 1.0.6, which resolves some compatibility issues with certain plugins.

= 1.0.6 =
* Added four more hooks for theme developers to fully control how scripts and styles should be enqueued and minified.
* Changed the Min URL hook a bit so themes can actually filter it.
* Added support for plugins or themes that try to enqueue and print script using the `wp_footer` action instead of the `init` action. Plugins like 'Jetpack by WordPress.com' should be working correctly now.
* Other improvements made to the positioning of styles and scripts.

**Enjoy BWP Minify!**

= 1.0.5 =
* Added support for theme developers who would like to integreate BWP Minify into their themes
	* Added a new hook added for `min` path.
	* Added new hooks to allow theme developers to only minify certain media files (see [this section](http://betterwp.net/wordpress-plugins/bwp-minify/#allowed-handles) for more details).
	* Some bug fixes.
* A lot of improvements have been made to catch styles and scripts printed using `wp_print_scripts` and `wp_print_styles`.
* The base (`b`) parameter has been removed from the minify string to add support for non-standard WordPress installation (`wp-content` has been moved or renamed.) Thanks to [Lee Powell](http://twitter.com/leepowell) for bug reports and patches!
* Fixed a bug that makes BWP Minify fail to determine the cache directory in a sub-folder installation of Multi-site.
* Fixed a possible incompatibility issue with Easy Fancybox, thanks to Bob for reporting!
* Minor bug fixes for login and signup pages.

= 1.0.4 =
* Fixed an incompatibility issue with media files' uppercase letters.
* Fixed a minor undefined offset notice, thanks to Torsten!

= 1.0.3 =
* Fixed a compatibility issue with dynamically generated media files, thanks to naimer!
* Not really a changelog, but [a small snippet](http://betterwp.net/wordpress-plugins/bwp-minify/#positioning-your-scripts) for users who want to exclude CSS files has been posted.

= 1.0.2 =
* Fixed a compatibility issue with other plugins loading styles and scripts on a separate .php page. Thanks to larry!
* Also fixed a possible bug in 1.0.1

= 1.0.1 =
* The plugin should now detect cache folder correctly for users who install WordPress in a sub-directory.

= 1.0.0 =
* Initial Release.

== Upgrade Notice ==

= 1.0.0 =
* Enjoy the plugin!
