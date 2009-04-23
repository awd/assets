<?php
/**
* Asset Model
* 
* [Short Description]
*
* 
* @package app.models
* @author Adam Doeler
* @version $Id$
* @copyright Adam Doeler
**/

# load the required common functions
App::import('Vendor', 'Assets.common');

class Asset extends AssetsAppModel {

##
# Model Variables
##
  var $name = 'Asset';
  var $cacheQueries = true;

##
# Magical Framework Methods
##
/**
* Delete the asset file on disk
* 
* @param boolean $cascade If true records that depend on this record will also be deleted
* @access private
*/
  function beforeDelete($cascade = true) {
    $this->read();
    
    if (!empty($this->data['Asset']) && is_array($this->data['Asset'])) {
      $assets = array();
      
      $entries = $this->find('all', array(
        'conditions' => array(
          'Asset.parent_id' => $this->data['Asset']['parent_id'],
          'Asset.model' => $this->data['Asset']['model'],
          'Asset.foreign_key' => $this->data['Asset']['foreign_key'],
          'Asset.field' => $this->data['Asset']['field']
        )
      ));
      
      if (!empty($entries) && is_array($entries)) {
        foreach ($entries as $entry) {
          $assets[] = $entry['Asset']['filename'];
        }
      }
      
      $model = new Object();
      $model->id = $this->data['Asset']['foreign_key'];
      $model->name = $this->data['Asset']['model'];
      
      $response = assetDelete($model, $assets);
      
      if (is_array($response) && $response['status'] != true) {
        assetError($this, $response['message'], $response['type']);
        return false;
      }
    }
    
    return true;
  }

##
# Private Methods
##
}
?>