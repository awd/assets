<?php
/**
* Common grouping of functions used with the Asset Behavior and Asset Helper
*
* @version 0.2
* @created November 30th, 2007
* @modified April 21st, 2009
* @author Adam Doeler
* @email adam@adamdoeler.com
* @website http://adamdoeler.com
* @cake 1.2.x.x
*/

/**
* Checks if the file dimension and the parsed dimension meet the validation requirements
*
* @param string $info image dimension 
* @param string $geometry (parsed dimension)
* @param string $gtlt determine if we test > or < or =
* @access private
*/
  function assetCheckDimensions($info, $geometry, $gtlt) {
    switch ($gtlt) {
      case 'greater':
        if ($info < $geometry) return false;
      break;
      case 'less':
        if ($info > $geometry) return false;
      break;
      default:
        if ($info != $geometry) return false;
      break;
    }
    
    return true;
  }
  
/**
* Delete the asset file on disk
* 
* @param object $model Model using this behavior
* @param array $assets An array containing file names to remove
* @access private
*/
  function assetDelete(&$model, $assets = array()) {
    $output = array();

    # initialize Folder class
    $folder =& new Folder();

    # define the directory path
    $path = WWW_ROOT . 'files' .DS;
    $path .= strtolower(Inflector::pluralize($model->name)) .DS;
    $path .= $model->id .DS;

    # determine if we need to remove all assets or an individual asset
    if (!empty($assets) && is_array($assets)) {
      $errors = false;

      foreach ($assets as $asset) if (!empty($asset) && file_exists($path.$asset) && !unlink($path.$asset)) $errors = true;

      if ($errors == true) {
        $output = array(
          'status' => false,
          'message' => 'Some individual files could not be removed, please contact an administrator',
          'type' => 'notice'
        );

        return $output;
      }
    } else {

      # remove files and directories from disk
      if (!$folder->delete($path)) {
        $output = array(
          'status' => false,
          'message' => 'Some files could not be removed, please contact an administrator',
          'type' => 'notice'
        );

        return $output;
      }

      # remove model storage directory if empty - reset path
      $path = WWW_ROOT . 'files' .DS;
      $path .= strtolower(Inflector::pluralize($model->name)) .DS;
    }

    # attempt to clean directory if empty of files
    $dirs = $folder->tree($path, false);

    if (count($dirs[0]) == 1 && count($dirs[1]) == 0) {
      if (!$folder->delete($path)) {
        $output = array(
          'status' => false,
          'message' => 'Parent directory not removed, please contact an administrator',
          'type' => 'notice'
        );

        return $output;
      }
    }

    return true;
  }

/**
* Creates the directory where files are to be stored, filepath looks like: /files/{model}/{id}/
* 
* @param object $model Model using this behavior
* @access private
*/
  function assetDirectoryCreate(&$model) {
    $folder =& new Folder();

    # define the directory path
    $path = WWW_ROOT . 'files' .DS;
    $path .= strtolower(Inflector::pluralize($model->name)) .DS;
    $path .= $model->id .DS;

    if (is_dir($path)) return true;

    # create the directory path
    return $folder->create($path);
  }

/**
* Returns the directory path created based on the model
* 
* @param object $model Model using this behavior
* @access private
*/
  function assetDirectoryPath(&$model) {
    $path = WWW_ROOT . 'files' .DS;
    $path .= strtolower(Inflector::pluralize($model->name)) .DS;
    $path .= $model->id .DS;

    return $path;
  }

/**
* Saves the error message to the parent Model validationErrors
* 
* @param object $model Model using this behavior
* @param string $message Input the error message
* @param string $type See AlertComponent for valid alert types
* @access private
*/
  function assetError(&$model, $message, $type = 'notice') {
    if (App::import('Component', 'Session')) {
      $session =& new SessionComponent();
      $session->delete('Alerts');

      # append $message to existing messages
      $alerts = $session->read('Alerts');
      $alerts[$type][] = $message;
      $session->write('Alerts', $alerts);
    }
  }

/**
* Returns the new file name based on the version, field, and extension
* 
* @param string $source Filename to parse and format
* @param string $version Determines if we are naming an original or a copy
* @param string $key append the key to the beginning of the original file only
* @access private
*/
  function assetFileName($source, $version = null, $key = null) {

    # get the file name and extension
    $filename = pathinfo($source, PATHINFO_FILENAME);
    $extension = pathinfo($source, PATHINFO_EXTENSION);

    if (!empty($version) && $version == 'original') {
      if (!empty($key)) $filename = $key .'.'. $filename;

      # safety the filename
      $filename = Inflector::slug($filename, '_');

      # original file creation
      $filename .= '.'. substr(md5(microtime(true).rand(0,9999)), 0, 5);
    } else {

      # versioned file creation
      #$version = !empty($version) ? $version : null;
      $filename = $version .'.'. $filename;
    }

    # return formatted filename w/ extension
    return strtolower($filename .'.'. $extension);
  }

/**
* Provide HumanizeSize a file size in bytes and an easier to read value will return 
* 
* @param int $size Size in bytes
* @access public
*/
  function assetHumanizeSize($size = 0) {
    $output = null;
    
    switch ($size) {
      case 0:
        $output = '0 Bytes';
      break;
      case 1:
        $output = '1 Byte';
      break;
      case $size < 1024:
        $output = $size . ' Bytes';
      break;
      case $size < 1024 * 1024:
        $output = sprintf("%01.0f", $size / 1024). 'KB';
      break;
      case $size < 1024 * 1024 * 1024:
        $output = sprintf("%01.2f", $size / 1024 / 1024). 'MB';
      break;
      case $size < 1024 * 1024 * 1024 * 1024:
        $output = sprintf("%01.2f", $size / 1024 / 1024 / 1024). 'GB';
      break;
      case $size < 1024 * 1024 * 1024 * 1024 * 1024:
        $output = sprintf("%01.2f", $size / 1024 / 1024 / 1024 / 1024). 'TB';
      break;
    }
    
    return $output;
  }

/**
* Returns an array of Asset Mime-Types
* 
* @param string $type Provided if a specific type is requested
* @access public
*/
  function assetMimes($type = null) {
    $mimes = array(
      'audio' => array(
        'audio/mpeg',
        'audio/mpg'),
      'flash' => array(
        'application/x-shockwave-flash'),
      'image' => array(
        'image/gif',
        'image/jpeg',
        'image/pjpeg',
        'image/png'),
      'pdf' => array(
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'text/pdf',
        'text/x-pdf'),
      'ppt' => array(
        'application/mspowerpoint',
        'application/powerpoint',
        'application/vnd.ms-powerpoint',
        'application/x-mspowerpoint'),
      'text' => array(
        'text/plain',
        'application/msword'),
      'video' => array(
        'application/octet-stream',
        'video/x-flv'),
      'zip' => array(
        'application/x-compressed',
        'application/x-zip-compressed',
        'application/zip',
        'multipart/x-zip',
        'application/x-tar',
        'application/x-compressed',
        'application/x-gzip',
        'multipart/x-gzip'),
    );
    
    return !empty($type) && in_array($type, array_keys($mimes)) ? $mimes[$type] : $mimes;
  }

/**
* Saves the newly renamed file into the previously created directory
* 
* @param object $model Model using this behavior
* @param string $source Full file path of the source file to be moved or copied
* @param string $filename New name of the created file
* @param mixed $upload Tmp files are moved (uploaded), while duplicate files are copied (for resizing purposes)
* @access private
*/
  function assetSaveFile(&$model, $source, $filename, $upload = true) {

    # create the desintation
    $destination = assetDirectoryPath($model) . $filename;
    
    switch (true) {
      case is_uploaded_file($source):
        if (!move_uploaded_file($source, $destination)) return false;
      break;
      case $upload == false:
        if (!copy($source, $destination)) return false;
      break;
      case $upload == 'move':
        if (!rename($source, $destination)) return false;
      break;
      case !is_uploaded_file($source):
        return assetSaveFile($model, $source, $filename, 'move');
      break;
    }

    # determine if the file exists
    if (!file_exists($destination)) return false;

    # change the permissions
    if (!chmod($destination, 0755)) return false;

    # nothing failed
    return true;
  }
  
/**
* Stringify an array of values, comma seperated
* 
* @param array $data Contains an array of values to be converted
* @access public
*/
  function assetStringify($data) {
    if (!is_array($data)) return $data;

    $humanize = array_map(array('Inflector', 'humanize'), $data);
    return implode(', ', $humanize);
  }
?>