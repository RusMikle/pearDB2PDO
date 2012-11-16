<?php
/*
 * 	Class:	DB
* 	Progr.:	Mikhail Tchervonenko
* 	Data: 	2012-10-15
* 	EMail: 	rusmikleATgmailPointCom
*   ICQ: 	35818796
*   Skype: 	RusMikle
*
*  ver 1.0.2
*
*  GNU General Public License
*/


class DB
{
    public $tp = "cms1_"; // Table Prefix
    private $conn = array();
    public $error = 0;

    private $show_errors = false;
    private $stop_after_error = false;
    private $on_error_rollback = true;

    public $transact_active = false;
    public $server = 'MYSQL';

    private $counter = array(); // $fcount * $rcount
    private $fcount = array();
    private $rcount = array();

    private $prep = array();
    private $mode;
    private $last_fetch_mode;

    private $mAffectedRows = 0;


    private $connect_pool_nr = 0;
    private $maxPoolNr = -1;


    // ***********************************
    // ***** err
    private function err ($e, $dop_string = '')
    {
        global $err;
        if ($this->on_error_rollback)
            $this->rollBack();

        if (is_object($this->conn[$this->connect_pool_nr]))
            $this->error = $this->conn[$this->connect_pool_nr]->errorCode(); // Get Pdo Error
        else
            $this->error = 4; // PDO Objekt existiert nicht

        $err = new ERR();
        $err->err_log($e->getMessage() . "   " . $dop_string, $this->show_errors, $this->stop_after_error, true);
    }

    // ***********************************
    // ***** set_pool
    public function set_pool ($lnPool = 1)
    {
        if ($lnPool >= 0 and $lnPool <= $this->maxPoolNr)
        {
            $this->connect_pool_nr = $lnPool;

            return true;
        }

        return false;
    }


    // ***********************************
    // ***** set_utf8_prop
    public function set_utf8_prop ()
    {
        if (!$this->is_connected())
            return false;

        switch ($this->server)
        {
            case 'MYSQL':
                $this->query("SET NAMES 'utf8';");
                $this->query("SET CHARACTER SET 'utf8'");
                $this->query("set character_set_client='utf8'");
                $this->query("set character_set_results='utf8'");
                $this->query("set collation_connection='utf8_general_ci'");
                break;
        }

        return true;
    }

    // ***********************************
    // ***** newid
    public function newid ($max_len = 8)
    {
        $code = strtoupper(substr(md5(uniqid(microtime(), 1)), 0, $max_len));

        return $code;
    }

    // ***********************************
    // ***** is_connected
    public function is_connected ()
    {

        if (!is_object($this->conn[$this->connect_pool_nr]) || empty($this->conn[$this->connect_pool_nr]))
        {
            $this->error = 2;

            return false;
        }

        return true;

    }

    // ***********************************
    // ***** connect_pool
    public function connect_pool ($coonect_pool_param)
    {
        $lnI = 0;
        if (is_array($coonect_pool_param))
        {
            foreach ($coonect_pool_param AS $value)
            {
                if (!$this->connect($value['db_server'],
                    $value['db_user'],
                    $value['db_pass'],
                    $value['db_database'],
                    $value['db_table_preffix'],
                    $value['show_errors'],
                    $value['stop_after_error'],
                    $value['on_error_rollback'],
                    $lnI,
                    $value['driver']
                )
                )
                {
                    return false;
                }
                else
                    $this->maxPoolNr = $lnI;

                $lnI++;
            }

            return true;
        }

        return false;
    }


    // ***********************************
    // ***** connect
    public function connect ($host = "localhost",
                             $user = "root",
                             $pass = "",
                             $database = "",
                             $table_prefix = "st__",
                             $show_errors = false,
                             $stop_after_error = false,
                             $on_error_rollback = true,
                             $connect_pool_nr = 0,
                             $driver = array(PDO :: MYSQL_ATTR_INIT_COMMAND => 'SET NAMES `utf8`'))
    {
        $this->tp                = $table_prefix;
        $this->show_errors       = $show_errors;
        $this->stop_after_error  = $stop_after_error;
        $this->on_error_rollback = $on_error_rollback;
        $this->connect_pool_nr   = $connect_pool_nr;

        try
        {
            switch ($this->server)
            {
                case 'MYSQL':
                    $this->conn[$this->connect_pool_nr] = new PDO("mysql:host=$host;dbname=$database", $user, $pass, $driver);
                    //  |PDO::ERRMODE_SILENT|PDO::ERRMODE_EXCEPTION
                    $this->conn[$this->connect_pool_nr]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    break;
            }
        } catch (PDOException $e)
        {
            $this->err($e);
            $this->conn[$this->connect_pool_nr] = null;

            return false;
        }

        $this->error = 0;

        return true;
    }

    // ***********************************
    // ***** ConnectFromPropel (Make connection aus exists PDO connect (from Propel)
    public function ConnectFromPropel ($database_name, $show_errors = false, $stop_after_error = false, $on_error_rollback = true)
    {
        $this->show_errors       = $show_errors;
        $this->stop_after_error  = $stop_after_error;
        $this->on_error_rollback = $on_error_rollback;

        $this->conn = Propel::getConnection($database_name);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->error = 0;

        return true;
    }


    // ***********************************
    // ***** quote
    function quote ($parameter, $parameter_type = PDO::PARAM_STR)
    {
        if (is_null($parameter))
        {
            return "NULL";
        }

        return $this->conn[$this->connect_pool_nr]->quote($parameter, $parameter_type);
    }

    // ***********************************
    // ***** __destruct
    function __destruct ()
    {
        if (is_object($this->conn[$this->connect_pool_nr]))

            $this->conn[$this->connect_pool_nr] = null;
    }

    // ***********************************
    // ***** inTransaction
    public function inTransaction ()
    {
        return $this->transact_active;
        // return $this->conn[$this->connect_pool_nr]->inTransaction();
    }

    // ***********************************
    // ***** beginTransaction
    public function beginTransaction ()
    {
        if (!$this->is_connected())
            return false;

        try
        {
            $this->conn[$this->connect_pool_nr]->beginTransaction();

            $this->transact_active = true;
        } catch (PDOException $e)
        {
            $this->transact_active = false;

            $this->err($e);

            return false;
        }

        $this->error = 0;

        return true;
    }

    // ***********************************
    // ***** rollBack
    public function rollBack ()
    {

        if (!$this->is_connected())
            return false;

        if ($this->inTransaction())
        {
            try
            {
                $this->conn[$this->connect_pool_nr]->rollBack();

                $this->transact_active = false;
            } catch (PDOException $e)
            {
                $this->transact_active = false;

                $this->err($e);

                return false;
            }
        }
        $this->error = 0;

        return true;
    }

    // ***********************************
    // ***** commit
    public function commit ()
    {

        if (!$this->is_connected())
            return false;

        if ($this->inTransaction())
        {
            try
            {
                $this->conn[$this->connect_pool_nr]->commit();

                $this->transact_active = false;
            } catch (PDOException $e)
            {

                $this->transact_active = false;

                $this->err($e);

                return false;
            }
        }
        $this->error = 0;

        return true;
    }


    // ***********************************
    // ***** close_pool
    public function close_pool ()
    {
        $lnI = 0;
        if ($this->maxPoolNr >= 0)
            foreach ($this->conn AS $value)
            {
                if ($this->set_pool($lnI))
                    $this->close();
                $lnI++;
            }
    }

    // ***********************************
    // ***** close
    public function close ()
    {
        if (!$this->is_connected())
            return false;

        $this->rollBack();

        $this->conn[$this->connect_pool_nr] = null;

        $this->error = 0;
    }

    // ***********************************
    // ***** query
    public function query ($sql, $params = false, $alias = 0)
    {
        if (!$this->is_connected())
            return false;

        $this->error         = 0;
        $this->mode          = 0;
        $this->mAffectedRows = 0;

        if (is_array($params))
        {
            try
            {
                $this->prep[$alias] = $this->conn[$this->connect_pool_nr]->prepare($sql);

                if (count($params[key($params)], 0) > 1) // Wenn parameters bei Name gesendet
                {
                    /*
                     * z.B.
                    * $params['varname'][0]=$varvalue;
                    * $params['varname'][1]=PDO::PARAM_STR;
                    * $params['varname'][2]=12;
                    * $params['varname'][3]=$drv_options;
                    *
                    * $params['varname1'][0]=$varvalue1;
                    * $params['varname1'][1]=PDO::PARAM_INT;
                    * $params['varname1'][4]=$drv_options;
                    *
                    */

                    $lnI = 0;
                    foreach ($params AS $key => $value)
                    {
                        foreach ($value AS $val)
                        {
                            $var[$lnI] = $val;
                            $lnI++;

                        }
                        if ($lnI > 0)
                        {
                            if (is_integer($var[0]) && empty($var[1]))
                            {
                                $var[1] = PDO::PARAM_INT;
                            }
                            elseif (is_float($var[0]) && empty($var[1]))
                            {
                                $var[1] = PDO::PARAM_FLOAT;
                            }
                            elseif (is_bool($var[0]))
                            {
                                $var[1] = PDO::PARAM_BOOLEAN;
                            }
                            elseif (is_null($var[0]) && empty($var[1]))
                            {
                                $var[0] = null;
                                $var[1] = PDO::PARAM_NULL;
                            }
                            elseif (empty($var[1]) || empty($var[2]))
                            {
                                if (empty($var[1]))
                                    $var[1] = PDO::PARAM_STR;

                                if (empty($var[2]))
                                    $var[2] = strlen($var[0]);
                            }

                            $this->prep[$alias]->bindParam(':' . $key, $var[0], $var[1], $var[2], $var[3]); // $key- Param. name, 0 - value, 1 - Typ, 2 - Length, 3 drviver options ,
                            unset($var);
                        }
                        $lnI = 0;
                    }

                    $this->prep[$alias]->execute();
                }
                else
                {
                    $this->prep[$alias]->execute($params); // Wenn array nur mit values
                    $this->mAffectedRows = $this->prep[$alias]->rowCount();
                }
            } catch (PDOException $e)
            {

                $this->err($e, $sql);

                return false;
            }
        }
        else
        {
            try
            {
                $this->prep[$alias] = $this->conn[$this->connect_pool_nr]->query($sql);
            } catch (PDOException $e)
            {

                $this->err($e, $sql);

                return false;
            }
        }

        return true;
    }


    // ***********************************
    // ***** limitQuery
    public function limitQuery ($sql, $first, $cnt, $params = false)
    {
        $sql .= ' LIMIT ' . (int)$first . ', ' . (int)$cnt;

        return $this->query($sql, $params);
    }


    // ***********************************
    // ***** repeat
    public function repeat ($params, $alias = 0)
    {
        if (!$this->is_connected())
            return false;

        $this->error = 0;
        if (is_array($params) && ($this->prep[$alias] instanceof PDOStatement))
        {
            try
            {
                if (count($params[key($params)], 0) > 1) // Wenn parameters bei Name gesendet
                {
                    /*
                     * z.B.
                    * $params['varname'][0]=$varvalue;
                    * $params['varname'][1]=PDO::PARAM_STR;
                    * $params['varname'][2]=12;
                    * $params['varname'][3]=$drv_options;
                    *
                    * $params['varname1'][0]=$varvalue1;
                    * $params['varname1'][1]=PDO::PARAM_INT;
                    * $params['varname1'][4]=$drv_options;
                    *
                    */

                    $lnI = 0;
                    foreach ($params AS $key => $value)
                    {
                        foreach ($value AS $val)
                        {
                            $var[$lnI] = $val;
                            $lnI++;

                        }
                        if ($lnI > 0)
                        {
                            $this->prep[$alias]->bindParam(':' . $key, $var[0], $var[1], $var[2], $var[3]); // $key- Param. name, 0 - value, 1 - Typ, 2 - Length, 3 drviver options ,
                            unset($var);
                        }
                        $lnI = 0;
                    }

                    $this->prep[$alias]->execute();
                }
                else
                    $this->prep[$alias]->execute($params); // Wenn array nur mit values

            } catch (PDOException $e)
            {

                $this->err($e);

                return false;
            }

            $this->get_fields_count($this->prep[$alias], $alias);
            $this->get_records_count($this->prep[$alias], $alias);
            $this->counter[$alias] = $this->fcount[$alias] * $this->rcount[$alias];

            if ($this->mode == 0) // bei query
                return true;

            $ret = array();


            if ($this->mode == 1) // bei getOne
            {
                $ret = $this->prep[$alias]->fetch(PDO::FETCH_BOTH);

                return $ret[0];
            }

            if ($this->mode == 2) // bei getRow
                $ret = $this->prep[$alias]->fetch($this->last_fetch_mode);

            if ($this->mode == 3) // bei getAll
            {
                while ($row = $this->prep[$alias]->fetch($this->last_fetch_mode))
                    $ret[] = $row;

            }

            return $ret;
        }
        else
        {
            $this->error = 1;

            return false;
        }
    }

    // ***********************************
    // ***** exec
    public function exec ($sql)
    {
        if (!$this->is_connected())
            return false;

        $this->error = 0;
        try
        {
            return $this->conn[$this->connect_pool_nr]->exec($sql);
        } catch (PDOException $e)
        {

            $this->err($e, $sql);

            return false;
        }
    }

    // ***********************************
    // ***** count
    public function count ($alias = 0)
    {
        return $this->counter[$alias];
    }

    // ***********************************
    // ***** rcount
    public function rcount ($alias = 0)
    {
        return $this->rcount[$alias];
    }

    // ***********************************
    // ***** fcount
    public function fcount ($alias = 0)
    {
        return $this->fcount[$alias];
    }

    // ***********************************
    // ***** get_fields_count
    private function get_fields_count ($res, $alias = 0)
    {
        $this->fcount[$alias] = 0;

        if (!is_object($res))
            return 0;

        $this->fcount[$alias] = $res->columnCount();

        return $this->fcount[$alias];
    }

    // ***********************************
    // ***** get_records_count
    private function get_records_count ($res, $alias = 0)
    {
        $this->rcount[$alias] = 0;

        if (!is_object($res))
            return 0;

        $this->rcount[$alias] = $res->rowCount();

        return $this->rcount[$alias];
    }

    // ***********************************
    // ***** getOne
    public function getOne ($sql, $params = false, $alias = 0)
    {
        if (!$this->is_connected())
            return false;

        if (empty($sql))
        {
            $this->error = 1;

            return false;
        }

        if (!$this->query($sql, $params, $alias) || !($this->prep[$alias] instanceof PDOStatement))
            return false;

        $this->mode = 1;

        $ret = $this->prep[$alias]->fetch(PDO::FETCH_BOTH);
        $this->prep[$alias]->closeCursor();


        return $ret[0];
    }

    // ***********************************
    // ***** getRow
    public function getRow ($sql, $params = false, $alias = 0, $fetch_mode = PDO::FETCH_ASSOC)
    {
        /*	$ret_type
        PDO::FETCH_ASSOC: returns an array indexed by column name as returned in your result set
        PDO::FETCH_BOTH (default): returns an array indexed by both column name and 0-indexed column number as returned in your result set
        PDO::FETCH_BOUND: returns TRUE and assigns the values of the columns in your result set to the PHP variables to which they were bound with the PDOStatement::bindParam() method
        PDO::FETCH_CLASS: returns a new instance of the requested class, mapping the columns of the result set to named properties in the class. If fetch_style includes PDO::FETCH_CLASSTYPE (e.g. PDO::FETCH_CLASS | PDO::FETCH_CLASSTYPE) then the name of the class is determined from a value of the first column.
        PDO::FETCH_INTO: updates an existing instance of the requested class, mapping the columns of the result set to named properties in the class
        PDO::FETCH_LAZY: combines PDO::FETCH_BOTH and PDO::FETCH_OBJ, creating the object variable names as they are accessed
        PDO::FETCH_NUM: returns an array indexed by column number as returned in your result set, starting at column 0
        PDO::FETCH_OBJ: returns an anonymous object with property names that correspond to the column names returned in your result set
        */
        if (!$this->is_connected())
            return false;


        if (empty($sql))
        {
            $this->error = 1;

            return false;
        }

        $this->last_fetch_mode = $fetch_mode;

        $this->counter[$alias] = 0;

        if (!$this->query($sql, $params, $alias) || !($this->prep[$alias] instanceof PDOStatement))
            return false;

        $this->mode = 2;

        $this->get_fields_count($this->prep[$alias], $alias);
        $this->counter[$alias] = $this->fcount[$alias];

        $ret = array();

        $ret = $this->prep[$alias]->fetch($fetch_mode);
        $this->prep[$alias]->closeCursor();

        return $ret;
    }

    // ***********************************
    // ***** getAll
    public function getAll ($sql, $params = false, $alias = 0, $fetch_mode = PDO::FETCH_ASSOC)
    {
        /*	$ret_type
        PDO::FETCH_ASSOC: returns an array indexed by column name as returned in your result set
        PDO::FETCH_BOTH (default): returns an array indexed by both column name and 0-indexed column number as returned in your result set
        PDO::FETCH_BOUND: returns TRUE and assigns the values of the columns in your result set to the PHP variables to which they were bound with the PDOStatement::bindParam() method
        PDO::FETCH_CLASS: returns a new instance of the requested class, mapping the columns of the result set to named properties in the class. If fetch_style includes PDO::FETCH_CLASSTYPE (e.g. PDO::FETCH_CLASS | PDO::FETCH_CLASSTYPE) then the name of the class is determined from a value of the first column.
        PDO::FETCH_INTO: updates an existing instance of the requested class, mapping the columns of the result set to named properties in the class
        PDO::FETCH_LAZY: combines PDO::FETCH_BOTH and PDO::FETCH_OBJ, creating the object variable names as they are accessed
        PDO::FETCH_NUM: returns an array indexed by column number as returned in your result set, starting at column 0
        PDO::FETCH_OBJ: returns an anonymous object with property names that correspond to the column names returned in your result set
        */
        if (!$this->is_connected())
            return false;

        if (empty($sql))
        {
            $this->error = 1;

            return false;
        }

        $this->last_fetch_mode = $fetch_mode;

        $this->counter[$alias] = 0;

        if (!$this->query($sql, $params, $alias) || !($this->prep[$alias] instanceof PDOStatement))
            return false;

        $this->mode = 3;

        $this->get_fields_count($this->prep[$alias], $alias);
        $this->get_records_count($this->prep[$alias], $alias);

        $ret = array();

        while ($row = $this->prep[$alias]->fetch($fetch_mode))
            $ret[] = $row;

        $this->counter[$alias] = $this->fcount[$alias] * $this->rcount[$alias];
        $this->prep[$alias]->closeCursor();

        return $ret;
    }

    // **********************************
    // ***** fetchRow
    public function fetchRow ($alias = 0, $fetch_mode = PDO::FETCH_ASSOC)
    {
        $row = $this->prep[$alias]->fetch($fetch_mode);

        return $row;
    }


    // **********************************
    // ***** numRows
    public function numRows ()
    {
        $alias = 0;

        return $this->rcount[$alias];
    }

    // **********************************
    // ***** affectedRows
    public function affectedRows ()
    {
        return $this->mAffectedRows;
    }


    // **********************************
    // ***** getAssoc
    // @param $sql
    // @param bool $fld - parameter only for compatibility mit pear db
    // @param bool $params
    public function  getAssoc ($sql, $fld = true, $params = false)
    {
        $res = $this->getAll($sql, $params);
        if (!empty($res))
        {
            $keys = array_keys($res[0]);
            if (!empty($keys[0]))
            {
                $res = $this->make_assoc_array($res, $keys[0]);
                unset($keys);
            }
        }

        return $res;
    }


    // ***********************************
    // ***** make_assoc_array
    public function make_assoc_array ($ar, $field)
    {
        if (empty($ar))
        {
            return $ar;
        }
        $res = array();
        foreach ($ar as $v)
        {
            $res[$v[$field]] = $v;
        }
        unset($ar);

        return $res;
    }


    // ***********************************
    // ***** lastId
    public function lastId ($name = null)
    { //$name - name of the sequence object from which the ID should be returned.
        if (!$this->is_connected())
            return false;

        return $this->conn[$this->connect_pool_nr]->lastInsertId($name);
    }

    // ***********************************
    // ***** nextId
    public function nextId ($table_name)
    {
        if (!$this->is_connected())
            return false;

        if (empty($table_name))
            return false;


        $id = false;

        $table_name = strtolower(trim($table_name)) . "_seq";

        switch ($this->server)
        {
            case 'MYSQL':
                $this->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (`id` bigint(20) unsigned) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
                $this->query("LOCK TABLES " . $table_name . " READ");

                $id = (int)$this->getOne("SELECT id FROM " . $table_name);
                if (empty($id))
                {
                    $id = 1;
                    $this->query("INSERT into " . $table_name . " SET id=" . $id);
                }
                else
                {
                    $id++;
                    $this->query("UPDATE " . $table_name . " SET id=" . $id);
                }

                $this->query("UNLOCK TABLES");
                break;
        }

        return $id;
    }

    // ***********************************
    // ***** iserror
    public function iserror ($res)
    { // dieses Metot existiert nur für Kompatibilitat mit alte PEAR Bibliothek !!!!
        if ($this->error > 0 || (!is_object($res) && !is_array($res) && empty($res)))
            return true;
        else
            return false;
    }
}

// End Class
?>