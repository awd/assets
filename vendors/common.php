<?php
/**
* Common grouping of functions used with the Asset Behavior and Asset Helper
*
* @version 0.2
* @created November 30th, 2007
* @modified February 6th, 2009
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
* @param string $parent Name of the Parent Model for this Asset
* @param int $foreignKey Parent ID for the Parent Model
* @access public
*/
  function assetDelete(&$model, $parent, $foreignKey, $assets = array()) {
    $output = array();

    # initialize Folder class
    $folder =& new Folder();

    # define the directory path
    $path = WWW_ROOT . 'files' .DS;
    $path .= strtolower(Inflector::pluralize($parent)) .DS;
    $path .= $foreignKey .DS;

    # determine if we need to remove all assets or an individual asset
    if (!empty($assets) && is_array($assets)) {

      $errors = false;

      foreach ($assets as $asset) {
        if (file_exists($path.$asset) && !unlink($path.$asset)) $errors = true;
      }

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
      $path .= strtolower(Inflector::pluralize($parent)) .DS;
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
* Provide HumanizeSize a file size in bytes and an easier to read value will return 
* 
* @param int $size Size in bytes
* @access public
*/
  function assetHumanizeSize($size = 0) {
    
    switch ($size) {
      case 0:
        return '0 Bytes';
      break;
      case 1:
        return '1 Byte';
      break;
      case $size < 1024:
        return $size . ' Bytes';
      break;
      case $size < 1024 * 1024:
        return sprintf("%01.0f", $size / 1024). 'KB';
      break;
      case $size < 1024 * 1024 * 1024:
        return sprintf("%01.2f", $size / 1024 / 1024). 'MB';
      break;
      case $size < 1024 * 1024 * 1024 * 1024:
        return sprintf("%01.2f", $size / 1024 / 1024 / 1024). 'GB';
      break;
      case $size < 1024 * 1024 * 1024 * 1024 * 1024:
        return sprintf("%01.2f", $size / 1024 / 1024 / 1024 / 1024). 'TB';
    }
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