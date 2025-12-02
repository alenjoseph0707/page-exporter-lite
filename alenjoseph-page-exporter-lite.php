<?php

/**
 * Plugin Name: AlenJoseph Page Exporter Lite
 * Plugin URI:  https://profiles.wordpress.org/alenjoseph
 * Description: Export individual WordPress posts/pages in WXR/XML format with a single click.
 * Version:     1.1.3
 * Author:      Alen Joseph
 * Author URI:  https://alenjoseph0707.github.io/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alenjoseph-page-exporter-lite
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: true
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PAGEEXLI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PAGEEXLI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PAGEEXLI_PLUGIN_VERSION', '1.1.3');

class PAGEEXLI_Page_Exporter {

    private $supported_post_types = array();

    public function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    public function init()
    {

        $this->set_supported_post_types();

        add_filter('post_row_actions', array($this, 'add_export_action'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_export_action'), 10, 2);

        add_action('admin_init', array($this, 'handle_export_request'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    private function set_supported_post_types()
    {

        $post_types = get_post_types(array('public' => true), 'names');
        if (isset($post_types['attachment'])) {
            unset($post_types['attachment']);
        }

        $this->supported_post_types = apply_filters('pageexli_supported_post_types', $post_types);

        $saved_post_types = get_option('pageexli_post_types', array());
        if (! empty($saved_post_types)) {
            $this->supported_post_types = array_intersect($this->supported_post_types, $saved_post_types);
        }
    }

    private function is_supported_post_type($post_type)
    {
        return in_array($post_type, $this->supported_post_types, true);
    }

    public function add_export_action($actions, $post)
    {

        if (! current_user_can('administrator') || ! $this->is_supported_post_type($post->post_type)) {
            return $actions;
        }

        $export_url = wp_nonce_url(
            admin_url('admin.php?action=pageexli_export_post&post_id=' . absint($post->ID)),
            'pageexli_export_post_' . $post->ID
        );

        /* translators: %s: Post type label (e.g. "page" or "post") */
        $title_text = sprintf(
            // translators: %s is the post type name (e.g., "page", "post")
            __('Export this %s', 'alenjoseph-page-exporter-lite'),
            $post->post_type
        );

        $actions['pageexli_export'] = sprintf(
            '<a href="%s" class="pageexli-export-link" title="%s">%s</a>',
            esc_url($export_url),
            esc_attr($title_text),
            esc_html__('Export', 'alenjoseph-page-exporter-lite')
        );

        return $actions;
    }

    public function handle_export_request()
    {

        if (! isset($_GET['action']) || sanitize_key(wp_unslash($_GET['action'])) !== 'pageexli_export_post') {
            return;
        }

        // Validate post_id existence
        if (! isset($_GET['post_id'])) {
            wp_die(esc_html__('Invalid post ID.', 'alenjoseph-page-exporter-lite'));
        }

        // Properly sanitize and validate $_GET['post_id']
        $post_id_raw = sanitize_text_field(wp_unslash($_GET['post_id']));
        
        // Validate numeric value before conversion
        if (! is_numeric($post_id_raw)) {
            wp_die(esc_html__('Invalid post ID.', 'alenjoseph-page-exporter-lite'));
        }

        $post_id = absint($post_id_raw);

        if (! isset($_GET['_wpnonce'])) {
            wp_die(esc_html__('Missing security token.', 'alenjoseph-page-exporter-lite'));
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        if (! wp_verify_nonce($nonce, 'pageexli_export_post_' . $post_id)) {
            wp_die(esc_html__('Security check failed.', 'alenjoseph-page-exporter-lite'));
        }

        if (! current_user_can('administrator')) {
            wp_die(esc_html__('You do not have permission to export posts.', 'alenjoseph-page-exporter-lite'));
        }

        $post = get_post($post_id);

        if (! $post || ! $this->is_supported_post_type($post->post_type)) {
            wp_die(esc_html__('Invalid or unsupported post type.', 'alenjoseph-page-exporter-lite'));
        }

        $this->export_post($post);
    }

    private function export_post($post)
    {

        $attachments = get_attached_media('', $post->ID);

        $post_type_obj   = get_post_type_object($post->post_type);
        $post_type_label = $post_type_obj ? strtolower($post_type_obj->labels->singular_name) : $post->post_type;

        $filename = sanitize_file_name($post_type_label . '-' . ($post->post_name ? $post->post_name : $post->ID)) . '.xml';

        // Send headers
        if (! headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is a well-formed export XML
        echo $this->generate_xml($post, $attachments);
        exit;
    }

    private function generate_xml($post, $attachments)
    {

        $sitename  = get_bloginfo('name');
        $site_url  = get_bloginfo('url');

        $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
        $xml .= '<!-- Generated by Page Exporter Lite -->' . "\n\n";

        $xml .= '<rss version="2.0"
			xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:wfw="http://wellformedweb.org/CommentAPI/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:wp="http://wordpress.org/export/1.2/">' . "\n\n";

        $xml .= "<channel>\n";
        $xml .= "	<title>" . $this->cdata($sitename) . "</title>\n";
        $xml .= "	<link>" . esc_url($site_url) . "</link>\n";
        $xml .= "	<description>" . $this->cdata(get_bloginfo('description')) . "</description>\n";
        $xml .= "	<pubDate>" . gmdate('D, d M Y H:i:s +0000') . "</pubDate>\n";
        $xml .= "	<language>" . get_bloginfo('language') . "</language>\n";
        $xml .= "	<wp:wxr_version>1.2</wp:wxr_version>\n";
        $xml .= "	<wp:base_site_url>" . esc_url($site_url) . "</wp:base_site_url>\n";
        $xml .= "	<wp:base_blog_url>" . esc_url($site_url) . "</wp:base_blog_url>\n\n";

        if ($post->post_type === 'post') {
            $xml .= $this->get_categories_xml($post);
            $xml .= $this->get_tags_xml($post);
        }

        $xml .= $this->get_custom_taxonomies_xml($post);
        $xml .= $this->get_post_xml($post);

        foreach ($attachments as $attachment) {
            $xml .= $this->get_attachment_xml($attachment);
        }

        $xml .= "</channel>\n</rss>";

        return $xml;
    }

    private function get_categories_xml($post)
    {
        $xml        = '';
        $categories = get_the_category($post->ID);

        if ($categories && ! is_wp_error($categories)) {
            foreach ($categories as $category) {
                $parent_slug = $category->parent ? get_category($category->parent)->slug : '';

                $xml .= "	<wp:category>\n";
                $xml .= "		<wp:term_id>{$category->term_id}</wp:term_id>\n";
                $xml .= "		<wp:category_nicename>" . $this->cdata($category->slug) . "</wp:category_nicename>\n";
                $xml .= "		<wp:category_parent>" . $this->cdata($parent_slug) . "</wp:category_parent>\n";
                $xml .= "		<wp:cat_name>" . $this->cdata($category->name) . "</wp:cat_name>\n";
                $xml .= "	</wp:category>\n";
            }
        }

        return $xml;
    }

    private function get_tags_xml($post)
    {
        $xml  = '';
        $tags = get_the_tags($post->ID);

        if ($tags && ! is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $xml .= "	<wp:tag>\n";
                $xml .= "		<wp:term_id>{$tag->term_id}</wp:term_id>\n";
                $xml .= "		<wp:tag_slug>" . $this->cdata($tag->slug) . "</wp:tag_slug>\n";
                $xml .= "		<wp:tag_name>" . $this->cdata($tag->name) . "</wp:tag_name>\n";
                $xml .= "	</wp:tag>\n";
            }
        }

        return $xml;
    }

    private function get_custom_taxonomies_xml($post)
    {

        $xml        = '';
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');

        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, array('category', 'post_tag'), true)) {
                continue;
            }

            $terms = get_the_terms($post->ID, $taxonomy->name);

            if ($terms && ! is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $parent_slug = $term->parent ? get_term($term->parent)->slug : '';

                    $xml .= "	<wp:term>\n";
                    $xml .= "		<wp:term_id>{$term->term_id}</wp:term_id>\n";
                    $xml .= "		<wp:term_taxonomy>" . $this->cdata($taxonomy->name) . "</wp:term_taxonomy>\n";
                    $xml .= "		<wp:term_slug>" . $this->cdata($term->slug) . "</wp:term_slug>\n";
                    $xml .= "		<wp:term_parent>" . $this->cdata($parent_slug) . "</wp:term_parent>\n";
                    $xml .= "		<wp:term_name>" . $this->cdata($term->name) . "</wp:term_name>\n";
                    $xml .= "	</wp:term>\n";
                }
            }
        }

        return $xml;
    }

    private function get_post_xml($post)
    {

        $pub_date      = gmdate('D, d M Y H:i:s +0000', strtotime($post->post_date));
        $post_date     = $post->post_date;
        $post_date_gmt = $post->post_date_gmt;

        $xml  = "	<item>\n";
        $xml .= "		<title>" . $this->cdata($post->post_title) . "</title>\n";
        $xml .= "		<link>" . esc_url(get_permalink($post->ID)) . "</link>\n";
        $xml .= "		<pubDate>$pub_date</pubDate>\n";
        $xml .= "		<dc:creator>" . $this->cdata(get_the_author_meta('login', $post->post_author)) . "</dc:creator>\n";
        $xml .= "		<guid isPermaLink=\"false\">" . esc_url(get_permalink($post->ID)) . "</guid>\n";
        $xml .= "		<description></description>\n";
        $xml .= "		<content:encoded>" . $this->cdata($post->post_content) . "</content:encoded>\n";
        $xml .= "		<excerpt:encoded>" . $this->cdata($post->post_excerpt) . "</excerpt:encoded>\n";

        $xml .= "		<wp:post_id>{$post->ID}</wp:post_id>\n";
        $xml .= "		<wp:post_date>" . $this->cdata($post_date) . "</wp:post_date>\n";
        $xml .= "		<wp:post_date_gmt>" . $this->cdata($post_date_gmt) . "</wp:post_date_gmt>\n";
        $xml .= "		<wp:comment_status>" . $this->cdata($post->comment_status) . "</wp:comment_status>\n";
        $xml .= "		<wp:ping_status>" . $this->cdata($post->ping_status) . "</wp:ping_status>\n";
        $xml .= "		<wp:post_name>" . $this->cdata($post->post_name) . "</wp:post_name>\n";
        $xml .= "		<wp:status>" . $this->cdata($post->post_status) . "</wp:status>\n";
        $xml .= "		<wp:post_parent>{$post->post_parent}</wp:post_parent>\n";
        $xml .= "		<wp:menu_order>{$post->menu_order}</wp:menu_order>\n";
        $xml .= "		<wp:post_type>" . $this->cdata($post->post_type) . "</wp:post_type>\n";
        $xml .= "		<wp:post_password>" . $this->cdata($post->post_password) . "</wp:post_password>\n";
        $xml .= "		<wp:is_sticky>" . (is_sticky($post->ID) ? '1' : '0') . "</wp:is_sticky>\n";

        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {

            if (in_array($taxonomy, array('category', 'post_tag'), true)) {
                continue;
            }

            $terms = get_the_terms($post->ID, $taxonomy);
            if ($terms && ! is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $xml .= '		<category domain="' . esc_attr($taxonomy) . '" nicename="' . esc_attr($term->slug) . '">' . $this->cdata($term->name) . '</category>' . "\n";
                }
            }
        }

        $custom_fields = get_post_meta($post->ID);
        foreach ($custom_fields as $key => $values) {

            $include_field = true;

            if (strpos($key, '_') === 0) {
                $acf_patterns = array(
                    '/^_[a-z0-9_]+$/',
                    '/^_wp_page_template$/',
                    '/^_thumbnail_id$/',
                    '/^_edit_lock$/',
                    '/^_edit_last$/',
                    '/^_wp_attachment_metadata$/',
                    '/^_wp_attached_file$/'
                );

                $include_field = false;
                foreach ($acf_patterns as $pattern) {
                    if (preg_match($pattern, $key)) {
                        $include_field = true;
                        break;
                    }
                }
            }

            if (! $include_field) {
                continue;
            }

            foreach ($values as $value) {
                $xml .= "		<wp:postmeta>\n";
                $xml .= "			<wp:meta_key>" . $this->cdata($key) . "</wp:meta_key>\n";
                $xml .= "			<wp:meta_value>" . $this->cdata($value) . "</wp:meta_value>\n";
                $xml .= "		</wp:postmeta>\n";
            }
        }

        $xml .= "	</item>\n\n";
        return $xml;
    }

    private function get_attachment_xml($attachment)
    {

        $pub_date = gmdate('D, d M Y H:i:s +0000', strtotime($attachment->post_date));

        $xml  = "	<item>\n";
        $xml .= "		<title>" . $this->cdata($attachment->post_title) . "</title>\n";
        $xml .= "		<link>" . esc_url(wp_get_attachment_url($attachment->ID)) . "</link>\n";
        $xml .= "		<pubDate>$pub_date</pubDate>\n";
        $xml .= "		<dc:creator>" . $this->cdata(get_the_author_meta('login', $attachment->post_author)) . "</dc:creator>\n";
        $xml .= "		<guid isPermaLink=\"false\">" . esc_url(wp_get_attachment_url($attachment->ID)) . "</guid>\n";
        $xml .= "		<description></description>\n";
        $xml .= "		<content:encoded>" . $this->cdata($attachment->post_content) . "</content:encoded>\n";
        $xml .= "		<excerpt:encoded>" . $this->cdata($attachment->post_excerpt) . "</excerpt:encoded>\n";

        $xml .= "		<wp:post_id>{$attachment->ID}</wp:post_id>\n";
        $xml .= "		<wp:post_date>" . $this->cdata($attachment->post_date) . "</wp:post_date>\n";
        $xml .= "		<wp:post_date_gmt>" . $this->cdata($attachment->post_date_gmt) . "</wp:post_date_gmt>\n";
        $xml .= "		<wp:comment_status>" . $this->cdata($attachment->comment_status) . "</wp:comment_status>\n";
        $xml .= "		<wp:ping_status>" . $this->cdata($attachment->ping_status) . "</wp:ping_status>\n";
        $xml .= "		<wp:post_name>" . $this->cdata($attachment->post_name) . "</wp:post_name>\n";
        $xml .= "		<wp:status>" . $this->cdata($attachment->post_status) . "</wp:status>\n";
        $xml .= "		<wp:post_parent>{$attachment->post_parent}</wp:post_parent>\n";
        $xml .= "		<wp:menu_order>{$attachment->menu_order}</wp:menu_order>\n";
        $xml .= "		<wp:post_type>" . $this->cdata($attachment->post_type) . "</wp:post_type>\n";
        $xml .= "		<wp:post_password>" . $this->cdata($attachment->post_password) . "</wp:post_password>\n";
        $xml .= "		<wp:is_sticky>0</wp:is_sticky>\n";

        $attachment_url = wp_get_attachment_url($attachment->ID);
        $xml           .= "		<wp:attachment_url>" . $this->cdata($attachment_url) . "</wp:attachment_url>\n";

        $attachment_meta = wp_get_attachment_metadata($attachment->ID);
        if ($attachment_meta) {
            $xml .= "		<wp:postmeta>\n";
            $xml .= "			<wp:meta_key>_wp_attachment_metadata</wp:meta_key>\n";
            $xml .= "			<wp:meta_value>" . $this->cdata(serialize($attachment_meta)) . "</wp:meta_value>\n";
            $xml .= "		</wp:postmeta>\n";
        }

        $custom_fields = get_post_meta($attachment->ID);
        foreach ($custom_fields as $key => $values) {
            if ($key === '_wp_attachment_metadata') {
                continue;
            }
            foreach ($values as $value) {
                $xml .= "		<wp:postmeta>\n";
                $xml .= "			<wp:meta_key>" . $this->cdata($key) . "</wp:meta_key>\n";
                $xml .= "			<wp:meta_value>" . $this->cdata($value) . "</wp:meta_value>\n";
                $xml .= "		</wp:postmeta>\n";
            }
        }

        $xml .= "	</item>\n\n";
        return $xml;
    }

    public function add_admin_menu()
    {

        add_options_page(
            __('Page Exporter Lite Settings', 'alenjoseph-page-exporter-lite'),
            __('Page Exporter Lite', 'alenjoseph-page-exporter-lite'),
            'manage_options',
            'alenjoseph-page-exporter-lite',
            array($this, 'settings_page')
        );
    }

    public function settings_page()
    {

        if (isset($_POST['submit'])) {

            check_admin_referer('pageexli_settings');

            $post_types = array();

            // Properly sanitize $_POST['pel_post_types']
            if (isset($_POST['pageexli_post_types']) && is_array($_POST['pageexli_post_types'])) {
                // Sanitize the entire array immediately
                $post_types_raw = array_map('sanitize_text_field', wp_unslash($_POST['pageexli_post_types']));
                
                // Verify it's an array
                if (is_array($post_types_raw)) {
                    foreach ($post_types_raw as $pt) {
                        // Additional validation: ensure it's a valid post type
                        if (post_type_exists($pt)) {
                            $post_types[] = $pt;
                        }
                    }
                }
            }

            update_option('pageexli_post_types', $post_types);

            echo '<div class="notice notice-success"><p><strong>' . esc_html__('Settings saved.', 'alenjoseph-page-exporter-lite') . '</strong></p></div>';

            $this->set_supported_post_types();
        }

        $all_post_types = get_post_types(array('public' => true), 'objects');
        if (isset($all_post_types['attachment'])) {
            unset($all_post_types['attachment']);
        }

        $selected_post_types = get_option('pageexli_post_types', array_keys($all_post_types));
?>
        <div class="wrap">
            <h1><?php esc_html_e('Page Exporter Lite Settings', 'alenjoseph-page-exporter-lite'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('pageexli_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Export for Post Types', 'alenjoseph-page-exporter-lite'); ?></th>
                        <td>
                            <?php foreach ($all_post_types as $type => $obj) : ?>
                                <label>
                                    <input type="checkbox"
                                        name="pageexli_post_types[]"
                                        value="<?php echo esc_attr($type); ?>"
                                        <?php checked(in_array($type, $selected_post_types, true)); ?>>
                                    <?php echo esc_html($obj->labels->name . ' (' . $type . ')'); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    private function cdata($content)
    {
        return '<![CDATA[' . $content . ']]>';
    }

    public function enqueue_scripts($hook)
    {

        if ('edit.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();

        if (! $this->is_supported_post_type($screen->post_type)) {
            return;
        }

        wp_enqueue_style(
            'alenjoseph-page-exporter-lite',
            PAGEEXLI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PAGEEXLI_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'alenjoseph-page-exporter-lite',
            PAGEEXLI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PAGEEXLI_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'alenjoseph-page-exporter-lite',
            'pageexli_ajax',
            array(
                'nonce'           => wp_create_nonce('pageexli_nonce'),
                'exporting'       => esc_html__('Exporting...', 'alenjoseph-page-exporter-lite'),
                'export_complete' => esc_html__('Export complete!', 'alenjoseph-page-exporter-lite'),
                'export_error'    => esc_html__('Export failed. Please try again.', 'alenjoseph-page-exporter-lite'),
            )
        );
    }
}

new PAGEEXLI_Page_Exporter();

register_activation_hook(__FILE__, 'pageexli_activation');
function pageexli_activation()
{

    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('Page Exporter Lite requires WordPress 6.0 or higher.', 'alenjoseph-page-exporter-lite'));
    }

    $default = array('post', 'page');
    add_option('pageexli_post_types', $default);
}

register_deactivation_hook(__FILE__, 'pageexli_deactivation');
function pageexli_deactivation()
{
    delete_option('pageexli_post_types');
}