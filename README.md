
# WP GraphQL Redirection

Exposes Redirection plugin in the GraphQL schema.

## Requirements

- Requires PHP 7.4+
- Requires WordPress 5.0+
- Requires [WP-GraphQL](https://wordpress.org/plugins/wp-graphql/) 1.8+
- Requires [Redirection](https://wordpress.org/plugins/redirection/) 5.0+

## Install and Activate

WPGraphQl Redirection is a WordPress Plugin. YOu must then have a running WordPress instance with both [WPGraphQL](https://wordpress.org/plugins/wp-graphql/) and [Redirection](https://wordpress.org/plugins/redirection/) plugins installed and activated.

### Installing From Github

To install the plugin from Github, you can [download the latest release zip file](https://github.com/superhuit-agency/wp-graphql-redirection/releases), upload the Zip file to your WordPress install, and activate the plugin.

[Click here](https://wordpress.org/support/article/managing-plugins/) to learn more about installing WordPress plugins from a Zip file.

### Installing from Composer

composer require superhuit/wp-graphql-redirection

## Example

```graphql
query getAllRedirections {
  redirections {
    code
    target
    url
  }
}

query getTheRedirection($uri: String!) {
  redirections(uri: $uri) {
    code
    target
    url
  }
}
```

## WPGraphQL Smart Cache

When [WPGraphQL Smart Cache](https://wordpress.org/plugins/wpgraphql-smart-cache/) is active, queries selecting `redirections` are not object-cached, and they are not tagged with the `list:redirectionitemtype` list type. Frontends usually look up `redirections(uri:)` on every page render, so that shared cache collection would otherwise grow with every URL rendered, and nothing purges it when a redirect changes. Redirects take effect on the next lookup that reaches WordPress (a network cache in front of GET requests still keeps responses for their max-age).

Keep the lookup in its own request: a query mixing `redirections` with other root fields isn't object-cached either, yet Smart Cache still maps its key, once per URI, into the collections of those other fields.

## Tests

```sh
composer install
composer test
```
