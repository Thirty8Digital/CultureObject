=== Plugin Name ===
Contributors: lgladdy
Tags: collections, museum, culture, objects, object, sync
Requires at least: 4.1
Tested up to: 4.4
Stable tag: 2.1.3
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0

CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web.

== Description ==

CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web.

It supports a number of collection management systems (AdLib, CollectionSpace, CSV, CultureGrid, Emu, RAMM)

== Installation ==

* Install it
* Activate it
* Point it at your data
* Run the import
* Build the necessary theme pages to display your content.
* Point in wonder at your beautiful new site before heading to the pub to celebrate.

== Changelog ==

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
    * Providers can now provide a list of fields which are available to be remapped. If enabled, and the theme declares support for "cos-remaps" via [add_theme_support](http://codex.wordpress.org/Function_Reference/add_theme_support) a list of all fields will be shown in the Culture Object settings page, and can be overridden by the user. As a theme developer, you should use cos_get_remapped_field_name('key') in order to get the remapped name for a field.
