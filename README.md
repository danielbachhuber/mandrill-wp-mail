# Mandrill wp_mail Drop-In

A simple drop-in replacement for WordPress' wp_mail function.

## How to Use

To implement this drop-in, add it to your `mu-plugins` directory. In order for it to work, you must define your Mandrill API key. The easiest way to do this is to add it to your `wp-config.php` file like so:

`define( 'MANDRILL_API_KEY', 'your-api-key' );`
