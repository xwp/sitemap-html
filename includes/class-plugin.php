<?php

namespace SitemapHtml;

use SitemapHtml\Posts;
use SitemapHtml\Date;

/**
 * HTML Sitemap functionality for WordPress.
 */
class Plugin {
	use Singleton;

	/**
	 * Query variable name for storing the sitemap date.
	 *
	 * @var string
	 */
	const QUERY_VAR_DAY = 'sitemap_day';

	/**
	 * Query variable name for storing the month AND year.
	 *
	 * @var string
	 */
	const QUERY_VAR_YEAR_MONTH = 'sitemap_yearmonth';

	/**
	 * Name of the cron action used for updating the sitemap index.
	 *
	 * @var string
	 */
	const ACTION_UPDATE_SITEMAP = 'sitemap_html_update';

	/**
	 * Name of the cron action used for daily updates of the sitemap index.
	 *
	 * @var string
	 */
	const ACTION_UPDATE_SITEMAP_DAILY = 'sitemap_html_update_daily';

	/**
	 * Instance of the post fetcher.
	 *
	 * @var \SitemapHtml\Posts
	 */
	protected $posts;

	/**
	 * Instantiate the module.
	 */
	public function __construct() {
		global $wpdb;

		$this->posts = new Posts( $wpdb, $this->post_types() );
		$this->init();
	}

	/**
	 * Hook into WP.
	 *
	 * @return void
	 */
	protected function init() {
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'wp_headers', [ $this, 'cache_headers' ] );
		add_filter( 'wp_title_parts', [ $this, 'set_page_title' ] );
		add_filter( 'get_canonical_url', [ $this, 'set_canonical' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'maybe_sitemap_not_found' ] );
		add_action( 'save_post_page', [ $this, 'schedule_sitemap_updates' ] );
		add_action( 'save_post_post', [ $this, 'schedule_sitemap_updates' ] );
		add_action( 'transition_post_status', [ $this, 'schedule_sitemap_update_on_post_change' ], 10, 3 );
		add_action( self::ACTION_UPDATE_SITEMAP, [ $this, 'update_sitemap' ] );
		add_action( self::ACTION_UPDATE_SITEMAP_DAILY, [ $this, 'update_sitemap' ] );
		add_shortcode( 'sitemap-html-dated', [ $this, 'render_sitemap_html_shortcode' ] );
		add_filter( 'the_content', [ $this, 'inject_sitemap_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_sitemap_styles' ] );
		add_action( 'admin_notices', [ $this, 'sitemap_html_admin_notice' ] );
	}

	/**
	 * Inject the [sitemap-html-dated] shortcode into the content for the sitemap page.
	 *
	 * @param string $content The original content.
	 *
	 * @return string The modified content.
	 */
	public function inject_sitemap_shortcode( $content ) {
		if ( $this->is_sitemap() ) {
			return $content . '[sitemap-html-dated]';
		}
		return $content;
	}

	/**
	 * Register our custom query variables.
	 *
	 * @param  array $vars List of registered query variables.
	 *
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR_DAY;
		$vars[] = self::QUERY_VAR_YEAR_MONTH;

		return $vars;
	}

	/**
	 * Add custom rewrite rules to enable year, month and day filtering.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		$sitemap_page = $this->page_with_sitemap();

		if ( $sitemap_page ) {
			$rules = $this->get_page_rewrite_rules( $sitemap_page );

			foreach ( $rules as $rule ) {
				add_rewrite_rule( $rule['from'], $rule['to'], 'top' );
			}
		}
	}

	/**
	 * Send modified cache headers to cache sitemaps for longer.
	 */
	public function cache_headers( $headers ) {

		// Don't do anything for the sitemaps index, the month sitemaps, or the current day or yesterday, as the pages may change
		if ( ! $this->is_sitemap() || $this->is_root() || $this->is_month() || $this->is_today() || $this->is_yesterday() ) {
			return $headers;
		}

		$smaxage = 30 * DAY_IN_SECONDS;

		// Add the Cache-Control header if it doesn't exist, with our custom s-maxage.
		if ( ! array_key_exists( 'Cache-Control', $headers ) ) {
			$headers['Cache-Control'] = 'max-age=300, stale-while-revalidate, s-maxage=' . $smaxage;

			// Replace the existing maxage with our custom s-maxage.
		} elseif ( strpos( $headers['Cache-Control'], 's-maxage' ) ) {
			$headers['Cache-Control'] = preg_replace( '/s-maxage=\d+/', 's-maxage=' . $smaxage, $headers['Cache-Control'] );

			// Append our custom s-maxage to the existing header.
		} else {
			$headers['Cache-Control'] .= ', s-maxage=' . $smaxage;
		}

		return $headers;
	}

	/**
	 * Append year, month or date to the page title.
	 *
	 * @param array $parts Parts of the page title.
	 *
	 * @return array
	 */
	public function set_page_title( $parts ) {
		if ( empty( $parts[0] ) || ! $this->is_sitemap() ) {
			return $parts;
		}

		$timestamp = $this->timestamp();

		if ( $this->is_month() ) {
			$parts[0] = sprintf(
				'%s: %s',
				$parts[0],
				date_i18n( 'F Y', $timestamp )
			);
		} elseif ( $this->is_day() ) {
			$parts[0] = sprintf(
				'%s: %s',
				$parts[0],
				date_i18n( 'F d, Y', $timestamp )
			);
		}

		return $parts;
	}

	/**
	 * Set the canonical link on sitemap pages.
	 *
	 * @param string   $url  Current canonical URL.
	 * @param \WP_Post $post Post object.
	 *
	 * @return string
	 */
	public function set_canonical( $url, $post ) {
		if ( $this->is_sitemap( $post ) ) {
			if ( $this->is_month() ) {
				return $this->url_month( $this->timestamp() );
			} elseif ( $this->is_day() ) {
				return $this->url_day( $this->timestamp() );
			}
		}

		return $url;
	}

	/**
	 * Return a 404 page on sitemap archives that don't contain any posts.
	 *
	 * @param  \WP_Query $query Query of the request.
	 *
	 * @return void
	 */
	public function maybe_sitemap_not_found( $query ) {
		if ( $query->is_main_query() && $query->is_page() ) {
			$query_page_id = intval( $query->get( 'page_id' ) ); // This is present only on routes with our rewrite.

			if ( ! empty( $query_page_id ) ) {
				$page = get_post( $query_page_id );
			} elseif ( ! empty( $query->queried_object ) ) {
				$page = $query->queried_object;
			} else {
				return;
			}

			// Return a 404 for all requests with a potential date query that don't have posts.
			if ( $this->is_sitemap( $page ) && ( $query->get( 'page' ) || $this->is_date_query() ) && ! $this->timestamp_has_posts() ) {
				$query->set_404();
				$query->set( 'page_id', null ); // Prevent canonical redirect to the sitemap index page.
			}
		}
	}

	/**
	 * Schedule or unschedule sitemap updates depending on if we have a page
	 * with the sitemap slug.
	 *
	 * @return void
	 */
	public function schedule_sitemap_updates() {
		$sitemap_page = $this->page_with_sitemap();

		if ( $sitemap_page ) {
			$this->schedule_sitemap_update();
		} else {
			$this->unschedule_sitemap_update();
		}
	}

	/**
	 * Schedule a sitemap index update when a post is published or unpublished.
	 *
	 * @param  string   $new_status New post status.
	 * @param  string   $old_status Previous post status.
	 * @param  \WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public function schedule_sitemap_update_on_post_change( $new_status, $old_status, $post ) {
		if ( $this->has_post_published_status_changed( $new_status, $old_status ) && in_array( $post->post_type, $this->post_types(), true ) ) {
			$this->schedule_sitemap_update();
		}
	}

	/**
	 * Update the sitemap index.
	 *
	 * @return void
	 */
	public function update_sitemap() {
		$this->posts->update();
	}

	/**
	 * Return the post types that should be included in the sitemap.
	 *
	 * @return array
	 */
	public function post_types() {
		return (array) apply_filters( 'sitemap_html_post_types', [ 'post' ] );
	}

	/**
	 * Check if the post was published or unpublished based on post status.
	 *
	 * @param  string  $new_status Post status now.
	 * @param  string  $old_status Post status before.
	 *
	 * @return boolean
	 */
	public function has_post_published_status_changed( $new_status, $old_status ) {
		return ( $new_status !== $old_status && ( 'publish' === $new_status || 'publish' === $old_status ) );
	}

	/**
	 * Schedule sitemap update every day as a fallback.
	 *
	 * @return void
	 */
	protected function schedule_sitemap_update() {
		// Ensure we always have the daily update.
		if ( ! wp_next_scheduled( self::ACTION_UPDATE_SITEMAP_DAILY ) ) {
			wp_schedule_event(
				$this->timestamp_top_hour( time() ),
				'daily',
				self::ACTION_UPDATE_SITEMAP_DAILY
			);
		}

		/**
		 * Add a one time update in the next 15 minutes. Note that core will
		 * automatically ignore the event if another one is scheduled less than
		 * ten minutes before this one.
		 */
		wp_schedule_single_event(
			$this->timestamp_quarter_hour( time() ),
			self::ACTION_UPDATE_SITEMAP
		);
	}

	/**
	 * Unschedule the daily sitemap update event.
	 *
	 * @return boolean|null Returns `null` if no update event found.
	 */
	protected function unschedule_sitemap_update() {
		$timestamp = wp_next_scheduled( self::ACTION_UPDATE_SITEMAP_DAILY );

		if ( ! empty( $timestamp ) ) {
			return wp_unschedule_event( $timestamp, self::ACTION_UPDATE_SITEMAP_DAILY );
		}

		return null;
	}

	/**
	 * Get the UNIX timestamp at the start of an hour.
	 *
	 * @param integer $timestamp Reference timestamp.
	 *
	 * @return integer
	 */
	public function timestamp_top_hour( $timestamp ) {
		return intval( intval( (int) $timestamp / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
	}

	/**
	 * Get the UNIX timestamp at the closest quarter hour.
	 *
	 * @param integer $timestamp Reference timestamp.
	 *
	 * @return integer
	 */
	public function timestamp_quarter_hour( $timestamp ) {
		return intval( intval( (int) $timestamp / ( HOUR_IN_SECONDS / 4 ) ) * HOUR_IN_SECONDS / 4 );
	}

	/**
	 * Generate the rewrite rules for a sitemap index page.
	 *
	 * @param  \WP_Post $page Index page object.
	 *
	 * @return array
	 */
	protected function get_page_rewrite_rules( $page ) {
		$rules = [
			'day'        => [
				'from' => '%s/(\d{6})(\d{1,2})/?',
				'to'   => sprintf(
					// Note the escaped %%d for page_id which we're replacing later.
					'index.php?page_id=%%d&%s=$matches[1]&%s=$matches[2]',
					self::QUERY_VAR_YEAR_MONTH,
					self::QUERY_VAR_DAY
				),
			],
			'year-month' => [
				'from' => '%s/(\d{6})/?',
				'to'   => sprintf(
					// Note the escaped %%d for page_id which we're replacing later.
					'index.php?page_id=%%d&%s=$matches[1]',
					self::QUERY_VAR_YEAR_MONTH
				),
			],
		];

		return array_map(
			function ( $rule ) use ( $page ) {
				return [
					'from' => sprintf( $rule['from'], $page->post_name ),
					'to'   => sprintf( $rule['to'], $page->ID ),
				];
			},
			$rules
		);
	}

	/**
	 * Check if there are posts on a specific date.
	 *
	 * @return boolean
	 */
	public function timestamp_has_posts() {
		// Ensure we have a valid timestamp to work with.
		if ( ! $this->timestamp() ) {
			return false;
		}

		$query_vars = [
			'fields'              => 'ids',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true, // We don't use pagination here.
			'post_type'           => $this->post_types(),
		];

		$date_query_vars = [
			'year'     => $this->year(),
			'monthnum' => $this->month(),
			'day'      => $this->day(),
		];

		// Merge query with all non-empty date related query params.
		$query_vars = array_merge( $query_vars, array_filter( $date_query_vars ) );

		$query = new \WP_Query( $query_vars );

		return $query->have_posts();
	}

	/**
	 * If request contains any of the date specific query parameters.
	 *
	 * @return boolean
	 */
	public function is_date_query() {
		return ( $this->year() || $this->month() || $this->day() );
	}

	/**
	 * If we're currently viewing the root of the HTML sitemap.
	 *
	 * @return boolean
	 */
	public function is_root() {
		return ( $this->is_sitemap() && ! $this->month() && ! $this->day() );
	}

	/**
	 * If we're viewing the year/month route of the sitemap.
	 *
	 * @return boolean
	 */
	public function is_month() {
		return ( $this->is_sitemap() && $this->month() && ! $this->day() );
	}

	/**
	 * If we're viewing the posts of a specific day.
	 *
	 * @return boolean
	 */
	public function is_day() {
		return ( $this->is_sitemap() && $this->month() && $this->day() );
	}

	/**
	 * Check if the current sitemap is today's.
	 *
	 * @return bool True if it is today's sitemap.
	 */
	private function is_today() {
		return ( $this->get_time_difference() === 0 );
	}

	/**
	 * Check if the current sitemap is yesterday's.
	 *
	 * @return bool True if it is yesterday's sitemap.
	 */
	private function is_yesterday() {
		return ( $this->get_time_difference() === 86400 );
	}

	/**
	 * Get a time difference between the current day and sitemap day.
	 *
	 * @return int The time difference, in seconds.
	 */
	private function get_time_difference() {
		$current_time = Date::make( gmdate( 'Y' ), gmdate( 'n' ), gmdate( 'j' ) );
		$sitemap_time = $this->timestamp();
		return $current_time - $sitemap_time;
	}

	/**
	 * Get the year component of the current sitemap request, if present.
	 *
	 * Re-use the core `m` query variable to avoid registering custom query
	 * variables.
	 *
	 * @return integer|null
	 */
	public function year() {
		$var = get_query_var( self::QUERY_VAR_YEAR_MONTH );

		if ( ! empty( $var ) && is_numeric( $var ) ) {
			return absint( substr( $var, 0, 4 ) );
		}

		return null;
	}

	/**
	 * Get the month component of the current sitemap request, if available.
	 *
	 * The same query component is shared with the request year.
	 *
	 * @return integer|null
	 */
	public function month() {
		$var = get_query_var( self::QUERY_VAR_YEAR_MONTH );

		if ( ! empty( $var ) && is_numeric( $var ) ) {
			return absint( substr( $var, 4, 2 ) );
		}

		return null;
	}

	/**
	 * Get the day component of the current sitemap request, if available.
	 *
	 * Re-use the core `day` variable to avoid registering custom query
	 * variables.
	 *
	 * @return integer|null
	 */
	public function day() {
		$var = get_query_var( self::QUERY_VAR_DAY );

		if ( ! empty( $var ) ) {
			return absint( $var );
		}

		return null;
	}

	/**
	 * Get the post object of the current request.
	 *
	 * @return \WP_Post|null
	 */
	public function page() {
		if ( is_page() ) {
			return get_queried_object();
		}

		return null;
	}

	/**
	 * Check if the current page has the 'sitemap' slug.
	 *
	 * @param \WP_Post|null $page Post object to check.
	 *
	 * @return boolean
	 */
	public function is_sitemap( $page = null ) {
		if ( ! isset( $page ) ) {
			$page = $this->page();
		}

		return ( isset( $page->post_name ) && 'sitemap' === $page->post_name );
	}

	/**
	 * Get the page that has the 'sitemap' slug.
	 *
	 * @return \WP_Post|null
	 */
	protected function page_with_sitemap() {
		return get_page_by_path( 'sitemap' );
	}

	/**
	 * Internal helper for generating the canonical sitemap URLs.
	 *
	 * @param  array $paths List of date components such as [ 2024, 12 ].
	 *
	 * @return string
	 */
	protected function url( $paths = [] ) {
		return user_trailingslashit(
			sprintf(
				'%s/%s',
				rtrim( get_permalink( $this->page() ), '/' ),
				implode( '', $paths )
			)
		);
	}

	/**
	 * Canonical URL to a sitemap of a particular year > month.
	 *
	 * @param  integer $timestamp Timestamp representing any day of that month.
	 *
	 * @return string
	 */
	public function url_month( $timestamp ) {
		$date = new Date( $timestamp );

		return $this->url(
			[
				$date->year(),
				$date->pad( $date->month() ),
			]
		);
	}

	/**
	 * Canonical URL to a sitemap of a particular year > month > day.
	 *
	 * @param  integer $timestamp Timestamp representing any time of that day.
	 *
	 * @return string
	 */
	public function url_day( $timestamp ) {
		$date = new Date( $timestamp );

		return $this->url(
			[
				$date->year(),
				$date->pad( $date->month() ),
				$date->pad( $date->day() ),
			]
		);
	}

	/**
	 * Get the timestamp of the current request.
	 *
	 * @return string|false
	 */
	public function timestamp() {
		return Date::make( $this->year(), $this->month(), $this->day() );
	}

	/**
	 * Get timestamps of month with posts grouped by year.
	 *
	 * @return array
	 */
	public function links_by_years() {
		$links = [];

		foreach ( $this->posts->by_years() as $year => $months ) {
			$links[ $year ] = array_reverse(
				array_map(
					function ( $month ) use ( $year ) {
						return Date::make( $year, $month, 1 );
					},
					$months
				)
			);
		}

		return $links;
	}

	/**
	 * Get timestamps of days in month with posts.
	 *
	 * @param  integer $timestamp Timestamp of any day in that particular month.
	 *
	 * @return array
	 */
	public function links_by_months( $timestamp ) {
		$date = new Date( $timestamp );

		return array_map(
			function ( $day ) use ( $date ) {
				return $date->make( $date->year(), $date->month(), $day );
			},
			array_reverse( $this->posts->by_months( $date->year(), $date->month() ), true )
		);
	}

	/**
	 * Get posts published on a specific day.
	 *
	 * @param  integer $timestamp Timestamp of the day.
	 *
	 * @return array
	 */
	public function links_by_day( $timestamp ) {
		$date = new Date( $timestamp );

		$query = new \WP_Query(
			[
				'post_status'         => 'publish',
				'posts_per_page'      => 100, // We shouldn't have more than 100 posts a day.
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true, // We don't use pagination here.
				'post_type'           => $this->post_types(),
				'year'                => $date->year(),
				'monthnum'            => $date->month(),
				'day'                 => $date->day(),
			]
		);

		if ( $query->have_posts() ) {
			return $query->posts;
		}

		return [];
	}

	/**
	 * Create the sitemap page if it doesn't exist.
	 *
	 * @return void
	 */
	public function create_sitemap_page() {
		// Check if a page with slug 'sitemap' exists.
		$page = get_page_by_path( 'sitemap' );

		// We have the 'sitemap' page, so we don't need to create it.
		if ( $page ) {
			set_transient( 'sitemap_html_activation_notice', __( 'Sitemap HTML Plugin: The page with the "sitemap" slug already exists. The sitemap will be appended to the page available at this URL: <a href="/sitemap" target="_blank">/sitemap</a>.', 'sitemap-html' ), 10 );
			return;
		}

		// Create the page.
		$page_id = wp_insert_post(
			[
				'post_title'   => __( 'Sitemap', 'sitemap-html' ),
				'post_name'    => 'sitemap',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
				'post_author'  => get_current_user_id(),
			]
		);

		if ( is_wp_error( $page_id ) ) {
			set_transient( 'sitemap_html_activation_error', $page_id->get_error_message(), 10 );
		} else {
			set_transient( 'sitemap_html_activation_notice', __( 'Sitemap HTML Plugin: The page with the "sitemap" slug has been created automatically. The sitemap is available at this URL: <a href="/sitemap" target="_blank">/sitemap</a>.', 'sitemap-html' ), 10 );
		}
	}

	/**
	 * Show the activation error notice.
	 *
	 * @return void
	 */
	public function sitemap_html_admin_notice() {
		$error_message  = get_transient( 'sitemap_html_activation_error' );
		$notice_message = get_transient( 'sitemap_html_activation_notice' );

		if ( $error_message ) {
			// Display the admin notice.
			printf(
				'<div class="notice notice-error"><p>%s<br>%s</p></div>',
				esc_html( __( 'Sitemap HTML Plugin: The page with the "sitemap" slug could not be created during activation, please create it manually. Error message:', 'sitemap-html' ) ),
				esc_html( $error_message )
			);

			// Delete the transient to prevent the notice from reappearing.
			delete_transient( 'sitemap_html_activation_error' );
		}

		if ( $notice_message ) {
			// Display the admin notice.
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				wp_kses_post( $notice_message )
			);

			// Delete the transient to prevent the notice from reappearing.
			delete_transient( 'sitemap_html_activation_error' );
		}
	}

	/**
	 * Enqueue the sitemap stylesheet on the sitemap page.
	 *
	 * @return void
	 */
	public function enqueue_sitemap_styles() {
		if ( $this->is_sitemap() ) {
			wp_enqueue_style(
				'sitemap-html-style',
				SITEMAP_HTML_PLUGIN_URL . 'assets/sitemap-html.css',
				[],
				SITEMAP_HTML_VERSION ?? '1.0.0'
			);
		}
	}

	/**
	 * Get the breadcrumbs markup.
	 *
	 * @param array $breadcrumbs The breadcrumbs array.
	 *
	 * @return string The breadcrumbs markup.
	 */
	public function get_breadcrumbs_markup( $breadcrumbs ) {
		if ( empty( $breadcrumbs ) ) {
			return '';
		}

		$markup  = '<nav id="sitemap-html-breadcrumbs" aria-label="Breadcrumb">';
		$markup .= '<ol itemscope itemtype="http://schema.org/BreadcrumbList">';

		$position = 1;

		foreach ( $breadcrumbs as $breadcrumb ) {
			$markup .= '<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">';

			$markup .= sprintf(
				'<a itemprop="item" href="%s"><span itemprop="name">%s</span></a>',
				esc_url( $breadcrumb['link'] ),
				esc_html( $breadcrumb['label'] )
			);

			$markup .= sprintf( '<meta itemprop="position" content="%d">', $position );
			$markup .= '</li>';

			++$position;
		}

		$markup .= '</ol>';
		$markup .= '</nav>';

		return $markup;
	}

	/**
	 * Generate the HTML Sitemap.
	 *
	 * @param array $links The links to render.
	 * @param bool  $is_root Whether the sitemap is the root sitemap.
	 * @param array $breadcrumbs The breadcrumbs to render.
	 *
	 * @return string The HTML output of the sitemap.
	 */
	public function get_sitemap_html_markup( array $links, bool $is_root, array $breadcrumbs ): string {
		$output = '';

		if ( ! $is_root ) {
			$output .= $this->get_breadcrumbs_markup( $breadcrumbs );
		}

		$output .= '<div id="sitemap-html">';

		foreach ( $links as $section ) {
			$items = array_map(
				function ( $item ) {
					return sprintf(
						'<li>
							<a href="%s">%s</a>
						</li>',
						esc_url( $item['link'] ),
						esc_html( $item['label'] )
					);
				},
				$section['items']
			);

			$output .= sprintf(
				'<div class="%s">
					<h2>%s</h2>
					<ul>
						%s
					</ul>
				</div>',
				esc_attr( implode( ' ', $section['classes'] ) ),
				esc_html( $section['label'] ),
				implode( '', $items )
			);
		}

		$output .= '</div>'; // #sitemap-html

		return $output;
	}

	/**
	 * Render the HTML Sitemap as a shortcode.
	 *
	 * @return string The HTML output of the sitemap.
	 */
	public function render_sitemap_html_shortcode() {
		// Determine the context
		$is_root      = $this->is_root();
		$is_month     = $this->is_month();
		$is_day       = $this->is_day();
		$sitemap_page = get_queried_object();

		// Return early if the page is invalid. Shortcode is only intended to be used on the sitemap page.
		if ( ! $sitemap_page || ! isset( $sitemap_page->ID ) ) {
			return '';
		}

		// Prepare links based on context
		$links = [];

		if ( $is_root ) {
			foreach ( $this->links_by_years() as $sitemap_year => $sitemap_months ) {
				$links[] = [
					'label'   => $sitemap_year,
					'classes' => [ 'sitemap-html__year' ],
					'items'   => array_map(
						function ( $timestamp ) {
							return [
								'label' => date_i18n( 'F', $timestamp ),
								'link'  => $this->url_month( $timestamp ),
							];
						},
						$sitemap_months
					),
				];
			}
		} elseif ( $is_month ) {
			$links[] = [
				'label'   => date_i18n( 'F Y', $this->timestamp() ),
				'classes' => [ 'sitemap-html__month' ],
				'items'   => array_map(
					function ( $timestamp ) {
						return [
							'label' => date_i18n( 'F j', $timestamp ),
							'link'  => $this->url_day( $timestamp ),
						];
					},
					$this->links_by_months( $this->timestamp() )
				),
			];
		} elseif ( $is_day ) {
			$links[] = [
				'label'   => date_i18n( 'F j, Y', $this->timestamp() ),
				'classes' => [ 'sitemap-html__day' ],
				'items'   => array_map(
					function ( $post ) {
						return [
							'label' => $post->post_title,
							'link'  => get_permalink( $post ),
						];
					},
					$this->links_by_day( $this->timestamp() )
				),
			];
		}

		$breadcrumbs = [
			[
				'link'     => get_permalink( $sitemap_page ),
				'label'    => __( 'Index', 'html-sitemap' ),
				'position' => 1,
			],
		];

		if ( $is_day ) {
			$breadcrumbs[] = [
				'link'     => $this->url_month( $this->timestamp() ),
				'label'    => date_i18n( 'F Y', $this->timestamp() ),
				'position' => 2,
			];
		}

		// Return early if there are no links to render.
		if ( empty( $links ) ) {
			return '<p>' . esc_html__( 'No posts found.', 'sitemap-html' ) . '</p>';
		}

		// Generate the HTML
		return $this->get_sitemap_html_markup( $links, $is_root, $breadcrumbs );
	}
}
