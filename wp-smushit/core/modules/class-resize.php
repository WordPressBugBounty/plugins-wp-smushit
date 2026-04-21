<?php
/**
 * Smush resize functionality: Resize class
 *
 * TODO: [WPMUDEV SMUSH UI] remove the class since it creates deprecated methods only
 *
 * @package Smush\Core\Modules
 * @version 2.3
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

namespace Smush\Core\Modules;

use Smush\Core\Core;
use Smush\Core\Helper;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Resize\File_Resizer;
use Smush\Core\Resize\Resize_Helper;
use Smush\Core\Resize\Resize_Optimization;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Resize
 */
class Resize extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'resize';

	/**
	 * Specified width for resizing images
	 *
	 * @var int
	 */
	public $max_w = 0;

	/**
	 * Specified Height for resizing images
	 *
	 * @var int
	 */
	public $max_h = 0;

	/**
	 * If resizing is enabled or not
	 *
	 * @var bool
	 */
	public $resize_enabled = false;

	/**
	 * Resize constructor.
	 *
	 * Initialize class variables, after all stuff has been loaded.
	 */
	public function init() {
	}

	/**
	 * Get the settings for resizing
	 *
	 * @param bool $skip_check Added for Mobile APP uploads.
	 */
	public function initialize( $skip_check = false ) {
		_deprecated_function(__METHOD__, '3.16.0');
	}

	/**
	 * We do not need this module on WordPress 5.3+.
	 *
	 * @since 3.3.2
	 */
	public function maybe_disable_module() {
		_deprecated_function(__METHOD__, '3.16.0');
	}

	/**
	 *  Checks whether the image should be resized.
	 *
	 * @uses self::check_should_resize().
	 *
	 * @param string $id Attachment ID.
	 * @param string $meta Attachment Metadata.
	 *
	 * @return bool Should resize or not
	 */
	public function should_resize( $id = '', $meta = '' ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Resize_Optimization::should_optimize' );

		$media_item          = Media_Item_Cache::get_instance()->get( $id );
		$resize_optimization = new Resize_Optimization( $media_item );
		return $resize_optimization->should_optimize();
	}

	/**
	 * Check whether to resmush image or not.
	 *
	 * @since 3.9.6
	 *
	 * @usedby Smush\App\Ajax::scan_images()
	 *
	 * @param bool $should_resmush Should resmush status.
	 * @param int  $attachment_id  Attachment ID.
	 * @return bool|string resize|TRUE|FALSE
	 */
	public function should_resmush( $should_resmush, $attachment_id ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Resize_Optimization::should_optimize' );

		return $should_resmush || $this->should_resize( $attachment_id );
	}

	/**
	 * Handles the Auto resizing of new uploaded images
	 *
	 * @param int   $id Attachment ID.
	 * @param mixed $meta Attachment Metadata.
	 *
	 * @return mixed Updated/Original Metadata if image was resized or not
	 */
	public function auto_resize( $id, $meta ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Resize_Optimization::optimize' );

		// Do not perform resize while restoring images/ Editing images.
		if ( ! empty( $_REQUEST['do'] ) && ( 'restore' === $_REQUEST['do'] || 'scale' === $_REQUEST['do'] ) ) {
			return $meta;
		}

		$media_item          = Media_Item_Cache::get_instance()->get( $id );
		$resize_optimization = new Resize_Optimization( $media_item );
		$resize_optimization->optimize();

		return $meta;
	}

	/**
	 * Generates the new image for specified width and height,
	 * Checks if the size of generated image is greater,
	 *
	 * @param string $file_path Original File path.
	 * @param int    $original_file_size File size before optimisation.
	 * @param int    $id Attachment ID.
	 * @param array  $meta Attachment Metadata.
	 * @param bool   $unlink Whether to unlink the original image or not.
	 *
	 * @return array|bool|false If the image generation was successful
	 */
	public function perform_resize( $file_path, $original_file_size, $id, $meta = array(), $unlink = true ) {
		_deprecated_function( __METHOD__, '3.16.0', 'File_Resizer::resize' );

		$resize_helper = new Resize_Helper();
		$dimensions    = $resize_helper->get_resize_dimensions();
		if ( $id ) {
			$media_item = Media_Item_Cache::get_instance()->get( $id );
			if ( $media_item ) {
				$dimensions = $resize_helper->get_resize_dimensions();
			}
		}

		$resizer = new File_Resizer( $file_path, $original_file_size, $dimensions->width, $dimensions->height );
		$resized = $resizer->resize();

		return $resized
			? array(
				'file'      => basename( $file_path ),
				'width'     => $resizer->get_new_width(),
				'height'    => $resizer->get_new_height(),
				'mime-type' => mime_content_type( $file_path ),
				'filesize'  => $resizer->get_new_filesize(),
			)
			: false;
	}

	/**
	 * Return Filename.
	 *
	 * @param string $filename Filename.
	 *
	 * @return mixed
	 */
	public function file_name( $filename ) {
		_deprecated_function( __METHOD__, '3.16.0' );
		if ( empty( $filename ) ) {
			return $filename;
		}

		return $filename . 'tmp';
	}
}
