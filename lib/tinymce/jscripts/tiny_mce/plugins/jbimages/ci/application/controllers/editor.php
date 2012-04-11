<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/** See http://ca.php.net/manual/en/function.realpath.php#92552 */
// fixes windows paths...
// (windows accepts forward slashes and backwards slashes, so why does PHP
// use backwards?
function fix_path($path) {
	return str_replace('\\','/',$path);
}

/** See http://ca.php.net/manual/en/function.realpath.php#92552 */
// takes two absolute paths and determines if one is a subdirectory of the
// other. it doesn't care if it is an immediate child or 10 subdirectories
// deep... use absolute paths for both for best results
function is_child($parent, $child) {
	if(false !== ($parent = realpath($parent))) {
		$parent = fix_path($parent);
		if(false !== ($child = realpath($child))) {
			$child = fix_path($child);
			if(substr($child, 0, strlen($parent)) == $parent)
				return true;
		}
	}
   
	return false;
}

class Editor extends CI_Controller {

	/* Constructor */

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('jbimages','language'));

		if (is_allowed() === FALSE)
		{
			exit;
		}

		$this->config->load('uploader_settings', TRUE);
	}

	/* Helper */

	private function _lang_set($lang)
	{
		// We do say hello to any language set as lang_id in **_dlg.js
		$langs = array('russian','english');
		if (!in_array($lang, $langs))
		{
			$lang = 'english';
		}

		$this->config->set_item('language', $lang);
		$this->lang->load('jbstrings', $lang);
	}

	/* Default upload routine */

	public function upload($lang='english')
	{
		$this->_lang_set($lang);

		$conf['img_path'] = $this->config->item('img_path', 'uploader_settings');
		$conf['allow_resize'] = $this->config->item('allow_resize', 'uploader_settings');
		//$conf['latinize_name'] = $this->config->item('latinize_name', 'uploader_settings'); //not used

		$config['allowed_types'] = $this->config->item('allowed_types', 'uploader_settings');
		$config['max_size'] = $this->config->item('max_size', 'uploader_settings');
		$config['encrypt_name'] = $this->config->item('encrypt_name', 'uploader_settings');
		$config['overwrite'] = $this->config->item('overwrite', 'uploader_settings');
		$config['upload_path'] = $this->config->item('upload_path', 'uploader_settings');

		if (!$conf['allow_resize'])
		{
			$config['max_width'] = $this->config->item('max_width', 'uploader_settings');
			$config['max_height'] = $this->config->item('max_height', 'uploader_settings');
		}
		else
		{
			$conf['max_width'] = $this->config->item('max_width', 'uploader_settings');
			$conf['max_height'] = $this->config->item('max_height', 'uploader_settings');

			if ($conf['max_width'] == 0 and $conf['max_height'] == 0)
			{
				$conf['allow_resize'] = FALSE;
			}
		}

		// Check that the image directory isn't full
		$maxUploadDirSize = $this->config->item('max_upload_dir_size', 'uploader_settings') * 1024;
		$uploadDirSizeExceeded = false;
		if ($maxUploadDirSize != 0) {
			$this->load->helper('file');

			// Add the total of all files in the directory
			$totalSize = 0;
			$files = get_dir_file_info($config['upload_path']);
			foreach($files as $file) {
				$totalSize += $file['size'];
			}

			// Add the size of the newly uploaded file
			$totalSize += $_FILES['userfile']['size'];

			if($totalSize > $maxUploadDirSize)
			{
				$uploadDirSizeExceeded = true;
			}
		}

		$this->load->library('upload', $config);

		if (!$uploadDirSizeExceeded && $this->upload->do_upload())
		{
			$result = $this->upload->data();

			if ($conf['allow_resize'] and $conf['max_width'] > 0 and $conf['max_height'] > 0 and (($result['image_width'] > $conf['max_width']) or ($result['image_height'] > $conf['max_height'])))
			{
				$resizeParams = array
				(
					'source_image'=>$result['full_path'],
					'new_image'=>$result['full_path'],
					'width'=>$conf['max_width'],
					'height'=>$conf['max_height']
				);

				$this->load->library('image_lib', $resizeParams);
				$this->image_lib->resize();
			}


			/* //The old resize routine DEPRECATED

			if ($conf['allow_resize'] and (($result['image_width'] > $conf['max_width'] and $conf['max_width'] > 0) or ($result['image_height'] > $conf['max_height'] and $conf['max_height'] > 0)))
			{
				if ($conf['max_height'] == 0)
				{
					$aspect_ratio = $result['image_width'] / $result['image_height'];
					$new_width = $conf['max_width'];
					$new_height = floor($new_width / $aspect_ratio);
				}
				elseif ($conf['max_width'] == 0)
				{
					$aspect_ratio = $result['image_height'] / $result['image_width'];
					$new_height = $conf['max_height'];
					$new_width = floor($new_height / $aspect_ratio);
				}
				else
				{
					$new_width = $conf['max_width'];
					$new_height = $conf['max_height'];
				}

				$resizeParams = array
				(
					'source_image'=>$result['full_path'],
					'width'=>$new_width,
					'height'=>$new_height
				);
				$this->load->library('image_lib', $resizeParams);
				$this->image_lib->resize();
			}*/

			$result['result'] = "file_uploaded";
			$result['resultcode'] = 'ok';
			$result['file_name'] = $conf['img_path'] . '/' . $result['file_name'];
			$this->load->view('ajax_upload_result', $result);
		}
		else
		{
			if ($uploadDirSizeExceeded)
			{
				$result['result'] = 'Maximum space for upload directory exceeded.';
			}
			else
			{
				$result['result'] = $this->upload->display_errors('', '');
			}
			$result['resultcode'] = 'failed';
			$result['file_name'] = '';
			$this->load->view('ajax_upload_result', $result);
		}
	}

	/* Display a list of images in the upload directory and allow them to be deleted */

	function listImages($lang='english') {
		$this->_lang_set($lang);
		if($this->config->item('list_images', 'uploader_settings')) {
			$this->load->helper('file');
			$imageDir = $this->config->item('upload_path', 'uploader_settings');
			$imageUrl = $this->config->item('img_path', 'uploader_settings');
			
			$files = get_dir_file_info($imageDir);
			if(!empty($files)) {
				$fileList = array();
				foreach($files as $file) {
					$image = array('img_path' => $imageUrl . '/' . $file['name'],
									'name' => $file['name'],
									'size' => round(((int)$file['size'] / 1024), 2));
					$fileList[] = $image;
				}

				$data['files'] = $fileList;	
			} else $data['files'] = null;
			
			$this->load->view('file_list', $data);
		} else $this->load->view('no_file_list');
	}

	/* Display a list of images in the upload directory and allow them to be deleted */
	function deleteImage($imageName, $lang='english') {
		$this->_lang_set($lang);
		if($this->config->item('list_images', 'uploader_settings')) {
			$this->load->helper('file');
			$imageDir = $this->config->item('upload_path', 'uploader_settings');
			$imageUrl = $this->config->item('img_path', 'uploader_settings');
			
			// Make sure image exists in upload path
			$imagePath = $imageDir . '/' . basename(urldecode($imageName));
			if(is_file($imagePath) && file_exists($imagePath) && is_child($imageDir, dirname($imagePath))) {
				unlink($imagePath);
			} else {
				show_error('File does not exist');
			}

			$this->listImages();
		} else $this->load->view('no_file_list');
	}

	/* Blank Page (default source for iframe) */

	public function blank($lang='english')
	{
		$this->_lang_set($lang);
		$this->load->view('blank');
	}

	public function index($lang='english')
	{
		$this->blank($lang);
	}
}

/* End of file editor.php */
/* Location: ./application/controllers/editor.php */
