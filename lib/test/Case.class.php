<?php
/**
 * Copyright (c) 2011 J. Walter Thompson dba JWT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/** Extends PHPUnit for use with Symfony 1.4.
 *
 * Note:  This class is designed to work with Symfony 1.4 and might not work
 *  properly with other versions of Symfony.
 *
 * @author Phoenix Zerin <phoenix@todofixthis.com>
 *
 * @package sfJwtPhpUnitPlugin
 * @subpackage lib.test
 */
abstract class Test_Case extends PHPUnit_Framework_TestCase
{
  const
    ERR_HEADER =
      '*** Test environment needs to be updated before running tests! ***',

    DEFAULT_APPLICATION = 'frontend',
    DEFAULT_ENVIRONMENT = 'test',

    REQUIRED_PHPUNIT_VERSION = '3.6.6';

  static private
    $_dbNameCheck,
    $_uploadsDirCheck,
    $_defaultApplication;

  /** @var sfCommandApplication */
  static private $_controller;

  protected
    /** The name of the application configuration to load for this test case.
     *
     * Generally only applies to functional tests.
     */
    $_application,

    /** The name of the plugin configuration to load for this test case.
     *
     * Generally only applies to unit tests.
     */
    $_plugin,

    /** Set to true to rebuild the database before the next test.
     *
     * Set to true in _setUp() to *always* rebuild the database before each test
     *  in the test case.
     */
    $_alwaysRebuildDB = false;

  /** @var sfApplicationConfiguration */
  private $_configuration;
  /** @var Test_FixtureLoader */
  private $_fixtureLoader;
  /** @var Test_State */
  private $_state;

  /** Accessor for the default application name.
   *
   * @return string
   */
  static public function getDefaultApplicationName(  )
  {
    return
      empty(self::$_defaultApplication)
        ? self::DEFAULT_APPLICATION
        : self::$_defaultApplication;
  }

  /** Sets the default application name.
   *
   * @param string $application
   *
   * @throws InvalidArgumentException If $application is empty.
   * @return string old value.
   */
  static public function setDefaultApplicationName( $application )
  {
    if( empty($application) )
    {
      throw new InvalidArgumentException(
        'Empty argument passed to setDefaultApplicationName().'
      );
    }

    $old = self::$_defaultApplication;
    self::$_defaultApplication = (string) $application;
    return $old;
  }

  /** Sets the task controller object for this test (for {@see runTask()}).
   *
   * @param $controller sfCommandApplication
   *
   * @return void
   */
  static public function setController( sfCommandApplication $controller )
  {
    self::$_controller = $controller;
  }

  /** Init the class instance.
   *
   * @param string  $name
   * @param array   $data
   * @param string  $dataName
   */
  public function __construct(
          $name     = null,
    array $data     = array(),
          $dataName = ''
  )
  {
    $this->_checkPHPUnitVersion();

    parent::__construct($name, $data, $dataName);
  }

  /** (Global) Init test environment.
   *
   * Note that test case subclasses should use _setUp().
   *
   * @return void
   */
  final public function setUp(  )
  {
    $this->_initContext();

    $this->_assertTestDatabaseConnection();
    $this->_assertTestUploadsDir();

    $configuration        = $this->getApplicationConfiguration();

    $this->_fixtureLoader = new Test_FixtureLoader($configuration);
    $this->_state         = new Test_State($configuration);

    /* Set custom sfConfig values here. */
    sfConfig::add(array(
      'sf_fixture_dir'    =>
        $this->_fixtureLoader->getFixtureDir(false, $this->_plugin)
    ));

    $this->_state
      ->flushDatabase($this->_alwaysRebuildDB)
      ->flushUploads()
      ->flushConfigs();

    $this->_init();
    $this->_setUp();
  }

  /** (Global) Clean up test environment.
   *
   * Note that test case subclasses should use _tearDown().
   *
   * @return void
   */
  final public function tearDown(  )
  {
    $this->_tearDown();

    /* If the test did too good of a job of cleaning up after itself, create a
     *  tiny mess so that PHPUnit_Framework_TestCase feels productive.
     */
    if( ob_get_level() < 1 )
    {
      ob_start();
    }
  }

  /** Accessor for a variable set in a fixture.
   *
   * @param string $key
   *
   * @return mixed
   */
  protected function getFixtureVar( $key )
  {
    return $this->_fixtureLoader->$key;
  }

  /** Loads a text fixture into the database.
   *
   * @param string      $fixture  Fixture file name (e.g., test_data.yml).
   * @param bool        $force    If true, the fixture will be loaded even if it
   *  has already been loaded during this test.
   * @param string|bool $plugin   Determines where to load the fixture file:
   *  - (bool) false: Look in `sf_test_dir/fixtures`.
   *  - (bool) true:  Look in `sf_plugin_dir/$this->_plugin/test/fixtures`.
   *  - (string):     Look in `sf_plugin_dir/$plugin/test/fixtures`.
   *
   * If $plugin is true, but $this->_plugin is not set, the result is the same
   *  as if $plugin is false.
   *
   * @return mixed
   */
  protected function loadFixture( $fixture, $force = false, $plugin = true )
  {
    /* Shortcut:  Allow calling loadFixture($fixture, $plugin). */
    if( is_string($force) )
    {
      $plugin = $force;
      $force  = false;
    }
    elseif( $plugin === true )
    {
      $plugin = $this->_plugin;
    }

    return $this->_fixtureLoader->loadFixture($fixture, false, $plugin, $force);
  }

  /** Loads a production fixture into the database.
   *
   * Production fixtures are identical to YAML test fixtures, except they are
   *  located in `sf_data_dir`.
   *
   * @param string  $fixture  The name of the fixture file (e.g., users.yml).
   * @param bool    $force    If true, the fixture will be loaded even if it has
   *  already been loaded during this test.
   * @param string|bool $plugin   Determines where to load the fixture file:
   *  - (bool) false: Look in `sf_data_dir/fixtures`.
   *  - (bool) true:  Look in `sf_plugin_dir/$this->_plugin/data/fixtures`.
   *  - (string):     Look in `sf_plugin_dir/$plugin/data/fixtures`.
   *
   * If $plugin is true, but $this->_plugin is not set, the result is the same
   *  as if $plugin is false.
   *
   * @return mixed
   */
  protected function loadProductionFixture(
    $fixture,
    $force    = false,
    $plugin   = true
  )
  {
    /* Shortcut:  Allow calling loadProductionFixture($fixture, $plugin). */
    if( is_string($force) )
    {
      $plugin = $force;
      $force  = false;
    }
    elseif( $plugin === true )
    {
      $plugin = $this->_plugin;
    }

    return $this->_fixtureLoader->loadFixture($fixture, true, $plugin, $force);
  }

  /** Runs a Symfony task.
   *
   * @param $name string
   * @param $args string[]
   * @param $opts string[]
   *
   * @throws RuntimeException         If the task runner is not available (this
   *  represents an internal error with JPUP and therefore should never, ever
   *  happen :P).
   * @throws InvalidArgumentException If no such task exists.
   * @return int Returns the status code from the task (usually 0).
   *
   * Note that this method intentionally does *not* consume exceptions generated
   *  by the task!
   */
  protected function runTask(
          $name,
    array $args = array(),
    array $opts = array()
  )
  {
    if( ! self::$_controller )
    {
      throw new RuntimeException(
        'Task runner does not exist at runtime!  This is an internal error with sfJwtPhpUnitPlugin; please file a bug report.'
      );
    }

    if( $task = self::$_controller->getTaskToExecute($name) )
    {
      if( $task instanceof sfCommandApplicationTask )
      {
        /** @var $task sfCommandApplicationTask */
        $task->setCommandApplication(self::$_controller);
      }

      return $task->run($args, $opts);
    }
    else
    {
      throw new InvalidArgumentException(sprintf('No such task "%s".', $name));
    }
  }

  /** Checks to make sure we have the correct version of PHPUnit installed.
   *
   * @return void
   */
  private function _checkPHPUnitVersion(  )
  {
    $current = PHPUnit_Runner_Version::id();
    if( ! version_compare($current, self::REQUIRED_PHPUNIT_VERSION, '>=') )
    {
      self::_halt(sprintf(
        'JPUP is not compatible with PHPUnit %s; please upgrade to %s or later.',
          $current,
          self::REQUIRED_PHPUNIT_VERSION
      ));
    }
  }

  /** Initialize the application context.
   *
   * @return void
   */
  private function _initContext(  )
  {
    if( empty($this->_application) )
    {
      $this->_application = self::getDefaultApplicationName();
    }

    if( ! sfContext::hasInstance() )
    {
      sfContext::createInstance($this->getApplicationConfiguration());
    }

    sfContext::switchTo($this->_application);
  }

  /** Initialize the application configuration.
   *
   * @return sfApplicationConfiguration
   */
  protected function getApplicationConfiguration(  )
  {
    if( ! isset($this->_configuration) )
    {
      $this->_configuration = ProjectConfiguration::getApplicationConfiguration(
        $this->_application,
        'test',
        true
      );
    }

    return $this->_configuration;
  }

  /** Check to make sure we are using the "test" environment.
   *
   * Throws an error if the check fails to avoid executing test code in a
   *  production environment.
   *
   * @return void
   */
  private function _assertTestEnvironment(  )
  {
    if( sfConfig::get('sf_environment') != 'test' )
    {
      self::_halt(sprintf(
        'JPUP is trying to run in the %s environment instead of test!',
          sfConfig::get('sf_environment')
      ));
    }

    /* In same places, Symfony checks for sf_test rather than sf_environment.
     *  Since we've just finished verifying that we're in the test environment,
     *  we can also assume that test mode is on.
     *
     * Even if we have to set it ourselves.
     */
    sfConfig::set('sf_test', true);
  }

  /** Verifies that we are not connected to the production database.
   *
   * @param bool $force
   *
   * @return void Triggers an error if our database connection is unsafe for
   *  testing.
   */
  private function _assertTestDatabaseConnection( $force = false )
  {
    if(
          ((! self::$_dbNameCheck) or $force)
      and $db = $this->getDatabaseConnection()
    )
    {
      $this->_assertTestEnvironment();

      $config = sfConfigHandler::replaceConstants(sfYaml::load(
        sfConfig::get('sf_root_dir') . '/config/databases.yml'
      ));

      /* Check to see if a test database has been specified. */
      if( empty($config['test']['doctrine']['param']['dsn']) )
      {
        self::_halt('Please specify a "test" database in databases.yml.');
      }

      $test = $config['test']['doctrine']['param']['dsn'];

      $prod =
        isset($config['prod']['doctrine']['param']['dsn'])
          ? $config['prod']['doctrine']['param']['dsn']
          : $config['all']['doctrine']['param']['dsn'];

      /* Check to see if a *separate* test database has been specified. */
      if( $prod == $test )
      {
        self::_halt('Please specify a *separate* "test" database in databases.yml.');
      }

      /* Check to see that the active connection is using the correct DSN. */
      if( $db->getOption('dsn') != $test )
      {
        self::_halt('Doctrine connection is not using test DSN!');
      }

      self::$_dbNameCheck = true;
    }
  }

  /** Gets the Doctrine connection, initializing it if necessary.
   *
   * @return Doctrine_Connection
   */
  protected function getDatabaseConnection(  )
  {
    if( sfConfig::get('sf_use_database') )
    {
      try
      {
        return Doctrine_Manager::connection();
      }
      catch( Doctrine_Connection_Exception $e )
      {
        new sfDatabaseManager(sfContext::getInstance()->getConfiguration());
        return Doctrine_Manager::connection();
      }
    }

    return null;
  }

  /** Validates the uploads directory to ensure we're not going to inadvertently
   *   put test uploads in the wrong place and/or delete production files.
   *
   * @param bool $force
   *
   * @return void
   */
  protected function _assertTestUploadsDir( $force = false )
  {
    if( ! self::$_uploadsDirCheck or $force )
    {
      $this->_assertTestEnvironment();

      $config = sfConfigHandler::replaceConstants(sfYaml::load(
        $this->_getSettingsFilename()
      ));

      /* Determine whether a the test uploads directory is different than the
       *  production one.
       */
      if( isset($config['prod']['.settings']['upload_dir']) )
      {
        $prod = $config['prod']['.settings']['upload_dir'];
      }
      elseif( isset($config['all']['.settings']['upload_dir']) )
      {
        $prod = $config['all']['.settings']['upload_dir'];
      }
      else
      {
        /* Get the default value:  no good way to do this in Symfony 1.4. */
        $prod = sfConfig::get('sf_web_dir') . DIRECTORY_SEPARATOR . 'uploads';
      }

      $test = sfConfig::get('sf_test_dir');

      if( $prod == $test )
      {
        self::_halt(sprintf(
          'Please specify a *separate* test value for sf_upload_dir in %s.',
            $this->_getSettingsFilename()
        ));
      }

      /* Check the directory itself to make sure it's valid. */
      if( ! file_exists($test) )
      {
        /* If it doesn't exist, let's see if we can't create it. */
        if( ! mkdir($test, 0777, true) )
        {
          self::_halt(
            'Test upload directory (%s) does not exist.  Please create this directory before continuing.',
            $test
          );
        }
      }
      elseif( ! is_dir($test) )
      {
        self::_halt('Test upload directory (%s) not a directory.', $test);
      }

      if( ! is_writable($test) )
      {
        self::_halt('Test upload directory (%s) is not writable.', $test);
      }

      self::$_uploadsDirCheck = true;
    }
  }

  /** Returns the name of the settings file for the application context.
   *
   * @return string
   */
  protected function _getSettingsFilename(  )
  {
    return (sfConfig::get('sf_app_dir') . '/config/settings.yml');
  }

  /** Halts test script execution.
   *
   * @param string    $message Can use sprintf() syntax.
   * @param mixed,...
   *
   * @return void
   */
  static protected function _halt(
    /** @noinspection PhpUnusedParameterInspection */
    $message /*, $value,... */
  )
  {
    echo
      self::ERR_HEADER, PHP_EOL,
      call_user_func_array('sprintf', func_get_args()), PHP_EOL,
      PHP_EOL;

    /* Explicitly halt execution. */
    exit;
  }

  /** Init test environment.
   *
   * @return void
   */
  protected function _setUp(  )
  {
  }

  /** Clean up test environment.
   *
   * @return void
   */
  protected function _tearDown(  )
  {
  }

  /** Used by subclasses to do any pre-test initialization.
   *
   * @return void
   */
  abstract protected function _init(  );
}