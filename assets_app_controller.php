<?php
/**
* AssetsAppController
* 
* [Short Description]
* 
*
* @package app.controllers
* @author Adam Doeler
* @version $Id$
* @copyright Adam Doeler
**/
class AssetsAppController extends AppController {
  
  function delete($id) {
    $this->Asset->delete($id);
    $this->redirect($this->referer());
  }
  
}
?>