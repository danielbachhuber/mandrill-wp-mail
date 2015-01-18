<?php
/**
 * Plugin Name:  Mandrill wp_mail Drop-In
 * Plugin URI:   https://github.com/danielbachhuber/mandrill-wp-mail
 * Description:  Drop-in replacement for wp_mail using the Mandrill API.
 * Version:      0.0.1
 * Author:       Daniel Bachhuber
 * Author URI:   https://github.com/danielbachhuber
 * License:      GPL-2.0+
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Override WordPress' default wp_mail function with one that sends email
 * using Mandrill's API.
 *
 * Note that this function requires the MANDRILL_API_KEY constant to be defined
 * in order for it to work. The easiest place to define this is in wp-config.
 *
 * @since  0.0.1
 * @access public
 * @todo   Add support for attachments
 * @param  string $to
 * @param  string $subject
 * @param  string $message
 * @param  mixed $headers
 * @param  array $attachments
 * @return bool true if mail has been sent, false if it failed
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {

	// Compact the input, apply the filters, and extract them back out
	extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

	// Get the site domain and get rid of www.
	$sitename = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
	if ( 'www.' === substr( $sitename, 0, 4 ) ) {
		$sitename = substr( $sitename, 4 );
	}

	$from_email = 'wordpress@' . $sitename;

	$message_args = array(
		// Email
		'subject'                    => $subject,
		'html'                       => $message,
		'to'                         => $to,
		'headers'                    => array(
			'Content-type'           => apply_filters( 'wp_mail_content_type', 'text/plain' ),
		),

		// Mandrill defaults
		'tags'                       => array(
			'user'                   => array(),
			'general'                => array(),
			'automatic'              => array(),
		),
		'from_name'                  => 'WordPress',
		'from_email'                 => $from_email,
		'template_name'              => '',
		'track_opens'                => null,
		'track_clicks'               => null,
		'url_strip_qs'               => false,
		'merge'                      => true,
		'global_merge_vars'          => array(),
		'merge_vars'                 => array(),
		'google_analytics_domains'   => array(),
		'google_analytics_campaign'  => array(),
		'meta_data'                  => array(),
		'important'                  => false,
		'inline_css'                 => null,
		'preserve_recipients'        => null,
		'view_content_link'          => null,
		'tracking_domain'            => null,
		'signing_domain'             => null,
		'return_path_domain'         => null,
		'subaccount'                 => null,
		'recipient_metadata'         => null,
		'auto_text'                  => true,
	);
	$message_args = apply_filters( 'mandrill_wp_mail_pre_message_args', $message_args );

	/**
	 * Check whether there are custom headers to be sent
	 */
	if ( ! empty( $headers ) ) {

		$tempheaders = $headers;

		// Prepare the passed headers
		if ( ! is_array( $headers ) ) {
			$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		if ( ! empty( $tempheaders ) ) {

			foreach ( (array) $tempheaders as $header ) {

				if ( false === strpos( $header, ':' ) ) {
					continue;
				}

				// Explode them out
				list( $name, $content ) = explode( ':', trim( $header ), 2 );

				// Cleanup crew
				$name    = trim( $name );
				$content = trim( $content );

				switch ( strtolower( $name ) ) {

					case 'from':

						if ( false !== strpos( $content, '<' ) ) {
							// So... making my life hard again?
							$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
							$from_name = str_replace( '"', '', $from_name );
							$from_name = trim( $from_name );

							$from_email = substr( $content, strpos( $content, '<' ) + 1 );
							$from_email = str_replace( '>', '', $from_email );
							$from_email = trim( $from_email );
						} else {
							$from_name  = '';
							$from_email = trim( $content );
						}

						$message_args['from_email']  = $from_email;
						$message_args['from_name']   = $from_name;
						break;

					case 'bcc':

						// TODO: Mandrill's API only accept one BCC address. Other addresses will be silently discarded
						$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
						$message_args['bcc_address'] = $bcc[0];
						break;

					case 'reply-to':

						$message_args['headers'][ trim( $name ) ] = trim( $content );
						break;

					case 'importance':
					case 'x-priority':
					case 'x-msmail-priority':
						if ( ! $message_args['important'] ) {
							$message_args['important'] = ( strpos( strtolower( $content ), 'high' ) !== false ) ? true : false;
						}
						break;
					case 'content-type':

						$message_args['headers']['Content-type'] = trim( $content );
						break;

					default:
						if ( 'x-' === substr( $name,0 ,2 ) ) {
							$message_args['headers'][ trim( $name ) ] = trim( $content );
						}
						break;
				}
			}
		}
	}

	/**
	 * Sneaky support for multiple to addresses
	 */
	if ( ! is_array( $message_args['to'] ) ) {
		$message_args['to'] = explode( ',', $message_args['to'] );
	}
	$processed_to = array();
	foreach ( $message_args['to'] as $email ) {
		if ( is_array( $email ) ) {
			$processed_to[] = $email;
		} else {
			$processed_to[] = array( 'email' => $email );
		}
	}
	$message_args['to'] = $processed_to;

	/**
	 * Make sure our templates end up as HTML
	 */
	if ( ! empty( $message_args['headers']['Content-type'] ) && 'text/plain' === strtolower( $message_args['headers']['Content-type'] ) ) {
		$message_args['html'] = wpautop( $message_args['html'] );
	}

	/**
	 * Default filters we should still apply
	 */
	$message_args['from_email'] = apply_filters( 'wp_mail_from', $message_args['from_email'] );
	$message_args['from_name'] = apply_filters( 'wp_mail_from_name', $message_args['from_name'] );

	// Allow user to override
	$message_args = apply_filters( 'mandrill_wp_mail_message_args', $message_args );

	$request_args = array(
		'body' => array(
			'message' => $message_args,
			'key'     => MANDRILL_API_KEY,
		)
	);

	$request_url = 'https://mandrillapp.com/api/1.0/messages/send.json';
	$response = wp_remote_post( $request_url, $request_args );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	return true;
}
