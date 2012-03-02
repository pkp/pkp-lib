<?php
/*-------------------------------------------------------------------
| Image Upload Plugin for TinyMCE
| http://justboil.me/tinymce-images-plugin/
|
| IMPORTANT NOTE! In case, when TinyMCE’s folder is not protected with HTTP Authorisation,
| you should require is_allowed()  function to return
| `TRUE` if user is authorised,
| `FALSE` - otherwise.
| is_allowed()  is found at plugins/jbimages/is_allowed.php of your TinyMCE installation.
|
| This would protect upload script, if someone guesses it's url.
|
|
-------------------------------------------------------------------*/


/*-------------------------------------------------------------------
|
| Path to upload target folder, relative to domain name. NO TRAILING SLASH!
| Example: if an image is acessed via http://www.example.com/images/somefolder/image.jpg, you should specify here:
|
| $config['img_path'] = '/images/somefolder';
|
-------------------------------------------------------------------*/

	/* PKP Customization, 01/03/2012; Determine img_path from external file */
	require_once('integratePKP.php');
	$integratePKP = new IntegratePKP();

	$config['img_path'] = $integratePKP->getPKPImageUrl();


/*-------------------------------------------------------------------
|
| Allowed image filetypes. Specifying something other, than image types would result in error.
|
| $config['allowed_types'] = 'gif|jpg|png';
|
-------------------------------------------------------------------*/


	$config['allowed_types'] = 'gif|jpg|png';


/*-------------------------------------------------------------------
|
| Maximum image file size in kilobytes. This value can't exceed value set in php.ini.
| Set to `0` if you want to use php.ini default:
|
| $config['max_size'] = 0;
|
-------------------------------------------------------------------*/


	$config['max_size'] = 0;


/*-------------------------------------------------------------------
|
| Maximum image width. Set to `0` for no limit:
|
| $config['max_width'] = 0;
|
-------------------------------------------------------------------*/


	$config['max_width'] = 0;


/*-------------------------------------------------------------------
|
| Maximum image height. Set to `0` for no limit:
|
| $config['max_height'] = 0;
|
-------------------------------------------------------------------*/


	$config['max_height'] = 0;


/*-------------------------------------------------------------------
|
| Allow script to resize image that exceeds maximum width or maximum height (or both)
| If set to `TRUE`, image will be resized to fit maximum values (proportions are saved)
| If set to `FALSE`, user will recieve an error message.
|
| $config['allow_resize'] = TRUE;
|
-------------------------------------------------------------------*/


	$config['allow_resize'] = TRUE;


/*-------------------------------------------------------------------
|
| Image name encryption
| If set to `TRUE`, image file name will be encrypted in something like 7fdd57742f0f7b02288feb62570c7813.jpg
| If set to `FALSE`, original filenames will be preserved
|
| $config['encrypt_name'] = TRUE;
|
-------------------------------------------------------------------*/


	$config['encrypt_name'] = FALSE;


/*-------------------------------------------------------------------
|
| How to behave if 2 or more files with the same name are uploaded:
| `TRUE` - the entire file will be overwritten
| `FALSE` - a number will be added to the newly uploaded file name
|
-------------------------------------------------------------------*/


	$config['overwrite'] = FALSE;


/*-------------------------------------------------------------------
|
| Target upload folder relative to document root. Most likely, you will not need to change this setting.
|
-------------------------------------------------------------------*/

	/* PKP Customization, 01/03/2012; Specify upload path */
	$config['upload_path'] = $integratePKP->getPKPImageUploadPath();


/*-------------------------------------------------------------------
|
| Maximum size of upload directory in kilobytes (set to 0 for no limit)
| PKP Customization, 01/03/2012
|
-------------------------------------------------------------------*/

	$config['max_upload_dir_size'] = 25000;


/*-------------------------------------------------------------------
|
| Show uploaded images and allow for images to be deleted
| PKP Customization, 01/03/2012
|
-------------------------------------------------------------------*/

	$config['list_images'] = TRUE;


/*-------------------------------------------------------------------
|
| THAT IS ALL. HAVE A NICE DAY! )))
|
-------------------------------------------------------------------*/
?>
