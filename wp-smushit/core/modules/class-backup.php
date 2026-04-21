<?php
/**
 * Smush backup class
 *
 * @package Smush\Core\Modules
 */

namespace Smush\Core\Modules;

use Smush\Core\Backups\Backups;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Media\Media_Item_Optimizer;
use WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Backup
 * TODO: [WPMUDEV SMUSH UI] this file can be removed since it contains deprecated methods only. First make sure the replacement class works correctly
 */
class Backup extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'backup';

	/**
	 * Backup constructor.
	 */
	public function init() {
	}

	/**
	 * Check if the backup file exists.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Current file path.
	 * @return bool  True if the backup file exists, false otherwise.
	 */
	public function backup_exists( $attachment_id, $file_path = false ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Media\Media_Item::backup_file_exists' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		return $media_item->can_be_restored();
	}

	/**
	 * Creates a backup of file for the given attachment path.
	 *
	 * Checks if there is an existing backup, else create one.
	 *
	 * @param string $file_path      File path.
	 * @param int    $attachment_id  Attachment ID.
	 *
	 * @return void
	 */
	public function create_backup( $file_path, $attachment_id ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Backups\Backups::maybe_create_backup' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimizer  = new Media_Item_Optimizer( $media_item );
		$backups    = new Backups();
		$backups->maybe_create_backup( $media_item, $optimizer );
	}

	/**
	 * Store new backup path for the image.
	 *
	 * @param int    $attachment_id  Attachment ID.
	 * @param string $backup_path    Backup path.
	 * @param string $backup_key     Backup key.
	 */
	public function add_to_image_backup_sizes( $attachment_id, $backup_path, $backup_key = '' ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Media\Media_Item::add_backup_size' );

		if ( file_exists( $backup_path ) ) {
			return;
		}

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		$file_name  = basename( $backup_path );
		$image_size = getimagesize( $backup_path );
		if ( empty( $image_size ) ) {
			return;
		}

		$media_item->add_backup_size( $file_name, $image_size[0], $image_size[1], $backup_key );
	}

	/**
	 * Get backup sizes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return mixed False or an array of backup sizes.
	 */
	public function get_backup_sizes( $attachment_id ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Media\Media_Item::get_backup_sizes' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		$sizes      = $media_item->get_backup_sizes();
		return array_map( function ( $size ) {
			return $size->to_array();
		}, $sizes );
	}

	/**
	 * Back up an image if it hasn't backed up yet.
	 *
	 * @since 3.9.6
	 *
	 * @param int    $attachment_id  Image id.
	 * @param string $backup_file    File path to back up.
	 *
	 * Note, we used it to manage backup PNG2JPG to keep the backup file is the original file to avoid conflicts with a duplicate PNG file.
	 * If the backup file exists it will rename the original backup file to
	 * the new backup file.
	 *
	 * @return bool  True if added this file to the backup sizes, false if the image was backed up before.
	 */
	public function maybe_backup_image( $attachment_id, $backup_file ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Backups\Backups::maybe_create_backup' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimizer  = new Media_Item_Optimizer( $media_item );
		$backups    = new Backups();
		return $backups->maybe_create_backup( $media_item, $optimizer );
	}

	/**
	 * Get the backup file from the meta.
	 *
	 * @since 3.9.6
	 *
	 * @param int    $id  Image ID.
	 * @param string $file_path Current file path.
	 *
	 * @return bool|null  Backup file or false|null if the image doesn't exist.
	 */
	public function get_backup_file( $id, $file_path = false ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Media\Media_Item::get_default_backup_size()->get_file_path()' );

		if ( empty( $id ) ) {
			return null;
		}

		$media_item  = Media_Item_Cache::get_instance()->get( $id );
		$backup_size = $media_item->get_default_backup_size();
		if ( $backup_size ) {
			return $backup_size->get_file_path();
		}

		return null;
	}

	/**
	 * Restore the image and its sizes from backup
	 *
	 * @param string $attachment_id  Attachment ID.
	 * @param bool   $resp           Send JSON response or not.
	 *
	 * @return bool
	 */
	public function restore_image( $attachment_id = '', $resp = true ) {
		_deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Backups\Backups_Controller::handle_restore_ajax' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimizer  = new Media_Item_Optimizer( $media_item );
		$restored   = $optimizer->restore();

		if ( $restored ) {
			if ( $resp ) {
				wp_send_json_success( array(
					'stats'    => WP_Smush::get_instance()->library()->generate_markup( $attachment_id ),
					'new_size' => $media_item->get_full_size()->get_filesize(),
				) );
			} else {
				return true;
			}
		} else {
			if ( $resp ) {
				wp_send_json_error( array( 'error_msg' => $optimizer->get_errors()->get_error_message() ) );
			} else {
				return false;
			}
		}
	}

	/**
	 * Get the attachments that can be restored.
	 *
	 * @since 3.6.0  Changed from private to public.
	 *
	 * @return array  Array of attachments IDs.
	 */
	public function get_attachments_with_backups() {
		// _deprecated_function( __METHOD__, '3.16.0', '\Smush\Core\Backups\Backups::count_attachments_with_backups' );

		global $wpdb;

		$images_to_restore = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key='_wp_attachment_backup_sizes' 
				AND (`meta_value` LIKE '%smush-full%'
				OR `meta_value` LIKE '%smush_png_path%')"
		);

		return $images_to_restore;
	}

	/**
	 * Returns the backup path for attachment
	 *
	 * @param string $attachment_path  Attachment path.
	 *
	 * @return string
	 */
	public function get_image_backup_path( $attachment_path ) {
		_deprecated_function( __METHOD__, '3.16.0' );

		if ( empty( $attachment_path ) ) {
			return '';
		}

		$path = pathinfo( $attachment_path );

		if ( empty( $path['extension'] ) ) {
			return '';
		}

		return trailingslashit( $path['dirname'] ) . $path['filename'] . '.bak.' . $path['extension'];
	}

	/**
	 * Clear up all the backup files for the image while deleting the image.
	 *
	 * @since 3.9.6
	 * Note, we only call this method while deleting the image, as it will delete
	 * .bak file and might be the original file too.
	 *
	 * Note, for the old version < 3.9.6 we also save all PNG files (original file and thumbnails)
	 * when the site doesn't compress original file.
	 * But it's not safe to remove them if the user add another image with the same PNG file name, and didn't convert it.
	 * So we still leave them there.
	 *
	 * @param int $attachment_id  Attachment ID.
	 */
	public function delete_backup_files( $attachment_id ) {
		_deprecated_function( __METHOD__, '3.16.0' );

		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		foreach ( $media_item->get_backup_sizes() as $backup_size ) {
			unlink( $backup_size->get_file_path() );
		}
	}

}
