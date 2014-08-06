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

/** Maintains global state for test cases.
 *
 * @author Phoenix Zerin <phoenix@todofixthis.com>
 *
 * @package sfJwtPhpUnitPlugin
 * @subpackage lib.test
 */
class Test_State
{
  static private
    $_configs;

  /** @var sfProjectConfiguration */
  protected $_configuration;
  /** @var Test_Database_Driver */
  protected $_db;

  /** Inits the class instance.
   *
   * @param $configuration sfProjectConfiguration
   */
  public function __construct( sfProjectConfiguration $configuration )
  {
    $this->_configuration = $configuration;
    $this->initDb();
  }

  protected function initDb() {
    $this->_db            =
      Test_Database_Driver::factory($this->getDatabaseConnection());
  }

  /** Flush the database and reload base fixtures.
   *
   * @param bool $rebuild
   *  true:   The database will be dropped and rebuilt.
   *  false:  The method will try just to flush the data.
   *
   * Note that the first time flushDatabase() is called (per execution), the
   *  database will be rebuilt regardless of $rebuild.
   *
   * @return static
   */
  public function flushDatabase( $rebuild = false )
  {
    $this->_db->flushDatabase($rebuild);
    return $this;
  }

  /** Removes anything in the uploads directory.
   *
   * @return static
   */
  public function flushUploads(  )
  {
    $Filesystem = new sfFilesystem();
    $Filesystem->remove(
      sfFinder::type('any')->in(sfConfig::get('sf_upload_dir'))
    );

    return $this;
  }

  /** Restores all sfConfig values to their state before the current test was
   *   run.
   *
   * @return static
   */
  public function flushConfigs(  )
  {
    if( isset(self::$_configs) )
    {
      sfConfig::clear();
      sfConfig::add(self::$_configs);
    }
    else
    {
      self::$_configs = sfConfig::getAll();
    }

    return $this;
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
        new sfDatabaseManager($this->_configuration);
        return Doctrine_Manager::connection();
      }
    }

    return null;
  }
}