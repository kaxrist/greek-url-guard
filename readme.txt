=== Greek URL Guard ===
Contributors: kaxrist
Tags: greek, slugs, permalinks, seo, media
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert new Greek slugs and upload filenames into clean, SEO-friendly Greeklish without changing existing URLs.

== Description ==

Greek URL Guard helps Greek WordPress sites create clean Greeklish URLs and upload filenames from the start, without rewriting existing URLs.

It is built for site owners who want safer, readable, SEO-friendly slugs for new content while avoiding unexpected changes to old posts, terms, media files, or indexed URLs.

The plugin converts Greek characters in new posts, pages, public custom post types, categories, tags, public taxonomies, WooCommerce products, WooCommerce product categories, product tags, product attributes, and new media upload filenames. It also includes a slug preview tool and a lightweight SEO safety check for existing Greek slugs, media filenames, and possible overlapping custom slug code.

### What it does

* Converts new post and page slugs to Greeklish.
* Supports public custom post types and public taxonomies.
* Supports WooCommerce products, product categories, product tags, and product attributes.
* Cleans new media upload filenames.
* Converts underscores, spaces, repeated dashes, symbols, and Greek characters.
* Keeps existing URLs and existing uploaded files unchanged.
* Preserves manually entered slugs by default.
* Offers configurable slug and filename length limits.
* Includes a fast slug preview tool.
* Warns about existing Greek slugs, media filenames, or possible overlapping Greek slug converters.

== Installation ==

Install from the WordPress dashboard:

1. Go to Plugins > Add New.
2. Search for Greek URL Guard.
3. Click Install Now.
4. Activate the plugin.
5. Go to Settings > Greek URL Guard to review the options.

Upload the plugin ZIP:

1. Go to Plugins > Add New > Upload Plugin.
2. Select the `greek-url-guard.zip` file.
3. Click Install Now.
4. Activate the plugin.
5. Go to Settings > Greek URL Guard to review the options.

FTP upload:

1. Extract the `greek-url-guard.zip` file on your computer.
2. Upload the extracted `greek-url-guard` folder to `/wp-content/plugins/`.
3. Activate the plugin from the WordPress Plugins screen.
4. Go to Settings > Greek URL Guard to review the options.

== Frequently Asked Questions ==

= Will this change my existing URLs? =

No. The automatic rules apply to new content and new uploads only. Draft placeholder slugs created before a real title exists may be replaced before publication.

= Can it convert old Greek URLs? =

No. Greek URL Guard is designed to avoid unexpected changes to existing URLs. It focuses on new content and new uploads. If you need to change old URLs, review them carefully and set up redirects before making changes.

= Will this rename files that are already in the Media Library? =

No. Existing uploaded files are not renamed.

= What happens if I type a slug manually? =

Manual slugs are preserved by default. You can disable this option if you want Greek manual slugs to be converted too.

= Will changing a published title update the URL? =

No. Changing the title of already published content does not rewrite its existing URL. If you want to change that URL, edit the slug field directly and review whether redirects are needed.

= Can I choose what the plugin handles? =

Yes. In Settings > Greek URL Guard, you can choose whether the plugin handles posts, pages, public custom post types, categories, tags, public taxonomies, media filenames, and WooCommerce content when WooCommerce is active.

= Does it remove short Greek words from URLs? =

No. Search engines can handle short words, and removing them automatically can change the meaning of a title.

= Does it work with Elementor, Gutenberg, WooCommerce, WPML, or other builders and translation plugins? =

The plugin works at the WordPress slug and upload filename level. It does not edit page builder content or translated text.

= Does it support WooCommerce? =

Yes. When WooCommerce is active, the plugin can handle new product slugs, product categories, product tags, product attributes, and new upload filenames.

== Screenshots ==

1. Main settings and coverage options.
2. Slug preview result.
3. SEO Safety Check overview.

== Uninstall ==

Uninstalling the plugin never reverts slugs, renames files back, or edits content. Plugin settings are kept by default so they are available if the plugin is reinstalled. You can enable the cleanup option before uninstall if you want the plugin settings removed.

== Privacy ==

Greek URL Guard does not send data to external services, does not use cookies, and does not track visitors or administrators.

== Changelog ==

= 1.0.1 =

* Fix WooCommerce product slugs after delayed draft publishing.

= 1.0.0 =

* Initial public release.

== Upgrade Notice ==

= 1.0.1 =

Fixes WooCommerce product slug conversion when publishing products after draft editing.