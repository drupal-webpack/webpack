# Webpack

Integrates Drupal with [webpack](https://webpack.js.org/).

![build status](https://travis-ci.com/drupal-webpack/webpack.svg?branch=8.x-1.x)

## What it does?

The module allows developers to have their Drupal libraries bundled by webpack. It makes it easy to import npm packages and use modern javascript that will work across a variety of browsers (see [Webpack Babel](https://drupal.org/project/webpack_babel)).

## Dependencies

- [NPM](https://drupal.org/project/npm)
- `drush 9`
- `yarn`

## Setup

Your project needs to have a `package.json` file somewhere up the directory tree. In drupal-composer projects it is a common practice to place one next to the webroot and the project-wide `composer.json`. Placing the file inside the webroot would work too. If you don't have such a file `yarn init -yp` will generate an empty one.

Once you've got `package.json`, add the module as a local dependency.

`yarn add file:./web/modules/contrib/webpack`

## Usage

Add `webpack: true` to your library definition in `module_name.libraries.yml`.

### Local development

For local development, start the dev server with `drush webpack:serve --port 1234` and reload the page. The module will detect it and inject the development version (with live reload). It is important to either run it outside of docker containers or set up port forwarding.

When running inside a container add the `--docker`. This alone will work if the webserver is ran in the same container as drush. Otherwise, drupal will need some additional info in order to detect the server, i.e. `--dev-server-host=cli` where cli the hostname (i.e. the service name from docker-compose) of the container that runs drush.

### Building for prod

For local development, start the dev server with `drush webpack:serve --port 1234` and reload the page. The module will detect it and inject the development version (with live reload). It is important to either run it outside of docker containers or set up port forwarding.

On the server, add `drush webpack:build` to your after-deploy steps. The bundles will be written to `public://webpack` and included automatically.

The output directory can be changed at `/admin/config/webpack/settings` e.g. to put the files under source control. If you set it to a path that is outside of the public files folder make sure to export your site's config after building ([details](https://github.com/drupal-webpack/webpack/blob/e498e8b2ce8b986fe91b280af7b3797bdfa6f41b/src/Bundler.php#L133)).

## Know issues

Some builds can break because of javascript aggregation. It can be disabled at _/admin/config/development/performance_.

## How does it work?

Setup steps and the modus operandi have been described in [Progressive Decoupling - The why and the how](https://drupal-progressive-decoupling.github.io/#/composer-require-webpack) at Decoupled Drupal Days NY 2018 ([video](https://www.youtube.com/watch?v=i4Ktx0pz8xI)).

## Should I use it now?

It's still in alpha but the usage won't change much.
