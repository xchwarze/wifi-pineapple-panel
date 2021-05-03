<?php namespace pineapple;

abstract class APIModule
{
    protected $request;
    protected $response;
    protected $error;

    abstract public function route();

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function getResponse()
    {
        if (empty($this->error) && !empty($this->response)) {
            return $this->response;
        } elseif (empty($this->error) && empty($this->response)) {
            return array('error' => 'API returned empty response');
        } else {
            return array('error' => $this->error);
        }
    }
}
