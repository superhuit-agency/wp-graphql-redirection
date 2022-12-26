<?php
/**
 * Plugin Name:       WP GraphQL Redirection
 * Plugin URI:        https://github.com/superhuit-agency/wp-graphql-redirection
 * Description:       Exposes Redirection plugin in the GraphQL schema.
 * Author:            superhuit
 * Author URI:        https://www.superhuit.ch
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Text Domain:       wpgraphql-redirection
 * Requires at least: 5.0
 * Tested up to:      6.1
 *
 * @package WPGraphQLRedirection
 * @category Core
 * @author Superhuit, Kuuak
 * @version 1.0.0
 */

namespace WPGraphQLRedirection;

use Red_Item;
use WPGraphQLRedirection\Type\ObjectType\RedirectionItem;
use WPGraphQLRedirection\Model\RedirectionItem as RedirectionItemModel;

// Exit if accessed directly.
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

class WPGraphQLRedirection {
	/**
	 * WPGraphQLRedirection constructor.
	 *
	 * @return object|WPGraphQLRedirection - The one true WPGraphQLRedirection
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {

		if ( !$this->can_load_plugin() ) {
			$this->show_admin_notice();

			// Bail
			return;
		}

		/**
		 * Setup plugin constants
		 *
		 * @since 1.0.0
		 */
		$this->setup_constants();

		/**
		 * Included required files
		 *
		 * @since 1.0.0
		 */
		$this->setup_autoload();

		/**
		 * Initialize the plugin
		 *
		 * @since 1.0.0
		 */
		$this->init();
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since  1.0.0
	 * @return void
	 */
	private function setup_constants() {
		// Plugin version.
		if (!defined('WP_GRAPHQL_REDIRECTION_VERSION')) {
			define('WP_GRAPHQL_REDIRECTION_VERSION', '1.0.0');
		}

		// Plugin Folder Path.
		if (!defined('WP_GRAPHQL_REDIRECTION_PLUGIN_DIR')) {
			define('WP_GRAPHQL_REDIRECTION_PLUGIN_DIR', plugin_dir_path(__FILE__));
		}

		// Plugin Folder URL.
		if (!defined('WP_GRAPHQL_REDIRECTION_PLUGIN_URL')) {
			define('WP_GRAPHQL_REDIRECTION_PLUGIN_URL', plugin_dir_url(__FILE__));
		}

		// Plugin Root File.
		if (!defined('WP_GRAPHQL_REDIRECTION_PLUGIN_FILE')) {
			define('WP_GRAPHQL_REDIRECTION_PLUGIN_FILE', __FILE__);
		}

		// Whether to autoload the files or not.
		if (!defined('WP_GRAPHQL_REDIRECTION_AUTOLOAD')) {
			define('WP_GRAPHQL_REDIRECTION_AUTOLOAD', true);
		}

		// Whether to run the plugin in debug mode. Default is false.
		if (!defined('WP_GRAPHQL_REDIRECTION_DEBUG')) {
			define('WP_GRAPHQL_REDIRECTION_DEBUG', false);
		}
	}

	/**
	 * Include required files.
	 *
	 * Uses composer's autoload
	 *
	 * @access private
	 * @since  1.0.0
	 * @return void
	 */
	private function setup_autoload() {
		/**
		 * WP_GRAPHQL_REDIRECTION_AUTOLOAD can be set to "false" to prevent the autoloader from running.
		 * In most cases, this is not something that should be disabled, but some environments
		 * may bootstrap their dependencies in a global autoloader that will autoload files
		 * before we get to this point, and requiring the autoloader again can trigger fatal errors.
		 *
		 * The codeception tests are an example of an environment where adding the autoloader again causes issues
		 * so this is set to false for tests.
		 */
		if (defined('WP_GRAPHQL_REDIRECTION_AUTOLOAD') && true === WP_GRAPHQL_REDIRECTION_AUTOLOAD) {
			require_once WP_GRAPHQL_REDIRECTION_PLUGIN_DIR . 'vendor/autoload.php';
		}
	}

	private function init() {

		new I18n();

		add_action( 'graphql_register_types', [$this, 'register_types'] );
	}

	public function register_types() {

		RedirectionItem::register_type();

		register_graphql_field( 'RootQuery', 'redirections', [
			'type'        => [ 'list_of' => 'RedirectionItemType' ],
			'args' => [
				'uri' => [
					'type' => 'String',
					'description' => __( 'Unique Resource Identifier in the form of a path or permalink for a node. Ex: "/hello-world"', 'wpgraphql-redirection' ),
				],
			],
			'description' => __( 'List of redirections.', 'wpgraphql-redirection' ),
			'resolve'     => function($source, $args, $context, $info) {

				$items = isset($args['uri'])
					? Red_Item::get_for_matched_url( $args['uri'] )
					: Red_Item::get_all();


				if ( isset($args['uri']) ) {
					$items = array_filter($items, function($item) use ($args) {
						return $item->get_match($args['uri']);
					});
				}

				return array_map( function($item) use ($args) {
					return new RedirectionItemModel($item, $args['uri'] ?? null);
				}, $items );
			},
		] );

	}

	/**
	 * Check whether ACF and WPGraphQL are active, and whether the minimum version requirement has been
	 * met
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function can_load_plugin() {

		// Is WPGraphQL active?
		if ( ! class_exists( 'WPGraphQL' ) ) {
			return false;
		}

		// Is Redirection active?
		if ( ! class_exists( 'RED_ITEM' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Show admin notice to admins if this plugin is active but either
	 * WPGraphQL and/or Redirection are not active
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	function show_admin_notice() {

		/**
		 * For users with lower capabilities, don't show the notice
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		add_action(
			'admin_notices',
			function() {
				printf(
					'<div class="error notice"><p>%s</p></div>',
					esc_html__( 'Both WPGraphQL (v1.8+) and Redirection (v5.0+) plugins must be active for "wp-graphql-redirection" to work', 'wp-graphiql-acf' )
				);
			}
		);
	}
}


/**
 * Instantiate the WPGraphQLRedirection class on graphql_init
 *
 * @return WPGraphQLRedirection
 */
function graphql_init_redirection() {
	return new WPGraphQLRedirection();
}

add_action( 'graphql_init', __NAMESPACE__.'\graphql_init_redirection' );
