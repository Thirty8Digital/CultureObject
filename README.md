CultureObject v4.3.0
====================

Welcome
---------------------
CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web.

Documentation
---------------------
A regularly maintained documentation site can be found at https://docs.cultureobject.co.uk. 


How it works
---------------------

* Install it from here or the [WordPress plugin directory](https://wordpress.org/plugins/culture-object/)
* Activate it
* Point it at your data
* Run the import
* Build the necessary theme pages to display your content. 
* Point in wonder at your beautiful new site before heading to the pub to celebrate.

At present the data you provide to CultureObject can be one of two flavours:

1. A publicly queryable RESTy API
2. A structured xml / json data file
3. A CSV (with a header row for labels)

The data "shape" - and how to deal with the data the plugin finds - is dealt with in what are called "providers". These are PHP classes which you'll find in the /providers directory.

The plugin is built to be extensible so that when other providers are added to this directory it'll pick them up in the interface and give you the option to select them and run the import.


Usage Instructions
---------------------
We use the main branch here for development. If you want a known working build, grab the latest tag. (Currently v4.1)

Originally an in-house project at [Thirty8Digital](http://www.thirty8.co.uk) by [Liam Gladdy](https://gladdy.uk)

Presently, if you want to write a custom provider, you'll need to follow the default providers in the providers directory with the abstract class in CultureObject/Provider.class.php also giving you some pointers. 

Todo in 5.0
---------------------

* "Schema" learning support: CultureObject will learn about your data architecture and offer appropriate mappings automatically, just like CSV2 does in 3.0
* Dublin Core field mappings
* Multiple Image Importing

Developers
---------------------

Version 2 added support for image importing and field mapping. At the moment, only the CollectionSpace and CSV2 provider supports this functionality.

In order to enable field mapping, your theme must declare support for 'cos-remaps' using WordPress's [add_theme_support](http://codex.wordpress.org/Function_Reference/add_theme_support), from there you then use cos_get_remapped_field_name(<field_key>), or cos_remapped_field_name(<field_key>) to return, or output either the default, or remapped human-readable field name.

Change Log
---------------------

#### Version 4.3
* Add new MDS provider
* Bump third party dependencies

#### Version 4.2
* Updated PHPSpreadsheet to fix a vulnerability
* Modernise codebase to WordPress Code Standards
* Fixed several escaping security vulnerabilities

#### Version 4.1.0
* Fix some unescaped output to HTML
* Implement PSR-4 autoloader for classes

#### Version 4.0.0
* CSV2 improvements for images, taxonomies and many other bug fixes.
* Version bump for the sake of WordPress release

#### Version 3.6.0
* SWCE improvements

#### Version 3.5.0
* Code standard changes
* Improvements to internationalization efforts
* CSV2 supports a taxonomy field which can contain comma seperated values.
* Fixes a bug with CSV2 which means new objects could be created each import, rather than updating existing ones.
* In Version 4.0, we will require at least PHP 7.1 (or 7.2 after 1st December 2019), and then track PHP's supported versions going forward (until security fixes end) as detailed [on php.net](https://www.php.net/supported-versions.php)

#### Version 3.3.0
* Support CLI cron imports to get around fast-cgi timeouts on some budget hosting. run `php wp-content/plugins/<plugin_folder>/cron.php`
* Support category filters in SWCE provider.

#### Version 3.2.0
* Support field mapping for CSV, and enable CSV support for Culture Object Display

#### Version 3.1.1
* Minor changes to support Culture Object Display (A new plugin that extends any theme to support objects)

#### Version 3.1.0
* **New:** Support AJAX import for SWCE.

#### Version 3.0.0
* **New:** CSV2 Provider (Replaces CSV) - Support field name mapping, makes cleanup optional (for partial imports) and supports AJAX import.
* **New:** Full i18n support. If you want to contribute in your native language, [become a WordPress Translator](https://translate.wordpress.org/projects/wp-plugins/culture-object)
* **New:** CSV2/3.0.0 moves more of the logic out of providers and into CultureObject Core, meaning Version 4 can make writing a provider much easier.
* **Deprecated:** PHP < 5.5 support. We require at least PHP 5.5.
* **Fix:** Support WordPress Multisite. If you were using < 3.0.0 on multisite (you probably weren't, as it didn't really work), you will need to reconfigure Culture Object on each site.

#### Version 2.2.0
* **Fix:** Support WordPress 4.5
* **Fix:** Fix an issue with EMU imports with some JSON files

#### Version 2.1.3
* **Fix:** Revert to old PHP syntax so we work on PHP 5.3 (but please, please upgrade to PHP 5.6 or PHP 7)

#### Version 2.1.2
* **Fix:** Remove debug-disablement of taxonomy imports for CollectionSpace.

#### Version 2.1.1
* **Fix:** A bug with view files trying to load a file that didn't exist.

#### Version 2.1.0
* **Change:** Move provider settings into it's own submenu
* **New:** CSV Provider

#### Version 2.0.0
* **Change:** Change menu option to a standalone utility menu item, rather than putting it inside the general settings
* **New:** [CollectionSpace](http://www.collectionspace.org) provider
* **API New:** 2 new functions, cos_get_field() and cos_the_field() provide abstracted access to imported data. This is the recommended way to access Culture Object data from v2.0.0 onwards, as we can provide begin to implement context handlers in future releases, especially as WordPress 4.4 introduces a new taxonomy metadata API, which will remove some of complexities we're currently having to implement.
* **API New:** Providers can add an execute_init_action method which is attached to a WordPress [init action hook](https://codex.wordpress.org/Plugin_API/Action_Reference/init). This can be to register additional post types or taxonomies will your import process to write against, or do to additional hook registration to allow for things like nonce checks or password security functions.
* **API New:** Support for providers to automatically import images into the WordPress media library (currently only supported by CollectionSpace, but coming to other providers soon!)
* **API New:** Support for field remapping. (currently only supported by CollectionSpace, but coming to other providers soon!)
    * Providers can now provide a list of fields which are available to be remapped. If enabled, and the theme declares support for "cos-remaps" via [add_theme_support](http://codex.wordpress.org/Function_Reference/add_theme_support) a list of all fields will be shown in the Culture Object settings page, and can be overridden by the user. As a theme developer, you should use cos_get_remapped_field_name('key') in order to get the remapped name for a field.

