=== Culture Object ===
Contributors: lgladdy
Tags: museum, culture, objects, object, sync
Requires at least: 6.2
Tested up to: 6.5
Stable tag: 4.1.1
Requires PHP: 8.1
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0

CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web.

== Description ==

CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web.

It supports a number of collection management systems (AdLib, CollectionSpace, CSV, CultureGrid, Emu, RAMM) as well as uploading of CSV files.

== Installation ==

* Install it
* Activate it
* Point it at your data
* Run the import
* Build the necessary theme pages to display your content.
* Point in wonder at your beautiful new site before heading to the pub to celebrate.

== Changelog ==

= 4.2 =
Improvement: Updated dependencies, modernised codebase. Now requires WordPress 6.2 and PHP 8.1
Security: HPSpreadsheet to fix a vulnerability
Security: Fixed several escaping security vulnerabilities

= 4.1.1 =
Improvement: Implement WordPress coding standards

= 4.1 =
Security Fix: some unescaped output to HTML
Improvement: Implement PSR-4 autoloader for classes

= 4.0 =
Fix: CSV2 improvements for images, taxonomies and many other bug fixes.
Update: We now require PHP 7.3 and WordPress 5.2+ for the sake of testing ability on supported software.

= 3.6 =
New: SWCE Improvements

= 3.5 =
New: Code standard changes
New: Improvements to internationalization efforts
New: CSV2 supports a taxonomy field which can contain comma seperated values.
Fix: Fixes a bug with CSV2 which means new objects could be created each import, rather than updating existing ones.
Deprecated: In Version 4.0, we will require at least PHP 7.2, and then track PHP's supported versions going forward (until security fixes end) as detailed [on php.net](https://www.php.net/supported-versions.php)

= 3.3.0 =
New: Support CLI cron imports to get around fast-cgi timeouts on some budget hosting. run `php wp-content/plugins/<plugin_folder>/cron.php`
New: Support category filters in SWCE provider.

= 3.2.0 =
New: Support field mapping for CSV, and enable CSV support for Culture Object Display

= 3.1.1 =
Minor changes to support Culture Object Display

= 3.1.0 =
New: Support AJAX import for SWCE.

= 3.0.0 =
New: CSV2 Provider (Replaces CSV) - Support field name mapping, makes cleanup optional (for partial imports) and supports AJAX import.
New: Full i18n support. If you want to contribute in your native language, [become a WordPress Translator](https://translate.wordpress.org/projects/wp-plugins/culture-object)
New: CSV2/3.0.0 moves more of the logic out of providers and into CultureObject Core, meaning Version 4 can make writing a provider much easier.
Deprecated: PHP < 5.5 support. We require at least PHP 5.5.
Fix: Support WordPress Multisite. If you were using < 3.0.0 on multisite (you probably weren't, as it didn't really work!), you will need to reconfigure Culture Object on each site.

= 2.2.0 =
Fix: Support WordPress 4.5
Fix: an issue with EMU imports with some JSON files

= 2.1.3 =
Revert to old PHP syntax so we work on PHP 5.3 (but please, please upgrade to PHP 5.6 or PHP 7)

= 2.1.2 =
Fix: Remove debug-disablement of taxonomy imports for CultureObject.

= 2.1.1 =
Fix: A bug with view files trying to load a file that didn't exist.

= 2.1.0 =
Change: Move provider settings into it's own submenu
New: CSV Provider

= 2.0.0 = 
Change: Change menu option to a standalone utility menu item, rather than putting it inside the general settings

New: [CollectionSpace](http://www.collectionspace.org) provider

API New: 2 new functions, cos_get_field() and cos_the_field() provide abstracted access to imported data. This is the recommended way to access Culture Object data from v2.0.0 onwards, as we can provide begin to implement context handlers in future releases, especially as WordPress 4.4 introduces a new taxonomy metadata API, which will remove some of complexities we're currently having to implement.

API New: Providers can add an execute_init_action method which is attached to a WordPress [init action hook](https://codex.wordpress.org/Plugin_API/Action_Reference/init). This can be to register additional post types or taxonomies will your import process to write against, or do to additional hook registration to allow for things like nonce checks or password security functions.

API New: Support for providers to automatically import images into the WordPress media library (currently only supported by CollectionSpace, but coming to other providers soon!)

API New: Support for field remapping. (currently only supported by CollectionSpace, but coming to other providers soon!)

Providers can now provide a list of fields which are available to be remapped. If enabled, and the theme declares support for "cos-remaps" via [add_theme_support](http://codex.wordpress.org/Function_Reference/add_theme_support) a list of all fields will be shown in the Culture Object settings page, and can be overridden by the user. As a theme developer, you should use cos_get_remapped_field_name('key') in order to get the remapped name for a field.
