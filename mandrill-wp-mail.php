<?php
/**
 * wp_mail() drop-in for Mandrill
 * 
 * ... because sometimes you don't need an entire plugin
 * 
 * Cribbed in part from wpMandrill
 * 
 * @todo support for attachments
 */

function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {

	// Compact the input, apply the filters, and extract them back out
	extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

	// Get the site domain and get rid of www.
	$sitename = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
	if ( substr( $sitename, 0, 4 ) == 'www.' ) {
		$sitename = substr( $sitename, 4 );
	}

	$from_email = 'wordpress@' . $sitename;

	$message_args = array(
		// Email
		'subject'                    => $subject,
		'html'                       => $message,
		'to'                         => $to,

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
	$message_args['headers'] = array();
	if ( ! empty( $headers ) ) {

		// Prepare the passed headers
		if ( ! is_array( $headers ) ) {
			$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		} else {
			$tempheaders = $headers;
		}

		if ( ! empty( $tempheaders ) ) {
			
			foreach ( (array) $tempheaders as $header ) {

				if ( strpos( $header, ':' ) === false ) {
					continue;
				}

				// Explode them out
				list( $name, $content ) = explode( ':', trim( $header ), 2 );

				// Cleanup crew
				$name    = trim( $name    );
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

						$message_args['headers'][trim( $name )] = trim( $content );
						break;

					case 'importance':
					case 'x-priority':
					case 'x-msmail-priority':
						if ( ! $message_args['important'] ) {
							$message_args['important'] = ( strpos(strtolower($content),'high') !== false ) ? true : false;
						}
						break;
					default:
						if ( substr($name,0,2) == 'x-' ) {
							$message_args['headers'][trim( $name )] = trim( $content );
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

	$request_url = 'https://mandrillapp.com/api/1.0/messages/send';
	$response = wp_remote_post( $request_url, $request_args );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	return false;
}