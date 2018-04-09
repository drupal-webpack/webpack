# Webpack

Use [webpack](https://webpack.js.org/) to transpile, bundle and minify Drupal's javascript files. Work in progress.

## Should I use it now?

No, unless you want to test it out and contribute. Right now it's just a working prototype without any settings. The values are hardcoded for a composer project with a `package.json` in the repository root and libraries in `modules/custom`.

## What it does?

This module will allow developers to write their Drupal libraries in modern javascript, as well as import modules from npm.

## How it works?

Right now it decorates the `asset.js.collection_renderer` service and bundles all the js libraries matching criteria into a single bundle. This is going to change, as decorating the `asset.resolver` service seems to be much more promising.

## Contributing

If you'd like to contribute

- Find a TODO comment in code.
- Search for an existing task for this TODO item in the [issue queue](https://www.drupal.org/project/issues/webpack?status=All&categories=All).
  - If there is one, check the progress and help there if possible.
  - Otherwise, create a new issue with the name of the todo and assign it to yourself
- Usully it's best to discuss the solution before writing any patches.
