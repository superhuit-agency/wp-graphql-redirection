<?php
/**
 * Dependency-free tests for ActiveRedirects (no WordPress or Redirection needed).
 *
 *     composer test
 *
 * @package WPGraphQLRedirection
 */

use WPGraphQLRedirection\ActiveRedirects;

require __DIR__ . '/../vendor/autoload.php';

// Redirection stubs. Groups and redirects live in these arrays, keyed by ID.
$GLOBALS['groups']    = [];
$GLOBALS['redirects'] = [];

class WordPress_Module {
	const MODULE_ID = 1;
}

class Red_Item {
	public $id;
	private $group_id;

	public function __construct( $id, $group_id ) {
		$this->id       = $id;
		$this->group_id = $group_id;
	}

	public function get_group_id() {
		return $this->group_id;
	}

	private static function items( $filter ) {
		$items = [];
		foreach ( $GLOBALS['redirects'] as $id => $redirect ) {
			if ( $filter( $redirect ) ) {
				$items[] = new Red_Item( $id, $redirect['group'] );
			}
		}
		return $items;
	}

	// Same rows as Redirection's SQL: enabled items, joined on enabled groups of the module.
	public static function get_all_for_module( $module ) {
		return self::items( function( $redirect ) use ( $module ) {
			$group = $GLOBALS['groups'][ $redirect['group'] ] ?? null;
			return $redirect['enabled'] && $group && $group['module'] === $module && $group['enabled'];
		} );
	}

	// Same rows as Redirection's SQL: enabled items for the URL, whatever their group.
	public static function get_for_matched_url( $url ) {
		return self::items( function( $redirect ) use ( $url ) {
			return $redirect['enabled'] && $redirect['url'] === $url;
		} );
	}
}

// Minimal $wpdb stub, answering the enabled groups query from $GLOBALS['groups'].
class Wpdb_Stub {
	public $prefix  = 'wp_';
	public $queries = [];

	public function prepare( $query, ...$args ) {
		return vsprintf( $query, $args );
	}

	public function get_col( $query ) {
		$this->queries[] = $query;

		if ( ! preg_match( "/^SELECT id FROM (\w+)redirection_groups WHERE module_id=(\d+) AND status='enabled'$/", $query, $matches ) || $matches[1] !== $this->prefix ) {
			return [];
		}

		$ids = [];
		foreach ( $GLOBALS['groups'] as $id => $group ) {
			if ( $group['module'] === (int) $matches[2] && $group['enabled'] ) {
				$ids[] = (string) $id; // wpdb returns strings.
			}
		}
		return $ids;
	}
}
$GLOBALS['wpdb'] = new Wpdb_Stub();

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

function ids( $items ) {
	return array_map( function( $item ) {
		return $item->id;
	}, $items );
}

$GLOBALS['groups'] = [
	1 => [ 'module' => 1, 'enabled' => true ],  // WordPress
	2 => [ 'module' => 1, 'enabled' => false ], // WordPress, disabled
	3 => [ 'module' => 2, 'enabled' => true ],  // Apache
	4 => [ 'module' => 3, 'enabled' => true ],  // Nginx
];
$GLOBALS['redirects'] = [
	1 => [ 'url' => '/a', 'group' => 1, 'enabled' => true ],
	2 => [ 'url' => '/b', 'group' => 1, 'enabled' => false ],
	3 => [ 'url' => '/c', 'group' => 2, 'enabled' => true ], // Re-enabled on its own in a disabled group.
	4 => [ 'url' => '/a', 'group' => 3, 'enabled' => true ],
	5 => [ 'url' => '/e', 'group' => 4, 'enabled' => true ],
	6 => [ 'url' => '/f', 'group' => 99, 'enabled' => true ], // Group deleted.
	7 => [ 'url' => '/a', 'group' => 1, 'enabled' => true ],
];

echo "# get_for_matched_url\n";

check( 'no group query without a candidate', [ [], 0 ], [ ids( ActiveRedirects::get_for_matched_url( '/none' ) ), count( $GLOBALS['wpdb']->queries ) ] );
check( 'keeps enabled redirects of enabled WordPress groups, in order', [ 1, 7 ], ids( ActiveRedirects::get_for_matched_url( '/a' ) ) );
check( 'drops disabled redirects', [], ids( ActiveRedirects::get_for_matched_url( '/b' ) ) );
check( 'drops redirects of disabled groups', [], ids( ActiveRedirects::get_for_matched_url( '/c' ) ) );
check( 'drops redirects of Nginx groups', [], ids( ActiveRedirects::get_for_matched_url( '/e' ) ) );
check( 'drops redirects of missing groups', [], ids( ActiveRedirects::get_for_matched_url( '/f' ) ) );
check( 'groups queried once', 1, count( $GLOBALS['wpdb']->queries ) );

$GLOBALS['wpdb']->prefix = 'wp_2_'; // switch_to_blog()
$GLOBALS['groups'][3]['module'] = 1;

check( 'groups queried again for another site', [ [ 1, 4, 7 ], 2 ], [ ids( ActiveRedirects::get_for_matched_url( '/a' ) ), count( $GLOBALS['wpdb']->queries ) ] );

echo "# get_all\n";

check( 'only active redirects', [ 1, 4, 7 ], ids( ActiveRedirects::get_all() ) );

$GLOBALS['groups'] = [];

check( 'no groups', [], ids( ActiveRedirects::get_all() ) );

echo "\n" . ( $count - $failures ) . "/$count passed\n";

exit( $failures > 0 ? 1 : 0 );
