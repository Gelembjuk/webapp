<?php

namespace Gelembjuk\WebApp\View;

abstract class Display {
	protected $options;
	protected $data;
	protected $application;
	protected $deepCacheKey = '';
	
	public function __construct($application = null) {
		$this->application = $application;
	}
	public function init($options) {
		return true;
	}
	public function setData($data) {
		return true;
	}
	protected function cacheData($data, $expiration = 3600)
	{
        if (empty($this->deepCacheKey)) {
            return ;
        }
        $cacheItem = $this->application->cachePool()->getItem($this->deepCacheKey);
        $cacheItem->set($data);
		$cacheItem->expiresAfter($expiration);
		$this->application->cachePool()->save($cacheItem);
	}
	public function requireSettings() {
		if (!is_array($this->data)) {
			throw new \Exception('No data to display provided');
		}
		if (!is_array($this->options)) {
			throw new \Exception('No display options provided');
		}
		return true;
	}
	abstract public function display();
}
