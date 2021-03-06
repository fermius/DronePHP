<?php
/**
 * DronePHP (http://www.dronephp.com)
 *
 * @link      http://github.com/fermius/DronePHP
 * @copyright Copyright (c) 2016-2017 Pleets. (http://www.pleets.org)
 * @license   http://www.dronephp.com/license
 * @author    Darío Rivera <dario@pleets.org>
 */

/**
 * SQLServer class
 *
 * This is a database driver class to connect to SQLServer
 */
class Drone_Db_Driver_SQLServer extends Drone_Db_Driver_AbstractDriver implements Drone_Db_Driver_DriverInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct($options)
    {
        if (!array_key_exists("dbchar", $options))
            $options["dbchar"] = "UTF-8";

        parent::__construct($options);

        $auto_connect = array_key_exists('auto_connect', $options) ? $options["auto_connect"] : true;

        if ($auto_connect)
            $this->connect();
    }

    /**
     * Connects to database
     *
     * @throws RuntimeException
     * @throws Drone_Db_Driver_Exception_ConnectionException
     *
     * @return resource
     */
    public function connect()
    {
        if (!extension_loaded('sqlsrv'))
            throw new RuntimeException("The Sqlsrv extension is not loaded");

        if (!is_null($this->dbport) && !empty($this->dbport))
            $this->dbhost .= ', ' . $this->dbport;

        $db_info = array("Database" => $this->dbname, "UID" => $this->dbuser, "PWD" => $this->dbpass, "CharacterSet" => $this->dbchar);
        $this->dbconn = sqlsrv_connect($this->dbhost, $db_info);

        if ($this->dbconn === false)
        {
            $errors = sqlsrv_errors();

            $previousException;

            foreach ($errors as $error)
            {
                $previousException = new Drone_Db_Driver_Exception_ConnectionException($error["message"], $error["code"], $previousException);
            }

            throw $previousException;
        }

        return $this->dbconn;
    }

    /**
     * Excecutes a statement
     *
     * @param string $sql
     * @param params $params
     *
     * @throws Drone_Db_Driver_Exception_InvalidQueryException
     *
     * @return resource
     */
    public function execute($sql, Array $params = array())
    {
        $this->numRows = 0;
        $this->numFields = 0;
        $this->rowsAffected = 0;

        $this->arrayResult = null;

        # Bound variables
        if (count($params))
        {
            $this->result = sqlsrv_prepare($this->dbconn, $sql, $params);

            if (!$this->result)
            {
                $errors = sqlsrv_errors();

                foreach ($errors as $error)
                {
                    $this->errorprovider->error($error["code"], $error["message"]);
                }

                throw new Drone_Db_Driver_Exception_InvalidQueryException($error["message"], $error["code"]);
            }

            $r = sqlsrv_execute($this->result);
        }
        else
            $r = $this->result = sqlsrv_query($this->dbconn, $sql, $params, array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));

        if (!$r)
        {
            $errors = sqlsrv_errors();

            foreach ($errors as $error)
            {
                $this->errorProvider->error($error["code"], $error["message"]);
            }

            throw new Drone_Db_Driver_Exception_InvalidQueryException($error["message"], $error["code"]);
        }

        $this->getArrayResult();

        $this->numRows = sqlsrv_num_rows($this->result);
        $this->numFields = sqlsrv_num_fields($this->result);
        $this->rowsAffected = sqlsrv_rows_affected($this->result);

        if ($this->transac_mode)
            $this->transac_result = is_null($this->transac_result) ? $this->result: $this->transac_result && $this->result;

        return $this->result;
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        return sqlsrv_commit($this->dbconn);
    }

    /**
     * {@inheritDoc}
     */
    public function rollback()
    {
        return sqlsrv_rollback($this->dbconn);
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect()
    {
        parent::disconnect();
        return sqlsrv_close($this->dbconn);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        if (sqlsrv_begin_transaction($this->dbconn) === false)
        {
            $errors = sqlsrv_errors();

            foreach ($errors as $error)
            {
                $this->errorProvider->error($error["code"], $error["message"]);
            }

            throw new RuntimeException("Could not begin transaction");
        }

        return parent::beginTransaction();
    }

    /**
     * Returns an array with the rows fetched
     *
     * @throws LogicException
     *
     * @return array
     */
    protected function toArray()
    {
        $data = array();

        if ($this->result)
        {
            while ($row = sqlsrv_fetch_array($this->result))
            {
                $data[] = $row;
            }
        }
        else
            /*
             * "This kind of exception should lead directly to a fix in your code"
             * So much production tests tell us this error is throwed because developers
             * execute toArray() before execute().
             *
             * Ref: http://php.net/manual/en/class.logicexception.php
             */
            throw new LogicException('There are not data in the buffer!');

        $this->arrayResult = $data;

        return $data;
    }

    /**
     * By default __destruct() disconnects to database
     *
     * @return null
     */
    public function __destruct()
    {
        if ($this->dbconn)
            sqlsrv_close($this->dbconn);
    }
}