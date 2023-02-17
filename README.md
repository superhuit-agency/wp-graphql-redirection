
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
