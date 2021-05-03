<?php namespace pineapple;

class DatabaseConnection
{
    private $databaseFile;
    private $dbConnection;
    public $error;

    public function __construct($databaseFile)
    {
        $this->error = array();
        $this->databaseFile = $databaseFile;
        try {
            $this->dbConnection = new \SQLite3($this->databaseFile);
            $this->dbConnection->busyTimeout(20000);
        } catch (\Exception $e) {
            $this->error["databaseConnectionError"] = $e->getMessage();
        }
    }

    public function strError()
    {
        foreach ($this->error as $errorType => $errorMessage) {
            switch ($errorType) {
                case 'databaseConnectionError':
                    return "Could not connect to database: $errorMessage";
                    break;
                case 'databaseQueryError':
                    return "Could not execute query: $errorMessage";
                    break;
                case 'databaseExecutionError':
                    return "Could not execute query: $errorMessage";
                    break;
                default:
                    return "Unknown database error";
            }
        }

        return true;
    }

    public function getDatabaseFile()
    {
        return $this->databaseFile;
    }

    public function getDbConnection()
    {
        return $this->dbConnection;
    }

    public static function formatQuery(...$query)
    {
        $query = $query[0];
        $sqlQuery = $query[0];
        $sqlParameters = array_slice($query, 1);
        if (empty($sqlParameters)) {
            return $sqlQuery;
        }
        for ($i = 0; $i < count($sqlParameters); ++$i) {
            if (gettype($sqlParameters[$i]) === "string") {
                $escaped = \SQLite3::escapeString($sqlParameters[$i]);
                $sqlParameters[$i] = $escaped;
            }
        }
        $safeQuery = vsprintf($sqlQuery, $sqlParameters);
        return $safeQuery;
    }

    public function query(...$query)
    {
        $safeQuery = DatabaseConnection::formatQuery($query);
        $result = $this->dbConnection->query($safeQuery);
        if (!$result) {
            $this->error['databaseQueryError'] = $this->dbConnection->lastErrorMsg();
            return $this->error;
        }
        $resultArray = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            array_push($resultArray, $row);
        }
        return $resultArray;
    }

    public function exec(...$query)
    {
        $safeQuery = DatabaseConnection::formatQuery($query);
        try {
            $result = $this->dbConnection->exec($safeQuery);
        } catch (\Exception $e) {
            $this->error['databaseExecutionError'] = $e;
            return $this->error;
        }
        return array('success' => $result);
    }

    public function __destruct()
    {
        if ($this->dbConnection) {
            $this->dbConnection->close();
        }
    }
}
