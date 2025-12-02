=== AlenJoseph Page Exporter Lite ===
Contributors: alenjoseph
Tags: export, pages, xml, backup, migration
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress plugin that allows administrators to export individual pages directly from the WordPress Admin interface.

== Description ==

**Page Exporter Lite** enhances WordPress's native export functionality by providing a simple, one-click solution to export individual pages. Instead of using the default Tools > Export feature that only allows bulk exports by date or author, this plugin adds an "Export" action link directly to each page in your Pages list.

= Key Features =

* **One-Click Export**: Export individual pages with a single click
* **WordPress Native Format**: Exports in standard WordPress XML format for seamless import compatibility
* **Includes Attachments**: Automatically includes all images and media attached to the page
* **Admin Integration**: Seamlessly integrates with WordPress admin interface
* **Secure**: Only administrators can export pages
* **Lightweight**: Minimal impact on site performance
* **Compatible**: Works with WordPress 6.0 and above

= Use Cases =

* **Content Migration**: Move specific pages between WordPress sites
* **Page Backup**: Create backups of important pages
* **Development**: Export pages for testing or staging environments
* **Client Handoffs**: Provide clients with specific page content
* **Content Sharing**: Share page content with team members

= How It Works =

1. Navigate to **Pages > All Pages** in your WordPress admin
2. Hover over any page to see the action links
3. Click the **Export** link next to Edit, View, and Trash
4. The page will be exported as an XML file and automatically downloaded
5. Import the file using **Tools > Import** on any WordPress site

= Technical Details =

* Exports pages in WordPress eXtended RSS (WXR) format
* Includes page content, metadata, and custom fields
* Automatically includes all attached media files
* Maintains WordPress import compatibility
* Follows WordPress security best practices

== Installation ==

1. Upload the `page-exporter-lite` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Pages > All Pages** to start using the export feature

== Frequently Asked Questions ==

= Who can export pages? =

Only users with administrator privileges can export pages. This ensures site security and prevents unauthorized content export.

= What format are the exported files? =

Pages are exported in WordPress's standard XML format (WXR - WordPress eXtended RSS), which is compatible with WordPress's native import tool.

= Are attachments included in the export? =

Yes, all images and media files attached to the page are automatically included in the export file.

= Can I export multiple pages at once? =

The current version supports single-page exports only. Bulk export functionality is planned for a future release.

= Will this work with my custom fields? =

Yes, the plugin exports all custom fields and metadata associated with the page.

= Is the exported file compatible with WordPress import? =

Absolutely! The exported XML file can be imported using WordPress's native **Tools > Import** feature.

= Does this plugin slow down my site? =

No, the plugin is designed to be lightweight and only loads when needed in the admin area.

== Screenshots ==

1. Export action link in the Pages list
2. XML file download after clicking export
3. Successful import using WordPress Tools > Import

== Changelog ==

= 1.0.0 =
* Initial release
* Single page export functionality
* WordPress XML format export
* Attachment inclusion
* Admin interface integration
* Security implementation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Page Exporter Lite. Install to start exporting individual pages with one click.

== Support ==

For support, bug reports, or feature requests, please visit [your support page or GitHub repository].

== Contributing ==

We welcome contributions! Please see our [contribution guidelines] for more information.

== Privacy Policy ==

Page Exporter Lite does not collect, store, or transmit any user data. All export operations are performed locally on your server.
