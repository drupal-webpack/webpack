# Webpack

Use [webpack](https://webpack.js.org/) to transpile, bundle and minify Drupal's javascript files. Work in progress.

## Should I use it now?

No, unless you want to test it out and contribute. Right now it's just a working prototype without any settings. The values are hardcoded for a composer project with a `package.json` in the repository root and libraries in `modules/custom`.

## What it does?

This module will allow developers to write their Drupal libraries in modern javascript, as well as import modules from npm.

## How it works?

Right now it decorates the `asset.js.collection_renderer` service and bundles all the js libraries matching criteria into a single bundle. This is going to change, as decorating the `asset.resolver` service seems to be much more promising.
