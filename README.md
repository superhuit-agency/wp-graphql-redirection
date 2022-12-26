
# WP GraphQL Redirection

Exposes Redirection plugin in the GraphQL schema.

## Requirements

- Requires PHP 7.4+
- Requires WordPress 5.0+
- Requires [WP-GraphQL](https://wordpress.org/plugins/wp-graphql/) 1.8+
- Requires [Redirection](https://wordpress.org/plugins/redirection/) 5.0+

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
