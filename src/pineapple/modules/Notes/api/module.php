<?php namespace pineapple;

class Notes extends SystemModule
{

    private $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = new DatabaseConnection(self::DATABASE);
        if (!empty($this->dbConnection->error)) {
            $this->error = $this->dbConnection->strError();
            return;
        }
        $this->dbConnection->exec("CREATE TABLE IF NOT EXISTS notes (type INT, key TEXT UNIQUE NOT NULL, name TEXT, note TEXT);");
        if (!empty($this->dbConnection->error)) {
            $this->error = $this->dbConnection->strError();
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'setName':
                $this->response = $this->setName($this->request->type, $this->request->key, $this->request->name);
                break;
            case 'setNote':
                $this->response = $this->setNote($this->request->type, $this->request->key, $this->request->name, $this->request->note);
                break;
            case 'getNotes':
                $this->response = $this->getNotes();
                break;
            case 'getNote':
                $this->response = $this->getNote($this->request->key);
                break;
            case 'deleteNote':
                $this->response = $this->deleteNote($this->request->key);
                break;
            case 'downloadNotes':
                $this->response = $this->downloadNotes();
                break;
            case 'getKeys':
                $this->response = $this->getKeys();
                break;
        }
    }

    public function setName($type, $key, $name)
    {
        return $this->dbConnection->exec("INSERT OR REPLACE INTO notes (type, key, name) VALUES('%d', '%s', '%s');", $type, $key, $name);
    }

    public function setNote($type, $key, $name, $note)
    {
        if (empty($name) && empty($note)) {
            return $this->deleteNote($key);
        } else {
            return $this->dbConnection->exec("INSERT OR REPLACE INTO notes (type, key, name, note) VALUES ('%d', '%s', '%s', '%s');", $type, $key, $name, $note);
        }
    }

    public function getNotes()
    {
        $macs = $this->dbConnection->query("SELECT type, key, name, note FROM notes WHERE type=0;");
        $ssids = $this->dbConnection->query("SELECT type, key, name, note FROM notes WHERE type=1;");
        return array("macs" => $macs, "ssids" => $ssids);
    }

    public function getNote($key)
    {
        return array("note" => $this->dbConnection->query("SELECT type, key, name, note FROM notes WHERE key='%s';", $key));
    }

    public function deleteNote($key)
    {
        if (!isset($key)) {
            return array("success" => false);
        }
        $this->dbConnection->exec("DELETE FROM notes WHERE key='%s';", $key);
        return array("success" => true);
    }

    public function downloadNotes()
    {
        $noteData = $this->dbConnection->query('SELECT * FROM notes;');
        foreach ($noteData as $idx => $note) {
            if ($note['type'] == 0) {
                $note['type'] = 'MAC';
            } else if ($note['type'] == 1) {
                $note['type'] = 'SSID';
            }
            $noteData[$idx] = $note;
        }
        $fileName = '/tmp/notes.json';
        file_put_contents($fileName, json_encode($noteData, JSON_PRETTY_PRINT));
        return array("download" => $this->downloadFile($fileName));
    }

    public function getKeys()
    {
        $keys = array();
        $res = $this->dbConnection->query("SELECT key FROM notes;");
        foreach ($res as $idx => $key) {
            array_push($keys, $key['key']);
        }
        return array("keys" => $keys);
    }
}
