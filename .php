<?php 

require 'http.php';

//first handler is for proxying cross-domain 
if (isset($_SERVER['HTTP_X_DOMAIN_REQUEST'])) {
	try {
		$xhttp = new http(urldecode($_SERVER['HTTP_X_DOMAIN_REQUEST']),null,600); 
		set_time_limit(700);
		$r = array(
			'Portlet-ajax'=>1,
			'Portlet-fragments'=>'default'
		);
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) $r['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
		if (isset($_SERVER['HTTP_PORTLET_FRAGMENTS'])) $r['Portlet-fragments'] = $_SERVER['HTTP_PORTLET_FRAGMENTS'];
		$status = $xhttp->GET("",$r);
		header("HTTP/1.1 ".$status);
		foreach($xhttp->headers as $header=>$value) {	
			if ($header=="content-type") $value="text/xml";
			header("$header: $value");
		}
		
		$doc = new DOMDocument();
		@$doc->loadHTML($xhttp->body);
		$x = new DOMXPath($doc);
		$fragments = $x->query("//*[@id='".$r['Portlet-fragments']."']");
		if ($fragments->length ==0 ) {
			//log::error($xhttp->body,'x-domain-request'); // this requires js firephp enabled
			echo $xhttp->body;
		} else foreach($fragments as $f) {
			echo $doc->saveXML($f);
		}		
	} catch(Exception $e) {
		if ($e->getMessage()==28) { // timeout
			header("HTTP/1.1 304 Not Modified");
		} else {
			header("HTTP/1.1 502 Proxy Error");
		}		
	}
	exit();
}

session_start(); 
require 'log/log.php'; 


global $gridportal_debug_mode;
$gridportal_debug_mode = isset($_COOKIE['$debug']); 
if(@preg_match('/\sFirePHP\/([\.|\d]*)\s?/si',$_SERVER['HTTP_USER_AGENT']))  
{ 
	log::enableFirePhp(true);	
	log::info(session_get_cookie_params() ,'session_get_cookie_params() ');
	log::info(session_cache_expire() ,'session_cache_expire()');
	
} 

function session_benchmark($datetime) {
	
	if (is_string($datetime)) $datetime = strtotime($datetime); 	
	$session_file = session_save_path().'/sess_'.session_id();
	if (file_exists($session_file) && filemtime($session_file) < $datetime ) {
		session_destroy();
		session_start();
		log::warn("session benchmark cleared");
	}
}


ob_start('gridportal_main');

function gridportal_main($output) {
	try {		
		$portlets = array();										
		$doc = new DOMDocument;
		@$doc->loadHTML($output);
		$x = new DOMXPath($doc);
		
		log::info(
			'http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']
			. ( isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '')
			.'?'.$_SERVER['QUERY_STRING']		
		,$_SERVER['REQUEST_URI']);
		
		$input = array();
		if ($_GET ) $input = array_merge($_GET,$input,array());
		if ($_POST ) $input = array_merge($_POST,$input,array());
		//log::info(implode(",",array_keys($input)),"INPUT");		
		
		//portlet declared portals will be referenced from body by id 
		foreach($x->query("head/portlet[@id]") as $pid) {
			$id = $pid->getAttribute("id");					
			if (!$pid->hasAttribute("url")) throw new exception("Invalid portled delcaration `$id` in <head> - missing url attribute");			
			if (isset($portlets[$id])) throw new exception("Duplicate portled declaration `$id` found in <head>");
			$portlets[$id]['url'] = $pid->getAttribute("url");
			$portlets[$id]['id'] = $id;
			$portlets[$id]['input'] = $input;
			foreach($x->query("param",$pid) as $param) {
				$param_value = null; //for checking required at the end of the loop
				$param_name = ($param->hasAttribute("name") ? $param->getAttribute("name") : null);
				if ($param->hasAttribute("get")) {
					$input_param =$param->getAttribute("get");					
					if (!$param_name) $param_name = $input_param;					
					if (isset($input[$input_param])) {						
						$param_value = $input[$input_param];
						unset($portlets[$id]['input'][$input_param]);
						$portlets[$id]['config'][$param_name] = $param_value;	
						//$portlets[$id]['mapped'][$param_name] = $param_value;
						if ($param->hasAttribute("required")) {
							$portlets[$id]['required'][$input_param] = $param_name;
						}	
					}
					
				} elseif ($param->hasAttribute("cookie")) {
					$cookie_param =$param->getAttribute("cookie");
					$param_value = $_COOKIE[$cookie_param];								
					if (!$param_name) $param_name = $cookie_param ;
					$portlets[$id]['config'][$param_name] = &$_COOKIE[$cookie_param];  
					
				} elseif ($param->hasAttribute("value")) { // page-specific configuration param					
					$param_value = $param->getAttribute("value");				 
					if (!$param->hasAttribute("name")) {
						log::warn("No name given to configuration param in protlet `$id` , value '$param_value'");
						continue;							
					}
					$portlets[$id]['config'][$param->getAttribute("name")] = $param_value;					
				} else {
					log::warn("Invalid param declaration in protlet `$id`");
				}
				//
				if ($param->hasAttribute("required") && $param_value === null) {
					$portlets[$id]['error'] = "Portlet `$id` expects url query param `$input_param`";
					log::warn("Portlet `$id` expects url query param `$input_param`");												
				} 
			}
			//map all params
			$portlets[$id]['mapped'] = $portlets[$id]['config'];			
			foreach($portlets[$id]['required'] as $rk => $rc) {
				$portlets[$id]['mapped'][$rk] = $portlets[$id]['mapped'][$rc];
				unset($portlets[$id]['mapped'][$rc]);
			}
			$pid->parentNode->removeChild($pid);
		}	

		// prepare portal params
		$portal_params = array();
		foreach($_GET as $k=>$v) {
			$found = false;
			foreach($portlets as &$portlet) {
				if (array_key_exists($k, $portlet['mapped'])) {
					$found = true;
					unset($portlet);
					break;
				}
				unset($portlet);
			}
			if (!$found) $portal_params[$k] = $v;
		}
		//now unset input that doesn't belong to any portlet
		foreach($portal_params as $k=>$v) {
			unset($input[$k]);
			foreach($portlets as $id=>&$portlet) {			
				unset($portlet['input'][$k]);
				unset($portlet);
			}
			unset($portlet);
		}
		
		//portlet that are not declared in the head must have explicit url attribute
		$i= 1;
		$ids= array();
		foreach($x->query("body//portlet[@url]") as $p) {			
			$url = $p->getAttribute("url");
			$p->removeAttribute("url");
			if ($p->hasAttribute("id")) {
				$id = $p->getAttribute("id"); 
			} else {				
				$id = "portlet0{$i}";
				$i++;
			}
			if (isset($ids[$url])) {
				$id = $ids[$url];
			} else {
				$ids[$url] = $id;
			}
			$p->setAttribute("id",$id);		
			$portlets[$id]['url'] = $url;	
			$portlets[$id]['id'] = $id;		
			if (!isset($portlets[$id]['input'])) $portlets[$id]['input'] = $input;			 
		}
		
		$forms_register = &$_SESSION['forms'][$_SERVER['SCRIPT_NAME']];
				
		//used portals
		$ps = $x->query("body//portlet[@id]");
		if ($ps->length>0) {
		
			//collect required fragments		
			foreach($ps as $p) {
				$id = $p->getAttribute("id") ;
				//$fragment = ($p->hasAttribute("fragment") ? $p->getAttribute("fragment") : "default" );
				if (!$p->hasAttribute("fragment") ) {
					log::error('Portlet placement without fragment reference',"Portlet `$id`");
					continue;
				}								
				$fragment = $p->getAttribute("fragment");				 
				$portlets[$id]['fragments'][$fragment] = null;				
			}
						
			//fire action portal requests
			if ($input) {	
				$input_hash = 0 ; foreach(array_keys($input) as $key) $input_hash += crc32($key);
				$input_hash = (string) $input_hash;

				//first look for unique input patterns
				if (array_key_exists($input_hash,$forms_register)) {
					$portlet_id = $forms_register[$input_hash];
					if (!isset($portlets[$portlet_id])) 
						log::error('Expired page');						
					else {										
						$portlets[$portlet_id]['action'] = $portlets[$portlet_id]['input'];						
						prepare_portlet_request($portlets[$portlet_id]);														
					}							
				} elseif (isset($input['action'])) { // then for fall-back option form was tagged by action
					foreach($portlets as $id=>&$portlet) {								
						if ($id==$input['action']) {						
							unset($portlet['input']['action']);
							$portlet['action'] = $portlet['input'];						
							prepare_portlet_request($portlet);	
							unset($portlet);						 
							break; // only one portlet is the target of action																		
						}				
						unset($portlet);
					}
				} else { // form has a unique input hash	
					log::warn(count($forms_register)." forms present",'no form matches the input hash "'.implode(",",array_keys($input)).'"');
				}
				
				fetch_portlets($portlets);
			}				
			
			//fetch all portal render requests that has not been run in the action 
			foreach($portlets as $id=>&$portlet) {				
				prepare_portlet_request($portlet);	
				unset($portlet);
			}
			fetch_portlets($portlets);
			
			if ($_POST) {
				global $gridportal_debug_mode;
				if (!$gridportal_debug_mode ) {
					header("HTTP/1.1 302 Found");
					header("Location: ".$_SERVER['REQUEST_URI']);
					return;
				} else {
					log::warn("debug mode on","redirect-after-post disabled");
				}
			}			
			
			//initialize and validate all required fragments 
			foreach($ps as $p) {								
				$id = $p->getAttribute("id");
				if (isset($portlets[$id]['response'])) {
					try {
						$response = $portlets[$id]['response'];
						unset($portlets[$id]['response']);
						$tmp = new DOMDocument;	
						if (!@$tmp->loadHTML($response)) {
							unset($portlets[$id]['response']);
							throw new exception("Invalid text/html-fragments mark up in `$id`");
						} else {
										
							$tmpx = new DOMXPath($tmp);		
							$body = $tmpx->query("//body");							
							if ($body->length==0) throw new exception("Body element not found in the portlet `$id`.");
							elseif ($body->length>1) throw new exception("Body element occures more than once in the portlet `$id`.");																																	
							else $body=$body->item(0);

							//look only for placed fragments
							foreach($portlets[$id]['fragments'] as $f=>$notused) {							 	
							 	$cs = $tmpx->query("//*[@id='$f']");
							 	if ($cs->length==0) {
							 		$portlets[$id]['error'] ='Portlent fragment unavailable';
							 		log::error("fragmetns with id `$f` not found","Portlet `$id`");
							 		continue;
							 	} elseif ($cs->length>1) {
							 		$portlets[$id]['error'] ='Portlent fragment ambiguous';
							 		log::error("multiple fragmetns with id `$f`","Portlet `$id`");
							 		continue;
							 	} else {
							 		$c = $cs->item(0);	
							 	}

							 	//initialize classes
							 	$c->setAttribute("class", $id.' fragment' . ($c->hasAttribute("class") ? " ".$c->getAttribute("class") : "" ). ($p->hasAttribute("class") ? " ".$p->getAttribute("class") : "" ) );
								
								//validate links 								
								foreach($tmpx->query('.//*[@href]|.//*[@src]|.//*[@action]',$c) as $link) {
									if ($link->hasAttribute("href")) $attr="href";  
									elseif ($link->hasAttribute("src")) $attr="src"; 
									elseif ($link->hasAttribute("action")) $attr="action";									
									$href = $link->getAttribute($attr);
		
									//expand abolute uri to absolute url								
									if (preg_match('/^\//',$href)) {										
										$href=$portlets[$id]['base'].$href;
										log::warn("=> ".$href,"portlet `$id` contains root link: ".$link->getAttribute($attr));
									}
									//further process if link remains relative
									if (!preg_match('/^(\/|https?\:\/\/)/i',$href)) { //|\#|\?
										//root relative uri
										if (preg_match('/^([^\?\#]+)/i',$href,$nu) || $href=='') {
											$href= '/'.$href;
										}
										//extend query string with required params
										if (preg_match_all('/([^\?]*)\?(&?([^=]+)((\=([^\&]*))?))+/i',$href,$nv)) {										
											$params = array();
											foreach($portlets[$id]['required'] as $k=>$kc) {
												$params[$k] = $portlets[$id]['config'][$kc];
											}											
											foreach($nv[3] as $i=>$name) $params[urldecode($name)] = urldecode($nv[6][$i]);
											//$other_params = $portal_params;
											$other_params = $_GET;											
											foreach($portlets[$id]['mapped'] as $mk=>$mv) {
												unset($other_params[$mk]);
											}
											$href =$nv[1][0].http::query_string(array_merge($other_params,$params));										
										}
										//log::warn("=> ".$href,"portlet `$id` contains relative link: ".$link->getAttribute($attr));										
									} 									
									$link->setAttribute($attr,$href);
								}
								foreach($tmpx->query('.//*[@style]',$c) as $e) {
									//log::warn("<{$e->nodeName} style=\"{$e->getAttribute("style")}\">","removed inline style from fragment `$id/$f` mark-up");
									$e->removeAttribute("style");
								}
								$portlets[$id]['fragments'][$f] = $c;																				
							}		
						}
						//log::info(implode(', ',array_keys($portlets[$id]['fragments'])),"`$id` init fragments");
					} catch( Exception $e) {						
						log::error($e->getMessage());
						unset($_SESSION['cache'][$portlets[$id]['unid']]);	
						//TODO create function that drops the whole portlet's cache and flags it as unavailable for timeout controlable by portlet header or default					
						$portlets[$id]['fragments']['default'] = 'Error processing portlet request';
						$portlets[$id]['error'] = 'Portlet unavailable';
					}
				}
			}
			
			//insert fragments into the document and register actions 
			$forms_register = array();
			$attachScripts = false;
			foreach($ps as $p) {
				$fragment = $p->getAttribute("fragment");
				$id = $p->getAttribute("id");				
				$info = '';				
				try {
					$new = null;			
					if (isset($portlets[$id]['error'])) {						
						$new = $doc->createElement('div',$portlets[$id]['error']);
						$new->setAttribute("class","error");
						
					} elseif (isset($portlets[$id]['fragments'][$fragment])) {
						$new = $doc->importNode($portlets[$id]['fragments'][$fragment],true);
					} else {
						log::warn("Fragment `$id`/`$fragment` unavailable.");	
						continue;
					}						
					if ($new) {
						$new = $p->parentNode->insertBefore($new,$p);											
						//create ajax observer 					
						if (preg_match('/ajax/',$new->getAttribute("class"))) {
							$maxage = isset($portlets[$id]['cache']['max-age']) ? $portlets[$id]['cache']['max-age'] : 1; // initial request is after 3 seconds							
							log::info("new observer($id,$fragment,{$portlets[$id]['cache']['last-modified']},$maxage)","Portlet `$id`");
							$attachScripts[] = "new observer(\"$id\",\"".$fragment."\",\"{$portlets[$id]['url']}\",\"{$portlets[$id]['cache']['last-modified']}\",\"$maxage\");";							
						}
						//detect and prepare forms
						$forms = array();						
						if ($new->nodeName == "form") $forms[] = $new;
						foreach($x->query(".//form",$new) as $form) $forms[] = $form;
						foreach($forms as $form) {
							$form_post = $form->hasAttribute("method") && preg_match('/post/i',$form->getAttribute("method"));
							if (!$form->hasAttribute("action") && $p->hasAttribute("target")) {
								$form->setAttribute("action",$p->getAttribute("target"));
							}
							$inputs = array();	
							//load form input fields 						  
							foreach($x->query(".//input[@name]|.//hidden[@name]|.//select[@name]|.//textarea[@name]",$form) as $input) {
								if ($input->hasAttribute("type") && preg_match('/checkbox/i',$input->getAttribute("type"))) continue;
								$inputs[strtolower($input->getAttribute("name"))] = true;
							}		
				
							if ($_GET) { // Merge form input with default query string !														
								foreach($_GET as $qn=>$qv) {
									if ($form_post ) { // form.method="post"
										log::warn("post with default get parameters","to be implemented");
										//$form->setAttribute("action","?$qn=$qv");
									} else { // form.method="get"
										if (!isset($inputs[$qn])) { 
											$inputs[$qn] = true;
											$hidden = $form->appendChild($doc->createElement("input"));
											$hidden->setAttribute("type","hidden");
											$hidden->setAttribute("name",$qn);
											$hidden->setAttribute("value",$qv);
										}
									}
								}	
							}
							$input_hash = 0 ; foreach(array_keys($inputs) as $key) $input_hash += crc32($key);
							$input_hash = (string) $input_hash;
							if ($forms_register[$input_hash]) {																
								$info.= " form $id / action $input_hash";							
								$hidden = $form->appendChild($doc->createElement("input"));
								$hidden->setAttribute("type","hidden");
								$hidden->setAttribute("name","action");
								$hidden->setAttribute("value",$id);
							} else { // form method = get
								$info .= ' form hash '.$input_hash;															
								$forms_register[$input_hash] = $id;								
							}
						}				
					}	
					
						
					//log::info($info,"RENDER FRAGMENT $id/$fragment");
				} catch(Exception $e) {
					log::error($e,"RENDER FRAGMENT ERROR $id/$fragment");
				}
				
				$p->parentNode->removeChild($p);
				
			}
		}
		
		//attach scirpts
		if ($attachScripts) {
			$js = file_get_contents(dirname(__FILE__)."/embedded.js");			
			foreach($attachScripts as $handler) {
				$js .= "\n $handler\n";
			}
			$script = $doc->createElement("script",$js);
			$x->query("//head")->item(0)->appendChild($script);
			log::info("SCRIPTS ATTACHED");
		}

		//return final html
		header("HTTP\1.1 200 OK");
		header("Content-type: text/html; encoding=UTF-8");
		log::timeInfo('total php run',0.1);
		return $doc->saveHTML();
		
	} catch (Exception $e) {
		header("HTTP\1.1 500 Internal Server Error");
		header("Content-type: text/plain; encoding=UTF-8");
		log::timeInfo('total php run',0.05);
		return $e->getMessage()."\n\n".$e->getTraceAsString();
	}
	 

	 
}


function prepare_portlet_request(array &$portlet) {
	 
	//$portlet['id']
	//TODO portal unavailability touchfiles
	
	if (!isset($portlet['fragments'])) return; //never used FIXME some portlets may run when registered uri appears	
	if (isset($portlet['headers'])) return; // already prepared or even run
		
	if ($portlet['url'][0]=='/') {
		$HOST = 'http://'.(isset($_SERVER['HTTP_X_REWRITE_HOST']) ? $_SERVER['HTTP_X_REWRITE_HOST'] : $_SERVER['HTTP_HOST']);
		$portlet['url'] = $HOST.$portlet['url'];
	}
	
	preg_match('/^(https?\:\/\/[^\/\?\#\&]+)/i',ltrim($portlet['url']),$base);
	$portlet['base'] = $base[1];
	$portlet['method'] = $_SERVER['REQUEST_METHOD'];	
	$portlet['unid'] = sha1($portlet['url']);	
	$cookies = '';		
	foreach($_COOKIE as $n=>$v) {				
		if (is_array($v)) {	
			//log::info($v,"PRIVATE COOKIE $n");
			if ($n == $portlet['unid']) { //my private cookies 
				foreach($v as $cookie=>$value) {
					$cookies .= ($cookies ? "; " : "") . urlencode($cookie). '='. urlencode($value);					
				}
			}
		} elseif (substr($n,0,1) == '$') { //shared cookie
			//log::info($v,"SHARED COOKIE $n");
			$cookies .= ($cookies ? "; " : "") . urlencode($n). '='. urlencode($v);	
		}
	}

	$portlet['headers'] = array(
		'User-Agent' => $_SERVER['HTTP_USER_AGENT'] .' GridPortal/0.1',
		'Accept' => 'text/html',
		'Cookie' => $cookies,
		'Portlet-fragments' => implode(',',array_keys($portlet['fragments'])),
	);			
			
	//assign cache id and if has valid cache
	// unid - USED - hash of the portlet url, not id since this can differ on pages
	// config - USED - e.g. configuration name-to-value params, cookie-to-params or url-to-param 
	// action - NOT USED - actions are not cached at all		
	$cache_entity = (isset($portlet['config']) ? json_encode($portlet['config']) : '.');
	//log::info($cache_entity,"PORTLET `{$portlet['id']}` cache entity");	
	$cache_entity = md5($cache_entity);
	$portlet['cache'] = &$_SESSION['cache'][$portlet['unid']][$cache_entity]; 
	if (!$portlet['action']) {		
		$cache_hash = md5($portlet['url'].':'.json_encode($portlet['headers']));
		if ($cache_hash != $portlet['cache']['hash']) {
			$portlet['cache']['hash'] = $cache_hash;
			unset($portlet['cache']['data']);
		} elseif (isset($portlet['cache'])) {		
			$force = (isset($_SERVER['HTTP_PRAGMA']) && $_SERVER['HTTP_PRAGMA']=='no-cache');
			$revalidate = (isset($_SERVER['HTTP_CACHE_CONTROL']) && strpos($_SERVER['HTTP_CACHE_CONTROL'],'max-age=0')!==false);

			if (!$force) {
				if ($revalidate || isset($portlet['cache']['must-revalidate'])) { // 304 The best cache - always fresh and never too slow 
					$portlet['headers']['If-Modified-Since'] = $portlet['cache']['last-modified'];
				} elseif (isset($portlet['cache']['expires']) ) { // Second best is when portlet asserts the freshness of its response by max-age
					if (!$revalidate && $portlet['cache']['expires']>time()) load_cached_portlet($portlet);
				} elseif (!$revalidate) { // default cache is only for similar GET requests
					load_cached_portlet($portlet);
				}
			}							
		}
	}  else {
		unset($portlet['cache']);
		$_SESSION['cache'][$portlet['unid']] = null; // all cache entities for given portlet are cleared during action
	}	
		
	if (isset($portlet['fetched'])) return; // already fetched via cache

	$query_params = isset($portlet['config']) ? $portlet['config'] : array();
	
	if ($portlet['action'] && $_SERVER['REQUEST_METHOD'] != 'POST') { 
		foreach($portlet['action'] as $name=>$value) $query_params[$name] = $value;
	}		
	$portlet['url'] .= http::query_string($query_params);
	
	
}


function load_cached_portlet(&$portlet) {
	if (isset($portlet['cache']['data'])) {		
		$p = unserialize($portlet['cache']['data']);
		foreach($p as $n=>$v) if ($n!='cache') $portlet[$n] = $v;
		$log = $p; unset($log['data']);		
		log::info($log ,'PORTLET CACHE HIT `'.$portlet['id'].'`');
		return true;
	}
}



function fetch_portlets(&$portlets) { 
	if (false) return fetch_portlets_gridport($portlets); //TODO GridPort integration here
	else return fetch_portlets_queue($portlets); 
}


//TODO fetch_portlets_gridport(&$portlets) //parallel fetch


function fetch_portlets_queue(&$portlets) {
	
	foreach($portlets as &$portlet) {	
		if (!isset($portlet['headers']) 
		|| !isset($portlet['fragments']) 
		|| isset($portlet['fetched'])) {
			unset($portlet); 
			continue;
		}
				
		log::group("PORTLET ".(isset($portlet['action']) ? "ACTION": "FETCH") ." `".$portlet['id']."`: ".$portlet['method']." ".$portlet['url']);
		try {			
			$portlet['fetched'] = true;
			$entity = ($portlet['method'] == 'POST' ? $portlet['action'] : null);

			//A.process_portlet_response($portlet,http_curl($portlet['url'],$entity,$portlet['headers']));							
			//B.
			$http = new http($portlet['url']);
			if ($entity) $status = $http->POST('',$portlet['headers'],$entity);
			else $status = $http->GET('',$portlet['headers']);
			process_portlet_response($portlet,$status,$http->headers,$http->body);
				
		} catch (Exception $e) {
			log::error($e->getMessage() );
			$portlet['error'] = "Portlet `{$portlet['id']}` unavailable: ".$e->getMessage(); 
		}
		log::groupEnd();
		unset($portlet);		
	}		
}

function process_portlet_response(&$portlet,$response_status,$response_headers = null,$response_body = null) {
	
	if ($response_headers === null) {		
		$r = preg_split('/\r?\n\r?\n/',$response_status,2);		
		$headers = current($r);
		$response_body= next($r);
		if (!preg_match('/^HTTP\/[0-9]{1}\.[0-9]{1} ([0-9]+) (.*)[\r\n]+/i',$headers ,$rs)) throw new exception($headers);
		$response_status = $rs[1];		
		if (!preg_match_all('/(([a-z_-]+)\: ([^\r\n]+)[\r\n]+)/i',$headers ,$response )) throw new exception('Invalid response headers');		
		foreach($response[2] as $i=>&$header) $response_headers[strtolower(trim($header))] = $response[3][$i];
	}	
	log::info($response_headers,$response_status);
	//1. response validation
	if ($response_status == 200) {			 
		$portlet['response'] = (!preg_match('/^\s\<\?xml/i',$response_body) ?  '<?xml encoding="UTF-8">' : '') . $response_body;
		$portlet['cache']['data'] = null;
		$portlet['cache']['data'] = serialize($portlet);		
	} elseif ($response_status == 304 && isset($portlet['cache']['last-modified']))  {		
		load_cached_portlet($portlet);	
		return;		
	} else {		
		throw new exception($response_status ); 						
	}	
	
	//2. Last-modified; if not present current time will be set
	if (isset($response_headers['last-modified'])) {
		$portlet['cache']['last-modified'] = $response_headers['last-modified'];			
	} else $portlet['cache']['last-modified'] = date(DATE_COOKIE,time());
	
	//3. Cache-control directives
	unset($portlet['cache']['expires']);
	unset($portlet['cache']['must-revalidate']);
	if (isset($response_headers['cache-control'])) {
		$fields = http::get_header_fields($response_headers['cache-control']);
		if (isset($fields['max-age'])) {
			$portlet['cache']['max-age'] = $fields['max-age'];
			$portlet['cache']['expires'] = time()+$fields['max-age'];			
		}
		if (isset($fields['must-revalidate'])) $portlet['cache']['must-revalidate'] = true;				
	}
	
	//4. TODO update globals and preferences	
	
	//5. update and store shared and private cookies that the portlet has set
	if (isset($response_headers['set-cookie'])) {		
		if (is_string($response_headers['set-cookie'])) $response_headers['set-cookie'] = array($response_headers['set-cookie']);
		foreach($response_headers['set-cookie'] as $cookie) {
			$cookie = explode(";",$cookie);
			$cookie_nv = explode("=",array_shift($cookie));
			$cookie_name = urldecode(trim($cookie_nv[0]));			
			$cookie_value = urldecode($cookie_nv[1]);
			$cookie_path = '/';
			$cookie_expires = 0;
			while(count($cookie)>0) {
				$cookie_param = explode("=",array_shift($cookie));
				if (trim($cookie_param[0]) =='path') $cookie_path = $cookie_param[1];  
				if (trim($cookie_param[0]) =='expires') $cookie_expires = strtotime($cookie_param[1]);
			}
			
			if ($cookie_name[0] == '$') { //shared cookie								
				if (!$cookie_expires || $cookie_expires>time()) {
					$_COOKIE[$cookie_name] = $cookie_value;
				} else {
					$_COOKIE[$cookie_name] = null;// to delete all mapped references (e.g. params)
					unset($_COOKIE[$cookie_name]);					
				}
			} else {//private cookie
				$cookie_name = "{$portlet['unid']}[$cookie_name]";
			}
					
			setcookie($cookie_name,$cookie_value,$cookie_expires,$cookie_path);
			if (!$cookie_expires || $cookie_expires>time()) {
				log::info("SET COOKIE {$cookie_name} = {$cookie_value},{$cookie_expires},{$cookie_path}");				
			} else {						
				log::info("DELETE COOKIE {$cookie_name} ");
				setcookie($cookie_name,'delete',1,$cookie_path);
				
			}
		}
	}
				
}

