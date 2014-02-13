<?php
/**
 * AhrefsAPI v.0.1. Ahrefs.com API V2 wrapper for PHP
 *
 *
 * - https://ahrefs.com/api/documentation.php
 *
 *
 * Date: 17th December 2013
 * Requires file_get_contents
 * 
 */
require_once('ArrayRules.php');

if (!function_exists('curl_init')) {
  throw new Exception('CURL PHP extension needed.');
}

class AhrefsAPI {
	
	/**
	 * Class Variables
	 * @var String 	$apiURL 	Ahrefs api url base
	 * @var Array 	$params 	Array of parameters to be sent
	 * @var String	$paramsURL 	$_GET URL built from $params array
	 * @var String	$paramsURLs A collection of $paramsURL String from "prepare_" functions
	 * @var Boolean $debug  	Debug mode
	 * @var Boolean $cache		Local cache (test only)
	 * @var Boolean $err		Array to keep errors
	 * @var Boolean $reqParams	Required parameters
	 * @var Array	$where		Translation for "where" and "having" functions
	 * @var Array 	$functions	List of available functions
	 * @var Array 	$column 	List of available column for "select", "order_by", "where" and "having"
	 * @var Boolean $is_prepare is it _get or _prepare call
	 * @var Array 	$benchmare 	Keep script execution start time and end time
	 */
    private $apiURL = 'http://apiv2.ahrefs.com';
    private $params;
    private $oriParams;
    private $paramsURL;
    private $paramsURLs;
    private $debug;
    private $cache = false;
    private $err = array();
    private $reqParams = array('target', 'mode');
    private $where;
    private $functions;
    private $columns;
    private $is_prepare = false;
    private $benchmark = array();
    /**
     * Constructing class
     * @param string $apiToken Application API Token from ahrefs website
     * @param boolean $debug Debug status
     */
    public function __construct($token = '', $debug = false) {   
    	if (trim($token) == '')
    		throw new Exception("API token is required.");
    	$this->params['token'] = $token;
    	$this->params['output'] = 'json';
    	$this->debug = $debug;
    	$this->where = ArrayRules::$where;
    	$this->functions = ArrayRules::$functions;
        $this->columns = ArrayRules::$columns;
    }

    /**
     * Build API V2 parameters, displaying debug messages, resetting parameter variables, calling and returning API results from getContent()
     * @return output from getContent()
     */
    private function fetch() {
        $this->checkColumns();
    	$this->buildURL();
		if (!$this->isError()) {
		    if (!$this->is_prepare) {
		        $this->reset();
                return $this->run(false);
		    } else {
		        $this->paramsURLs[$this->params['from']] = $this->paramsURL;
		        $this->reset();
		        $this->is_prepare = false;
		    }
		}
    }
    
    /**
     * Batch run all the prepared API calls
     * 
     */
    public function run($multi = true) {
        $this->benchmark['start_time'] = microtime(true);
        $content = $this->getContent($multi);
        $this->benchmark['end_time'] = microtime(true);
        $this->displayDebug($multi);
        return $content;
    }
    /**
     * Checking parameters column for "select", "order_by", "where" and "having"
     * @return boolean 
     */    
    private function checkColumns() {
        $columns = $this->columns[$this->params['from']];
        //checking select
        if (isset($this->params['select'])) {
            $cols = explode(',',$this->params['select']);
            foreach ($cols as $col) {
                if (!isset($columns[$col]))
                   $this->err[] = "No column <b>$col</b> to select in table <i>{$this->params['from']}</i>.";
            }
        }
        //checking order_by
        if (isset($this->params['order_by'])) {
            $cols = explode(',',$this->params['order_by']);
            foreach ($cols as $col) {
                $col = explode(':', $col);
                if (count($col)==2) {
                    if (!in_array($col[1], array('asc','desc')))
                        $this->err[] = "Unknown option <b>$col[1]</b> to order_by in table <i>{$this->params['from']}</i>.";
                }
                $col = $col[0];
                if (!isset($columns[$col]))
                   $this->err[] = "No column <b>$col</b> to order_by in table <i>{$this->params['from']}</i>.";
            }
        }
        //checking where
        if (isset($this->oriParams['where'])) {
            $cols = $this->oriParams['where'];
            foreach ($cols as $col) {
                if (!isset($columns[$col[1]]))
                   $this->err[] = "No column <b>$col[1]</b> for 'where' condition in table <i>{$this->params['from']}</i>.";
                if (!$columns[$col[1]][1])
                    $this->err[] = "Column <b>$col[1]</b> can not be used in 'where' condition in table <i>{$this->params['from']}</i>.";
                    
            }
        }
        //checking having
        if (isset($this->oriParams['having'])) {
            $cols = $this->oriParams['having'];
            foreach ($cols as $col) {
                if (!isset($columns[$col[1]]))
                   $this->err[] = "No column <b>$col[1]</b> for 'having' condition in table <i>{$this->params['from']}</i>.";
                if (!$columns[$col[1]][2])
                    $this->err[] = "Column <b>$col[1]</b> can not be used in 'having' condition in table <i>{$this->params['from']}</i>.";
                
            }
        }   
    }

    /**
     * Create paramsURL parameter from params array
     *
     */
    private function buildURL() {
        foreach ($this->reqParams as $reqParam) {
            if (!isset($this->params[$reqParam]))
                throw new Exception("<b>$reqParam</b> is required.");
        }
        $paramStr = array();
        foreach ($this->params as $k => $v) {
            $paramStr[] = "$k=".urlencode($v);
        }
        $this->paramsURL = implode('&',$paramStr);
    }
    
    /**
     * Get paramsURL parameter from params array
     *
     */
    public function getURL($from) {
        $this->buildURL();
        return $this->paramsURL.'&from='.$from;
    }
    

    /**
     * Display any error $this->err
     * @return boolean 
     */
    private function isError() {
        if (!count($this->err))
            return false; //no error
        
        foreach ($this->err as $error) 
            echo "Error: $error<br>";
        return true;
            
    }
    
    /**
     * Magic method to catch all functions and parse it to set_param function
     * @param string $method The name of the function
     * @param array $args Arguments passed to the function
     */
    public function __call($method,$args) {
    	$method = explode('_', $method);
    	$call = $method[0];
    	if (count($method)>1) {
    		unset($method[0]);
    		$method = implode('_',$method);
    		$this->isFunction($call, $method);
    	} else 
    		$method = $method[0];
    	
    	$fn = array($this,'set_param');
    	switch ($call) {
    		case "set":
    			$arguments = array_merge(array($method),$args);
       		break;
    		case "to":
    			$arguments = array('output',$method);
       		break;
       		case "get":
    			$arguments = array('from',$method);
       		break;
       		case "prepare":
       		    $this->is_prepare = true;
       		    $arguments = array('from',$method);
       		break;
       		case "mode":
       			$arguments = array('mode',$method);
       		break;
       		case "select":
    			$arguments = array('select',implode(',',$args));
       		break;
       		case "order":
    			$arguments = array('order_by',implode(',',$args));
       		break;
       		case "where":
    			$arguments = array('where',array_merge(array($method),$args));
       		break;
       		case "having":
    			$arguments = array('having',array_merge(array($method),$args));
       		break;
       		default:	
       			throw new Exception("Function <b>$method</b> not found");
    	}
    	return call_user_func_array($fn,$arguments);
    }
    
    /**
     * Function to take and build the parameters needed to pass to the API
     * @param string $param The name of the parameter
     * @param string $value The value of the parameter
     * @return $this
     */
    private function set_param($param,$value) { 
        if (in_array($param, array('where', 'having'))) {
            $this->oriParams[$param][] = $value;

    		if ($value[2] != 'false' && $value[2] != 'true') {
    			$value[2] = '"'.$value[2].'"';
    		}
    		
    		if (strlen($value[0]) < 4) //if the operator is not lte, lt, gt, gte, eq, ne
    			$value = $value[1].$this->where[$value[0]].$value[2];
    		else
    			$value = "$value[0]($value[1],$value[2])";
    		if (!isset($this->params[$param]))
    			$this->params[$param] = $value;
    		else
    			$this->params[$param] .= ','.$value;
    	} else {
    		$this->params[$param] = $value;
    	}

    	if ($param == 'from') 
    		return $this->fetch();
    	else if ($param == 'prepare') 
    		return $this->prepare();
    	else
  		  	return $this;
    }
    
/*
    private function cacheResult() {
    	if ($this->cache) {
	        $filename = 'lib/cache/'.$this->paramsURL;
	        if (!is_file($filename)) {
	            $content = file_get_contents($this->apiURL.'/?'.$this->paramsURL);
	            file_put_contents($filename, $content);
	        } else {
	            $content = file_get_contents($filename);
	        }
    	} else 
    		$content = file_get_contents($this->apiURL.'/?'.$this->paramsURL);
   
        return $content;
    }
    */
    /**
     * Send the parameters to Ahrefs server and get the json/xml/php return
     * @return json/xml/php data
     */    
    private function getContent($multi = false) {
        $links = $this->paramsURLs;
        if (!$multi)
            $links[0] = $this->paramsURL;
        
        $mh = curl_multi_init();
        foreach ($links as $key => $params) {
            $ch[$key] = curl_init();
            //setting the links
            curl_setopt($ch[$key], CURLOPT_URL, $this->apiURL.'/?'.$params);
            curl_setopt($ch[$key], CURLOPT_HEADER, 0);
            curl_setopt($ch[$key], CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
            curl_multi_add_handle($mh,$ch[$key]);
        }
        
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        
        foreach ($links as $key => $params) {
            curl_multi_remove_handle($mh, $ch[$key]);
            //getting the output
            $results[$key] = curl_multi_getcontent($ch[$key]);
        }
        curl_multi_close($mh);
        
        if (!$multi)
            return $results[0];
        return $results;
    }
    
    /**
     * Reset params parameter
     */
    public function reset() {
    	$this->params = array(
    	        'token' => $this->params['token'],
    	        'target' => $this->params['target'],
    	    	'mode' => $this->params['mode'],
    	);
    	$this->oriParams = array();
    }
    
    /**
     * When debug is TRUE, this function will print out debug messages
     */
    private function displayDebug($multi = false) {
        $links = $this->paramsURLs;
        if (!$multi)
            $links[0] = $this->paramsURL;
    	if ($this->debug) {
    		$time = $this->benchmark['end_time'] - $this->benchmark['start_time'];
    		echo "<br><b>Execution time:</b> $time seconds.<br>";
    		foreach ($links as $link) 
    		  echo "<b>API link:</b> $this->apiURL/?$link<br>";
    		echo "</pre>";
    	}
    }
    
    /**
     * Checking if the function exist in this file
     * @param String $call The name of the prefix
     * @param String $name the name of the function
     * @return Error string
     */
    private function isFunction($call, $name) {
    	if (!(isset($this->functions[$call]) && in_array($name, $this->functions[$call]))) {
    		throw new Exception("Function <b>{$call}_{$name}</b> not found.");
    	}
    }
}


/**
 * Function to handle the error exceptions
 * @return Formatted error String
 */
function Error($exception) {
	echo "Error: " . $exception->getMessage();
}
set_exception_handler('Error');
