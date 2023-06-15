<?php

/**
 * DLM_Admin_Helper
 */
class DLM_Admin_Helper {

	/**
	 * Holds the class object.
	 *
	 * @since 4.4.7
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Primary class constructor.
	 *
	 * @since 4.4.7
	 */
	public function __construct() {
		// Set the weekly interval.
		add_filter( 'cron_schedules', array( $this, 'create_weekly_cron_schedule' ) );
		add_action( 'admin_init', array( $this, 'set_weekly_cron_schedule' ) );
		add_action( 'dlm_weekly_license', array( $this, 'general_license_validity' ) );
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return object The DLM_Admin_Helper object.
	 * @since 4.4.7
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof DLM_Admin_Helper ) ) {
			self::$instance = new DLM_Admin_Helper();
		}

		return self::$instance;

	}

	/**
	 * Tab navigation display
	 *
	 * @param  mixed $tabs Tabs used for settings navigation.
	 * @param  mixed $active_tab The active tab.
	 * @return void
	 */
	public static function dlm_tab_navigation( $tabs, $active_tab ) {

		if ( $tabs ) {

			$i = count( $tabs );
			$j = 1;

			foreach ( $tabs as $tab_id => $tab ) {

				$last_tab = ( $i == $j ) ? ' last_tab' : '';
				$active   = $active_tab == $tab_id ? ' nav-tab-active' : '';
				$j ++;

				if ( isset( $tab['url'] ) ) {
					// For Extensions and Gallery list tabs.
					$url = $tab['url'];
				} else {
					// For Settings tabs.
					$url = admin_url( 'edit.php?post_type=dlm_download&page=download-monitor-settings&tab=' . $tab_id );
				}

				echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . esc_attr( $last_tab ) . '" ' . ( isset( $tab['target'] ) ? 'target="' . esc_attr( $tab['target'] ) . '"' : '' ) . '>';

				if ( isset( $tab['icon'] ) ) {
					echo '<span class="dashicons ' . esc_attr( $tab['icon'] ) . '"></span>';
				}

				// For Extensions and Gallery list tabs.
				if ( isset( $tab['name'] ) ) {
					echo esc_html( $tab['name'] );
				}

				// For Settings tabs.
				if ( isset( $tab['label'] ) ) {
					echo esc_html( $tab['label'] );
				}

				if ( isset( $tab['badge'] ) ) {
					echo '<span class="dlm-badge">' . esc_html( $tab['badge'] ) . '</span>';
				}

				echo '</a>';
			}
		}
	}

	/**
	 * Callback to sort tabs/fields on priority.
	 *
	 * @param  mixed $a Current element from array.
	 * @param  mixed $b Next element from array.
	 * @return array
	 */
	public static function sort_data_by_priority( $a, $b ) {
		if ( ! isset( $a['priority'], $b['priority'] ) ) {
			return - 1;
		}
		if ( $a['priority'] === $b['priority'] ) {
			return 0;
		}

		return $a['priority'] < $b['priority'] ? - 1 : 1;
	}

	/**
	 * Checks if this is one of Download Monitor's page or not
	 *
	 * @return bool
	 * 
	 * @since 4.5.4
	 */
	public static function check_if_dlm_page() {

		if ( ! isset( $_GET['post_type'] ) || ( 'dlm_download' !== $_GET['post_type'] && 'dlm_product' !== $_GET['post_type'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Recreates the upgrade environment. Previously declared in DLM_Settings_Page
	 *
	 * @return bool
	 * @since 4.6.4
	 */
	public static function redo_upgrade() {

		global $wp, $wpdb, $pagenow;

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		// Drop the dlm_reports_log
		$drop_statement = "DROP TABLE IF EXISTS {$wpdb->prefix}dlm_reports_log,{$wpdb->prefix}dlm_downloads";
		$wpdb->query( $drop_statement );

		// Delete upgrade history and set the need DB pgrade
		delete_option( 'dlm_db_upgraded' );
		delete_transient('dlm_db_upgrade_offset');
		set_transient( 'dlm_needs_upgrade', '1', 30 * DAY_IN_SECONDS );

		return true;
	}

	/**
	 * Check the column type.
	 *
	 * @param string $table_name The table.
	 * @param string $col_name   The column.
	 * @param string $col_type   The type.
	 *
	 * @return bool|null
	 * @since 4.8.0
	 */
	public static function check_column_type( $table_name, $col_name, $col_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cannot be prepared. Fetches columns for table names.
		$results = $wpdb->get_results( "DESC $table_name" );
		if ( empty( $results ) ) {
			return null;
		}

		foreach ( $results as $row ) {

			if ( $row->Field === $col_name ) {

				// Got our column, check the params.
				if ( ( null !== $col_type ) && ( $row->Type !== $col_type ) ) {
					return false;
				}

				return true;
			} // End if found our column.
		}

		return null;
	}

	/**
	 * Check whether the license is valid or not.
	 *
	 * @param string $functionality The functionality.
	 *
	 * @return bool
	 *
	 * @since 3.8.2
	 */
	public function check_license_validity( $functionality ) {
		// Check if license is valid.
		if ( ! class_exists( 'DLM_Product_License' ) ) {
			return false;
		}
		$license = new DLM_Product_License( $functionality );

		if ( ! $license || ! $license->is_active() ) {
			return false;
		}

		return true;
	}

	/**
	 * Create dlm_weekly cron schedule.
	 *
	 * @param array $schedule Array of schedules.
	 *
	 * @return array
	 * @since 4.8.6
	 */
	public function create_weekly_cron_schedule( $schedule ) {
		// Set dlm_weekly cron schedule.
		$schedule['dlm_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'download-monitor' ),
		);

		return $schedule;
	}

	/**
	 * Set dlm_weekly cron schedule.
	 *
	 * @since 4.8.6
	 */
	public function set_weekly_cron_schedule() {

		if ( ! wp_next_scheduled( 'dlm_weekly_license' ) ) {
			wp_schedule_event( time(), 'weekly', 'dlm_weekly_license' );
		}
	}

	/**
	 * Check for license validity - the weekly cron job.
	 *
	 * @return void
	 * @since 4.8.6
	 */
	public function general_license_validity() {
		if ( ! class_exists( 'DLM_Product_Manager' ) || ! class_exists( 'DLM_Product_License' ) ) {
			return;
		}

		$product_manager = DLM_Product_Manager::get();
		$extension_handler = DLM_Extensions_Handler::get_instance();
		$product_manager->load_extensions();
		$extensions = $product_manager->get_products();

		if ( ! empty( $extensions ) ) {
			foreach ( $extensions as $slug => $extension ) {
				if ( ! $this->check_license_validity( $slug ) ) {
					$extension_handler->handle_extension_action( 'deactivate', array(
						'slug' => $slug,
						'name' => $extension->get_product_name()
					) );
				}
			}
		}
	}
}
