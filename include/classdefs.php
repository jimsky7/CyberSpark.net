<?php
	/**
		CyberSpark.net monitoring-alerting system
		classes
	*/


	class filter
	{
		public $rank = 0;
		public $name = null;		// name of the filter
		public $scan  = null;		// function to call for regular scan
		public $notify = null;		// ...for daily 'notify' scan
		public $init  = null;		// ...to initialize the filter
		public $destroy = null;		// ...when filter is being destroyed
	}

	class url
	{
		public $url = null;
		public $conditions = null;
		public $emails = null;
		public $conditionsArray = null;
	}

?>