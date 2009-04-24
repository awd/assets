<?php 
/**
* Asset Behavior for Models in Cake 1.2
* 
* the files are stored as follows
* WWW_ROOT/files/{model}/{foreign_key}/{field}.ext
* 
* resized versions of the same file will be stored as
* WWW_ROOT/files/{model}/{foreign_key}/{version}.{field}.ext
* 
* @version 1.1
* @created November 28th, 2007
* @modified April 22nd, 2009
* @author Adam Doeler
* @email adam@adamdoeler.com
* @website http://adamdoeler.com
* @cake 1.2.x.x
*/

# load the required resizing & common functions
App::import('Vendor', 'Assets.resize');
App::import('Vendor', 'Assets.common');
 
class AssetBehavior extends ModelBehavior {
/**
* Contains configuration settings for use with individual model objects.  This
* is used because if multiple models use this Behavior, each will use the same
* object instance.  Individual model settings should be stored as an
* associative array, keyed off of the model name.
* 
* @var array
* @access public
*/
  var $settings = array();

/**
* Allow Empty determines what data is expected before being added into the system.
* FALSE = data passed to the model's save() method MUST include the field and a non-empty data value for that field.
* This rule is only enforced when the field index is present in the supplied data array.
* 
* @var bool
* @access public
*/
  var $allowEmpty = false;

/**
* Directory path where asset will be stored on the server
* 
* @var string
* @access public
*/
  var $directory = null;

/**
* Errors determines if any critical errors have occured during any part of the process
* Some built-in automagic methods will halt if an error has been established for the parent model
* 
* @var bool
* @access public
*/
  var $errors = false;

/**
* Once the original file has been renamed and uploaded, this value is set to the newly created filename
* 
* @var string
* @access public
*/
  var $filename = null;

/**
* This value is updated in the afterSave method called for the parent model.
* 
* @var int
* @access public
*/
  var $foreignKey = null;

/**
* Maximum Size file size for uploads
* Default is 2mb (in bytes)
* 
* @var int
* @access public
*/
  var $maxSize = 2097152;

/**
* On determines when a specific set of actions should occur.
* Valid values are 'create', 'update' or true
* 
* @var bool
* @access public
*/
  var $on = null;

/**
* Overwrite setting determines the model binding association.
* TRUE = the model should overwrite existing data, having only one asset (hasOne).
* FALSE = the model should NOT overwrite existing data, having many assets (hasMany).
* 
* @var bool
* @access public
*/
  var $overwrite = true;

/**
* Name of the parent model instance
* 
* @var string
* @access public
*/
  var $parent = null;

/**
* Required determines what data is expected before being added into the system.
* This setting only verifies that the fields key is present
* 
* @var bool
* @access public
*/
  var $required = false;

/**
* Session is an object shortcut to the SessionComponent
* 
* @var object
* @access public
*/
  var $Session = null;

/**
* Allows maximum customization in terms of where the application stores specific assets.
* As an example; Some applications may require Images and Documents be saved into their own Model.
* 
* Please ensure all Models have been properly created and setup with the Asset Behavior
* 
* @var string
* @access public
*/
  var $useModel = 'Assets.Asset';

/**
* Validation Errors tie into the Asset Behavior instances validationError array
* 
* @var array
* @access public
*/
  var $validationErrors = array();
  
##
# Public Methods
##  
/**
* Setup this behavior with the specified configuration settings.
* 
* @param object $model Model using this behavior
* @param array $config Configuration settings for $model
* @access public
*/
  function setup(&$model, $config = array()) {
    if (!empty($config) && is_array($config)) {
      $this->settings[$model->name] = $config;
    } else {
      $this->errors[$model->name] = true;
      $this->_setError($model, 'AssetBehavior requires Configuration Settings within the '. $model->name .' Model.');
      return;
    }
    
    # loop through the AssetBehavior children and setup proper model bindings
    foreach ($config as $name => $setting) {

      if (empty($setting['useModel'])) $this->settings[$model->name][$name]['useModel'] = $this->useModel;
      
      if (!$this->_bindModel($model, $name)) {
        $this->_setError($model, 'The expected '. $name .' Model does not exist.', 'error');
        continue;
      }
      
      $this->_configSettings($model, $name);
    }
  }  
  
##
# Magical Framework Methods
##
/**
* Before Validate runs before the model is validated.
* Here all the model validations are set for Asset Behavior instance
* 
* @param object $model Model using this behavior
* @access public
*/
  function beforeValidate(&$model) {
    if ($this->errors[$model->name] === true) {
      $this->_setError($model, 'Critical Error found while starting beforeValidate: '. $model->name, 'error');
      return false;
    }
    
    foreach ($this->_settings($model) as $name => $setting) {
      $fieldName = Inflector::underscore($name);
      
      # determine when to validate
      $validatesOn = !empty($setting['on']) ? $setting['on'] : null;
      
      # shortcut to determine if we are creating, updating or neither
      $exists = !empty($model->data[$model->name][$model->primaryKey]) && is_numeric($model->data[$model->name][$model->primaryKey]) ? true : false;
      
      # prevent validations from being added when they are not required (always|create|update)
      if (empty($validatesOn) || ($validatesOn == 'create' && !$exists) || ($validatesOn == 'update' && $exists)) {
        
        # validate against the field itself
        $validations['uploadField'] = array(
          'allowEmpty' => $setting['allowEmpty'],
          'last' => true,
          'rule' => array('validateUploadField', $fieldName),
          'message' => 'AssetBehavior expects field named: "'. $model->name .'.'. $fieldName .'", please ensure this form can accept file-uploads.',
          'on' => $validatesOn,
          'required' => $setting['required']
        );
        
        # ensure the upload is valid and data exists
        $validations['uploadData'] = array(
          'rule' => array('validateUploadData', $fieldName, $setting['allowEmpty']),
          'message' => !empty($setting['message']) ? $setting['message'] : 'Please provide a file attachment.'
        );
        
      } else {
        
        # always check if the required field is present - when required by config
        if ((!empty($setting['required']) && $setting['required'] == true) || ($this->required == true)) {
          
          # validate against the field itself
          $validations['uploadField'] = array(
            'allowEmpty' => $setting['allowEmpty'],
            'last' => true,
            'rule' => array('validateUploadField', $fieldName),
            'message' => 'AssetBehavior expects field named: "'. $model->name .'.'. $fieldName .'", please ensure this form can accept file-uploads.',
            'required' => $setting['required']
          );
        }
        
      } #endif
      
      # the following validations will only occur when data exists (handled within the validation)
      
      # validate against the upload storage path
      $validations['uploadPath'] = array(
        'allowEmpty' => false,
        'last' => true,
        'rule' => array('validateUploadPath'),
        'message' => 'AssetBehavior cannot write to the "files" directory. Please contact an administrator.',
        'required' => true
      );
      
      # validate the file size
      $validations['uploadSize'] = array(
        'last' => true,
        'rule' => array('validateUploadSize', $fieldName, $setting['maxSize']),
        'message' => 'The file size must not exceed: '. assetHumanizeSize($setting['maxSize'])
      );
      
      # validate the file extension
      if (!empty($setting['allowMimes']) && in_array('*', $setting['allowMimes'])) {
        $message = 'Please ensure the file is one of the following: '. strtolower(assetStringify($setting['allowedExts'])) .'.';
      } else {
        $message = 'Please ensure the file extension is valid for ['. assetStringify($setting['allowedMimes']) .'] files.';
      }
      
      $validations['fileExtension'] = array(
        'last' => true,
        'rule' => array('validateFileExtension', $fieldName, $setting['allowedExts']),
        'message' => $message
      );
      
      # validate the file mime-type
      $validations['fileMime'] = array(
        'last' => true,
        'rule' => array('validateFileMime', $fieldName, $setting['allowedMimes']),
        'message' => 'Please ensure the mime-type is valid for ['. assetStringify($setting['allowedMimes']) .'] files.'
      );

      # validate the file dimensions (if image or swf)
      if (!empty($setting['dimensions'])) {
        $validations['fileDimensions'] = array(
          'last' => true,
          'rule' => array('validateFileDimensions', $fieldName, $setting['dimensions']),
          'message' => 'Please ensure the file dimensions match the requirements designated above.'
        );
      }
      
      # merge new custom validations with existing validations
      $model->validate[$fieldName] = $validations;
    }
  }
  
/**
* Before Save runs only when validation passes.
* This method helps prevent empty rows from being saved into the system
* 
* @param object $model Model using this behavior
* @access public
*/  
  function beforeSave(&$model) {
    if ($this->errors[$model->name] === true) {
      $this->_setError($model, 'Critical Error found while starting beforeSave: '. $model->name, 'error');
      return false;
    }
    
    foreach ($this->_settings($model) as $name => $setting) {
      $fieldName = Inflector::underscore($name);
      
      if (!empty($model->data[$model->name][$fieldName]['tmp_name'])) {
        $model->data[$name] = $model->data[$model->name][$fieldName];
      }
      
      unset($model->data[$model->name][$fieldName]);
    }
  }

/**
* After Delete cleans up rogue child records
* 
* @param object $model Model using this behavior
* @access public
*/  
  function afterDelete(&$model) {
    foreach ($this->_settings($model) as $name => $setting) {
      $model->{$name}->deleteAll(array('foreign_key' => $model->id, 'model' => $model->name));
    }
  }
  
/**
* After Save occurs after the original entry has been created
* 
* @param object $model Model using this behavior
* @param boolean $created True if a new record was created successfully (false on updates)
* @access public
*/
  function afterSave(&$model, $created) {
    if (!$created && !$model->exists()) return;
    
    foreach ($this->_settings($model) as $name => $setting) {
      if (empty($model->data[$name]['tmp_name'])) continue;
      
      # save the original entry
      if (!$this->_saveOriginal($model, $name)) return;
      
      # for images only - determine if resizing is required
      if (in_array($model->data[$name]['type'], assetMimes('image'))) $this->_resize($model, $name);
    }
  }

/**
* BeforeDelete removes child files and directories associated with parent model
* 
* note:
* if this is false, admins can compare the existing child assets for the model, with existing parent model entries
* once you see assets without a valid parent, you can locate the file easily on the file-system and remove manually
* 
* @param object $model Model using this behavior
* @param boolean $cascade True if dependent model data is also to be deleted
* @access private
*/
  function beforeDelete(&$model, $cascade = true) {
    $response = assetDelete($model);
    
    if (is_array($response) && $response['status'] != true) {
      $this->_setError($model, $response['message'], $response['type']);
      return false;
    }
    
    return true;
  }

##
# Validation Methods
#
# Possible Validation Method variables:
# @param array $fieldData Data will be sent as a string if the form enctype is not properly set
# @param string $fieldName Name of the field with upload($_FILES) information
# @param object $model Model using this behavior
# @param string $name Name of the association / behavior to work on
# @param array $config Configuration settings for $model behavior
##
  
/**
* Determines if the files directory is writable
* 
* @access private
*/
  function validateUploadPath(&$model, $fieldData) {
    $path = WWW_ROOT . 'files' .DS;
    
    if (!is_writable($path)) return false;
    
    return true;
  }
  
/**
* Validate the proper configuration and formatting of the upload field itself
* 
* @access private
*/  
  function validateUploadField(&$model, $fieldData, $fieldName) {
    if (empty($fieldData[$fieldName]) || !is_array($fieldData[$fieldName])) return false;
    return true;
  }

/**
* Validate the data exists and without error depending on if expected or provided
* If the data does not exist, further validation and processing should not occur - unset the data
* 
* @param bool $allowEmpty determines if we can validate without adding an asset
* @access private
*/
  function validateUploadData(&$model, $fieldData, $fieldName, $allowEmpty = true) {
    if ($allowEmpty == false || !empty($fieldData[$fieldName]['tmp_name'])) {
      
      # validate against provided values
      if (!empty($fieldData[$fieldName]['error']) && $fieldData[$fieldName]['error'] != 0) return false;
      if (empty($fieldData[$fieldName]['size']) || $fieldData[$fieldName]['size'] == 0) return false;
      
      # validate the temporary uploaded file using PHPs built-in method
      if (!is_uploaded_file($fieldData[$fieldName]['tmp_name'])) return false;
    }
    
    return true;
  }
  
/**
* Validate the provided file-size against the configuration settings
* 
* @param int $maxSize Maximum size permitted for file-uploads
* @access private
*/
  function validateUploadSize(&$model, $fieldData, $fieldName, $maxSize = 0) {
    if (empty($fieldData[$fieldName]['tmp_name'])) return true;
    
    if (!empty($maxSize) && $fieldData[$fieldName]['size'] > $maxSize) return false;
    
    return true;
  }
  
/**
* Validate the file extension against the configuration settings
* 
* @param array $allowedExts An array of allowed extensions or wildcard (*)
* @access private
*/
  function validateFileExtension(&$model, $fieldData, $fieldName, $allowedExts = array()) {
    if (empty($fieldData[$fieldName]['tmp_name'])) return true;
    
    $extension = strtolower(pathinfo($fieldData[$fieldName]['name'], PATHINFO_EXTENSION));
    
    if (!is_array($allowedExts) || (!in_array('*', $allowedExts) && !in_array($extension, $allowedExts))) return false;
    
    return true;
  }
  
/**
* Validate the file mime-type against the configuration settings
* 
* @param array $allowedMimes An array of allowed mime-types or wildcard (*)
* @access private
*/
  function validateFileMime(&$model, $fieldData, $fieldName, $allowedMimes = array()) {
    if (empty($fieldData[$fieldName]['tmp_name'])) return true;
    
    $availableMimes = assetMimes();
    
    foreach (!empty($allowedMimes) && is_array($allowedMimes) ? $allowedMimes : array() as $type) {
      if (($type == '*') || (in_array($fieldData[$fieldName]['type'], $availableMimes[$type]))) return true;
    }
    
    return false;
  }
  
/**
* Validates the file dimensions (if image or swf) against the configuration settings
* 
* @param array $dimensions Contains an un-indexed array with the first value being the dimension to work against
* @access private
*/
  function validateFileDimensions(&$model, $fieldData, $fieldName, $dimensions) {
    if (empty($fieldData[$fieldName]['tmp_name'])) return true;
    
    if (!empty($dimensions)) {
      $info = array();
      
      if (list($info['w'], $info['h'], $info['t']) = getimagesize($fieldData[$fieldName]['tmp_name'])) {
        $tmpGeometry = $dimensions;
        $tmpGeometry = str_replace('<', '', $tmpGeometry);
        $tmpGeometry = str_replace('>', '', $tmpGeometry);
        
        list($geometry['w'], $geometry['h']) = explode('x', $tmpGeometry);
        
        foreach ($geometry as $k => $v) {
          $geometry[$k] = str_replace('*', '', $geometry[$k]);
          if ($geometry[$k] == '') unset($geometry[$k]);
        }
        
        $geometry['gtlt'] = 'equal';
        
        if (strpos($dimensions, '>') > 0) {
          $geometry['gtlt'] = 'greater';
        } elseif (strpos($dimensions, '<') > 0) {
          $geometry['gtlt'] = 'less';
        }
        
        if (!empty($geometry['w']) && !assetCheckDimensions($info['w'], $geometry['w'], $geometry['gtlt'])) return false;
        if (!empty($geometry['h']) && !assetCheckDimensions($info['h'], $geometry['h'], $geometry['gtlt'])) return false;
      } else {
        return false;
      }
    }
    
    return true;
  }

##
# Private Methods
##
/**
* Creates Model bindings as a hasOne or hasMany association
* 
* @param object $model Model using this behavior
* @param string $name name of the association (defined in parent model)
* @access private
*/
  function _bindModel(&$model, $name) {
    $config = $this->settings[$model->name][$name];
    
    # determine which settings to use
    $config['overwrite'] = isset($config['overwrite']) && is_bool($config['overwrite']) ? $config['overwrite'] : $this->overwrite;
    $config['order'] = !empty($config['order']) ? $config['order'] : array($name .'.created' => 'ASC');
    
    # determine the association type
    switch ($config['overwrite']) {
      case false:
        $association = 'hasMany';
        if (!isset($config['limit'])) $config['limit'] = 5;
      break;
      default:
        $association = 'hasOne';
        $config['limit'] = 1;
      break;
    }
    
    # determine if the provided model is valid
    if (!App::import('Model', $config['useModel'])) {
      $this->error[$name] = true;
      $this->_setError($model, 'The provided '. $config['useModel'] .' Model does not exist, using '. $this->useModel .' Model by default.');
      
      # use default model - and determine if valid
      if (strtolower($config['useModel']) != 'asset' && !App::import('Model', $this->useModel)) {
        $this->_setError($model, 'The '. $this->useModel .' Model does not exist, please provide valid settings.', 'error');
        return false;
      }
      
      $config['useModel'] = $this->useModel;
    }
    
    # create the model binding
    $model->bindModel(array(
      $association => array(
        $name => array(
          'className' => $config['useModel'],
          'dependent' => true,
          'foreignKey' => 'foreign_key',
          'conditions' => array($name.'.model' => $model->name, $name.'.field' => $name, $name.'.version' => 'original'),
          'order' => $config['order'],
          'limit' => $config['limit']
        )
      )
    ), false);
    
    return true;
  }

/**
* Setup the final configuration settings
* 
* @param object $model Model using this behavior
* @param string $name name of the association (defined in parent model)
* @access private
*/
  function _configSettings(&$model, $name) {
    $config = &$this->settings[$model->name][$name];

    # determine which allowed extensions to use
    if (empty($config['allowedExts'])) $config['allowedExts'] = array('*');

    # determine which allowed mime types to use
    if (empty($config['allowedMimes'])) $config['allowedMimes'] = array('*');

    # determine which maxSize to use
    if (empty($config['maxSize'])) $config['maxSize'] = $this->maxSize;

    # determine which overwrite to use
    if (isset($config['overwrite']) && !is_bool($config['overwrite'])) $config['overwrite'] = $this->overwrite;

    # determine which allowEmpty to use
    if (isset($config['allowEmpty']) && !is_bool($config['allowEmpty'])) $config['allowEmpty'] = $this->allowEmpty;

    # determine which required to use
    if (isset($config['required']) && !is_bool($config['required'])) $config['required'] = $this->required;
  }

/**
* This method handles all potential resize operations
* 
* @param object $model Model using this behavior
* @param string $name Name of the AssetBehvior child instance
* @access private
*/
  function _resize(&$model, $name) {
    $setting = $this->settings[$model->name][$name];
    
    if (!empty($setting['resize']['original'])) unset($setting['resize']['original']);
    
    # loop through the resize options
    foreach (!empty($setting['resize']) && is_array($setting['resize']) ? $setting['resize'] : array() as $k => $v) {
      
      # create resized data to save into the database
      $versioned = array(
        'parent_id' => $model->{$name}->id,
        'model' => $model->name,
        'foreign_key' => $model->id,
        'field' => $name,
        'version' => strtolower($k),
        'filename' => assetFileName($model->data[$name]['filename'], $k),
        'mime' => $model->data[$name]['type'],
        'size' => null,
      );
      
      # move the versioned file
      if (!assetSaveFile($model, $this->directory.$model->data[$name]['filename'], $versioned['filename'], false)) {
        $this->_setError($model, 'Critical Error found while saving file in Resize: '. $name, 'error');
        return false;
      }
      
      # initialize and perform the resize task
      $resize = new Resize($this->directory.$versioned['filename'], $v);
      
      if (!$resize) {
        $this->_setError($model, 'Could not perform resizing tasks on: type: '. $k .' file: '. $model->data[$name]['filename'] .'.', 'error');
        return false;
      } else {
        
        # save remaining values
        $versioned['size'] = filesize($this->directory.$versioned['filename']);
        list($versioned['width'], $versioned['height']) = getimagesize($this->directory.$versioned['filename']);
        
        # save the new entry
        $versionModel = new $model->{$name};
        $versionModel->create();
        
        # save the versioned file data
        if (!$versionModel->save($versioned, false)) {
          $this->_setError($model, 'Critical Error found while saving record in Resize: '. $name, 'error');
          return false;
        }
      }
    }
  }

/**
* Saves the original data entry, and clears out any data needing to be overwritten (files and records)
* 
* @param object $model Model using this behavior
* @param string $name Name of the AssetBehvior child instance
* @access private
*/
  function _saveOriginal(&$model, $name) {
    $setting = $this->settings[$model->name][$name];
    
    # create original data to save into the database
    $original = array(
      'foreign_key' => $model->id,
      'model' => $model->name,
      'field' => $name,
      'version' => 'original',
      'filename' => null,
      'mime' => $model->data[$name]['type'],
      'size' => $model->data[$name]['size']
    );
    
    # include dimensions if a valid file-type (image, swf, etc)
    list($original['width'], $original['height']) = getimagesize($model->data[$name]['tmp_name']);
    
    # if this is not an update and files are set to overwrite
    if (!isset($setting['overwrite']) || $setting['overwrite'] == true) {
      
      # count the existing rows for renaming the file
      $existing = $model->{$name}->find('all', array(
        'fields' => array('id', 'filename'),
        'conditions' => array('model' => $model->name, 'foreign_key' => $model->id, 'field' => $name)
      ));
      
      # loop through the existing files and remove each entry
      foreach (!empty($existing) && is_array($existing) ? $existing : array() as $remove) {
        assetDelete($model, array($remove[$name]['filename']));
      }
      
      # delete all existing records for this field
      $model->{$name}->deleteAll(array('model' => $model->name, 'foreign_key' => $model->id, 'field' => $name));
    }
    
    # set the filename field value
    $original['filename'] = assetFileName($model->data[$name]['name'], 'original', $name);
    
    # save the original file data
    if (!$model->{$name}->save($original, false)) {
      $this->_setError($model, 'Critical Error found while saving record in afterSave: '. $name, 'error');
      return false;
    }
    
    # update parent id
    $model->{$name}->saveField('parent_id', $model->{$name}->id);
    
    # create the directory path for this asset
    if (!assetDirectoryCreate($model)) {
      $this->_setError($model, 'Storage directory was not created, file not properly uploaded. Please try again and/or contact an Administrator.');
      return false;
    }
    
    $this->directory = assetDirectoryPath($model);
    
    # move the original file
    if (!assetSaveFile($model, $model->data[$name]['tmp_name'], $original['filename'])) {
      $this->_setError($model, 'Critical Error found while saving file in afterSave: '. $name, 'error');
      return false;
    }
    
    # save the original filename
    unset($model->data[$name]);
    $model->data[$name]['filename'] = $original['filename'];
    $model->data[$name]['type'] = $original['mime'];
    
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
  function _setError(&$model, $message, $type = 'notice') {
    assetError($model, $message, $type);
  }

/**
* Convenience method which returns the current setttings
* 
* @param object $model Model using this behavior
* @access private
*/
  function _settings(&$model) {
    return $this->settings[$model->name];
  }
}
?>