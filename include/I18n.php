<?php

namespace WPGraphQLRedirection;

class I18n {
	function __construct() {
		add_action( 'init', [$this, 'load_plugin_textdomain'] );
		add_action( 'switch_locale', [$this, 'load_plugin_textdomain' ] );
	}

	function load_plugin_textdomain() {
		load_plugin_textdomain( 'wpgraphql-redirection', false, 'wp-graphql-redirection/languages' );
	}
}
