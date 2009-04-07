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
* @modified March 17th, 2009
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
    
    # save configuration settings as an associated array, keyed off of the model name
    if (!empty($config) && is_array($config)) {
      $this->settings[$model->name] = $config;
    } else {
      $this->errors[$model->name] = true;
      $this->_setError($model, 'Asset Behavior requires Configuration Settings within the '. $model->name .' Model.');
      return;
    }
    
    # loop through configuration settings and setup proper model bindings
    foreach ($config as $name => $setting) {

      # determine if this is a parent model or asset model behavior
      if (empty($setting['asset'])) {
        
        if (empty($setting['useModel'])) {
          $this->settings[$model->name][$name]['useModel'] = $this->useModel;
        }

        if (!$this->_bindModel($model, $name)) {
          $this->_setError($model, 'The expected '. $name .' Model does not exist.', 'error');
          continue;
        }
        
        # dynamically attach asset behavior - marked as asset
        $model->{$name}->Behaviors->attach('Asset', array($name => array_merge($setting, array('asset' => true, 'parent' => $model->name))));
        if (!empty($model->data[$name])) $model->{$name}->set($model->data[$name]);
        
        # assign the parent/child relationsip
        $this->parents[$model->name] = true;
      } else {
        
        # setup the remaining configuration settings
        $this->_configSettings($model, $name);
      }
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
    if (!empty($this->parents[$model->name])) return;
    
    # validate the intial setup
    if ($this->errors[$model->name] === true) {
      $this->_setError($model, 'Critical Error found while starting beforeValidate: '. $model->name, 'error');
      return false;
    }
    
    
    # validate against the field itself
    $validations['uploadPath'] = array(
      'allowEmpty' => false,
      'last' => true,
      'rule' => array('validateUploadPath'),
      'message' => 'Asset Behavior cannot write to the "files" directory. Please contact an administrator.',
      'required' => true
    );
    
    
    # shortcut to configuration settings
    $setting = $this->_settings($model);
    
    # shortcut to validatesOn variable
    $validatesOn = !empty($setting['on']) ? $setting['on'] : null;
    
    # shortcut to determine if we are creating or updating or neither
    $modelExists = !empty($model->data[$model->alias]['foreign_key']) && is_numeric($model->data[$model->alias]['foreign_key']) ? true : false;

    # prevent validations from being added when they are not required (always|create|update)
    if (empty($validatesOn) || ($validatesOn == 'create' && !$modelExists) || ($validatesOn == 'update' && $modelExists)) {

      # validate against the field itself
      $validations['uploadField'] = array(
        'allowEmpty' => $setting['allowEmpty'],
        'last' => true,
        'rule' => array('validateUploadField'),
        'message' => 'Asset Behavior expects file field named: "'. $model->alias .'.uploader"',
        'on' => $validatesOn,
        'required' => $setting['required']
      );
      
      # ensure the upload is valid and data exists
      $validations['uploadData'] = array(
        'last' => true,
        'rule' => array('validateUploadData', $setting['allowEmpty']),
        'message' => !empty($setting['message']) ? $setting['message'] : 'Please provide a file attachment.'
      );
      
    } else {
      
      # always check if the required field is present - when required by config
      if ((!empty($setting['required']) && $setting['required'] == true) || ($this->required == true)) {
        
        # validate against the field itself
        $validations['uploadField'] = array(
          'allowEmpty' => $setting['allowEmpty'],
          'last' => true,
          'rule' => array('validateUploadField'),
          'message' => 'Asset Behavior expects file field named: "'. $model->alias .'.uploader"',
          'required' => $setting['required']
        );
      }
    } #endif
    
    # the following validations will only occur when data exists (handled within the validation)
    
    # validate the file size
    $validations['uploadSize'] = array(
      'last' => true,
      'rule' => array('validateUploadSize', $setting['maxSize']),
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
      'rule' => array('validateFileExtension', $setting['allowedExts']),
      'message' => $message
    );
    
    # validate the file mime-type
    $validations['fileMime'] = array(
      'last' => true,
      'rule' => array('validateFileMime', $setting['allowedMimes']),
      'message' => 'Please ensure the mime-type is valid for ['. assetStringify($setting['allowedMimes']) .'] files.'
    );
    
    # validate the file dimensions (if image or swf)
    if (!empty($setting['dimensions'])) {
      $validations['fileDimensions'] = array(
        'last' => true,
        'rule' => array('validateFileDimensions', $setting['dimensions']),
        'message' => 'Please ensure the file dimensions match the requirements designated above.'
      );
    }
    
    # add new custom validations
    $model->validate = array('uploader' => $validations);
  }
  
/**
* Before Save runs only when validation passes.
* This method helps prevent empty rows from being saved into the system
* 
* @param object $model Model using this behavior
* @access public
*/  
  function beforeSave(&$model) {
    if (!empty($this->parents[$model->name])) return;
    
    # validate the intial setup
    if ($this->errors[$model->name] === true) {
      $this->_setError($model, 'Critical Error found while starting beforeSave: '. $model->name, 'error');
      return false;
    }
    
    # do not process empty rows
    if (empty($model->data[$model->alias]['uploader']['tmp_name'])) {
      unset($model->data[$model->alias]);
      return;
    }
  }

/**
* After Delete cleans up rogue child records
* 
* @param object $model Model using this behavior
* @access public
*/  
  function afterDelete(&$model) {
    if (!empty($this->parents[$model->name])) {
      
      # send the foreign key from the parent to the child
      $this->setParentAndForeignKey($model);
      return;
    }
    
    # delete all other records for this child instance
    $model->deleteAll(array('parent_id' => $model->id, 'model' => $this->parent, 'foreign_key' => $this->foreignKey));    
  }
  
/**
* After Save occurs after the original entry has been created
* 
* @param object $model Model using this behavior
* @param boolean $created True if the record was created successfully
* @access public
*/
  function afterSave(&$model, $created) {
    if (!empty($this->parents[$model->name])) {
      
      # send the foreign key from the parent to the child
      $this->setParentAndForeignKey($model);
      return;
    }
    
    # prevent empty or invalid data from being processed
    if (!empty($model->data[$model->alias]['model'])) return;
    
    # setup the directory path for this asset
    $this->_directory($model);
    
    # save the original entry
    if (!$this->_saveOriginal($model)) return false;
    
    # for images only - determine if resizing is required
    if (in_array($model->data[$model->alias]['uploader']['type'], assetMimes('image'))) {
      
      # prevent the resizing of the original file
      $setting = $this->_settings($model);
      unset($setting['resize']['original']);
      
      # handle the resizing operations
      $this->_resize($model);
    }
  }

/**
* Before Delete removes files and directories associated with the child instances data
* 
* note:
* if this is false, admins can compare the existing assets for the model, with existing parent model entries
* once you see assets without a valid parent, you can locate the file easily on the file-system and remove manually
* 
* @param object $model Model using this behavior
* @param boolean $cascade True if dependent model data is also to be deleted
* @access private
*/
  function beforeDelete(&$model, $cascade = true) {
    if (!empty($this->parents[$model->name])) {
      
      # send the foreign key from the parent to the child
      $this->setParentAndForeignKey($model);
      return;
    }
    
    $response = assetDelete($model, $this->parent, $this->foreignKey);
    
    if (is_array($response) && $response['status'] != true) {
      $this->_setError($model, $response['message'], $response['type']);
      return false;
    }
    
    return true;
  }

/**
* Update the childs foreignKey from the parents Behavior instance
* 
* @param object $model Model using this behavior
* @param string $parent Name of parent model instance
* @param int $foreignKey Foreign Key provided by the parent instance
* @access private
*/
  function setParentAndForeignKey(&$model, $parent = null, $foreignKey = null) {
    if (empty($foreignKey)) {
      # working on the parents instance

      foreach ($this->settings[$model->name] as $name => $setting) {
        if (!in_array('Asset', $model->{$name}->Behaviors->enabled())) continue;
        $model->{$name}->Behaviors->dispatchMethod($model->{$name}, 'setParentAndForeignKey', array('parent' => $model->alias, 'foreignKey' => $model->id));
      }
    } else {
      # working on the childs instance
      $this->foreignKey = $foreignKey;
      $this->parent = $parent;
    }
  }

##
# Validation Methods
#
# Possible Validation Method variables:
# @param array $fieldData Data will be sent as a string if the form enctype is not properly set
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
    $folder =& new Folder();
    
    # define the directory path
    $path = WWW_ROOT . 'files' .DS;
    
    # determine if directory is writeable
    if (!is_writable($path)) return false;
    
    # nothing has failed
    return true;
  }
  
/**
* Validate the proper configuration and formatting of the upload field itself
* 
* @access private
*/  
  function validateUploadField(&$model, $fieldData) {
    if (empty($fieldData['uploader']) || !is_array($fieldData['uploader'])) return false;
    
    # nothing has failed
    return true;
  }

/**
* Validate the data exists and without error depending on if expected or provided
* If the data does not exist, further validation and processing should not occur - unset the data
* 
* @param bool $allowEmpty determines if we can validate without adding an asset
* @access private
*/
  function validateUploadData(&$model, $fieldData, $allowEmpty = true) {
    
    # validate if expected or if provided
    if ($allowEmpty == false || !empty($fieldData['uploader']['tmp_name'])) {
      
      # validate against provided values
      if (!empty($fieldData['uploader']['error']) && $fieldData['uploader']['error'] != 0) return false;
      if (empty($fieldData['uploader']['size']) || $fieldData['uploader']['size'] == 0) return false;
      
      # validate the temporary uploaded file using PHPs built-in method
      if (!is_uploaded_file($fieldData['uploader']['tmp_name'])) return false;
    }
    
    # nothing has failed
    return true;
  }
  
/**
* Validate the provided file-size against the configuration settings
* 
* @param int $maxSize Maximum size permitted for file-uploads
* @access private
*/
  function validateUploadSize(&$model, $fieldData, $maxSize = 0) {
    
    # only proceed if upload data does exist
    if (empty($fieldData['uploader']['tmp_name'])) return true;
    
    # validate file size
    if (!empty($maxSize) && $fieldData['uploader']['size'] > $maxSize) return false;
    
    # nothing has failed
    return true;
  }
  
/**
* Validate the file extension against the configuration settings
* 
* @param array $allowedExts An array of allowed extensions or wildcard (*)
* @access private
*/
  function validateFileExtension(&$model, $fieldData, $allowedExts = array()) {
    
    # only proceed if upload data does exist
    if (empty($fieldData['uploader']['tmp_name'])) return true;
    
    # get the file extension
    $extension = strtolower(pathinfo($fieldData['uploader']['name'], PATHINFO_EXTENSION));
    
    # determine if the extension is valid
    if (!is_array($allowedExts) || (!in_array('*', $allowedExts) && !in_array($extension, $allowedExts))) return false;
    
    # nothing has failed
    return true;
  }
  
/**
* Validate the file mime-type against the configuration settings
* 
* @param array $allowedMimes An array of allowed mime-types or wildcard (*)
* @access private
*/
  function validateFileMime(&$model, $fieldData, $allowedMimes = array()) {
    
    # only proceed if upload data does exist
    if (empty($fieldData['uploader']['tmp_name'])) return true;
    
    $availableMimes = assetMimes();
    
    foreach (!empty($allowedMimes) && is_array($allowedMimes) ? $allowedMimes : array() as $type) {
      if (($type == '*') || (in_array($fieldData['uploader']['type'], $availableMimes[$type]))) return true;
    }
    
    # by default this validation fails
    return false;
  }
  
/**
* Validates the file dimensions (if image or swf) against the configuration settings
* 
* @param array $dimensions Contains an un-indexed array with the first value being the dimension to work against
* @access private
*/
  function validateFileDimensions(&$model, $fieldData, $dimensions) {
    
    # only proceed if upload data does exist
    if (empty($fieldData['uploader']['tmp_name'])) return true;
    
    if (!empty($dimensions)) {
      
      # create new array for dimension info
      $info = array();
      
      # determine if we can operate on this file
      if (list($info['w'], $info['h'], $info['t']) = getimagesize($fieldData['uploader']['tmp_name'])) {
        
        # file is valid - parse dimensions
        $tmpGeometry = $dimensions;
        $tmpGeometry = str_replace('<', '', $tmpGeometry);
        $tmpGeometry = str_replace('>', '', $tmpGeometry);
        
        # split the values at X
        list($geometry['w'], $geometry['h']) = explode('x', $tmpGeometry);
        
        # unset wildcard values
        foreach ($geometry as $k => $v) {
          $geometry[$k] = str_replace('*', '', $geometry[$k]);
          if ($geometry[$k] == '') unset($geometry[$k]);
        }
        
        # determine if upload needs to be greater or less than dimension settings
        $geometry['gtlt'] = 'equal';
        
        if (strpos($dimensions, '>') > 0) {
          $geometry['gtlt'] = 'greater';
        } elseif (strpos($dimensions, '<') > 0) {
          $geometry['gtlt'] = 'less';
        }
        
        # test the file against the required dimensions, test width/height, width only, or height only
        if (!empty($geometry['w']) && !assetCheckDimensions($info['w'], $geometry['w'], $geometry['gtlt'])) return false;
        if (!empty($geometry['h']) && !assetCheckDimensions($info['h'], $geometry['h'], $geometry['gtlt'])) return false;
      } else {
        
        # not a valid file to determine dimensions
        return false;
      }
    }
    
    # nothing failed
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
    # shortcut to configuration settings
    $config = $this->settings[$model->name][$name];
    
    # determine overwrite setting
    $config['overwrite'] = isset($config['overwrite']) && is_bool($config['overwrite']) ? $config['overwrite'] : $this->overwrite;
    
    # determine the association order
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
    # shortcut to configuration settings
    $config = &$this->settings[$model->name][$name];

    # determine which allowed extensions to use
    if (empty($config['allowedExts'])) {
      $config['allowedExts'] = array('*');
    }

    # determine which allowed mime types to use
    if (empty($config['allowedMimes'])) {
      $config['allowedMimes'] = array('*');
    }

    # determine which maxSize to use
    if (empty($config['maxSize'])) {
      $config['maxSize'] = $this->maxSize;
    }

    # determine which overwrite to use
    if (isset($config['overwrite']) && !is_bool($config['overwrite'])) {
      $config['overwrite'] = $this->overwrite;
    }

    # determine which allowEmpty to use
    if (isset($config['allowEmpty']) && !is_bool($config['allowEmpty'])) {
      $config['allowEmpty'] = $this->allowEmpty;
    }

    # determine which required to use
    if (isset($config['required']) && !is_bool($config['required'])) {
      $config['required'] = $this->required;
    }
  }

/**
* Create the full directory path for the asset based on the model and id
* filepath looks like: /files/{model}/{id}/
* 
* @param object $model Model using this behavior
* @access private
*/
  function _directory(&$model) {
    $folder =& new Folder();
    
    # define the directory path
    $path = WWW_ROOT . 'files' .DS;
    $path .= strtolower(Inflector::pluralize($this->parent)) .DS;
    $path .= $this->foreignKey .DS;
    
    # create the directory path
    $folder->create($path, 0770);
    
    # save the path reference
    $this->directory = $path;
  }
  
/**
* Returns the new file name based on the version, field, and extension
* 
* @param string $source Filename to parse and format
* @param string $version Determines if we are naming an original or a copy
* @param string $key append the key to the beginning of the original file only
* @access private
*/
  function _fileName($source, $version = null, $key = null) {
    
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
      $version = !empty($version) ? $version : null;
      $filename = $version .'.'. $filename;
    }
    
    # return formatted filename w/ extension
    return strtolower($filename .'.'. $extension);
  }

/**
* Remove a file from the file system
* 
* @param object $model Model using this behavior
* @param string $filename Name of the file to remove
* @access private
*/
  function _removeFile(&$model, $filename) {
    if (file_exists($this->directory.$filename)) unlink($this->directory.$filename); 
  }

/**
* This method handles all potential resize operations
* 
* @param object $model Model using this behavior
* @access private
*/
  function _resize(&$model) {
    
    # shortcut to configuration settings
    $setting = $this->_settings($model);
    
    foreach (!empty($setting['resize']) && is_array($setting['resize']) ? $setting['resize'] : array() as $k => $v) {
      
      # create resized data to save into the database
      $versioned = array(
        'parent_id' => $model->id,
        'model' => $this->parent,
        'foreign_key' => $this->foreignKey,
        'field' => $model->alias,
        'version' => strtolower($k),
        'filename' => null,
        'mime' => $model->data[$model->alias]['uploader']['type'],
        'size' => null,
      );
      
      # set the filename field value
      $versioned['filename'] = $this->_fileName($this->filename, $k);

      # move the versioned file    
      if (!$this->_saveFile($this->directory.$this->filename, $versioned['filename'], false)) {
        $this->_setError($model, 'Critical Error found while saving file in Resize: '. $model->alias, 'error');
        return false;
      }
      
      # initialize and perform the resize task
      $resize = new Resize($this->directory.$versioned['filename'], $v);
      
      if (!$resize) {
        $this->_setError($model, 'Could not perform resizing tasks on: type: '. $k .' file: '. $this->filename .'.', 'error');
        return false;
      } else {
        
        # save remaining values
        $versioned['size'] = filesize($this->directory.$versioned['filename']);
        list($versioned['width'], $versioned['height']) = getimagesize($this->directory.$versioned['filename']);
        
        # save the new entry
        $versionModel = new $model->name;
        $versionModel->create();
        
        # save the versioned file data
        if (!$versionModel->save($versioned, false)) {
          $this->_setError($model, 'Critical Error found while saving record in Resize: '. $model->alias, 'error');
          return false;
        }
      }
    }
  }

/**
* Saves the newly renamed file into the previously created directory
* 
* @param string $source Full file path of the source file to be moved or copied
* @param string $filename New name of the created file
* @param mixed $upload Tmp files are moved (uploaded), while duplicate files are copied (for resizing purposes)
* @access private
*/
  function _saveFile($source, $filename, $upload = true) {
    
    # create the desintation
    $destination = $this->directory . $filename;
    
    # determine if file is moved or copied
    switch ($upload) {
      case false:
        if (!copy($source, $destination)) return false;
      break;
      case 'move':
        if (!rename($source, $destination)) return false;
      break;
      default:
        if (!is_uploaded_file($source)) return $this->_saveFile($source, $filename, 'move');
      
        if (!move_uploaded_file($source, $destination)) return false;
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
* Saves the original data entry, and clears out any data needing to be overwritten (files and records)
* 
* @param object $model Model using this behavior
* @access private
*/
  function _saveOriginal(&$model) {
    
    # shortcut to configuration settings
    $setting = $this->_settings($model);
    
    # create original data to save into the database
    $original = array(
      'parent_id' => $model->id,
      'model' => $this->parent,
      'field' => $model->alias,
      'version' => 'original',
      'filename' => null,
      'mime' => $model->data[$model->alias]['uploader']['type'],
      'size' => $model->data[$model->alias]['uploader']['size'],
      'uploader' => $model->data[$model->alias]['uploader']
    );
    
    # include dimensions if a valid file-type
    list($original['width'], $original['height']) = getimagesize($model->data[$model->alias]['uploader']['tmp_name']);
    
    # if this is not an update and files are set to overwrite
    if (!isset($setting['overwrite']) || $setting['overwrite'] == true) {
      
      # count the existing rows for renaming the file
      $existing = $model->find('all', array('fields' => array('id', 'filename'), 'conditions' => array('model' => $this->parent, 'foreign_key' => $this->foreignKey, 'field' => $model->alias)));
      
      # loop through the existing files and remove each entry
      foreach (!empty($existing) && is_array($existing) ? $existing : array() as $remove) {
        $this->_removeFile($model, $remove[$model->alias]['filename']);
      }
      
      # delete all existing records for this field
      $model->deleteAll(array('model' => $this->parent, 'foreign_key' => $this->foreignKey, 'field' => $model->alias));
    }
    
    # set the filename field value
    $original['filename'] = $this->_fileName($model->data[$model->alias]['uploader']['name'], 'original', $model->alias);
    
    # save the original file data
    if (!$model->save($original, false)) {
      $this->_setError($model, 'Critical Error found while saving record in afterSave: '. $model->name, 'error');
      return false;
    }
    
    # move the original file    
    if (!$this->_saveFile($original['uploader']['tmp_name'], $original['filename'])) {
      $this->_setError($model, 'Critical Error found while saving file in afterSave: '. $model->name, 'error');
      return false;
    }
    
    # save the original filename
    $this->filename = $original['filename'];
    
    # reset the model data
    $model->set($original);
    
    # everything worked
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
    return $this->settings[$model->name][$model->alias];
  }
}
?>