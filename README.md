# HTML Sitemap Plugin

**HTML Sitemap** is a WordPress plugin that automatically generates a dynamic HTML sitemap for your website. This sitemap organizes your site's content by year, month, and day, making it easier for visitors and search engines to navigate and index your site effectively.

![HTML Sitemap Screenshot](./images/sitemap-html.jpg)

## Features

- **Automatic Sitemap Generation:** Creates and updates an HTML sitemap page upon activation, and daily as content changes.
- **Date-Based Organization:** Structures posts by year, month, and day for intuitive browsing.
- **Caching Mechanism:** Implements caching to optimize performance and reduce database queries.
- **Scheduled Updates:** Uses WordPress cron to schedule daily sitemap updates.

## Installation

### Using Composer

To install the plugin via Composer, follow these steps:

1. **Add the Repository:**
   - Open your project's `composer.json` file.
   - Add the following under the `repositories` section:

     ```json
     "repositories": [
         {
             "type": "vcs",
             "url": "https://github.com/xwp/sitemap-html"
         }
     ]
     ```

2. **Require the Plugin:**
   - Run the following command in your terminal:

     ```bash
     composer require xwp/sitemap-html
     ```

3. **Activate the Plugin:**
   - Once installed, activate the plugin through the 'Plugins' menu in WordPress.

### Manual Installation

1. **Download the Plugin:**
   - Download the `sitemap-html` plugin folder.

2. **Upload the Plugin:**
   - Upload the `sitemap-html` folder to the `/wp-content/plugins/` directory of your WordPress installation.

3. **Activate the Plugin:**
   - Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

Upon activation, the HTML Sitemap plugin will:

1. **Create a Sitemap Page:**
   - Automatically create a page with the slug `sitemap` and assign it the HTML Sitemap template.
   - If a page with the slug `sitemap` already exists, the plugin will append to its content the `[sitemap-html-dated]` shortcode.
   - The rewrite rules are flushed.

   **WordPress VIP**: On this hosting environment, flushing the rewrite rules requires a manual action: 
   Go to Settings > Permalinks, and hit Save Changes.

2. **Generate Sitemap Content:**
   - Organizes and displays published posts by year, month, and day on the sitemap page.

3. **Automatic Updates:**
   - Schedules daily updates to ensure the sitemap remains current with new or updated content.

## Uninstallation

When uninstalling the plugin:

1. **Manual Cleanup (if necessary):**
   - You can manually remove the sitemap page, if desired.

## Frequently Asked Questions

**Q: Can I include custom post types in the sitemap?**  
**A:** Yes! By default, the plugin includes only the `post` post type. You can modify the `sitemap_html_post_types` filter to include additional post types as needed.

**Q: Do I need to manually flush permalinks after activation?**  
**A:** No. The plugin automatically flushes permalinks after creating or updating the sitemap page upon activation, ensuring that rewrite rules are up to date without manual intervention.

**Q: How does caching work in the plugin?**  
**A:** The plugin caches sitemap data to optimize performance and reduce database queries. It utilizes WordPress options or large options if available (`wlo_update_option` and `wlo_get_option`) for efficient data handling.

**Q: Is there a way to exclude specific posts or pages from the sitemap?**  
**A:** Currently, the plugin includes all published posts by default. To exclude specific content, you can extend the plugin's functionality by modifying the query parameters within the `Posts` class. Contributing custom filters to the plug-in is also welcomed.

## Changelog

### 1.0.0
- Initial release.

## Support

If you encounter any issues or have questions about the HTML Sitemap plugin, please reach out to our support team or visit our [GitHub repository](https://github.com/xwp/sitemap-html).

## Contributing

Contributions are welcome! Please follow the standard GitHub workflow:

1. Fork the repository.
2. Create a feature branch.
3. Commit your changes.
4. Push to the branch.
5. Open a pull request.

Please ensure that your code adheres to the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

## License

This plugin is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

---
