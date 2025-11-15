<?php

return [
    // Navigation
    'nav.home' => 'Home',
    'nav.feeds' => 'Feeds',
    'nav.suggest' => 'Suggest',
    'nav.articles' => 'Articles',
    'nav.categories' => 'Categories',
    'nav.tags' => 'Tags',
    'nav.topics' => 'Topics',
    'nav.logout' => 'Logout',
    'nav.feed_builder' => 'Feed Builder',
    
    // Common
    'common.search' => 'Search',
    'common.filter' => 'Filter',
    'common.search_placeholder' => 'Search...',
    'common.all_categories' => 'All Categories',
    'common.all_tags' => 'All Tags',
    'common.all_topics' => 'All Topics',
    'common.simplified' => 'Simplified',
    'common.save_filter' => 'Save Filter',
    'common.clear_filters' => 'Clear Filters',
    'common.cancel' => 'Cancel',
    'common.save' => 'Save',
    'common.delete' => 'Delete',
    'common.edit' => 'Edit',
    'common.add' => 'Add',
    'common.update' => 'Update',
    'common.name' => 'Name',
    'common.slug' => 'Slug',
    'common.description' => 'Description',
    'common.language' => 'Language',
    'common.status' => 'Status',
    'common.actions' => 'Actions',
    'common.in' => 'in',
    'common.at' => 'at',
    
    // Languages
    'lang.pt-BR' => 'Portuguese (Brazil)',
    'lang.en' => 'English',
    'lang.es' => 'Spanish',
    
    // Status
    'status.online' => 'Online',
    'status.offline' => 'Offline',
    'status.paused' => 'Paused',
    'status.pending' => 'Pending',
    'status.rejected' => 'Rejected',
    
    // Home page
    'home.title' => 'Latest articles',
    'home.no_items' => 'No items found. Try adjusting your search or filter.',
    'home.published_at' => 'Published on',
    'home.author' => 'Author',
    
    // Feeds page
    'feeds.title' => 'Feeds',
    'feeds.feed' => 'Feed',
    'feeds.categories' => 'Categories',
    'feeds.tags' => 'Tags',
    'feeds.topics' => 'Topics',
    'feeds.verification' => 'Verification',
    'feeds.update' => 'Update',
    'feeds.articles' => 'Articles',
    'feeds.items' => 'Items',
    'feeds.no_feeds' => 'No feeds found.',
    'feeds.verified' => 'Verif',
    'feeds.updated' => 'Updated',
    'feeds.never' => 'Never',
    
    // Suggest Feed page
    'suggest.title' => 'Suggest',
    'suggest.heading' => 'Suggest Feed',
    'suggest.description' => 'Know an interesting blog that should be in our aggregator? Suggest it here!',
    'suggest.form.title' => 'Title',
    'suggest.form.title_placeholder' => 'E.g.: John\'s Blog',
    'suggest.form.site_url' => 'Site URL',
    'suggest.form.site_url_help' => 'The main URL of the site/blog',
    'suggest.form.feed_url' => 'Feed URL (RSS/Atom)',
    'suggest.form.feed_url_help' => 'The URL of the blog\'s RSS/Atom file',
    'suggest.form.category' => 'Category',
    'suggest.form.tags' => 'Topics',
    'suggest.form.select_tag' => 'Select Topics',
    'suggest.form.captcha' => 'Verification Code',
    'suggest.form.captcha_placeholder' => 'Enter the code shown',
    'suggest.form.captcha_help' => 'Click the image to generate a new code',
    'suggest.form.submit' => 'Send Suggestion',
    'suggest.form.validating' => 'Validating feed...',
    
    // Feed Builder page
    'feed_builder.title' => 'Feed Builder',
    'feed_builder.categories' => 'Categories',
    'feed_builder.tags' => 'Tags',
    'feed_builder.topics' => 'Topics',
    'feed_builder.rss_feed' => 'RSS Feed',
    'feed_builder.json_feed' => 'JSON Feed',
    
    // Categories page
    'categories.title' => 'Categories',
    'categories.no_categories' => 'No categories found.',
    'categories.article' => 'article',
    'categories.articles' => 'articles',
    
    // Tags page
    'tags.title' => 'Topics',
    'tags.no_tags' => 'No tags found.',
    'tags.article' => 'article',
    'tags.articles' => 'articles',
    
    // Admin - Login
    'admin.login.title' => 'Login',
    'admin.login.username' => 'Username',
    'admin.login.password' => 'Password',
    'admin.login.submit' => 'Sign In',
    
    // Admin - Items
    'admin.items.title' => 'Manage Articles',
    'admin.items.feed' => 'Feed',
    'admin.items.feeds' => 'Feeds',
    'admin.items.author' => 'Author',
    'admin.items.published' => 'Published',
    'admin.items.unknown_author' => 'Unknown',
    'admin.items.no_items' => 'No items found. Try adjusting your search or filter.',
    
    // Admin - Feeds
    'admin.feeds.title' => 'Manage Feeds',
    'admin.feeds.add_new' => 'Add New Feed',
    'admin.feeds.filter_status' => 'Filter by Status',
    'admin.feeds.all_status' => 'All Statuses',
    'admin.feeds.bulk_status' => 'Change Status',
    'admin.feeds.bulk_categories' => 'Edit Categories',
    'admin.feeds.bulk_tags' => 'Edit Tags',
    'admin.feeds.selected' => 'selected',
    'admin.feeds.no_feeds' => 'No feeds found.',
    'admin.feeds.try_filter' => 'Try another filter or ',
    'admin.feeds.add_first' => 'Add your first feed using the button above.',
    'admin.feeds.delete_confirm' => 'Are you sure you want to delete this feed? All feed items will also be deleted. This action cannot be undone.',
    'admin.feeds.delete_modal_title' => 'Delete Feed',
    'admin.feeds.bulk_categories_modal_title' => 'Bulk Edit Categories',
    'admin.feeds.bulk_categories_description' => 'Select the categories you want to apply to',
    'admin.feeds.bulk_categories_note' => 'selected feed(s). Current categories will be replaced.',
    'admin.feeds.apply_categories' => 'Apply Categories',
    'admin.feeds.bulk_tags_modal_title' => 'Bulk Edit Topics',
    'admin.feeds.bulk_tags_description' => 'Select the topics you want to apply to',
    'admin.feeds.bulk_tags_note' => 'selected feed(s). Current topics will be replaced.',
    'admin.feeds.apply_tags' => 'Apply Tags',
    'admin.feeds.status_updated' => 'Feed status successfully updated to:',
    
    // Admin - Feed Form
    'admin.feed_form.edit_title' => 'Edit Feed',
    'admin.feed_form.add_title' => 'Add New Feed',
    'admin.feed_form.site_title' => 'Site Title',
    'admin.feed_form.feed_url' => 'Feed URL',
    'admin.feed_form.site_url' => 'Site URL',
    'admin.feed_form.feed_type' => 'Feed Type',
    'admin.feed_form.auto_detect' => 'Auto-detect feed type',
    'admin.feed_form.feed_type_help' => 'If not selected, the system will automatically detect the feed type.',
    'admin.feed_form.categories' => 'Categories',
    'admin.feed_form.categories_help' => 'Hold Ctrl/Cmd to select multiple categories',
    'admin.feed_form.tags' => 'Topics',
    'admin.feed_form.tags_help' => 'Hold Ctrl/Cmd to select multiple tags',
    'admin.feed_form.update' => 'Update Feed',
    'admin.feed_form.add' => 'Add Feed',
    'admin.feed_form.saving' => 'Saving...',
    
    // Admin - Categories
    'admin.categories.title' => 'Manage Categories',
    'admin.categories.new' => 'New Category',
    'admin.categories.no_categories' => 'No categories registered',
    'admin.categories.feeds' => 'feeds',
    'admin.categories.delete_confirm' => 'Are you sure you want to delete this category?',
    
    // Admin - Category Form
    'admin.category_form.name' => 'Name',
    'admin.category_form.slug' => 'Slug',
    'admin.category_form.slug_help' => 'Leave blank to auto-generate',
    
    // Admin - Tags
    'admin.tags.title' => 'Manage Tags',
    'admin.tags.new' => 'New Tag',
    'admin.tags.no_tags' => 'No tags registered',
    'admin.tags.feeds' => 'feeds',
    'admin.tags.delete_confirm' => 'Are you sure you want to delete this tag?',
    
    // Footer
    'footer.description' => 'Directory and search engine for personal blogs updated in real time.',
    'footer.badge' => 'Badge',
    'footer.copied' => 'Copied!',
    'footer.copy_error' => 'Could not copy the code. Please try again.',
    
    // Meta
    'meta.description' => 'Directory of personal blogs based on RSS 1.0/2.0, Atom, JSON, XML feeds.',
    
    // Alerts and Messages
    'alert.error' => 'Error',
    'alert.success' => 'Success',
    'alert.warning' => 'Warning',
    'alert.info' => 'Information',
    
    // Pagination
    'pagination.previous' => 'Previous',
    'pagination.next' => 'Next',
];