# Auto load next Post for WordPress

That plugin provides the ability to load the next post when the user is on single post page and scrolls to the bottom of the page.

The plugin has dependency of the currently active theme. In order to work properly you should provide the following:

- The main entry of the article content (wrapper). This is CSS selector (class or id) or HTML element - anything which jQuery could work with.
- The template part slug used for showing the article - this is usually located in the theme folder and it is called from within the single.php or singular.php - something like "public/template/content/template"

In most cases the plugin will guess the template part, using get_template_part filter, but unfortunately that is not always possible with all different themes out there.

For the wrapper, if not provided with one, the plugin will try to use "main" HTML element.

Both (article wrapper and theme slug) could be entered via admin settings of the plugin.

Some themes do not use footer, the JS scroll event is triggered 300px before end of the content wrapper is reached which is usually enough for this to work properly.
