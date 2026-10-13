# AGENTS.md

Guidance for AI coding agents working in this repository.

## What this is

**Culture Object** is a WordPress plugin (v4.4, Apache 2.0) that syncs museum/collection object records from external collection-management systems (or uploaded CSV files) into a WordPress custom post type called `object`. Themes/other plugins (e.g. Culture Object Display) then render those posts.

- Requires PHP 8.1+ and WordPress 6.2+ (plugin header in `CultureObject.php`, `readme.txt`, and `phpcs.ruleset.xml` `testVersion`).
- Text domain: `culture-object`. All user-facing strings must be translatable.
- Docs: https://docs.cultureobject.co.uk. Development happens on `main`; tags are releases.

## Layout

```
CultureObject.php        Plugin bootstrap, instantiates \CultureObject\CultureObject, global helper functions
cron.php                 CLI-only entry point (exits for non-CLI SAPIs): boots wp-blog-header.php with CO_CLI_CRON defined to run a cron sync
src/                     PSR-4 namespace CultureObject\ (composer autoload, vendor/autoload.php is committed)
  CultureObject.php      Main class: registers `object` CPT, sync triggers (HTTP key / CLI / AJAX), debug purge
  Core.php               Abstract base: provider discovery (find_providers, get_sync_provider), get_core_setting
  Settings.php           Admin menu + Settings API pages, renders views/, calls provider hooks
  Helper.php             Field remapping lookups, add_image_to_gallery_from_url(), protected upload dir + safe upload filename helpers
  Provider.php           Abstract base class every provider extends
  Exception/             Exception, ProviderException, NotYetImplementedException
providers/<Name>/<Name>.class.php   One directory per data source (see below)
views/                   Admin page templates (settings.php, provider.php, remap.php)
js/, css/                Core admin AJAX-sync assets
languages/               Translation files (empty, .gitkeep)
```

`providers/GAC` and `providers/IndexPlusModa` are gitignored (private/local providers).

## Core concepts

### Provider discovery
`Core::find_providers()` globs `providers/*/*.class.php`, tokenises each file to find class names (ignoring names ending in `exception`), instantiates every class, and keeps those with `get_provider_information()`. So:
- File name must be `*.class.php` directly inside a provider directory.
- Provider classes (and their exception classes) are **global, un-namespaced**, and are loaded with `include_once` (not autoloaded). Constructors run during discovery, so they must be cheap and side-effect free.
- The selected provider is stored in option `cos_core_sync_provider` (class name).

### Provider contract
Extend `\CultureObject\Provider` and implement `get_provider_information()`, `register_settings()`, `perform_sync()`. Errors must throw a `ProviderException` subclass (each provider defines its own, e.g. `MDSException`).

`get_provider_information()` returns an array. Keys in use: `name`, `version`, `developer`, `cron` (bool, supports CLI/key-URL sync), `ajax` (bool, implements `perform_ajax_sync()`), `supports_remap` (bool, **requires** `register_remappable_fields()` or core disables the provider), `supports_images` (bool, shows the core "Import Images" option), `no_options`.

Optional hooks core calls if they exist: `execute_init_action()` (WP `init`; register taxonomies, enqueue admin assets), `execute_load_action()` (provider settings page load), `register_remappable_fields()`, `perform_ajax_sync()`.

### Sync entry points (`src/CultureObject.php`)
1. **Key URL**: `?perform_culture_object_sync=1&key=<cos_core_sync_key>` on any front-end request (`parse_request`), requires provider `cron => true`.
2. **CLI cron**: `php cron.php` (defines `CO_CLI_CRON`), same `cron` requirement.
3. **AJAX**: `wp_ajax_cos_sync`, requires nonce `cos_ajax_import_request` plus the sync key; calls `perform_ajax_sync()`, returns JSON `{state, message, detail, ...}`. Requires provider `ajax => true`. AJAX imports are chunked/resumable, driven by provider admin JS.

### Options and meta
- Core options: `cos_core_sync_provider`, `cos_core_sync_key`, `cos_core_import_images`.
- Field remaps: option `cos_remap_<lowercased field key>`; themes opt in with `add_theme_support( 'cos-remaps' )` and use `cos_get_remapped_field_name()` / `cos_remapped_field_name()`.
- Providers store fields as post meta on `object` posts; `_cos_object_id` post meta holds the source ID used to match existing objects on re-import (without it objects get recreated every import).
- Public template helpers in `CultureObject.php`: `cos_get_instance()`, `cos_get_field()`, `cos_the_field()`, plus the remap functions above.

### Providers present
AdLib, CollectionSpace, CSV2 (upload; uses PhpSpreadsheet via its own `providers/CSV2/composer.json`), CultureGrid, Emu, IndexPlus, MDS (Museum Data Service, newest), Modes, RAMM, SWCE. Only CollectionSpace, CSV2, MDS and Modes support remapping. Providers with admin UI have `assets/admin.js|css` and a copy of jquery-ui-fresh.css. Newer providers (CSV2, MDS) are the best templates for a new provider.

## Conventions

- Follow WordPress Coding Standards: tabs, spaces inside parentheses, Yoda-ish style optional (rule excluded), `array()` syntax, snake_case functions/methods. Config is `phpcs.ruleset.xml` (WordPress-Extra + PHPCompatibilityWP, vendor excluded). Run `phpcs --standard=phpcs.ruleset.xml <paths>` if phpcs with WPCS is installed (it is not bundled or in composer.json).
- **Security is a recurring theme** (recent releases fixed escaping vulnerabilities). Escape all output (`esc_html__`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitise and `wp_unslash` all input, and verify nonces. Where a nonce is verified upstream in core, providers use `//phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by CO upstream.`
- Wrap strings in `esc_html__( '...', 'culture-object' )`; add `/* Translators: */` comments for placeholders.
- Provider file uploads go in the protected `uploads/culture-object/` dir via `Helper::get_protected_upload_dir()` and `Helper::get_safe_upload_filename()` (forces an allowlisted extension; never trust the client file type).
- Imported data (uploaded files and remote feeds) is untrusted: `import_status` strings are injected as HTML by each provider's admin.js, so `esc_html()` every data value placed in them. Check `current_user_can()` as well as nonces on handlers.
- Version bumps: update the plugin header in `CultureObject.php`, `readme.txt` (Stable tag + changelog), and the changelog in `README.md`.
- There is no automated test suite, no build step, and no CI config in this repo. Verify changes with `php -l <file>` and phpcs where available; real behaviour needs a WordPress install.

## Known inconsistencies / gotchas

- `CultureObject::check_versions()` (`src/CultureObject.php`) still checks PHP 7.3 / WP 4.5, while the plugin header and readme require PHP 8.1 / WP 6.2. Also it uses `basename( __FILE__ )` for `deactivate_plugins`, which points at `CultureObject.php` inside `src/`, not the plugin main file.
- `register_post_type` uses `apply_filters( 'co_object_slug ', 'object' )` with a **trailing space in the hook name**, so the documented filter never fires under `co_object_slug`.
- `README.md` still says "Currently v4.1" and mentions `CultureObject/Provider.class.php` (now `src/Provider.php`).
- `Settings::register_settings()` throws a `ProviderException` from an `admin_init` callback if a remap provider lacks `register_remappable_fields()`.
- `Helper::add_image_to_gallery_from_url()` uses `file_get_contents` (http/https only, sanitised filename, image types only); providers fetching remote data use `file_get_contents` rather than `wp_remote_get`.
- `js/culture-object-sync.js` is a stub (alerts the raw response); provider `assets/admin.js` do the real AJAX work.
- The "Delete all objects" button on Main Settings (only shown with `?enable_purge=true`) POSTs `cos_purge_objects`, handled by `purge_objects()`; it requires `manage_options` and a nonce. It deletes **all** `object` posts.
