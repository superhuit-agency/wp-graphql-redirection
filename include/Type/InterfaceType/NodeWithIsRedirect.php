<?php
namespace WPGraphQL\Type\InterfaceType;

use Red_Item;

class NodeWithIsRedirected {

	/**
	 * Registers the NodeWithIsRedirected Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {

		register_graphql_interface_type(
			'NodeWithIsRedirected',
			[
				'description' => __( 'A node with the isRedirected property', 'wpgraphql-redirection' ),
				'fields'      => [
					'isRedirected'  => [
						'type'        => 'Boolean',
						'description' => __( 'Is the current node URI redirected somewhere.', 'wpgraphql-redirection' ),
						'resolve'     => function ( $source, $args ) {
							$items = Red_Item::get_for_matched_url( $source->link );
							return count($items) > 0;
						},
					],
				],
			]
		);
	}
}
