<?php
/**
 * Elgg admin functions.
 * Functions for adding and manipulating options on the admin panel.
 *
 * @package Elgg
 * @subpackage Core
 */

/**
 * Get the admin users 
 *
 * @param array $options Options array, @see elgg_get_entities() for parameters
 *
 * @return mixed Array of admin users or false on failure. If a count, returns int.
 * @since 1.8.0
 */
function elgg_get_admins(array $options = array()) {
	global $CONFIG;

	if (isset($options['joins'])) {
		if (!is_array($options['joins'])) {
			$options['joins'] = array($options['joins']);
		}
		$options['joins'][] = "join {$CONFIG->dbprefix}users_entity u on e.guid=u.guid";
	} else {
		$options['joins'] = array("join {$CONFIG->dbprefix}users_entity u on e.guid=u.guid");
	}

	if (isset($options['wheres'])) {
		if (!is_array($options['wheres'])) {
			$options['wheres'] = array($options['wheres']);
		}
		$options['wheres'][] = "u.admin = 'yes'";
	} else {
		$options['wheres'][] = "u.admin = 'yes'";
	}

	return elgg_get_entities($options);
}

/**
 * Write a persistent message to the admin view.
 * Useful to alert the admin to take a certain action.
 * The id is a unique ID that can be cleared once the admin
 * completes the action.
 *
 * eg: add_admin_notice('twitter_services_no_api',
 * 	'Before your users can use Twitter services on this site, you must set up
 * 	the Twitter API key in the <a href="link">Twitter Services Settings</a>');
 *
 * @param string $id      A unique ID that your plugin can remember
 * @param string $message Body of the message
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_add_admin_notice($id, $message) {
	if ($id && $message) {
		if (elgg_admin_notice_exists($id)) {
			return false;
		}
		$admin_notice = new ElggObject();
		$admin_notice->subtype = 'admin_notice';
		// admins can see ACCESS_PRIVATE but no one else can.
		$admin_notice->access_id = ACCESS_PRIVATE;
		$admin_notice->admin_notice_id = $id;
		$admin_notice->description = $message;

		return $admin_notice->save();
	}

	return FALSE;
}


/**
 * Remove an admin notice by ID.
 *
 * eg In actions/twitter_service/save_settings:
 * 	if (is_valid_twitter_api_key()) {
 * 		delete_admin_notice('twitter_services_no_api');
 * 	}
 *
 * @param string $id The unique ID assigned in add_admin_notice()
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_delete_admin_notice($id) {
	if (!$id) {
		return FALSE;
	}
	$result = TRUE;
	$notices = elgg_get_entities_from_metadata(array(
		'metadata_name' => 'admin_notice_id',
		'metadata_value' => $id
	));

	if ($notices) {
		// in case a bad plugin adds many, let it remove them all at once.
		foreach ($notices as $notice) {
			$result = ($result && $notice->delete());
		}
		return $result;
	}
	return FALSE;
}

/**
 * List all admin messages.
 *
 * @param int $limit Limit
 *
 * @return array List of admin notices
 * @since 1.8.0
 */
function elgg_get_admin_notices($limit = 10) {
	return elgg_get_entities_from_metadata(array(
		'type' => 'object',
		'subtype' => 'admin_notice',
		'limit' => $limit
	));
}

/**
 * Check if an admin notice is currently active.
 *
 * @param string $id The unique ID used to register the notice.
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_admin_notice_exists($id) {
	$notice = elgg_get_entities_from_metadata(array(
		'type' => 'object',
		'subtype' => 'admin_notice',
		'metadata_name_value_pair' => array('name' => 'admin_notice_id', 'value' => $id)
	));

	return ($notice) ? TRUE : FALSE;
}

/**
 * Add an admin area section or child section.
 * This is a wrapper for elgg_register_menu_item().
 *
 * Used in conjuction with http://elgg.org/admin/section_id/child_section style
 * page handler.
 *
 * The text of the menu item is obtained from elgg_echo(admin:$parent_id:$menu_id)
 *
 * This function handles registering the parent if it has not been registered.
 *
 * @param string $section    The menu section to add to
 * @param string $menu_id    The Unique ID of section
 * @param string $parent_id  If a child section, the parent section id
 * @param int    $priority   The menu item priority
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_register_admin_menu_item($section, $menu_id, $parent_id = NULL, $priority = 100) {

	// make sure parent is registered
	if ($parent_id && !elgg_is_menu_item_registered($menu_id, $parent_id)) {
		elgg_register_admin_menu_item($section, $parent_id);
	}

	// in the admin section parents never have links
	if ($parent_id) {
		$href = "admin/$parent_id/$menu_id";
	} else {
		$href = NULL;
	}

	$name = $menu_id;
	if ($parent_id) {
		$name = "$parent_id:$name";
	}

	return elgg_register_menu_item('page', array(
		'name' => $name,
		'href' => $href,
		'text' => elgg_echo("admin:$name"),
		'context' => 'admin',
		'parent_name' => $parent_id,
		'priority' => $priority,
		'section' => $section
	));
}

/**
 * Initialise the admin backend.
 *
 * @return void
 */
function admin_init() {
	elgg_register_action('admin/user/ban', '', 'admin');
	elgg_register_action('admin/user/unban', '', 'admin');
	elgg_register_action('admin/user/delete', '', 'admin');
	elgg_register_action('admin/user/resetpassword', '', 'admin');
	elgg_register_action('admin/user/makeadmin', '', 'admin');
	elgg_register_action('admin/user/removeadmin', '', 'admin');

	elgg_register_action('admin/site/update_basic', '', 'admin');
	elgg_register_action('admin/site/update_advanced', '', 'admin');

	elgg_register_action('admin/menu/save', '', 'admin');

	elgg_register_action('admin/delete_admin_notice', '', 'admin');

	elgg_register_action('admin/plugins/simple_update_states', '', 'admin');

	elgg_register_action('profile/fields/reset', '', 'admin');
	elgg_register_action('profile/fields/add', '', 'admin');
	elgg_register_action('profile/fields/edit', '', 'admin');
	elgg_register_action('profile/fields/delete', '', 'admin');
	elgg_register_action('profile/fields/reorder', '', 'admin');

	elgg_register_simplecache_view('js/admin');

	// administer
	// dashboard
	elgg_register_menu_item('page', array(
		'name' => 'dashboard',
		'href' => 'admin/dashboard',
		'text' => elgg_echo('admin:dashboard'),
		'context' => 'admin',
		'priority' => 10,
		'section' => 'administer'
	));
	// statistics
	elgg_register_admin_menu_item('administer', 'statistics', null, 20);
	elgg_register_admin_menu_item('administer', 'overview', 'statistics');

	// users
	elgg_register_admin_menu_item('administer', 'users', null, 20);
	elgg_register_admin_menu_item('administer', 'online', 'users', 10);
	elgg_register_admin_menu_item('administer', 'newest', 'users', 20);
	elgg_register_admin_menu_item('administer', 'add', 'users', 30);

	// configure
	// plugins
	elgg_register_admin_menu_item('configure', 'plugins', null, 10);
	elgg_register_admin_menu_item('configure', 'simple', 'plugins', 10);
	elgg_register_admin_menu_item('configure', 'advanced', 'plugins', 20);

	// settings
	elgg_register_admin_menu_item('configure', 'basic', 'settings', 10);
	elgg_register_admin_menu_item('configure', 'advanced', 'settings', 20);
	elgg_register_admin_menu_item('configure', 'menu_items', 'appearance', 30);
	elgg_register_admin_menu_item('configure', 'profile_fields', 'appearance', 40);
	// default widgets is added via an event handler elgg_default_widgets_init() in widgets.php
	// because it requires additional setup.

	// plugin settings are added in elgg_admin_add_plugin_settings_menu() via the admin page handler
	// for performance reasons.

	if (elgg_is_admin_logged_in()) {
		elgg_register_menu_item('topbar', array(
			'name' => 'administration',
			'href' => 'admin',
			'text' => elgg_view_icon('settings') . elgg_echo('admin'),
			'priority' => 100,
			'section' => 'alt',
		));
	}
			
	// widgets
	$widgets = array('online_users', 'new_users', 'content_stats', 'admin_welcome');
	foreach ($widgets as $widget) {
		elgg_register_widget_type(
				$widget,
				elgg_echo("admin:widget:$widget"),
				elgg_echo("admin:widget:$widget:help"),
				'admin'
		);
	}

	// automatic adding of widgets for admin
	elgg_register_event_handler('make_admin', 'user', 'elgg_add_admin_widgets');

	elgg_register_page_handler('admin', 'admin_settings_page_handler');
	elgg_register_page_handler('admin_plugin_screenshot', 'admin_plugin_screenshot_page_handler');
}

/**
 * Create the plugin settings submenu.
 *
 * This is done in a separate function called from the admin
 * page handler because of performance concerns.
 *
 * @return void
 * @access private
 */
function elgg_admin_add_plugin_settings_menu() {

	$active_plugins = elgg_get_plugins('active');
	if (!$active_plugins) {
		// nothing added because no items
		return FALSE;
	}

	foreach ($active_plugins as $plugin) {
		$plugin_id = $plugin->getID();
		if (elgg_view_exists("settings/$plugin_id/edit")) {
			elgg_register_menu_item('page', array(
				'name' => $plugin_id,
				'href' => "admin/plugin_settings/$plugin_id",
				'text' => $plugin->manifest->getName(),
				'parent_name' => 'settings',
				'context' => 'admin',
				'section' => 'configure',
		 ));
		}
	}
}

/**
 * Handles any set up required for administration pages
 * @access private
 */
function admin_pagesetup() {
	if (elgg_in_context('admin')) {
		$url = elgg_get_simplecache_url('css', 'admin');
		elgg_register_css('elgg.admin', $url);
		elgg_load_css('elgg.admin');
		elgg_unregister_css('elgg');

		// setup footer menu
		elgg_register_menu_item('admin_footer', array(
			'name' => 'faq',
			'text' => elgg_echo('admin:footer:faq'),
			'href' => 'http://docs.elgg.org/wiki/Category:Administration_FAQ',
		));

		elgg_register_menu_item('admin_footer', array(
			'name' => 'manual',
			'text' => elgg_echo('admin:footer:manual'),
			'href' => 'http://docs.elgg.org/wiki/Administration_Manual',
		));

		elgg_register_menu_item('admin_footer', array(
			'name' => 'community_forums',
			'text' => elgg_echo('admin:footer:community_forums'),
			'href' => 'http://community.elgg.org/pg/groups/world/',
		));

		elgg_register_menu_item('admin_footer', array(
			'name' => 'blog',
			'text' => elgg_echo('admin:footer:blog'),
			'href' => 'http://blog.elgg.org/',
		));
	}
}

/**
 * Handle admin pages.  Expects corresponding views as admin/section/subsection
 *
 * @param array $page Array of pages
 *
 * @return void
 */
function admin_settings_page_handler($page) {

	admin_gatekeeper();
	elgg_admin_add_plugin_settings_menu();
	elgg_set_context('admin');

	elgg_unregister_css('elgg');
	$url = elgg_get_simplecache_url('js', 'admin');
	elgg_register_js('elgg.admin', $url);
	elgg_load_js('elgg.admin');

	elgg_register_js('jquery.jeditable', 'vendors/jquery/jquery.jeditable.mini.js');
	elgg_load_js('jquery.jeditable');

	// default to dashboard
	if (!isset($page[0]) || empty($page[0])) {
		$page = array('dashboard');
	}

	// was going to fix this in the page_handler() function but
	// it's commented to explicitly return a string if there's a trailing /
	if (empty($page[count($page) - 1])) {
		array_pop($page);
	}

	$vars = array('page' => $page);

	// special page for plugin settings since we create the form for them
	if ($page[0] == 'plugin_settings' && isset($page[1])
		&& elgg_view_exists("settings/{$page[1]}/edit")) {

		$view = 'admin/plugin_settings';
		$plugin = elgg_get_plugin_from_id($page[1]);
		$vars['plugin'] = $plugin;

		$title = elgg_echo("admin:{$page[0]}");
	} else {
		$view = 'admin/' . implode('/', $page);
		$title = elgg_echo("admin:{$page[0]}");
		if (count($page) > 1) {
			$title .= ' : ' . elgg_echo('admin:' .  implode(':', $page));
		}
	}

	// gets content and prevents direct access to 'components' views
	if ($page[0] == 'components' || !($content = elgg_view($view, $vars))) {
		$title = elgg_echo('admin:unknown_section');
		$content = elgg_echo('admin:unknown_section');
	}

	$body = elgg_view_layout('admin', array('content' => $content, 'title' => $title));
	echo elgg_view_page($title, $body, 'admin');
}

/**
 * Serves up screenshots for plugins from
 * elgg/admin_plugin_ss/<plugin_id>/<size>/<ss_name>.<ext>
 *
 * @param array $pages The pages array
 * @return true
 */
function admin_plugin_screenshot_page_handler($pages) {
	admin_gatekeeper();

	$plugin_id = elgg_extract(0, $pages);
	// only thumbnail or full.
	$size = elgg_extract(1, $pages, 'thumbnail');

	// the rest of the string is the filename
	$filename_parts = array_slice($pages, 2);
	$filename = implode('/', $filename_parts);
	$filename = sanitise_filepath($filename, false);

	$plugin = new ElggPlugin($plugin_id);
	if (!$plugin) {
		$file = elgg_get_root_dir() . '_graphics/icons/default/medium.png';
	} else {
		$file = $plugin->getPath() . $filename;
		if (!file_exists($file)) {
			$file = elgg_get_root_dir() . '_graphics/icons/default/medium.png';
		}
	}

	header("Content-type: image/jpeg");

	// resize to 100x100 for thumbnails
	switch ($size) {
		case 'thumbnail':
			echo get_resized_image_from_existing_file($file, 100, 100, true);
			break;

		case 'full':
		default:
			echo file_get_contents($file);
			break;
	}

	return true;
}

/**
 * Adds default admin widgets to the admin dashboard.
 */
function elgg_add_admin_widgets($event, $type, $user) {
	elgg_set_ignore_access(true);

	// check if the user already has widgets
	if (elgg_get_widgets($user->getGUID(), 'admin')) {
		return true;
	}

	// In the form column => array of handlers in order, top to bottom
	$adminWidgets = array(
		1 => array('online_users', 'new_users', 'content_stats'),
		2 => array('admin_welcome'),
	);
	
	foreach ($adminWidgets as $column => $handlers) {
		foreach ($handlers as $position => $handler) {
			$guid = elgg_create_widget($user->getGUID(), $handler, 'admin');
			if ($guid) {
				$widget = get_entity($guid);
				$widget->move($column, $position);
			}
		}
	}
	elgg_set_ignore_access(false);
}

elgg_register_event_handler('init', 'system', 'admin_init');
elgg_register_event_handler('pagesetup', 'system', 'admin_pagesetup', 1000);
