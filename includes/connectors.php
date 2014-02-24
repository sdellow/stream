<?php

class WP_Stream_Connectors {

	/**
	 * Contexts registered
	 * @var array
	 */
	public static $connectors = array();

	/**
	 * Action taxonomy terms
	 * Holds slug to -localized- label association
	 * @var array
	 */
	public static $term_labels = array(
		'stream_connector' => array(),
		'stream_context'   => array(),
		'stream_action'    => array(),
	);

	/**
	 * Load built-in connectors
	 */
	public static function load() {
		require_once WP_STREAM_CLASS_DIR . 'connector.php';

		$classes = array();
		if ( $found = glob( WP_STREAM_DIR . 'connectors/*.php' ) ) {
			foreach ( $found as $class ) {
				include_once $class;
				$class     = ucwords( preg_match( '#(.+)\.php#', basename( $class ), $matches ) ? $matches[1] : '' );
				$classes[] = "WP_Stream_Connector_$class";
			}
		}

		/**
		 * Filter allows for adding additional connectors via classes that extend
		 * WP_Stream_Connector
		 *
		 * @param  array  Connector Class names
		 * @return array  Updated Array of Connector Class names
		 */
		self::$connectors = apply_filters( 'wp_stream_connectors', $classes );

		foreach ( self::$connectors as $connector ) {
			self::$term_labels['stream_connector'][$connector::$name] = $connector::get_label();
		}

		/**
		 * Action allows for perform any additional action after connector term loaded
		 *
		 * @param $labels array Connectors labels
		 */

		do_action( 'wp_stream_after_connector_term_labels_loaded',  self::$term_labels['stream_connector'] );

		// Get excluded connectors
		$excluded_connectors = WP_Stream_Settings::$options['exclude_connectors'];
		if ( is_callable( $excluded_connectors ) ) {
			$excluded_connectors = call_user_func( $excluded_connectors );
		}

		foreach ( self::$connectors as $connector ) {

			if ( in_array( $connector::$name, $excluded_connectors ) ) {
				continue;
			}

			// Check if the connectors extends the WP_Stream_Connector class, if not skip it
			if ( ! is_subclass_of( $connector, 'WP_Stream_Connector' ) ) {
				add_action(
					'admin_notices',
					function() use( $connector ) {
						printf( '<div class="error"><p>%s %s</p></div>', $connector, __( "class wasn't loaded because it doesn't extends the WP_Stream_Connector class", 'stream' ) );
					}
				);

				continue;
			}

			$connector::register();

			// Add new terms to our label lookup array
			self::$term_labels['stream_action']  = array_merge(
				self::$term_labels['stream_action'],
				$connector::get_action_labels()
			);
			self::$term_labels['stream_context'] = array_merge(
				self::$term_labels['stream_context'],
				$connector::get_context_labels()
			);
		}
	}

}
