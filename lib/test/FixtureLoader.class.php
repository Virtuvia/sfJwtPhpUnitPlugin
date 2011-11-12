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

/** Loads fixtures for unit/functional tests.
 *
 * @author Phoenix Zerin <phoenix@todofixthis.com>
 *
 * @package sfJwtPhpUnitPlugin
 * @subpackage lib.test
 */
class Test_FixtureLoader
{
  protected
    $_fixturesLoaded,
    $_varHolder,
    $_depth;

  /** Init the class instance.
   *
   * @return void
   */
  public function __construct(  )
  {
    $this->_depth = 0;

    $this->flushFixtures();
    $this->_varHolder = new sfParameterHolder();
  }

  /** Loads a fixture file.
   *
   * @param string|array(string)  $fixture The name of the fixture file
   *  (e.g., test_data.yml).
   * @param bool                  $force   If true, the fixture will be loaded
   *  even if it has already been loaded.
   *
   * @return mixed Some fixture files can return a value.  If an array value is
   *  passed in, an array will be returned as:
   *   {fixture_file_name: return_value, ...}
   *
   * If the fixture file was already loaded (and $force is false), loadFixture()
   *  will return false.
   */
  public function loadFixture( $fixture, $force = false )
  {
    if( is_array($fixture) )
    {
      $res = array();
      foreach( $fixture as $file )
      {
        $res[$file] = $this->loadFixture($file);
      }
      return $res;
    }
    elseif( $force or ! $this->isFixtureLoaded($fixture) )
    {
      if( $pos = strrpos($fixture, '.') )
      {
        $class =
            'Test_FixtureLoader_Loader_'
          . ucfirst(strtolower(substr($fixture, $pos + 1)));

        ++$this->_depth;

        $Loader = new $class($this);
        $res = $Loader->loadFixture($fixture);

        --$this->_depth;
      }
      else
      {
        throw new Exception(sprintf(
          'Fixture filename "%s" has no extension.',
            $fixture
        ));
      }

      $this->_fixturesLoaded[$fixture] = true;
      return $res;
    }
    else
    {
      /* Fixture file was already loaded. */
      return false;
    }
  }

  /** Returns whether a fixture has been loaded.
   *
   * @param string $fixture
   *
   * @return bool
   */
  public function isFixtureLoaded( $fixture )
  {
    return ! empty($this->_fixturesLoaded[$fixture]);
  }

  /** Resets $_fixturesLoaded.  Generally only used by
   *   Test_Case::flushDatabase().
   *
   * @return Test_FixtureLoader $this
   */
  public function flushFixtures(  )
  {
    $this->_fixturesLoaded = array();
    return $this;
  }

  /** Accessor for $_depth.
   *
   * Implemented as an instance method rather than a static method so that it is
   *  accessible to PHP fixture files.
   *
   * @return int
   */
  public function getDepth(  )
  {
    return $this->_depth;
  }

  /** Generic accessor.
   *
   * @param string $var
   *
   * @return mixed
   */
  public function __get( $var )
  {
    return $this->_varHolder->$var;
  }

  /** Generic modifier.
   *
   * @param string $var
   * @param mixed  $val
   *
   * @return mixed $val
   */
  public function __set( $var, $val )
  {
    return $this->_varHolder->$var = $val;
  }

  /** Generic isset() handler.
   *
   * @param string $var
   *
   * @return bool
   */
  public function __isset( $var )
  {
    return isset($this->_varHolder->$var);
  }

  /** Generic unset() handler.
   *
   * @param string $var
   *
   * @return void
   */
  public function __unset( $var )
  {
    unset($this->_varHolder->$var);
  }
}