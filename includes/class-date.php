<?php

namespace SitemapHtml;

/**
 * Helper for working with dates.
 */
class Date {

	/**
	 * Reference UNIX timestamp.
	 *
	 * @var integer
	 */
	protected $timestamp;

	/**
	 * Create a sitemap date instance.
	 *
	 * @param integer $timestamp Reference timestamp.
	 */
	public function __construct( $timestamp ) {
		$this->timestamp = $timestamp;
	}

	/**
	 * Extract a numeric date/time component from a timestamp.
	 *
	 * @param  string $component Component symbol such as d, Y, m, etc.
	 *
	 * @return integer
	 */
	protected function extract( $component ) {
		return intval( gmdate( $component, $this->timestamp ) );
	}

	/**
	 * Get the numerical day of month.
	 *
	 * @return integer
	 */
	public function day() {
		return $this->extract( 'd' );
	}

	/**
	 * Get the numerical month.
	 *
	 * @return integer
	 */
	public function month() {
		return $this->extract( 'm' );
	}

	/**
	 * Get the numeric year.
	 *
	 * @return integer
	 */
	public function year() {
		return $this->extract( 'Y' );
	}

	/**
	 * Create a timestamp out of year, month and day components.
	 *
	 * @param  integer $year  Year.
	 * @param  integer $month Month.
	 * @param  integer $day   Day.
	 *
	 * @return integer|false
	 */
	public static function make( $year, $month = 1, $day = 1 ) {
		$month = is_numeric( $month ) ? intval( $month ) : 1;
		$day   = is_numeric( $day ) ? intval( $day ) : 1;

		return strtotime(
			sprintf(
				'%04d-%02d-%02d',
				intval( $year ),
				intval( $month ),
				intval( $day )
			)
		);
	}

	/**
	 * Pad a number with leading zeros.
	 *
	 * @param  integer $number Number.
	 *
	 * @return string
	 */
	public static function pad( $number ) {
		return sprintf( '%02d', intval( $number ) );
	}
}
