<?php

class App_Model_SQLServerModelExample extends Drone_Sql_AbstractionModel
{
    public function myQuery()
    {
        $sql = "SELECT * FROM SYS.TABLES";
        $result = $this->getDb()->query($sql);
        return $this->getDb()->getArrayResult();
    }
}