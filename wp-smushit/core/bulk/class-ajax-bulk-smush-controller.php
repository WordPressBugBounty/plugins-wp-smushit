<?php

namespace Smush\Core\Bulk;

use Smush\Core\Controller;
use Smush\Core\Error_Handler;
use Smush\Core\Helper;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Membership\Membership;

class Ajax_Bulk_Smush_Controller extends Controller {
	/**
	 * @var Bulk_Optimize
	 */
	private $bulk_optimize;
	/**
	 * @var Membership
	 */
	private $membership;
	private $media_item_cache;

	public function __construct() {
		$this->bulk_optimize    = new Bulk_Optimize();
		$this->membership       = Membership::get_instance();
		$this->media_item_cache = Media_Item_Cache::get_instance();

		$this->register_ajax_handler( 'start_bulk_optimization', array( $this, 'start_bulk_optimization' ) );
		$this->register_ajax_handler( 'bulk_optimize_attachments', array( $this, 'bulk_optimize_attachments' ) );
		$this->register_ajax_handler( 'complete_bulk_optimization', array( $this, 'complete_bulk_optimization' ) );
	}

	private function register_ajax_handler( $action, $handler ) {
		add_action( "wp_ajax_$action", $handler );
	}

	public function start_bulk_optimization() {
		$this->bulk_optimize->start_bulk_optimization();
	}

	public function bulk_optimize_attachments() {
		check_ajax_referer( 'wp-smush-ajax', '_nonce' );

		if ( ! Helper::is_user_allowed( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'error'         => 'unauthorized',
					'error_message' => esc_html__( "You don't have permission to do this.", 'wp-smushit' ),
				),
				403
			);
		}

		if ( empty( $_REQUEST['attachment_id'] ) ) {
			wp_send_json_error(
				array(
					'error'    => 'missing_id',
					'continue' => false,
				)
			);
		}

		$attachment_id = (int) $_REQUEST['attachment_id'];
		$result        = $this->bulk_optimize->optimize_attachment( $attachment_id );
		$stats         = $this->bulk_optimize->compile_stats( $attachment_id );
		$show_warning  = (int) $this->membership->should_show_premium_status_warning( $attachment_id );

		if ( is_wp_error( $result ) && $result->has_errors() ) {
			$error = Error_Handler::get_error( $result, $this->media_item_cache->get( $attachment_id ) );

			wp_send_json_error(
				array(
					'stats'        => $stats,
					'error'        => $error,
					'show_warning' => $show_warning,
				)
			);
		}

		wp_send_json_success(
			array(
				'stats'        => $stats,
				'show_warning' => $show_warning,
			)
		);
	}

	public function complete_bulk_optimization() {
		$this->bulk_optimize->complete_bulk_optimization();
	}
}
