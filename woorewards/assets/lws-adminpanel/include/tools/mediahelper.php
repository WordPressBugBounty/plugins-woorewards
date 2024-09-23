<?php
namespace LWS\Adminpanel\Tools;

if( !defined( 'ABSPATH' ) ) exit();

class MediaHelper
{
	// return media sizes source array
	static function getMediaSizes($mediaSizes = array())
	{
		foreach(\wp_get_registered_image_subsizes() as $k => $size) {
			$dim = isset($size['width']) ? ' (width : ' . $size['width'] . 'px ' : '';
			$dim .= !empty($dim) ? '|' : '(';
			$dim .= isset($size['height']) ? ' height : ' . $size['height'] . 'px)' : ')';
			$mediaSizes[] = array('value' => $k, 'label' => $k . $dim);
		}
		return $mediaSizes;
	}

	// Checks if the searchedMediaSize is still enlisted and returns it, if not then return the $defaultMediaSize
	static function getVerifiedMediaSize($searchedMediaSize = '', $defaultMediaSize = '')
	{
		return \in_array($searchedMediaSize, array_column(self::getMediaSizes(), 'value')) ? $searchedMediaSize : $defaultMediaSize;
	}

	static function uploadImage($path, $filename, $title='', $description='', $status='inherit')
	{
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$now = \date_create();
		$subdir = $now->format('Y') . '/' . $now->format('m');
		$uploadDir = \wp_upload_dir()['basedir'] . '/' . $subdir;
		$uploadFile = $uploadDir . '/' . $filename;

		if( !file_exists($uploadDir) )
		{
			if( false === @mkdir($uploadDir, 0777, true) )
			{
				error_log("Cannot create directory in ./uploads");
				return false;
			}
		}

		if( !@copy($path, $uploadFile) )
		{
			error_log("Cannot copy image from $path to $uploadFile");
			return false;
		}

		$wp_filetype = \wp_check_filetype($filename, null );
		$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => \trim($title) ? $title : \sanitize_file_name($filename),
				'post_content'   => $description,
				'post_status'    => $status,
		);
		$attach_id = \wp_insert_attachment($attachment, $uploadFile);
		$attach_data = \wp_generate_attachment_metadata($attach_id, $uploadFile);
		\wp_update_attachment_metadata($attach_id, $attach_data);
		return $attach_id;
	}
}