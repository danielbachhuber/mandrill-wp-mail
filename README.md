# Mandrill wp_mail Drop-In

A simple drop-in replacement for WordPress' wp_mail function.

## How to Use

To implement this drop-in, add it to your `mu-plugins` directory. In order for it to work, you must define your Mandrill API key. The easiest way to do this is to add it to your `wp-config.php` file like so:

`define( 'MANDRILL_API_KEY', 'your-api-key' );`

## A Note About Composer

This plugin can be installed and managed using Composer; however, because of the way Composer and mu-plugins work, you'll need a [bit of a workaround](https://gist.github.com/richardtape/05c70849e949a5017147) to make sure the plugin is loaded. For more information about how to use this drop-in with Comopser, read this [blog post](https://richardtape.com/2014/08/22/composer-and-wordpress-mu-plugins/) by [Richard Tape](https://github.com/richardtape).
