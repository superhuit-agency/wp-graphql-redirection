<?php

namespace WPGraphQLRedirection;

use Red_Item;
use WordPress_Module;

/**
 * Gets the redirects exposed in the schema: only active ones, i.e. enabled redirects
 * in enabled groups of the WordPress module (what Red_Item::get_all_for_module() returns).
 */
class ActiveRedirects {

	/**
	 * Get all active redirects, ordered by position.
	 *
	 * @return Red_Item[]
	 */
	public static function get_all() {
		return Red_Item::get_all_for_module( WordPress_Module::MODULE_ID );
	}

	/**
	 * Get the active redirects that may match a URL, in the order Redirection checks them.
	 *
	 * Red_Item::get_for_matched_url() only checks the redirect status, not its group.
	 *
	 * @param string $url URL to match.
	 * @return Red_Item[]
	 */
	public static function get_for_matched_url( $url ) {
		$items = Red_Item::get_for_matched_url( $url );

		if ( empty( $items ) ) {
			return [];
		}

		$group_ids = self::get_group_ids();

		return array_values( array_filter( $items, function( $item ) use ( $group_ids ) {
			return isset( $group_ids[ $item->get_group_id() ] );
		} ) );
	}

	/**
	 * Get the IDs of the enabled groups of the WordPress module, as keys.
	 * Cached for the request, per site (the table prefix changes with switch_to_blog()).
	 *
	 * @return array<int, int>
	 */
	private static function get_group_ids() {
		global $wpdb;
		static $group_ids = [];

		if ( ! isset( $group_ids[ $wpdb->prefix ] ) ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}redirection_groups WHERE module_id=%d AND status='enabled'", WordPress_Module::MODULE_ID ) );
			$group_ids[ $wpdb->prefix ] = array_flip( array_map( 'intval', $ids ) );
		}

		return $group_ids[ $wpdb->prefix ];
	}
}
