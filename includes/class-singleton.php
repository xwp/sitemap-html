<?php

namespace SitemapHtml;

/**
 * Singleton trait to ensure a class has only one instance.
 */
trait Singleton {
	/**
	 * Instance of the class.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Get the single instance of the class.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct object creation.
	 */
	private function __construct() {}

	/**
	 * Prevent object cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {}
}
