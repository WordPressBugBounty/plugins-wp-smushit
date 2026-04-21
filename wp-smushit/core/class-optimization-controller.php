<?php

namespace Smush\Core;

use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Media_Library\Media_Library_Row;
use Smush\Core\Membership\Membership;
use Smush\Core\Stats\Global_Stats;

/**
 * // TODO: [WPMUDEV SMUSH UI] create tests
 */
class Optimization_Controller extends Controller {

	/**
	 * @var Optimization_Controller
	 */
	private static $instance;
	/**
	 * @var Global_Stats
	 */
	private $global_stats;

	private $membership;
	/**
	 * @var Settings
	 */
	private $settings;
	private $media_item_cache;

	private function __construct() {
		$this->global_stats     = Global_Stats::get();
		$this->membership       = Membership::get_instance();
		$this->settings         = Settings::get_instance();
		$this->media_item_cache = Media_Item_Cache::get_instance();

		$this->register_action( 'wp_smush_image_sizes_changed', array( $this, 'mark_global_stats_as_outdated' ) );
		$this->register_action( 'wp_smush_settings_updated', array(
			$this,
			'maybe_mark_global_stats_as_outdated',
		), 10, 2 );

		// TODO: handle auto optimization when media item is uploaded and async is disabled
		$this->register_action( 'wp_ajax_optimize_attachment', array( $this, 'optimize_attachment' ) );
		$this->register_action( 'wp_async_wp_generate_attachment_metadata', array(
			$this,
			'auto_optimize_attachment',
		) );
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function mark_global_stats_as_outdated() {
		$this->global_stats->mark_as_outdated();
	}

	public function maybe_mark_global_stats_as_outdated( $old_settings, $settings ) {
		$old_original            = ! empty( $old_settings['original'] );
		$new_original            = ! empty( $settings['original'] );
		$original_status_changed = $old_original !== $new_original;
		if ( $original_status_changed ) {
			$this->mark_global_stats_as_outdated();
		}
	}

	public function optimize_attachment() {
		if ( ! isset( $_REQUEST['attachment_id'] ) ) {
			wp_send_json_error(
				array( 'error_msg' => esc_html__( 'No attachment ID was provided.', 'wp-smushit' ) )
			);
		}

		if ( ! check_ajax_referer( 'wp-smush-ajax', '_nonce', false ) ) {
			wp_send_json_error(
				array( 'error_msg' => esc_html__( 'Nonce verification failed', 'wp-smushit' ) )
			);
		}

		if ( ! Helper::is_user_allowed( 'upload_files' ) ) {
			wp_send_json_error(
				array( 'error_msg' => esc_html__( "You don't have permission to work with uploaded files.", 'wp-smushit' ) )
			);
		}

		if ( $this->membership->is_api_hub_access_required() ) {
			wp_send_json_error(
				array( 'error_msg' => esc_html__( 'A WPMU DEV Hub connection is required to optimize images.', 'wp-smushit' ) )
			);
		}

		$attachment_id  = (int) $_REQUEST['attachment_id'];
		$optimizer      = Optimizer::get_instance();
		$is_optimized   = $optimizer->optimize( $attachment_id );
		$media_lib_item = Media_Library_Row::get_instance( $attachment_id );
		$markup         = $media_lib_item->generate_markup();

		if ( $is_optimized ) {
			wp_send_json_success( $markup );
		} else {
			$errors = $optimizer->get_errors();

			wp_send_json_error( array(
				'error'        => $errors->get_error_code(),
				'error_msg'    => $errors->get_error_message(),
				'html_stats'   => $markup,
				'show_warning' => $this->membership->should_show_premium_status_warning( $attachment_id ),
			) );
		}
	}

	public function should_auto_optimize( $attachment_id ) {
		if ( $this->membership->is_api_hub_access_required() ) {
			return false;
		}

		if ( ! $this->settings->is_automatic_compression_active() ) {
			return false;
		}

		$media_item = $this->media_item_cache->get( $attachment_id );
		if ( ! $media_item->is_valid() ) {
			return false;
		}

		/**
		 * Skip auto smush filter.
		 *
		 * @param bool $skip_auto_smush Whether to skip auto smush or not.
		 */
		$skip_auto_smush = apply_filters( 'wp_smush_should_skip_auto_smush', false, $attachment_id );

		// We don't want very large files to be auto smushed.
		$skip_auto_smush = $skip_auto_smush || $media_item->is_large();
		if ( $skip_auto_smush ) {
			return false;
		}

		return true;
	}

	public function auto_optimize_attachment( $id ) {
		// If we don't have image id or auto Smush is disabled, return.
		if ( empty( $id ) || ! $this->should_auto_optimize( $id ) ) {
			return;
		}

		$optimizer = Optimizer::get_instance();
		$optimizer->optimize( $id );
	}
}
