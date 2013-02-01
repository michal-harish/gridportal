<?php
class http {
	
	private $url;
	private $proxy;
	private $timeout = 7;
	private $process;
	
	//response
	public $status;	
	public $headers;
	public $body;
	
	public function __construct($url, $proxy = null, $timeout = 7) {
		$this->url = $url;
		$this->timeout = $timeout;
	}
	
	public function GET($uri, array $headers) {
		if(!$this->process = curl_init($this->url.$uri)) {
			throw new exception("HTTP connection to {$this->url}{$uri} failed.");
		}
		$headers ['Expect'] = '';
		$curl_headers = array(); foreach($headers as $name=>$value) $curl_headers[] = "$name: ".$value;
		//log::info($curl_headers ,'request');  	
		curl_setopt($this->process, CURLOPT_HTTPHEADER, $curl_headers);
		curl_setopt($this->process, CURLOPT_TIMEOUT, $this->timeout);
		if ($this->proxy) curl_setopt($this->process, CURLOPT_PROXY, $this->proxy);
		curl_setopt($this->process, CURLOPT_HEADER, 1); // include response headers 	
		curl_setopt($this->process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->process, CURLOPT_FOLLOWLOCATION, 0); // redirects cannot be automatic for protlet because special headers need to be added
		$return = curl_exec($this->process);
		//curl_close($this->process);
		$this->process_response($return);
		return $this->status;					
	}
	
	public function POST($uri, array $headers, $entity, array $files = array()) {
		if($files) {			
			foreach($files as $i => $file) {
				if (is_numeric($i)) $entity[basename($file)] = "@$file";
				else ;//TODO when files are attached directly not via filename
			}
		}
		$this->process = curl_init($this->url.$uri);
		$headers ['Expect'] = '';
		$curl_headers = array(); foreach($headers as $name=>$value) $curl_headers[] = "$name: ".$value;
		//log::info($curl_headers ,'request');  	
		curl_setopt($this->process, CURLOPT_HTTPHEADER, $curl_headers);
		curl_setopt($this->process, CURLOPT_POST, 1);
		curl_setopt($this->process, CURLOPT_POSTFIELDS, $entity);
		//curl_setopt($this->process,CURLOPT_ENCODING , 'gzip');
		curl_setopt($this->process, CURLOPT_TIMEOUT, $this->timeout);
		if ($this->proxy) curl_setopt($this->process, CURLOPT_PROXY, $this->proxy);
		curl_setopt($this->process, CURLOPT_HEADER, 1); // include response headers 	
		curl_setopt($this->process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->process, CURLOPT_FOLLOWLOCATION, 0); // redirects cannot be automatic for protlet because special headers need to be added
		$return = curl_exec($this->process);					
		$this->process_response($return);
		return $this->status;			
	}
	
	private function process_response($response) {
		if (curl_errno($this->process)>0) throw new exception(curl_errno($this->process));
		else curl_close($this->process);
		$this->status = 0;
		$this->headers = null;
		$this->body = null;
		$r = preg_split('/\r?\n\r?\n/',$response,2);		
		$headers = current($r);
		$this->body = next($r);
		if (!preg_match('/^HTTP\/[0-9]{1}\.[0-9]{1} ([0-9]+) (.*)[\r\n]+/i',$headers ,$rs)) throw new exception($headers);
		$this->status = $rs[1];		
		if (!preg_match_all('/(([a-z_-]+)\: ([^\r\n]+)[\r\n]+)/i',$headers ,$rh )) throw new exception('Invalid response headers');		
		foreach($rh[2] as $i=>&$header) $this->headers[strtolower(trim($header))] = $rh[3][$i];
	}
		
	static public function get_header_fields($header_value, $separator = ',') {	
		$result = array();		
		$fields = explode($separator,$header_value);			
		while(count($fields)>0) {
			$field = explode("=",array_shift($fields));
			$result [trim($field[0])] = isset($field[1]) ? $field[1] : true;		
		}
		return $result ;
	}	
	
	static public function query_string(array $params) {
		$query_string = '';
		foreach($params as $name=>$value) 
			$query_string .= ($query_string ? '&' : '?') . "$name". ($value ? "=".urlencode($value) : "");
		return $query_string;
	}
	
	
}