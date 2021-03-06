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
 * EntityAdapter class
 *
 * This class allows to persist objects to database (Data Mapper pattern)
 */
class Drone_Db_TableGateway_EntityAdapter
{
    /**
     * @var Drone_Db_TableGateway $tableGateway
     */
    private $tableGateway;

    /**
     * Constructor
     *
     * @param Drone_Db_TableGateway_TableGateway $tableGateway
     */
    public function __construct(Drone_Db_TableGateway_TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    /**
     * Returns the tableGateway
     *
     * @return Drone_Db_TableGateway
     */
    public function getTableGateway()
    {
        return $this->tableGateway;
    }

    /**
     * Returns a rowset with entity instances
     *
     * @param array $where
     *
     * @return Drone_Db_Entity[]
     */
    public function select(Array $where)
    {
        $result = $this->tableGateway->select($where);

        if (!count($result))
            return $result;

        $array_result = array();

        foreach ($result as $row)
        {
            $filtered_array = array();

            foreach ($row as $key => $value)
            {
                if (is_string($key))
                    $filtered_array[$key] = $value;
            }

            $user_entity = get_class($this->tableGateway->getEntity());

            $entity = new $user_entity($filtered_array);

            $array_result[] = $entity;
        }

        return $array_result;
    }

    /**
     * Creates a row from an entity or array
     *
     * @param Drone_Db_Entity|array $entity
     *
     * @throws InvalidArgumentException
     *
     * @return resource|object
     */
    public function insert($entity)
    {
        if ($entity instanceof Drone_Db_Entity)
            $entity = get_object_vars($entity);
        else if (!is_array($entity))
            throw new InvalidArgumentException("Invalid type given. Drone_Db_Entity or Array expected");

        $this->parseEntity($entity);

        return $this->tableGateway->insert($entity);
    }

    /**
     * Updates an entity
     *
     * @param Drone_Db_Entity|array $entity
     * @param array $where
     *
     * @throws RuntimeException inherit from internal execute()
     * @throws InvalidArgumentException
     *
     * @return resource|boolean
     */
    public function update($entity, $where)
    {
        if ($entity instanceof Drone_Db_Entity)
        {
            $changedFields = $entity->getChangedFields();
            $entity = get_object_vars($entity);

            $fieldsToModify = array();

            foreach ($entity as $key => $value)
            {
                if (in_array($key, $changedFields))
                    $fieldsToModify[$key] = $value;
            }

            $entity = $fieldsToModify;
        }
        else if (!is_array($entity))
            throw new InvalidArgumentException("Invalid type given. Drone_Db_Entity or Array expected");

        $this->parseEntity($entity);

        return $this->tableGateway->update($entity, $where);
    }

    /**
     * Deletes an entity
     *
     * @param Drone_Db_Entity|array $entity
     *
     * @throws RuntimeException inherit from internal execute()
     * @throws InvalidArgumentException
     *
     * @return boolean
     */
    public function delete($entity)
    {
        if ($entity instanceof Drone_Db_Entity)
            $entity = get_object_vars($entity);
        else if (!is_array($entity))
            throw new InvalidArgumentException("Invalid type given. Drone_Db_Entity or Array expected");

        return $this->tableGateway->delete($entity);
    }

   /**
     * Converts several objects to SQLFunction objects
     *
     * @param array $entity
     *
     * @return array
     */
    private function parseEntity(Array &$entity)
    {
        $drv = $this->getTableGateway()->getDriver()->getDriverName();

        foreach ($entity as $field => $value)
        {
            if ($value instanceof DateTime)
            {
                switch ($drv)
                {
                    case 'Oci8':
                        $entity[$field] = new Drone_Db_SQLFunction('TO_DATE', array($value->format('Y-m-d'), 'YYYY-MM-DD'));
                        break;
                    case 'Mysqli':
                        $entity[$field] = new Drone_Db_SQLFunction('STR_TO_DATE', array($value->format('Y-m-d'), '%Y-%m-%d'));
                        break;
                    case 'Sqlsrv':
                        $entity[$field] = new Drone_Db_SQLFunction('CONVERT', array('DATETIME', $value->format('Y-m-d')));
                        break;
                }
            }
        }

        return $entity;
    }
}