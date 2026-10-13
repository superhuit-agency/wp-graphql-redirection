<?php

namespace WPGraphQLRedirection;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;

/**
 * Keeps `redirections` lookups out of WPGraphQL Smart Cache.
 *
 * WPGraphQL tags every query selecting `RootQuery.redirections` with the list type
 * `list:redirectionitemtype`, and Smart Cache appends each query key to one shared
 * collection under that tag. A headless frontend looks up `redirections(uri:)` for
 * every URL it renders, so that collection grows without bound and Smart Cache
 * rewrites it in full on every response.
 *
 * Nothing purges that tag when a redirect changes, so it is dropped, and responses
 * selecting `redirections` are no longer object-cached: they would otherwise go stale
 * (an empty result keeps hiding a redirect added later). The lookup itself is a
 * single query (which Redirection can cache itself), so caching the response gains little.
 *
 * @see https://github.com/superhuit-agency/wp-graphql-redirection/issues/8
 */
class SmartCache {

	/**
	 * Name of the root field this plugin registers.
	 */
	const FIELD_NAME = 'redirections';

	/**
	 * List type key WPGraphQL's QueryAnalyzer derives for `[RedirectionItemType]`.
	 */
	const LIST_TYPE_KEY = 'list:redirectionitemtype';

	public function __construct() {
		add_filter( 'graphql_cache_collection_get_list_types', [$this, 'filter_list_types'], 10, 1 );

		// Only WPGraphQL Smart Cache applies this filter.
		add_filter( 'graphql_cache_is_object_cache_enabled', [$this, 'filter_is_object_cache_enabled'], 10, 2 );
	}

	/**
	 * Whether WPGraphQL Smart Cache is loaded.
	 *
	 * @return bool
	 */
	public static function is_smart_cache_active() {
		return defined( 'WPGRAPHQL_SMART_CACHE_VERSION' );
	}

	/**
	 * Drops `list:redirectionitemtype` from the list types of the analyzed query.
	 *
	 * The list types also feed the X-GraphQL-Keys header, so they are left untouched
	 * unless Smart Cache is active.
	 *
	 * @param string[] $map List-type cache keys.
	 *
	 * @return string[]
	 */
	public function filter_list_types( $map ) {
		if ( ! self::is_smart_cache_active() ) {
			return $map;
		}

		return self::remove_list_type( $map );
	}

	/**
	 * @param string[] $map List-type cache keys.
	 *
	 * @return string[]
	 */
	public static function remove_list_type( $map ) {
		if ( ! is_array( $map ) ) {
			return $map;
		}

		return array_values( array_filter( $map, function( $key ) {
			return self::LIST_TYPE_KEY !== $key;
		} ) );
	}

	/**
	 * Disables Smart Cache's object cache for requests selecting `redirections`.
	 *
	 * @param bool                    $enabled Whether the object cache is enabled for the request.
	 * @param \WPGraphQL\Request|null $request The request being executed.
	 *
	 * @return bool
	 */
	public function filter_is_object_cache_enabled( $enabled, $request ) {
		if ( ! $enabled ) {
			return $enabled;
		}

		return ! self::request_selects_redirections( $request );
	}

	/**
	 * Whether any operation of the request (batched or not) selects `redirections`.
	 *
	 * Smart Cache decides per request, so one matching operation in a batch is enough.
	 *
	 * @param \WPGraphQL\Request|object|null $request The request being executed.
	 *
	 * @return bool
	 */
	public static function request_selects_redirections( $request ) {
		if ( ! is_object( $request ) || ! isset( $request->params ) ) {
			return false;
		}

		$params = is_array( $request->params ) ? $request->params : [ $request->params ];

		foreach ( $params as $param ) {
			if ( ! is_object( $param ) ) {
				continue;
			}

			$operation = isset( $param->operation ) && is_string( $param->operation ) ? $param->operation : null;

			if ( self::query_selects_redirections( self::get_query_string( $param ), $operation ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a GraphQL document selects `RootQuery.redirections`.
	 *
	 * Looks at the root selections of the operation to execute, following fragment
	 * spreads and inline fragments. Returns false when the document can't be parsed,
	 * so Smart Cache keeps its default behaviour.
	 *
	 * @param string|null $query          The GraphQL document.
	 * @param string|null $operation_name The operation to execute, if the request names one.
	 *
	 * @return bool
	 */
	public static function query_selects_redirections( $query, $operation_name = null ) {
		// Field names are case-sensitive and can't be escaped: skip parsing documents that can't select it.
		if ( ! is_string( $query ) || false === strpos( $query, self::FIELD_NAME ) ) {
			return false;
		}

		try {
			$document = Parser::parse( $query, [ 'noLocation' => true ] );
		} catch ( \Throwable $e ) {
			return false;
		}

		$operations = [];
		$fragments  = [];

		foreach ( $document->definitions as $definition ) {
			if ( $definition instanceof OperationDefinitionNode ) {
				$operations[] = $definition;
			} elseif ( $definition instanceof FragmentDefinitionNode ) {
				$fragments[ $definition->name->value ] = $definition;
			}
		}

		// Only the named operation runs. If no operation matches the name, check them all.
		if ( ! empty( $operation_name ) ) {
			$named = array_filter( $operations, function( $operation ) use ( $operation_name ) {
				return null !== $operation->name && $operation->name->value === $operation_name;
			} );

			if ( ! empty( $named ) ) {
				$operations = $named;
			}
		}

		$visited = [];

		foreach ( $operations as $operation ) {
			if ( self::selection_set_selects_field( $operation->selectionSet, $fragments, $visited ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param SelectionSetNode|null                 $selection_set The selections to look into.
	 * @param array<string,FragmentDefinitionNode> $fragments     The document's fragments, by name.
	 * @param array<string,bool>                   $visited       The fragments already looked into.
	 *
	 * @return bool
	 */
	private static function selection_set_selects_field( $selection_set, array $fragments, array &$visited ) {
		if ( ! $selection_set instanceof SelectionSetNode ) {
			return false;
		}

		foreach ( $selection_set->selections as $selection ) {
			if ( $selection instanceof FieldNode ) {
				// Aliases don't matter: `name` is the field, `alias` the response key.
				if ( self::FIELD_NAME === $selection->name->value ) {
					return true;
				}
			} elseif ( $selection instanceof InlineFragmentNode ) {
				if ( self::selection_set_selects_field( $selection->selectionSet, $fragments, $visited ) ) {
					return true;
				}
			} elseif ( $selection instanceof FragmentSpreadNode ) {
				$name = $selection->name->value;

				// A fragment's selections don't change, so each one is looked into once (also breaks cycles).
				if ( isset( $visited[ $name ] ) || ! isset( $fragments[ $name ] ) ) {
					continue;
				}
				$visited[ $name ] = true;

				if ( self::selection_set_selects_field( $fragments[ $name ]->selectionSet, $fragments, $visited ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * The query string of an operation, resolving Smart Cache persisted queries sent by ID only.
	 *
	 * @param \GraphQL\Server\OperationParams|object $param The operation params.
	 *
	 * @return string|null
	 */
	private static function get_query_string( $param ) {
		if ( ! empty( $param->query ) && is_string( $param->query ) ) {
			return $param->query;
		}

		// Smart Cache resolves a single operation's `queryId` into `query` before execution,
		// but not those of batched operations, nor the `documentId` / `id` aliases.
		if ( empty( $param->queryId ) || ! is_string( $param->queryId ) || ! class_exists( 'WPGraphQL\SmartCache\Document' ) ) {
			return null;
		}

		try {
			$query = ( new \WPGraphQL\SmartCache\Document() )->get( $param->queryId );
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_string( $query ) ? $query : null;
	}
}
