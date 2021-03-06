<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Db
 */

namespace Zend\Db\Adapter\Driver\Sqlsrv;

use Zend\Db\Adapter\Driver\StatementInterface,
    Zend\Db\Adapter\ParameterContainer,
    Zend\Db\Adapter\Exception;

/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 */
class Statement implements StatementInterface
{

    /**
     * @var resource
     */
    protected $sqlsrv = null;

    /**
     * @var Sqlsrv
     */
    protected $driver = null;

    /**
     * @var string
     */
    protected $sql = null;

    /**
     * @var bool
     */
    protected $isQuery = null;

    /**
     * @var array
     */
    protected $parameterReferences = array();

    /**
     * @var Zend\Db\Adapter\ParameterContainer\ParameterContainer
     */
    protected $parameterContainer = null;

    /**
     * @var resource
     */
    protected $resource = null;

    /**
     *
     * @var boolean
     */
    protected $isPrepared = false;

    /**
     * Set driver
     * 
     * @param  Sqlsrv $driver
     * @return Statement 
     */
    public function setDriver(Sqlsrv $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * 
     * One of two resource types will be provided here:
     * a) "SQL Server Connection" when a prepared statement needs to still be produced
     * b) "SQL Server Statement" when a prepared statement has been already produced 
     * (there will need to already be a bound param set if it applies to this query)
     * 
     * @param resource
     */
    public function initialize($resource)
    {
        $this->sqlsrv = $resource;
    }

    /**
     * Set parameter container
     * 
     * @param ParameterContainer $parameterContainer
     */
    public function setParameterContainer(ParameterContainer $parameterContainer)
    {
        $this->parameterContainer = $parameterContainer;
    }

    /**
     * @return ParameterContainer
     */
    public function getParameterContainer()
    {
        return $this->parameterContainer;
    }

    /**
     * Get resource
     * 
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param string $sql
     */
    public function setSql($sql)
    {
        $this->sql = $sql;
    }

    /**
     * Get sql
     * 
     * @return string 
     */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * @param string $sql
     */
    public function prepare($sql = null)
    {
        if ($this->isPrepared) {
            throw new Exception\RuntimeException('Already prepared');
        }
        $sql = ($sql) ?: $this->sql;

        $pRef = &$this->parameterReferences;
        for ($position = 0; $position < substr_count($sql, '?'); $position++) {
            $pRef[$position] = array('', SQLSRV_PARAM_IN, null, null);
        }

        $this->resource = sqlsrv_prepare($this->sqlsrv, $sql, $pRef);

        $this->isPrepared = true;
    }

    /**
     * @return bool
     */
    public function isPrepared()
    {
        return $this->isPrepared;
    }

    /**
     * Execute
     * 
     * @param  array|ParameterContainer $parameters
     * @return type 
     */
    public function execute($parameters = null)
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }

        if ($parameters !== null) {
            if (is_array($parameters)) {
                $parameters = new ParameterContainer($parameters);
            }
            if (!$parameters instanceof ParameterContainer) {
                throw new Exception\InvalidArgumentException('ParameterContainer expected');
            }
            $this->parameterContainer = $parameters;
        }

        if ($this->parameterContainer) {
            $this->bindParametersFromContainer();
        }

        $resultValue = sqlsrv_execute($this->resource);

        if ($resultValue === false) {
            $errors = sqlsrv_errors();
            // ignore general warnings
            if ($errors[0]['SQLSTATE'] != '01000') {
                throw new Exception\RuntimeException($errors[0]['message']);
            }
        }

        $result = $this->driver->createResult($this->resource);
        return $result;
    }

    /**
     * Bind parameters from container
     * 
     */
    protected function bindParametersFromContainer()
    {
        $values = $this->parameterContainer->getPositionalArray();
        $position = 0;
        foreach ($values as $value) {
            $this->parameterReferences[$position++][0] = $value;
        }

        // @todo bind errata
        //foreach ($this->parameterContainer as $name => &$value) {
        //    $p[$position][0] = $value;
        //    $position++;
        //    if ($this->parameterContainer->offsetHasErrata($name)) {
        //        $p[$position][3] = $this->parameterContainer->offsetGetErrata($name);
        //    }
        //}
    }

}
