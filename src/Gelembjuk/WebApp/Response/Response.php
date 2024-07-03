<?php

namespace Gelembjuk\WebApp\Response;

class Response  {
    const SUCCESS = 'success';
    const VIEW = 'view';
    const REDIRECT = 'redirect';

    protected $kind;
    protected $url;
    protected $responseformat;
    protected $message = null;

    public function __construct($kind, $url = '', $responseformat = 'html') 
    {
        $this->kind = $kind;
        $this->url = $url;
        $this->responseformat = $responseformat;
    }
    public function withMessage($message) 
    {
        $this->message = $message;
        return $this;
    }
    public function withUrl($url) 
    {
        $this->url = $url;
        return $this;
    }
    public function getMessage() 
    {
        return $this->message;
    }
    public function getUrl() 
    {
        return $this->url;
    }
    public function isRedirect($responseformat) 
    {
        if($this->kind == self::REDIRECT) {
            return true;
        }
        if ($responseformat == 'html' && $this->kind == self::SUCCESS) {
            return true;
        }
        return false;
    }
    public function isView($responseformat) 
    {
        if($this->kind == self::VIEW) {
            return true;
        }
        if ($responseformat != 'html' && $this->kind == self::SUCCESS) {
            return true;
        }
        return false;
    }
    public function getActionInfo()
    {
        return [$this->kind, $this->url ,$this->responseformat, $this->message];
    }
}
