<?php  
/**
* Resize Object is used with the Asset Behavior
*
* @version 0.2
* @created November 30th, 2007
* @modified February 6th, 2009
* @author Adam Doeler
* @email adam@adamdoeler.com
* @website http://adamdoeler.com
* @cake 1.2.x.x
*/
Class Resize extends Object {
  
/**
* filename and filepath are set in the constructor
*/
  var $filename;
  var $filepath;
  
/**
* an array of the source and destination dimensions
*/
  var $geometry = array();
  
/**
* an array when resizing tasks need a position within the source   
*/
  var $offset = array();
  
/**
* an array of the source and destination resources
*/
  var $resource = array();
  
/**
* Constructor class for setting the file and path
* Also performs the resizing action based on the provided $task and $dimensions
* 
* @param string $file requires the full path to the $file to manipulate
* @param array $options contains the $task and the $dimensions options to perform
* @access public
*/
  function __construct($file, $options = array()) {
    
    # test the source file exists before setting and processing
    if (file_exists($file)) {
      $this->filename = pathinfo($file, PATHINFO_BASENAME); 
      $this->filepath = $file;
    } else {
      return false;
    }
    
    # ensure our options are coming in as an array
    if (!is_array($options)) {
      return false;
    }
    
    # determine if the task is valid and execute it
    if (method_exists(__CLASS__, $options[0])) {
      if (!call_user_func(array(__CLASS__, $options[0]), 
        $options[1], 
        !empty($options[2]) ? $options[2] : null, 
        !empty($options[3]) ? $options[3] : null,
        !empty($options[4]) ? $options[4] : null))
      {
        return false;
      }
    }
  }


##
# Re-sizing Methods
##
   
/**
* Crop the image to a specific size
* 
* @param string $dimensions dimensions required for this task
* @access public
*/
  function crop($dimensions) {
    
    # parse the dimensions provided from the file source, and the model ActsAs settings
    $this->_parseDimensions($dimensions);
    
    # create the source and destination resources
    $this->_createImageResource();

    # finalize the cropping dimensions

    # determine if we need to re-adjust the width
    if ($this->geometry['src']['w'] < $this->geometry['new']['w']) {
      
      # set the aspect ratio
      $aspect = $this->geometry['src']['w'] / $this->geometry['src']['h'];
      
      # create temp image
      $create = true;
      
      # re-adjust the new dimensions
      $this->geometry['new']['w'] = $this->geometry['dst']['w'];
      $this->geometry['new']['h'] = round(abs($this->geometry['new']['w'] / $aspect));
    }

    # determine if we need to re-adjust the height
    if ($this->geometry['src']['h'] < $this->geometry['new']['h']) {
      
      # set the aspect ratio
      $aspect = $this->geometry['src']['h'] / $this->geometry['src']['w'];
      
      # create temp image
      $create = true;
      
      # re-adjust the new dimensions
      $this->geometry['new']['h'] = $this->geometry['dst']['h'];
      $this->geometry['new']['w'] = round(abs($this->geometry['new']['h'] / $aspect));
    }
    
    # create a temp image to up-scale the original
    if (!empty($create) && $create === true) {
      $this->resource['tmp'] = imagecreatetruecolor($this->geometry['new']['w'], $this->geometry['new']['h']);
      
      # scale the original image to the proper dimensions for cropping purposes
      imagecopyresampled($this->resource['tmp'], $this->resource['src'], 0, 0, 0, 0, $this->geometry['new']['w'], $this->geometry['new']['h'], $this->geometry['src']['w'], $this->geometry['src']['h']);
    }
    
    # determine if we are creating the final file based on the tmp or src
    if (!empty($this->resource['tmp']) && is_resource($this->resource['tmp'])) {
      
      # crop the image from the dead-center
      $this->offset['src']['x'] = round(($this->geometry['new']['w'] / 2) - ($this->geometry['dst']['w'] / 2));
      $this->offset['src']['y'] = round(($this->geometry['new']['h'] / 2) - ($this->geometry['dst']['h'] / 2));
      
      # generate the final resource based off the tmp image resource (above)
      imagecopyresampled($this->resource['new'], $this->resource['tmp'], 0, 0, $this->offset['src']['x'], $this->offset['src']['y'], $this->geometry['new']['w'], $this->geometry['new']['h'], $this->geometry['src']['w'], $this->geometry['src']['h']);
    } else {
      # crop the image from the dead-center
      $this->offset['src']['x'] = round(($this->geometry['src']['w'] / 2) - ($this->geometry['dst']['w'] / 2));
      $this->offset['src']['y'] = round(($this->geometry['src']['h'] / 2) - ($this->geometry['dst']['h'] / 2));
      
      # generate the final resource
      imagecopyresampled($this->resource['new'], $this->resource['src'], 0, 0, $this->offset['src']['x'], $this->offset['src']['y'], $this->geometry['src']['w'], $this->geometry['src']['h'], $this->geometry['src']['w'], $this->geometry['src']['h']);
    }
    
    # save the new image based on the changes above
    $this->_saveImageResource();
    
    # success
    return true;
  }
  
/**
* Scale the image proportionally to the dimensions required
* 
* @param string $dimensions dimensions required for this task
* @param boolean $aspect true if we are to maintain the aspect ratio --OR--
*        string $aspect fill if we are to fill the image dimensions (maintains aspect ratio, cropping will occur) --OR--
*        boolean $aspect false|null will fill the image dimensions and NOT maintain aspect ratio
* @param string $align instruct the task how to position the scaled image
* @param string $fillColor matte background colour when image becomes smaller than dimensions
* @access public
*/
  function scale($dimensions, $aspect = null, $align = null, $fillColor = null) {
    
    # parse the dimensions provided from the file source, and the model ActsAs settings
    $this->_parseDimensions($dimensions);
    
    # create the source and destination resources
    $this->_createImageResource($fillColor);

    # create empty offset array
    $this->_createEmptyOffset();

    # finalize the scaling dimensions
    
    # determine if we use an aspect ratio
    if (!empty($aspect) && ($aspect == true || $aspect === 'fill')) {
      
      # determine how we calculate the aspect ratio
      if ($aspect === 'fill') {
        
        # image will fill the dimensions based on the largest property (width|height)
        if ($this->geometry['src']['w'] > $this->geometry['src']['h']) {
          $aspect = $this->geometry['src']['w'] / $this->geometry['dst']['w'];
        } else {
          $aspect = $this->geometry['src']['h'] / $this->geometry['dst']['h'];
        }
      } else {
        
        # image will fit inside the dimensions based on the smallest property (width|height)
        if ($this->geometry['src']['w'] > $this->geometry['src']['h']) {
          $aspect = $this->geometry['src']['h'] / $this->geometry['dst']['h'];
        } else {
          $aspect = $this->geometry['src']['w'] / $this->geometry['dst']['w'];
        }
      }
      
      # re-adjust the new dimensions
      $this->geometry['new']['w'] = round($this->geometry['src']['w'] / $aspect);
      $this->geometry['new']['h'] = round($this->geometry['src']['h'] / $aspect);
    }
    
    # determine if we need to align the new image
    if (!empty($align) && $align != null) {
      
      # get the align values 0 => [top|middle|bottom], 1 => [left|center|right]
      $align = explode(' ', $align);
      
      # handle the y-axis
      switch ($align[0]) {
        case 'bottom':
          $this->offset['dst']['y'] = -($this->geometry['new']['h'] - $this->geometry['dst']['h']);
        break;
        case 'middle':
          $this->offset['dst']['y'] = -($this->geometry['new']['h'] / 2) + ($this->geometry['dst']['h'] / 2);
        break;
        case 'top':
          $this->offset['dst']['y'] = 0;
        break;
      }
      
      # handle the x-axis
      switch ($align[1]) {
        case 'center':
          $this->offset['dst']['x'] = -($this->geometry['new']['w'] / 2) + ($this->geometry['dst']['w'] / 2);
        break;
        case 'left':
          $this->offset['dst']['x'] = 0;
        break;
        case 'right':
          $this->offset['dst']['x'] = -($this->geometry['new']['w'] - $this->geometry['dst']['w']);
        break;
      }
    }
    
    # generate the final resource
    imagecopyresampled($this->resource['new'], $this->resource['src'], $this->offset['dst']['x'], $this->offset['dst']['y'], 0, 0, $this->geometry['new']['w'], $this->geometry['new']['h'], $this->geometry['src']['w'], $this->geometry['src']['h']);
    
    # save the new image based on the changes above
    $this->_saveImageResource();
    
    # success
    return true;
  }
  
  
##
# Private Methods
##
   
/**
* Create an empty offset array 
* 
* @access private
*/
  function _createEmptyOffset() {
    $this->offset['src']['x'] = $this->offset['src']['y'] = 0;
    $this->offset['dst']['x'] = $this->offset['dst']['y'] = 0;
  } 
   
/**
* Creates a new image resource using the GDLibrary
* The image must be saved after resizing processes have compeleted
* 
* @param string $fillColor matte background colour when image becomes smaller than dimensions
* @access private
*/
  function _createImageResource($fillColor = null) {
    
    # use GDLibrary to create a new image resource
    $this->resource['new'] = imagecreatetruecolor($this->geometry['dst']['w'], $this->geometry['dst']['h']);
    
    # determine if we use a custom background colour (default is black)
    if (!empty($fillColor)) {
      $fillColor = str_replace('#', '', $fillColor);
      $fillColor = imagecolorallocate(
        $this->resource['new'],
        hexdec(substr($fillColor, 0, 2)), 
        hexdec(substr($fillColor, 2, 2)), 
        hexdec(substr($fillColor, 4, 2))
      );
      
      imagefill($this->resource['new'], 0, 0, $fillColor);
    }
    
    # grab the image binary source
    $this->resource['src'] = @imagecreatefromstring(file_get_contents($this->filepath));
  }
   
/**
* Parse the passed dimensions
* 
* @param string $dimensions dimensions to parse
* @access private 
*/
  function _parseDimensions($dimensions) {
    
    # save the original dimension string
    $this->geometry['original'] = $dimensions;
    
    # while we are here, collect the dimensions of the actual file
    list($this->geometry['src']['w'], $this->geometry['src']['h'], $this->resource['type']) = getimagesize($this->filepath);
    
    # create the variable to use as we modify the string
    $geometry = $dimensions;
    
    # remove the <> symbols
    $geometry = str_replace('<', '', $geometry);
    $geometry = str_replace('>', '', $geometry);
    
    # split the values by x
    $geometry = explode('x', $geometry);
    
    # assign the new values for the width and height
    foreach ($geometry as $key => $value) {
      
      if ($value != '*') {
        switch ($key) {
          case 0:
            # width
            $this->geometry['dst']['w'] = $value;
          break;
          case 1:
            # height
            $this->geometry['dst']['h'] = $value;
          break;
        } 
      }
    } # foreach
    
    # generate the new dimensions
    $this->_parseNewDimensions();
  }
  
/**
* From the now existing model and file dimensions, create the potential new dimensions
* 
* @access private 
*/
  function _parseNewDimensions() {
    
    # default value
    $this->geometry['gtlt'] = null;
    
    # check if we are performing a greater/less than task
    if (strpos($this->geometry['original'], '>') > 0) {
      $this->geometry['gtlt'] = 'greater';
    } elseif (strpos($this->geometry['original'], '<') > 0) {
      $this->geometry['gtlt'] = 'less';
    }
    
    # test the existing geometry below, against both width/height, width only, height only
    # also determine the results based on the gtlt variable found above
    if (!empty($this->geometry['dst']['w']) && !empty($this->geometry['dst']['h'])) {
      
      # aspect is not required for this case
      
      # switch to the appropriate case
      switch ($this->geometry['gtlt']) {
        case 'greater':
          # only resize if the original is larger than the model
          if (($this->geometry['src']['w'] > $this->geometry['dst']['w']) || ($this->geometry['src']['h'] > $this->geometry['dst']['h'])) {
            $this->geometry['new']['w'] = $this->geometry['dst']['w'];
            $this->geometry['new']['h'] = $this->geometry['dst']['h'];
          }
        break;
        case 'less':
          # only resize if the original is smaller than the model
          if (($this->geometry['src']['w'] < $this->geometry['dst']['w']) || ($this->geometry['src']['h'] < $this->geometry['dst']['h'])) {
            $this->geometry['new']['w'] = $this->geometry['dst']['w'];
            $this->geometry['new']['h'] = $this->geometry['dst']['h'];
          }
        break;
        default:
          $this->geometry['new']['w'] = $this->geometry['dst']['w'];
          $this->geometry['new']['h'] = $this->geometry['dst']['h'];
        break;
      }
    } elseif (!empty($this->geometry['dst']['w']) && empty($this->geometry['dst']['h'])) {
      
      # set the aspect ratio
      $aspect = $this->geometry['src']['w'] / $this->geometry['src']['h'];
      
      # switch to the appropriate case
      switch ($this->geometry['gtlt']) {
        case 'greater':
          # only resize if the original is larger than the model
          if ($this->geometry['src']['w'] > $this->geometry['dst']['w']) {
            $this->geometry['new']['w'] = $this->geometry['dst']['w'];
            $this->geometry['new']['h'] = round(abs($this->geometry['new']['w'] / $aspect));
          }
        break;
        case 'less':
          # only resize if the original is smaller than the model
          if ($this->geometry['src']['w'] < $this->geometry['dst']['w']) {
            $this->geometry['new']['w'] = $this->geometry['dst']['w'];
            $this->geometry['new']['h'] = round(abs($this->geometry['new']['w'] / $aspect));
          }
        break;
        default:
          $this->geometry['new']['w'] = $this->geometry['dst']['w'];
          $this->geometry['new']['h'] = round(abs($this->geometry['new']['w'] / $aspect));
        break;
      }
    } elseif (empty($this->geometry['dst']['w']) && !empty($this->geometry['dst']['h'])) {
      
      # set the aspect ratio
      $aspect = $this->geometry['src']['h'] / $this->geometry['src']['w'];
      
      # switch to the appropriate case
      switch ($this->geometry['gtlt']) {
        case 'greater':
          # only resize if the original is larger than the model
          if ($this->geometry['src']['h'] > $this->geometry['dst']['h']) {
            $this->geometry['new']['h'] = $this->geometry['dst']['h'];
            $this->geometry['new']['w'] = round(abs($this->geometry['new']['h'] / $aspect));
          }
        break;
        case 'less':
          # only resize if the original is smaller than the model
          if ($this->geometry['src']['h'] < $this->geometry['dst']['h']) {
            $this->geometry['new']['h'] = $this->geometry['dst']['h'];
            $this->geometry['new']['w'] = round(abs($this->geometry['new']['h'] / $aspect));
          }
        break;
        default:
          $this->geometry['new']['h'] = $this->geometry['dst']['h'];
          $this->geometry['new']['w'] = round(abs($this->geometry['new']['h'] / $aspect));
        break;
      }
    }
    
    # defaults for the new resource
    $this->geometry['new']['w'] = !empty($this->geometry['new']['w']) ? $this->geometry['new']['w'] : $this->geometry['src']['w'];
    $this->geometry['new']['h'] = !empty($this->geometry['new']['h']) ? $this->geometry['new']['h'] : $this->geometry['src']['h'];    
    
    # defaults for the dst resource
    $this->geometry['dst']['w'] = !empty($this->geometry['dst']['w']) ? $this->geometry['dst']['w'] : $this->geometry['new']['w'];
    $this->geometry['dst']['h'] = !empty($this->geometry['dst']['h']) ? $this->geometry['dst']['h'] : $this->geometry['new']['h'];
  }
  
/**
* Save the image after all required processes have completed
* 
* @access private
*/
  function _saveImageResource() {
    
    # save the file based on the mime type of the source file
    switch (image_type_to_mime_type($this->resource['type'])) {
      case 'image/gif':
        imagegif($this->resource['new'], $this->filepath);
      break;
      case 'image/png':
        imagepng($this->resource['new'], $this->filepath);
      break;
      default:
        imagejpeg($this->resource['new'], $this->filepath, 100);
      break;
    }
    
    # new image has been generated - destroy the memory resource
    imagedestroy($this->resource['new']);
    
    # free up memory by removing the resource
    unset($this->resource);
  }
}
?>