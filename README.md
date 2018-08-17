# Webpack

Integrates Drupal with [webpack](https://webpack.js.org/).

## What it does?

The module allows developers to have their Drupal libraries bundled by webpack. It makes it easy to import npm packages and use modern javascript that will work across a variety of browsers (see [Webpack Babel](https://drupal.org/project/webpack_babel)).

## Dependencies

Right now the module assumes that `yarn` is installed and available in the PATH.

## Setup

Your project needs to have a `package.json` file somewhere up the directory tree. In drupal-composer projects it is a common practice to place one next to the webroot and the project-wide `composer.json`. Placing the file inside the webroot would work too. If you don't have such a file, fear not. `yarn init -yp` will generate an empty one.

Once you've got `package.json`, add the following npm dependencies.
`yarn add webpack` 
`yarn add webpack-serve --dev` 

## Usage

Add `webpack: true` to your library definition in `module_name.libraries.yml`.

For local development, start the dev server with `yarn webpack:serve` and reload the page. The module will detect it and inject the development version (with live reload).

On the server, add `yarn webpack:build` to your after-deploy steps. The bundles will be written to `public://webpack` and included automatically.

## How it works?

Setup steps and the modus operandi have been described in [Progressive Decoupling - The why and the how](https://drupal-progressive-decoupling.github.io/#/composer-require-webpack) at Decoupled Drupal Days NY 2018.

## Should I use it now?

It's still in alpha but the usage won't change much.
