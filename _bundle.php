<?php

namespace Bundles\Twilio;
use Exception;
use e;

class Bundle {

	private $type;
	private $cache;

	private $to;
	private $from;

	private $cacheFile;

	/**
	 * Twilio API Version
	 */
	private $ApiVersion = "2010-04-01"; 

	private $AccountSid = "account-id";
	private $AuthToken = "auth-token";

	public function _on_framework_loaded() {
		/**
		 * Load Twilio Settings
		 */
		//$this->AccountSid = e::$environment->requireVar('twilio.AccountSid');
		//$this->AuthToken = e::$environment->requireVar('twilio.AuthToken');

		$twilio_settings = array(
			'voice' => e::$environment->requireVar('twilio.voice', 'woman | man'),
			'language' => e::$environment->requireVar('twilio.language', 'en | en-gb | es | fr | de')
		);

		/**
		 * Give Twilio Settings an LHTML Hook
		 */
		e::configure('lhtml')->activeAddKey('hook', ':twilio', $twilio_settings);

		/**
		 * Set Class Locations
		 */
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\TwilioRestResponse', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\TwilioException', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\TwilioRestClient', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Verb', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Response', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Say', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Reject', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Play', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Record', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Dial', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Redirect', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Pause', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Hangup', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Gather', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Number', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Conference', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\Sms', __DIR__ . '/library/twilio.php');
		e::configure('autoload')->activeAddKey('special', 'Bundles\\Twilio\\Lib\\TwilioUtils', __DIR__ . '/library/twilio.php');
	}

	/**
	 * Run on every twilio page
	 */
	public function twilInit() {

		/**
		 * Determine if were handling a call or a sms
		 */
		$post = e::$resource->post;

		/*if(!isset($post['From']))
			$post['From'] = '12485222452';
		if(!isset($post['CallStatus']) && !isset($post['SmsStatus']))
			$post['CallStatus'] = '12485222452';*/

		$this->from = $post['From'];
		$this->to = $post['To'];

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

		if(!empty($this->cache));
			//dump($this->cache);

		/**
		 * Get Member
		 */
		if(empty($this->cache['Member'])) {
			if($this->type === 'phone')
				$member = array_shift(e::$events->twilio_call($post['From']));
			else if($this->type === 'sms')
				$member = array_shift(e::$events->twilio_sms($post['From']));

			/**
			 * Save member to cache
			 */
			if(isset($member) && method_exists($member, '__toArray'))
				$this->cache['Member'] = $member->__toArray();

		}

		/**
		 * Save all posted data to the cache
		 */
		if(empty($this->cache))
			$this->cache = $post;
		else $this->cache = e\array_merge_recursive_simple($this->cache, $post);
		$data = base64_encode(e::$encryption->encrypt(serialize($this->cache), $post['From']));
		file_put_contents($cacheFile, $data);

		/**
		 * Set to a LHTML Hook
		 */
		if($member) e::configure('lhtml')->activeAddKey('hook', ':member', $member);
		e::configure('lhtml')->activeAddKey('hook', ':twilcache', $this->cache);

		/**
		 * Set the cache file to the class
		 */
		$this->cacheFile = $cacheFile;

		return $this->cache;
	}

	public function saveCache($var, $val = '') {
		if(!is_array($this->cache)) return false;

		if(is_array($var))
			$this->cache = e\array_merge_recursive_simple($this->cache, $var);
		else $this->cache[$var] = $val;

		/**
		 * Save all posted data to the cache
		 */
		$data = base64_encode(e::$encryption->encrypt(serialize($this->cache), $this->from));
		file_put_contents($this->cacheFile, $data);

		return true;
	}

	public function clearCache() {
		if(!is_array($this->cache)) return false;

		unlink($this->cacheFile);
		return true;
	}

	public function _on_portal_route($path, $dir) {
		if(strpos($_SERVER['HTTP_USER_AGENT'], 'TwilioProxy') === 0) {
			try {
				$this->twilInit();
			}
			catch(Exception $e) {
				return false;
			}

			/**
			 * Start routing the request
			 */
			$this->route($path, array($dir));

			/**
			 * If we didnt complete since we know we are twilio throw an exception
			 */
			throw new Exception('The page Twilio requested did not exist.');
		}
	}

	/*public function _on_portal_exception($path, $dir, $exception) {
		$this->exception($path, array($dir), $exception);
	}
	
	public function _on_router_exception($path, $exception) {
		$this->exception($path, array(e\site), $exception);
	}

	public function exception($path, $dirs, $exception) {

		/**
		 * Determine if were dealing with phone or sms
		 *
		if($this->type)
			$type = $this->type;
		else $type = 'phone';

		/**
		 * If its a phone speak the notification else SMS it
		 *
		if($type == 'phone')
			$ret = 'Say';
		else if($type == 'sms') 
			$ret = 'Sms';

		/**
		 * Output the error
		 *
		header('Content-Type: application/xml; charset=utf-8');
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
		echo "<Response>";
		echo "<$ret>A server error was encountered. Support has been notified. Please try again later. Thank You, Good Bye.</$ret>";
		echo "</Response>";
		e\Disable_Trace();
		e\Complete();
	}*/
	
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
			$dir = $dir.'/twiml';

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

	public function _on_sendSMS($from = '', $to = '', $body = 'No Content') {
		/**
		 * Prepare Api
		 */
		$client = new Lib\TwilioRestClient($this->AccountSid, $this->AuthToken);

		/**
		 * Twilio Message Data
		 */
		$data = array(
			'From'	=> $from,	// Outgoing Caller ID
			'To'	=> $to,		// The phone number you wish to dial
			'Body'	=> $body
		);

		/**
		 * Create request and send to twilio
		 */
		$response = $client->request("/$this->ApiVersion/Accounts/$this->AccountSid/SMS/Messages", "POST", $data); 

		if($response->IsError)
			throw new Exception("Error sending message: {$response->ErrorMessage}");
		else e::$events->message(array('type' => 'success', 'message' => "Message sent: {$response->ResponseXml->SMSMessage->Sid}"));
		
		/**
		 * Return the message id
		 */
		return $response->ResponseXml->SMSMessage->Sid;
	}

	public function _on_sendPhoneCall($from = '', $to = '', $url = false) {
		if(!$url) $url = 'http://'.$_SERVER['HTTP_HOST'].'/twiml';

		/**
		 * Prepare Api
		 */
		$client = new Lib\TwilioRestClient($this->AccountSid, $this->AuthToken);

		/**
		 * Twilio Message Data
		 */
		$data = array(
			'From'	=> $from,	// Outgoing Caller ID
			'To'	=> $to,		// The phone number you wish to dial
			'Url'	=> $url
		);

		/**
		 * Create request and send to twilio
		 */
		$response = $client->request("/$this->ApiVersion/Accounts/$this->AccountSid/Calls", "POST", $data); 

		if($response->IsError)
			throw new Exception("Error connecting call: {$response->ErrorMessage}");
		else e::$events->message(array('type' => 'success', 'message' => "Call connected: {$response->ResponseXml->SMSMessage->Sid}"));
		
		/**
		 * Return the call id
		 */
		return $response->ResponseXml->SMSMessage->Sid;
	}

}
