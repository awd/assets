<?php
App::import('Vendor', 'Assets.common');

class CommonTestCase extends CakeTestCase {
/**
 * setUp
 *
 * @return void
 **/
	function startTest() {
	}
  
/**
 * test dimension checking
 * 
 * @return void
 */
  function testAssetCheckDimensions() {
    $result = assetCheckDimensions(null, null, null);
    $this->assertTrue($result);
    
    $result = assetCheckDimensions(null, null, 'greater');
    $this->assertTrue($result);
    
    $result = assetCheckDimensions(null, null, 'less');
    $this->assertTrue($result);
    
    $result = assetCheckDimensions(100, 500, null);
    $this->assertFalse($result);
    
    $result = assetCheckDimensions(100, 500, 'greater');
    $this->assertFalse($result);
    
    $result = assetCheckDimensions(100, 50, 'less');
    $this->assertFalse($result);
  }
  
/**
 * test asset removal
 * 
 * @return void
 */
  function testAssetDelete() {
  }
  
/**
 * test directory creation
 * 
 * @return void
 */
  function testAssetDirectoryCreate() {
  }
  
/**
 * test directory path
 * 
 * @return void
 */
  function testAssetDirectoryPath() {
    $model = new Object();
    $model->id = 1;
    $model->name = 'News';
    
    $result = assetDirectoryPath($model);
    $this->assertTrue(preg_match("/\/files\/news\/1\//", $result));
    
    $model->id = 2;
    $model->name = 'Person';
    
    $result = assetDirectoryPath($model);
    $this->assertTrue(preg_match("/\/files\/people\/2\//", $result));
    
    $model->id = 3;
    $model->name = 'Virus';
    
    $result = assetDirectoryPath($model);
    $this->assertTrue(preg_match("/\/files\/viri\/3\//", $result));
  }
  
/**
 * test error reporting
 * 
 * @return void
 */
  function testAssetError() {
    if (App::import('Component', 'Session')) {
      $session =& new SessionComponent();
      $session->delete('Alerts');
      
      assetError($session, 'Notice Message', 'notice');
      $result = $session->check('Alerts.notice');
      $this->assertTrue($result);
      $this->assertTrue(in_array('Notice Message', $session->read('Alerts.notice')));
      
      assetError($session, 'Error Message', 'error');
      $result = $session->check('Alerts.error');
      $this->assertTrue($result);
      $this->assertTrue(in_array('Error Message', $session->read('Alerts.error')));
      
      assetError($session, 'Success Message', 'success');
      $result = $session->check('Alerts.success');
      $this->assertTrue($result);
      $this->assertTrue(in_array('Success Message', $session->read('Alerts.success')));
      
      $session->delete('Alerts');
      unset($session);
    }
  }
  
/**
 * test file name creation
 * 
 * @return void
 */
  function testAssetFileName() {
    $result = assetFileName("/tmp/fake_file.jpg", "original", null);
    $this->assertTrue(preg_match("/fake_file\.(.*)\.jpg/", $result));
    
    $result = assetFileName("/tmp/fake_file.jpg", "original", null);
    $this->assertTrue(preg_match("/fake_file\.([a-zA-Z0-9]+)\.jpg/", $result));
    
    $result = assetFileName("/tmp/fake_file.jpg", "original", null);
    $this->assertFalse(preg_match("/fake_file\.(![a-zA-Z0-9]+).jpg/", $result));
    
    $result = assetFileName("/tmp/fake_file.666.jpg", "thumbnail", null);
    $this->assertTrue(preg_match("/thumbnail\.fake_file\.666\.jpg/", $result));
    
    $result = assetFileName("/tmp/fake_file.jpg", "original", "key");
    $this->assertTrue(preg_match("/key_fake_file\.(.*)\.jpg/", $result));
  }
  
/**
 * test humanize size
 * 
 * @return void
 */
  function testAssetHumanizeSize() {
    $result = assetHumanizeSize();
    $expected = '0 Bytes';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(0);
    $expected = '0 Bytes';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1);
    $expected = '1 Byte';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1023);
    $expected = '1023 Bytes';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1024);
    $expected = '1KB';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(2048);
    $expected = '2KB';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1048576);
    $expected = '1.00MB';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1073741824);
    $expected = '1.00GB';
    $this->assertEqual($result, $expected);
    
    $result = assetHumanizeSize(1099511627776);
    $expected = '1.00TB';
    $this->assertEqual($result, $expected);
  }
  
/**
 * test mimes
 * 
 * @return void
 */
  function testAssetMimes() {
    $result = assetMimes();
    $this->assertTrue(is_array($result));
    
    $result = assetMimes('audio');
    $this->assertTrue(in_array('audio/mpeg', $result));
    
    $result = assetMimes('video');
    $this->assertTrue(in_array('video/x-flv', $result));
  }
  
/**
 * test saving file
 * 
 * @return void
 */
  function testAssetSaveFile() {
  }
  
/**
 * test stringify function
 * 
 * @return void
 */
  function testAssetStringify() {
    $result = assetStringify(array(
      'first', 'second', 'third'
    ));
    $expected = "First, Second, Third";
    $this->assertEqual($result, $expected);
    
    $result = assetStringify(array(
      'first', 'second', 'third'
    ));
    $expected = "first, second, third";
    $this->assertNotEqual($result, $expected);
    
    $result = assetStringify("random string here");
    $expected = "random string here";
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
		#unset($this->Asset, $this->Controller);
		#ClassRegistry::removeObject('view');
		#ClassRegistry::flush();
	}  
}
?>