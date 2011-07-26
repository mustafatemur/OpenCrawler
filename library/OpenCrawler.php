<?php

class OpenCrawler extends Zend_Controller_Plugin_Abstract
{
	function __construct()
	{
		echo "Hello World!";
	}
	
	public function loadUrl($url)
	{
		
	}
	
	public function parseRobots($url)
	{
		
	}
	
	public function parseHeaders($url)
	{
		
	}
	
	public function loadContent($url)
	{
		
	}
	
	public function loadNext()
	{
		
	}
	
	public function loadLinks(&$DOMDocument)
	{
		
	}
	
	public function pushLink($url)
	{
		
	}
	
	public function crawlerAccess($url)
	{
		
	}
	
	/**************************************************************
	These functions aim to implement a relative URL resolver
	according to the RFC 1808 specification.
	Copyright (C) 2007 David Doran (http://www.daviddoranmedia.com/)
	****************************************************************/
	function absoluteUrl($base, $path = null)
	{
		
	}
}