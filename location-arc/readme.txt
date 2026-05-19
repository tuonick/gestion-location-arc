=== Location d'Arc (ACSIM) ===
Contributors:      florianbossard
Tags:              archery, rental, contract, management, pdf
Requires at least: 5.9
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        0.2.60
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Archery club bow rental management: contracts, equipment inventory, PDF generation and email delivery.

== Description ==

**Location d'Arc** is a WordPress plugin designed for archery clubs that manage a fleet of rental equipment (risers and limbs).

Key features:

* Rental contract management (creation, renewal, archiving)
* Equipment inventory: risers and limbs with availability status
* Automatic PDF contract generation (built-in generator, no external dependencies)
* Contract delivery by email to the member
* Frontend dashboard (shortcode) to view and manage contracts
* CSV import for equipment and contracts
* Configurable contract types and pricing
* Compatible with the France Tir API (member import)

PDF generation uses **LocarcPDF**, a self-contained generator built into the plugin — no Composer, no extra installation required.

== Installation ==

1. Download the plugin and upload the `location-arc` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress **Plugins** menu.
3. Go to **Location d'Arc** in the admin menu to configure the plugin.
4. Fill in the club information (email, equipment manager, PDF header) under **Settings**.

To display the frontend dashboard on a page, use the shortcode:

    [locarc_dashboard]

== Frequently Asked Questions ==

= Does a PDF library need to be installed separately? =

No. PDF generation uses **LocarcPDF**, a generator built directly into the plugin. No external dependency or Composer command is required.

= Which PHP versions are supported? =

PHP 7.4 minimum. PHP 8.0, 8.1, 8.2 and 8.3 are supported.

= Is the plugin multisite compatible? =

Not tested in a multisite environment.

= Is the plugin in French only? =

The plugin interface and PDF output are in French, as it is designed for French archery clubs. An English translation is planned for a future release.

== Screenshots ==

1. Active contracts list with sorting and filters
2. Contract creation form
3. Risers and limbs inventory
4. Sample generated PDF contract
5. Settings page

== Changelog ==

= 0.2.60 =
* Added: sorting and filters on all frontend dashboard tables
* Added: read-only equipment preview in contract forms
* Added: equipment detail modal (click on identifier)
* Fixed: sidebar panel height on small screens
* Changed: replaced Dompdf with LocarcPDF, a self-contained PDF generator

= 0.2.59 =
* Added: customizable PDF header from settings
* Changed: removed AI tool mentions, developer credit

= 0.2.58 =
* Redesigned admin interface (KPIs, paid/unpaid badges, action dropdown)
* Date formatting dd/mm/yyyy with expiry color coding

== Upgrade Notice ==

= 0.2.60 =
Recommended update. New sort/filter features, equipment preview, and built-in PDF generator (no more Dompdf dependency).
