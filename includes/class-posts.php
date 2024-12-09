<?php

namespace SitemapHtml;

/**
 * Helpers for building date-based HTML sitemaps.
 *
 * Use direct SQL queries since we require grouping, similar to
 * wp_get_archives() but a lot more flexible. Use heavy caching to avoid
 * direct database calls.
 */
class Posts {

	/**
	 * Instance of the WP DB interface.
	 *
	 * @var \wpdb
	 */
	protected $db;

	/**
	 * Post types to include in the sitemap.
	 *
	 * @var array
	 */
	protected $post_types;

	/**
	 * Setup the object.
	 *
	 * @param \wpdb $db         WordPress DB interface.
	 * @param array $post_types Post types to include.
	 */
	public function __construct( $db, $post_types = [ 'post' ] ) {
		$this->db         = $db;
		$this->post_types = (array) $post_types;
	}

	/**
	 * Generate a cache key for a query.
	 *
	 * @param  string $query DB query string.
	 *
	 * @return string
	 */
	protected function cache_key( $query ) {
		return sprintf(
			'sitemap-html-posts-%s',
			md5( $query . implode( '/', $this->post_types ) )
		);
	}

	/**
	 * If the WP Large Options should be used instead.
	 *
	 * @see https://wpvip.com/plugins/wp-large-options/
	 *
	 * @return boolean
	 */
	protected function use_large_option() {
		return ( function_exists( 'wlo_update_option' ) && function_exists( 'wlo_get_option' ) );
	}

	/**
	 * Fetch results from cache, if available.
	 *
	 * Returns `null` if the entry was not found in cache.
	 *
	 * @param  string $query DB query string.
	 *
	 * @return mixed
	 */
	protected function cache_get( $query ) {
		$key = $this->cache_key( $query );

		if ( $this->use_large_option() ) {
			return wlo_get_option( $key );
		}

		return get_option( $key );
	}

	/**
	 * Cache DB query results.
	 *
	 * @param string $query   WP DB query string.
	 * @param mixed  $results Data to store.
	 */
	protected function cache_set( $query, $results ) {
		$key = $this->cache_key( $query );

		if ( $this->use_large_option() ) {
			return wlo_update_option( $key, $results );
		}

		return update_option( $key, $results, false );
	}

	/**
	 * Build the SQL where statement for the various post types.
	 *
	 * @return string
	 */
	protected function query_post_types_in() {
		$conditionals = [];

		foreach ( $this->post_types as $post_type ) {
			$conditionals[] = "'" . esc_sql( $post_type ) . "'";
		}

		return implode( ', ', $conditionals );
	}

	/**
	 * Returns the DB query for fetching all dates with content.
	 *
	 * @return string
	 */
	protected function query_for_days() {
		return "SELECT
                DISTINCT DATE_FORMAT(post_date, '%Y-%m-%d')
            FROM {$this->db->posts}
            WHERE
                post_type IN ({$this->query_post_types_in()}) AND
                post_status = 'publish'
            ORDER BY post_date DESC";
	}

	/**
	 * Get the results from the DB and cache the results.
	 *
	 * @param  string $query DB query to run.
	 *
	 * @return mixed
	 */
	public function update() {
		$query = $this->query_for_days();

		// Convert to timestamp integer values for optimized caching.
		$results = array_map(
			function ( $timestamp ) {
				return strtotime( $timestamp );
			},
			$this->db->get_col( $query )
		);

		$this->cache_set( $query, $results );

		return $results;
	}

	/**
	 * Check if the sitemap dates have been generated and cached.
	 *
	 * @return boolean
	 */
	public function is_cached() {
		return is_array( $this->cache_get( $this->query_for_days() ) );
	}

	/**
	 * Get a list of links to all months with posts.
	 *
	 * @return array
	 */
	protected function months() {
		$months = [];

		foreach ( $this->days() as $day ) {
			$month_id = sprintf( '%d-%d', $day->year, $day->month );

			if ( ! isset( $months[ $month_id ] ) ) {
				$months[ $month_id ] = $day;
			}
		}

		return array_values( $months );
	}

	/**
	 * Get a list of all days with posts.
	 *
	 * @return array
	 */
	protected function days() {
		$days = $this->cache_get( $this->query_for_days() );

		// Ensure the same return type when nothing is in cache.
		if ( empty( $days ) || ! is_array( $days ) ) {
			return [];
		}

		// Convert to a format that is easy to work with.
		return array_map(
			function ( $timestamp ) {
				list( $year, $month, $dayofmonth ) = explode( '-', gmdate( 'Y-n-j', $timestamp ) );

				return (object) [
					'year'       => (int) $year,
					'month'      => (int) $month,
					'dayofmonth' => (int) $dayofmonth,
				];
			},
			$days
		);
	}

	/**
	 * Return all month with posts grouped by year.
	 *
	 * @return array
	 */
	public function by_years() {
		$by_year = [];

		foreach ( $this->months() as $month ) {
			$by_year[ $month->year ][] = $month->month;
		}

		return $by_year;
	}

	/**
	 * Return days of a month with posts.
	 *
	 * @param  integer $year  Year.
	 * @param  integer $month Month of the year.
	 *
	 * @return array
	 */
	public function by_months( $year, $month ) {
		// Extract day in a specific year and month.
		$days = array_filter(
			$this->days(),
			function ( $day ) use ( $year, $month ) {
				return ( $day->year === $year && $day->month === $month );
			}
		);

		$days = array_map(
			function ( $day ) {
				return $day->dayofmonth;
			},
			$days
		);

		return array_unique( $days );
	}
}
