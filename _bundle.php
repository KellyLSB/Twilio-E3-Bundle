<?php

namespace Bundles\Twilio;
use Exception;
use e;

class Bundle {

	private $type;
	private $cache;

	public function _on_framework_loaded() {
		$twilio_settings = array(
			'voice' => e::$environment->requireVar('twilio.voice', 'woman | man'),
			'language' => e::$environment->requireVar('twilio.language', 'en | en-gb | es | fr | de')
		);

		e::configure('lhtml')->activeAddKey('hook', ':twilio', $twilio_settings);
	}

	public function _on_portal_route($path, $dir) {
		//if(strpos($_SERVER['HTTP_USER_AGENT'], 'TwilioProxy') === 0) {
			/**
			 * Determine if were handling a call or a sms
			 */
			$post = e::$resource->post;
			if(isset($post['CallStatus']))
				$this->type = 'phone';
			else if(isset($post['SmsStatus']))
				$this->type = 'sms';

			/**
			 * Check if the cache directory exists and is writable
			 */
			if(!is_dir($cacheDir = __DIR__.'/cache'))
				throw new Exception('Twilio: The directory '.$cacheDir.' does not exist.');
			if(!is_writable($cacheDir))
				throw new Exception('Twilio: The directory '.$cacheDir.' is not writable.');

			/**
			 * Get the cache filename
			 */
			$cacheFile = $cacheDir.'/'.preg_replace("/\D/", "", $post['From']).'_'.substr(md5($_SERVER['HTTP_HOST']), 0, 6).'.twl';

			/**
			 * Load cache is it already exists
			 */
			if(is_file($cacheFile) && filemtime($cacheFile) > (time() - 3600))
				$this->cache = unserialize(e::$encryption->decrypt(base64_decode(file_get_contents($cacheFile)), $post['From']));
			else $this->cache = array();

			if(!is_array($this->cache))
				$this->cache = array();

			if(!empty($this->cache))
				dump($this->cache);

			/**
			 * Save all posted data to the cache
			 */
			if(empty($this->cache))
				$this->cache = $post;
			else $this->cache = e\array_merge_recursive_simple($this->cache, $post);
			$data = base64_encode(e::$encryption->encrypt(serialize($this->cache), $post['From']));
			file_put_contents($cacheFile, $data);

			/**
			 * Start routing the request
			 */
			$this->route($path, array($dir));

			/**
			 * If we didnt complete since we know we are twilio throw an exception
			 */
			throw new Exception('The page Twilio requested did not exist.');
		//}
	}

	public function _on_portal_exception($path, $dir, $exception) {
		$this->exception($path, array($dir), $exception);
	}
	
	public function _on_router_exception($path, $exception) {
		$this->exception($path, array(e\site), $exception);
	}

	public function exception($path, $dirs, $exception) {

		/**
		 * Determine if were dealing with phone or sms
		 */
		if($this->type)
			$type = $this->type;
		else $type = 'phone';

		/**
		 * If its a phone speak the notification else SMS it
		 */
		if($type == 'phone')
			$ret = 'Say';
		else if($type == 'sms') 
			$ret = 'Sms';

		/**
		 * Output the error
		 */
		header('Content-Type: application/xml; charset=utf-8');
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
		echo "<Response>";
		echo "<$ret>A server error was encountered. Support has been notified. Please try again later. Thank You, Good Bye.</$ret>";
		echo "</Response>";
		e\Disable_Trace();
		e\Complete();
	}
	
	public function route($path, $dirs = null) {
		
		if(is_null($dirs))
			$dirs = e::configure('twilio')->locations;

		/**
		 * If were looking for the root file
		 */
		if(!isset($path[0]) || $path[0] == '')
			$path = array('index');
		
		/**
		 * Get the file name
		 */
		$name = strtolower(implode('/', $path));
		
		/**
		 * Log File
		 */
		e\Trace(__CLASS__, "Twilio: Looking for $name.twiml");

		/**
		 * Find the file
		 */
		foreach($dirs as $dir) {

			for($i=0;$i<2;$i++) {
				if(is_file($file = $dir.'/'.$name.'.twiml'))
					break;
				
				unset($file);
				if(array_pop(explode('/', $name)) === 'index')
					break;

				$name = $name.'/index';
			}

			if(!isset($file))
				continue;
			
			/**
			 * Be Awesome and Parse in LHTML
			 */
			$stack = e::$lhtml->file($file)->parse();
			header('Content-Type: application/xml; charset=utf-8');
			echo $stack->build();
			e\Disable_Trace();
			e\Complete();
		}
		
	}

}