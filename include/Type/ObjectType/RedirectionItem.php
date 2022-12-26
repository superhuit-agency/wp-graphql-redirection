<?php

namespace WPGraphQLRedirection\Type\ObjectType;

class RedirectionItem {

	/**
	 * Register the RedirectionItem type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type( 'RedirectionItemType', [
			'description' => __( 'An Post Type object', 'wpgraphql-redirection' ),
			'interfaces'  => [],
			'fields'      => [
				'code'   => [ 'type' => 'Int' ],
				'target' => [ 'type' => 'String' ],
				'url'    => [ 'type' => 'String' ],
			]
		]);
	}
}
