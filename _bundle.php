<?php

namespace Bundles\Twilio;
use Exception;
use e;

class Bundle {

	public $type;

	private static $url_vars;

	public function _on_framework_loaded() {
		$twilio_settings = array(
			'voice' => e::$environment->requireVar('twilio.voice', 'woman | man'),
			'language' => e::$environment->requireVar('twilio.language', 'en | en-gb | es | fr | de')
		);

		e::configure('lhtml')->activeAddKey('hook', ':twilio', $twilio_settings);
	}

	public function _on_portal_route($path, $dir) {
		$this->route($path, array($dir));
	}

	public function _on_portal_exception($path, $dir, $exception) {
		$this->exception($path, array($dir), $exception);
	}
	
	public function _on_router_exception($path, $exception) {
		$this->exception($path, array(e\site), $exception);
	}

	public function exception($path, $dirs, $exception) {
		if($this->type)
			$type = $this->type;
		else $type = 'phone';

		if($type == 'phone')
			$ret = 'Say';
		else if($type == 'sms') 
			$ret = 'Sms';

		echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
		echo "<Response>";
			echo "<$ret>A server error was encountered. Support has been notified. Please try again later. Thank You, Good Bye.</$ret>";
		echo "</Response>";
		e\Complete();
	}
	
	public function route($path, $dirs = null) {
		
		// If dirs are not specified, use defaults
		if(is_null($dirs))
			$dirs = e::configure('twilio')->locations;
		
		// Make sure path contains valid controller name
		if(!isset($path[0]) || $path[0] == '')
			$path = array('index');
		
		// Get the lhtml name
		$name = strtolower(implode('/', $path));
		
		e\Trace(__CLASS__, "Looking for $name.twiml");
		
		// Check all dirs for a matching lhtml
		foreach($dirs as $dir) {
			// Look in lhtml folder
			if(basename($dir) !== 'twiml')
				$dir .= '/twiml';
			
			// Skip if missing
			if(!is_dir($dir))
				continue;
			
			$matched = false;	$vars = array();	$nodir = false; $badmatch = false;
			$p = 1;
			foreach($path as $key => $segment) {
	 			if($matched == 'file') $vars[] = $segment;
	 			if((!$matched || $matched == 'dir') && is_dir("$dir/$segment")) {
					$dir .= "/$segment";
					$matched = 'dir';
				}
				elseif(is_file("$dir/$segment.twiml")) {
					$file = "$dir/$segment.twiml";
					$matched = 'file';
				}
				elseif($matched != 'file') {
					$badmatch = true;
				}
			}
			
			if($matched != 'file' && is_file("$dir/index.twiml")) {
				$file = "$dir/index.twiml";
				$matched = 'index';
			}

			# no match at all, just continue
			if($matched == false) continue;
			
			# set the url vars to use
			self::$url_vars = $vars;

			/**
			 * Parse the TWIML file
			 * @author Nate Ferrero
			 */
			$start = microtime(true);
			$out = e::$lhtml->file($file)->parse(null, true)->build();
			$end = microtime(true);
			$time = ($end - $start) * 1000;

			// Show debug time if set
			if(isset($_GET['--twiml-time'])) {
				

				// $file $time
				eval(d);
			}

			/**
			 * Output the header
			 * @author Nate Ferrero
			 */
			header('Content-Type: application/xml; charset=utf-8');
			e\Disable_Trace();

			/**
			 * HACK
			 * Since double quotes aren't parsed correctly (and &doesnt; work in some cases)
			 * @author Nate Ferrero
			 * @todo Find a better solution for this!
			 */
			$out = str_replace(array('-#-'), array('&quot;'), $out);
			echo $out;

			// Complete the page load
			e\Complete();
		}
	}

}