<?php
/**
* Asset Helper for Views in Cake 1.2
* 
* the files are stored as follows
* WWW_ROOT/files/{model}/{foreign_key}/{field}.ext
* 
* resized versions of the same file will be stored as
* WWW_ROOT/files/{model}/{foreign_key}/{version}.{field}.ext
* 
* @version 1.1
* @created November 28th, 2007
* @modified March 17th, 2009
* @author Adam Doeler
* @email adam@adamdoeler.com
* @website http://adamdoeler.com
* @cake 1.2.x.x
*/

# load the required common functions
App::import('Vendor', 'Assets.common');

class AssetHelper extends AppHelper {

##
# Helpers
##
  var $helpers = array('Html');
  
##
# Custom Methods
##
/**
* Generate the detach URL based on the model data provided
* 
* @param mixed $data Model Data to operate on
* @access public
*/
  function detach($data = array()) {
    if (empty($data)) return null;
    
    # create an empty route array
    $output = array(
      'controller' => 'assets',
      'action' => 'delete',
      'id' => $data['id']
    );
    
    return $this->output($output);
  }

/**
* Displays the asset file extension
* 
* @param mixed $data Model Data to operate on
* @access public
*/
  function extension($data = array()) {
    return pathinfo($this->url($data), PATHINFO_EXTENSION);
  }

/**
* Display the filesize of this asset
* 
* @param mixed $data Model Data to operate on
* @param boolean $human Pass true to make the filesize human readable, pass false to return size in bytes
* @access public
*/
  function filesize($data = array(), $human = true) {
    if (empty($data)) return null;
    
    if ($human == true) return assetHumanizeSize($data['size']);
    
    if ($human == false) return $data['size'];
  }

/**
* Generate an image tag and insert the url
* 
* 
* @param array $data contains an array with the model, foreign_key, and filename
* @param string $version indicates the version of the file to return
* @param array $options to include with the HtmlHelper Image method
* @access public
*/
  function image($data = array(), $version = null, $options = array()) {
    $path = $this->url($data, $version);
    
    return $this->output($this->Html->image($path, $options));
  }
  
/**
* Returns the URI to the file asset
* 
* @param array $data contains an array with the model, foreign_key, and filename
* @param string $version indicates the version of the file to return
* @access public
*/
  function url($data = array(), $version = null) {
    if (empty($data)) return null;
    
    # ensure version is non-null before adding separator
    $version = !empty($version) && $version != 'original' ? $version .'.' : null;
    
    # format the mode name before putting it into the uri path
    $data['model'] = Inflector::pluralize(strtolower($data['model']));
    
    # finalize the output string
    $output = DS. 'files' .DS. $data['model'] .DS. $data['foreign_key'] .DS. $version . $data['filename'];
    
    # return the uri to the files/model/foreign_key/version.filename
    return $this->output($output);
  }
}
?>