<?php
/**
* AssetsController
* 
* [Short Description]
* 
*
* @package app.controllers
* @author Adam Doeler
* @version $Id$
* @copyright Adam Doeler
**/
class AssetsController extends AssetsAppController {

##
# Controller Variables
##
  var $name = 'Assets';
  
##
# Frontend Methods
##

##
# Backend Methods
##
/**
* Custom Backend Delete Operation
* 
* @access public
*/
  function backend_delete($id) {
    
    # retrieve this records results
    $entry = $this->Asset->read(null, $id);
    
    if (!empty($entry['Asset']) && is_array($entry['Asset'])) {
      
      # set the delete conditions
      $conditions = array(
        'Asset.parent_id' => $entry['Asset']['parent_id'],
        'Asset.model' => $entry['Asset']['model'],
        'Asset.foreign_key' => $entry['Asset']['foreign_key'],
        'Asset.field' => $entry['Asset']['field']
      );
      
      # remove the entries
      if ($this->{$this->modelClass}->deleteAll($conditions, true, true)) {
        # no errors were encountered
        $this->Alert->write('You have successfully removed the Asset.', 'success');
      } else {
        # errors were encountered
        $this->Alert->write('There were problems when attempting to remove this Asset.', 'error');
      }
    }
    
    $this->redirect($this->referer());
  }

/**
* Backend Related - generates a new fieldset (ajax only)
* 
* @access public
*/
  function backend_related() {

    # ensure we have a valid request
    if ($this->RequestHandler->isAjax()) {
      $this->layout = 'ajax';
      $this->set('k', mktime());
      $this->set('id', mktime());
    } else {
      # not a valid request
      $this->Alert->write('You have made an invalid request.', 'notice');
      $this->redirect('index');
    }
  }
  
##
# Private Methods
##

##
# Magical Framework Methods
##
/**
* Fine-tune authorized user access to this controller
*
* @access private
*/  
  function isAuthorized($type = null, $object = null, $user = null) {
    if (!parent::isAuthorized($type, $object, $user)) return false;
    return true;
  }
}
?>