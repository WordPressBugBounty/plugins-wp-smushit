<?php
/**
 * PNG to JPG conversion: Png2jpg class
 *
 * TODO: [WPMUDEV SMUSH UI] remove the class since it creates deprecated methods only
 *
 * @package Smush\Core\Modules
 *
 * @version 2.4
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

namespace Smush\Core\Modules;

use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Png2Jpg\Png2Jpg_Optimization;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Png2jpg
 */
class Png2jpg extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'png_to_jpg';

	/**
	 * Whether module is pro or not.
	 *
	 * @var string
	 */
	protected $is_pro = false;

	/**
	 * Init method.
	 *
	 * @since 3.0
	 */
	public function init() {
	}

	/**
	 * Check if given attachment id can be converted to JPEG or not
	 *
	 * @param string $id    Atachment ID.
	 * @param string $size  Image size.
	 * @param string $mime  Mime type.
	 * @param string $file  File.
	 *
	 * @since 3.9.6 We removed the private method should_convert
	 * and we also handled the case which we need to delete the download file inside S3.
	 *
	 * Note, we also used this for checking resmush, so we only download the attached file (s3)
	 * if it's necessary (self::is_transparent()). Check the comment on self::__construct() for detail.
	 *
	 * @return bool True/False Can be converted or not
	 */
	public function can_be_converted( $id = '', $size = 'full', $mime = '', $file = '' ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Png2Jpg_Optimization::should_optimize' );

		$media_item   = Media_Item_Cache::get_instance()->get( $id );
		$optimization = new Png2Jpg_Optimization( $media_item );
		return $optimization->should_optimize();
	}

	/**
	 * Check whether to resmush image or not.
	 *
	 * @since 3.9.6
	 *
	 * @usedby Smush\App\Ajax::scan_images()
	 *
	 * @param bool $should_resmush Current status.
	 * @param int  $attachment_id  Attachment ID.
	 * @return bool|string png2jpg|TRUE|FALSE
	 */
	public function should_resmush( $should_resmush, $attachment_id ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Png2Jpg_Optimization::should_reoptimize' );

		$media_item   = Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimization = new Png2Jpg_Optimization( $media_item );
		return $should_resmush || $optimization->should_reoptimize();
	}

	/**
	 * Update the image URL, MIME Type, Attached File, file path in Meta, URL in post content
	 *
	 * @param string $id      Attachment ID.
	 * @param string $o_file  Original File Path that has to be replaced.
	 * @param string $n_file  New File Path which replaces the old file.
	 * @param string $meta    Attachment Meta.
	 * @param string $size_k  Image Size.
	 * @param string $o_type  Operation Type "conversion", "restore".
	 *
	 * @return array  Attachment Meta with updated file path.
	 */
	public function update_image_path( $id, $o_file, $n_file, $meta, $size_k, $o_type = 'conversion' ) {
		_deprecated_function( __METHOD__, '3.16.0' );

		return $meta;
	}

	/**
	 * Convert a PNG to JPG, Lossless Conversion, if we have any savings
	 *
	 * @param string       $id    Image ID.
	 * @param string|array $meta  Image meta.
	 *
	 * @uses Backup::add_to_image_backup_sizes()
	 *
	 * @return mixed|string
	 *
	 * TODO: Save cumulative savings
	 */
	public function png_to_jpg( $id = '', $meta = '' ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Png2Jpg_Optimization::optimize' );

		$media_item   = Media_Item_Cache::get_instance()->get( $id );
		$optimization = new Png2Jpg_Optimization( $media_item );

		if ( $optimization->should_optimize() ) {
			$optimization->optimize();
		}

		return $media_item->get_wp_metadata();
	}

	/**
	 * Check whether the given attachment was converted from PNG to JPG.
	 *
	 * @param int $id  Attachment ID.
	 *
	 * @since 3.9.6 Use this function to check if an image is converted from PNG to JPG.
	 * @see Backup::get_backup_file() To check the backup file.
	 *
	 * @return int|false False if the image id is empty.
	 * 0 Not yet converted, -1 Tried to convert but it failed or not saving, 1 Convert successfully.
	 */
	public function is_converted( $id ) {
		_deprecated_function( __METHOD__, '3.16.0', 'Png2Jpg_Optimization::is_optimized' );

		$media_item   = Media_Item_Cache::get_instance()->get( $id );
		$optimization = new Png2Jpg_Optimization( $media_item );
		return $optimization->is_optimized();
	}
}
