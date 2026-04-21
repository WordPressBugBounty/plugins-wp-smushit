<?php

namespace Smush\Core;

use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Media\Media_Item_Optimizer;
use WP_Error;

/**
 * This is a light weight facade that acts as the first entry point for optimization. The real work is done by {@see Media_Item_Optimizer}.
 */
class Optimizer {
	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;
	/**
	 * @var bool
	 */
	private $optimization_in_progress;
	/**
	 * @var Media_Item_Cache
	 */
	private $media_item_cache;
	/**
	 * @var \WP_Error
	 */
	private $errors;

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->media_item_cache = Media_Item_Cache::get_instance();
		$this->errors           = new \WP_Error();
	}

	public function optimize( $attachment_id ) {
		if ( $this->optimization_in_progress ) {
			$this->set_errors( new WP_Error( 'in_progress', 'Smush already in progress' ) );
			return false;
		}

		$this->optimization_in_progress = true;

		// Reset the errors before starting
		$this->set_errors( null );

		$media_item           = $this->media_item_cache->get( $attachment_id );
		$media_item_optimizer = new Media_Item_Optimizer( $media_item );
		$optimized            = $media_item_optimizer->optimize();
		if ( ! $optimized ) {
			$errors = $media_item->has_errors()
				? $media_item->get_errors()
				: $media_item_optimizer->get_errors();
			$this->set_errors( $errors );
		}

		$this->optimization_in_progress = false;

		return $optimized;
	}

	public function get_errors() {
		if ( is_null( $this->errors ) ) {
			$this->errors = new WP_Error();
		}
		return $this->errors;
	}

	private function set_errors( $error ) {
		$this->errors = $error;
	}
}
