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

/** Provides JPUP-specific database functionality.
 *
 * @author Phoenix Zerin <phoenix@todofixthis.com>
 *
 * @package sfJwtPhpUnitPlugin
 * @subpackage test.lib.database
 */
class Test_Database_Driver
{
  static protected
    $_dbRebuilt,
    $_dbFlushTree;

  /** @var Doctrine_Connection */
  protected $_connection;

  /** Factory method.
   *
   * @param $connection Doctrine_Connection
   *
   * @return static
   */
  static public function factory( Doctrine_Connection $connection )
  {
    switch( $connection->getDriverName() )
    {
      case Test_Database_Driver_Mysql::NAME:
        return new Test_Database_Driver_Mysql($connection);
    }

    return new self($connection);
  }

  /** Inits the class instance.
   *
   * @param $connection Doctrine_Connection
   */
  public function __construct( Doctrine_Connection $connection )
  {
    $this->_connection = $connection;
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
    if( $this->_connection )
    {
      /* The first time we run a test case, drop and rebuild the database.
       *
       * After that, we can simply truncate all tables for speed.
       */
      if( empty(self::$_dbRebuilt) or $rebuild )
      {
        $this->_loadModels();
      }
      else
      {
        $this->_flushModels();
      }
    }

    return $this;
  }

  /** Returns the name of the Doctrine database.
   *
   * @throws RuntimeException If the active connection has no DSN.
   * @return string
   */
  protected function getDatabaseName(  )
  {
    /* Why oh why does Doctrine_Connection not do this for us? */
    /** @noinspection PhpVoidFunctionResultUsedInspection */
    if( ! $dsn = $this->_connection->getOption('dsn') )
    {
      throw new RuntimeException(sprintf(
        'Doctrine connection "%s" does not have a DSN!',
          $this->_connection->getName()
      ));
    }

    $info = $this->_connection->getManager()->parsePdoDsn($dsn);
    return (isset($info['dbname']) ? $info['dbname'] : null);
  }

  /** Pre-flush handler.
   *
   * @return void
   */
  protected function _doPreFlush(  )
  {
  }

  /** Post-flush handler.
   *
   * @return void
   */
  protected function _doPostFlush(  )
  {
  }

  protected function _flushModels() {
     /* Determine the order we need to load models. */
    if( ! isset(self::$_dbFlushTree) )
    {
      /** @noinspection PhpUndefinedFieldInspection */
      $models = $this->_connection->unitOfWork->buildFlushTree(
        Doctrine_Core::getLoadedModels()
      );
      self::$_dbFlushTree = array_reverse($models);
    }

    $this->_doPreFlush();

    /* Delete records, paying special attention to SoftDelete. */
    foreach( self::$_dbFlushTree as $model )
    {

      $table = Doctrine_Core::getTable($model);

      if( $table->hasTemplate('SoftDelete') )
      {
        /** @var $record Doctrine_Template_SoftDelete */
        foreach( $table->createQuery()->execute() as $record )
        {
          $record->hardDelete();
        }
      }

      $table->createQuery()->delete()->execute();
      $table->clear();
    }

    $this->_doPostFlush();

    /** Clear all Doctrine table repositories to prevent memory leaks
     *    between tests.
     */
    $this->_connection->clear();
  }

  protected function _loadModels()
  {
    /* Don't try to drop the database unless it exists. */
    $name = $this->getDatabaseName();
    /** @noinspection PhpUndefinedFieldInspection */
    if( $name and $this->_connection->import->databaseExists($name) )
    {
      $this->_connection->dropDatabase();
    }

    $this->_connection->createDatabase();

    Doctrine_Core::loadModels(
      sfConfig::get('sf_lib_dir').'/model/doctrine',
      Doctrine_Core::MODEL_LOADING_CONSERVATIVE
    );

    Doctrine_Core::createTablesFromArray(Doctrine_Core::getLoadedModels());

    self::$_dbRebuilt = true;
  }
}