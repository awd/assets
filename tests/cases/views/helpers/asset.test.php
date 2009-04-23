<?php
App::import('Helper', array('Assets.Asset'));
App::import('Helper', array('Html'));
App::import('Core', array('View', 'Controller'));

class AssetHelperTestCase extends CakeTestCase {
/**
 * setUp
 *
 * @return void
 **/
	function startTest() {
	  Router::connect('/', array('controller' => 'pages', 'action' => 'display', 'home'));
	  Router::parse('/');
	  
	  $this->Asset =& new AssetHelper();
	  $this->Asset->Html =& new HtmlHelper();
	  
	  $this->Controller =& ClassRegistry::init('Controller');
	  if (isset($this->_debug)) {
	    Configure::write('debug', $this->_debug);
	  }
	}
	
/**
 * test detach path
 *
 * @return void
 **/
  function testDetatch() {
    $data = array('id' => 1);
    
    $result = $this->Asset->detach($data);
    $expected = array('controller' => 'assets', 'action' => 'delete', 'id' => 1);
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->detach(array());
    $expected = null;
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->detach(array('id'));
    $expected = null;
    $this->assertEqual($result, $expected);
  }

/**
 * test file extension
 *
 * @return void
 **/
  function testExtension() {
    $data = array(
      'id' => 1,
      'model' => 'User',
      'foreign_key' => 1,
      'filename' => 'user.jpg'
    );
    
    $result = $this->Asset->extension($data);
    $expected = 'jpg';
    $this->assertEqual($result, $expected);
    
    $data = array(
      'id' => 1,
      'model' => 'User',
      'foreign_key' => 1,
      'filename' => 'user.something.jpg'
    );
    
    $result = $this->Asset->extension($data);
    $expected = 'jpg';
    $this->assertEqual($result, $expected);
    
    $data = array(
      'id' => 1,
      'model' => 'User',
      'foreign_key' => 1,
      'filename' => 'user.exe'
    );
    
    $result = $this->Asset->extension($data);
    $expected = 'exe';
    $this->assertEqual($result, $expected);
  }
  
/**
 * test file size
 *
 * @return void
 **/
  function testFilesize() {
    $result = $this->Asset->filesize(null);
    $this->assertEqual($result, null);
    
    $data = array(
      'id' => 1,
      'size' => '1',
    );
    
    $result = $this->Asset->filesize($data);
    $expected = '1 Byte';
    $this->assertEqual($result, $expected);
    
    $data = array(
      'id' => 1,
      'size' => '2',
    );
    
    $result = $this->Asset->filesize($data);
    $expected = '2 Bytes';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->filesize($data, false);
    $expected = '2';
    $this->assertEqual($result, $expected);
    
    $data = array(
      'id' => 1,
      'size' => '2048',
    );
    
    $result = $this->Asset->filesize($data, false);
    $expected = '2048';
    $this->assertEqual($result, $expected);
  }

/**
 * test image path
 *
 * @return void
 **/
  function testImage() {
    $data = array(
      'id' => 1,
      'model' => 'User',
      'foreign_key' => 1,
      'filename' => 'user.jpg'
    );

    $result = $this->Asset->image($data, null);
    $expected = '<img src="/files/users/1/user.jpg" alt="" />';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->image($data, null, array('alt' => 'ALT TITLE'));
    $expected = '<img src="/files/users/1/user.jpg" alt="ALT TITLE" />';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->image($data, null, array());
    $expected = '<img src="/files/users/1/user.jpg" alt="" />';
    $this->assertEqual($result, $expected);

    $result = $this->Asset->image($data, 'avatar');
    $expected = '<img src="/files/users/1/avatar.user.jpg" alt="" />';
    $this->assertEqual($result, $expected);

    $result = $this->Asset->image($data, 'avatar', array('alt' => 'ALT TITLE'));
    $expected = '<img src="/files/users/1/avatar.user.jpg" alt="ALT TITLE" />';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->image($data, 'avatar', array());
    $expected = '<img src="/files/users/1/avatar.user.jpg" alt="" />';
    $this->assertEqual($result, $expected);

    $result = $this->Asset->image(array(), null);
    $expected = null;
    $this->assertEqual($result, $expected);

    $result = $this->Asset->image(array('id'), null);
    $expected = null;
    $this->assertEqual($result, $expected);
  }

/**
 * test url path
 *
 * @return void
 **/
  function testUrl() {
    $data = array(
      'id' => 1,
      'model' => 'User',
      'foreign_key' => 1,
      'filename' => 'user.jpg'
    );
    
    $result = $this->Asset->url($data, null);
    $expected = '/files/users/1/user.jpg';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->url($data, 'avatar');
    $expected = '/files/users/1/avatar.user.jpg';
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->url(array(), null);
    $expected = null;
    $this->assertEqual($result, $expected);
    
    $result = $this->Asset->url(array('id'), null);
    $expected = null;
    $this->assertEqual($result, $expected);
  }
  
/**
 * reset the view paths
 *
 * @return void
 **/
	function endCase() {
	}
	
/**
 * tearDown
 *
 * @access public
 * @return void
 */
	function tearDown() {
		unset($this->Asset, $this->Controller);
		ClassRegistry::removeObject('view');
		ClassRegistry::flush();
	}
}
?>