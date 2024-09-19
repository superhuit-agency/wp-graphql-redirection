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
							/**
							 * Replicates what Redirection does to check if the current URL is redirected
							 * @see https://github.com/johngodley/redirection/blob/8a4b91fa8eabdf0c0d9d03cbbddcbfb0d91e046d/modules/wordpress.php#L334-L340
							 */
							$isRedirected = false;
							$original_url = $source->link;
							$decoded_url  = rawurldecode( $original_url );

							$items = Red_Item::get_for_matched_url( $decoded_url );

							$i = 0;
							while ( $isRedirected === false && $i < count($items) ) {
								$action       = $items[$i]->get_match( $decoded_url, $original_url );
								$isRedirected = $action !== false;
								$i++;
							}

							return $isRedirected;
						},
					],
				],
			]
		);
	}
}
