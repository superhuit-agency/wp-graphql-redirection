<?php
/**
 * Dependency-free tests for the WPGraphQL Smart Cache integration (no WordPress needed).
 *
 * Run after `composer install` (dev dependencies provide webonyx/graphql-php):
 *
 *     composer test
 *
 * @package WPGraphQLRedirection
 */

use GraphQL\Server\OperationParams;
use WPGraphQLRedirection\SmartCache;

require __DIR__ . '/../vendor/autoload.php';

// Minimal WordPress stub: records the hooks the class registers.
$GLOBALS['wp_filters'] = [];
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_filters'][ $hook ] = [ $callback, $priority, $accepted_args ];
	return true;
}

$failures = 0;
$count    = 0;

function check( $label, $expected, $actual ) {
	global $failures, $count;
	$count++;

	if ( $expected === $actual ) {
		echo "ok   - $label\n";
		return;
	}

	$failures++;
	echo "FAIL - $label\n       expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

function request( $params ) {
	$request         = new stdClass();
	$request->params = $params;
	return $request;
}

function params( $query, $operation = null ) {
	return OperationParams::create( [ 'query' => $query, 'operationName' => $operation ] );
}

$redirections = 'query redirectionsQuery($uri: String!) { redirections(uri: $uri) { code target } }';
$page         = 'query PageQuery($uri: String!) { nodeByUri(uri: $uri) { id ... on NodeWithIsRedirected { isRedirected } } }';

echo "# query_selects_redirections\n";

$cases = [
	'frontend lookup'                                  => [ $redirections, null, true ],
	'shorthand query'                                  => [ '{ redirections { code } }', null, true ],
	'list all redirections'                            => [ 'query { redirections { url target } }', null, true ],
	'aliased field'                                    => [ 'query { r: redirections(uri: "/a") { code } }', null, true ],
	'mixed with other root fields'                     => [ 'query { generalSettings { title } redirections(uri: "/a") { code } }', null, true ],
	'with @include directive'                          => [ 'query ($on: Boolean!) { redirections @include(if: $on) { code } }', null, true ],
	'root inline fragment'                             => [ 'query { ... on RootQuery { redirections { code } } }', null, true ],
	'root inline fragment without type condition'      => [ 'query { ... { redirections { code } } }', null, true ],
	'root fragment spread'                             => [ 'query { ...Root } fragment Root on RootQuery { redirections { code } }', null, true ],
	'nested fragment spreads'                          => [ 'query { ...A } fragment A on RootQuery { ...B } fragment B on RootQuery { redirections { code } }', null, true ],
	'cyclic fragments do not loop'                     => [ 'query { ...A } fragment A on RootQuery { ...B } fragment B on RootQuery { ...A }', null, false ],
	'unknown fragment spread'                          => [ 'query { ...Missing } # redirections', null, false ],
	'page query'                                       => [ $page, null, false ],
	'empty query'                                      => [ '', null, false ],
	'null query'                                       => [ null, null, false ],
	'name only in a comment'                           => [ "query { posts { nodes { id } } } # redirections", null, false ],
	'name only in a string argument'                   => [ 'query { nodeByUri(uri: "/redirections") { id } }', null, false ],
	'name only as an operation name'                   => [ 'query redirections { posts { nodes { id } } }', null, false ],
	'name only as a variable'                          => [ 'query ($redirections: String) { nodeByUri(uri: $redirections) { id } }', null, false ],
	'nested field with the same name'                  => [ 'query { viewer { redirections } }', null, false ],
	'fragment on a nested type'                        => [ 'query { viewer { ...F } } fragment F on User { redirections }', null, false ],
	'unused fragment selecting it'                     => [ 'query { posts { nodes { id } } } fragment F on RootQuery { redirections { code } }', null, false ],
	'different casing'                                 => [ 'query { Redirections { code } }', null, false ],
	'unparsable document'                              => [ 'query { redirections { code }', null, false ],
	'named operation selecting it'                     => [ "query A { posts { nodes { id } } }\nquery B { redirections { code } }", 'B', true ],
	'named operation not selecting it'                 => [ "query A { posts { nodes { id } } }\nquery B { redirections { code } }", 'A', false ],
	'unknown operation name falls back to all'         => [ "query A { posts { nodes { id } } }\nquery B { redirections { code } }", 'C', true ],
	'no operation name, several operations'            => [ "query A { posts { nodes { id } } }\nquery B { redirections { code } }", null, true ],
];

foreach ( $cases as $label => list( $query, $operation, $expected ) ) {
	check( $label, $expected, SmartCache::query_selects_redirections( $query, $operation ) );
}

echo "# request_selects_redirections\n";

check( 'single request selecting it', true, SmartCache::request_selects_redirections( request( params( $redirections ) ) ) );
check( 'single request not selecting it', false, SmartCache::request_selects_redirections( request( params( $page ) ) ) );
check( 'batch with one operation selecting it', true, SmartCache::request_selects_redirections( request( [ params( $page ), params( $redirections ) ] ) ) );
check( 'batch with no operation selecting it', false, SmartCache::request_selects_redirections( request( [ params( $page ), params( $page ) ] ) ) );
check( 'operation name is honoured', false, SmartCache::request_selects_redirections( request( params( "query A { posts { nodes { id } } }\nquery B { redirections { code } }", 'A' ) ) ) );
check( 'persisted query without Smart Cache', false, SmartCache::request_selects_redirections( request( OperationParams::create( [ 'queryId' => 'abc' ] ) ) ) );
check( 'null request', false, SmartCache::request_selects_redirections( null ) );
check( 'request without params', false, SmartCache::request_selects_redirections( new stdClass() ) );

echo "# hooks\n";

$smart_cache = new SmartCache();

check( 'list types filter registered', [ [ $smart_cache, 'filter_list_types' ], 10, 1 ], $GLOBALS['wp_filters']['graphql_cache_collection_get_list_types'] ?? null );
check( 'object cache filter registered', [ [ $smart_cache, 'filter_is_object_cache_enabled' ], 10, 2 ], $GLOBALS['wp_filters']['graphql_cache_is_object_cache_enabled'] ?? null );

echo "# filter_is_object_cache_enabled\n";

check( 'disabled for a redirections lookup', false, $smart_cache->filter_is_object_cache_enabled( true, request( params( $redirections ) ) ) );
check( 'enabled for other queries', true, $smart_cache->filter_is_object_cache_enabled( true, request( params( $page ) ) ) );
check( 'stays disabled when already disabled', false, $smart_cache->filter_is_object_cache_enabled( false, request( params( $page ) ) ) );
check( 'enabled when the request is unknown', true, $smart_cache->filter_is_object_cache_enabled( true, null ) );

echo "# filter_list_types\n";

$list_types = [ 'list:post', 'list:redirectionitemtype', 'list:page' ];

check( 'unchanged without Smart Cache', $list_types, $smart_cache->filter_list_types( $list_types ) );

define( 'WPGRAPHQL_SMART_CACHE_VERSION', 'test' );

check( 'redirection list type removed with Smart Cache', [ 'list:post', 'list:page' ], $smart_cache->filter_list_types( $list_types ) );
check( 'only list type removed', [], $smart_cache->filter_list_types( [ 'list:redirectionitemtype' ] ) );
check( 'other list types untouched', [ 'list:post' ], $smart_cache->filter_list_types( [ 'list:post' ] ) );
check( 'empty map', [], $smart_cache->filter_list_types( [] ) );
check( 'non-array map passed through', null, $smart_cache->filter_list_types( null ) );

echo "\n" . ( $count - $failures ) . "/$count passed\n";

exit( $failures > 0 ? 1 : 0 );
