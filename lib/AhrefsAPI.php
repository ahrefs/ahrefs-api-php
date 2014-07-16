<?php
/**
 * AhrefsAPI v.0.1. Ahrefs.com API V2 wrapper for PHP
 *
 *
 * - https://ahrefs.com/api/documentation.php
 *
 *
 */
require_once('ArrayRules.php');

if (!function_exists('curl_init')) {
    throw new Exception('CURL PHP extension needed.');
}

class AhrefsAPI {

    /**
     * Class Variables
     * @var String 	$apiURL 	    Ahrefs api url base
     * @var String 	$token  	    token for API access
     * @var Array 	$params 	    Array of parameters to be sent
     * @var String	$paramsURL 	    $_GET URL built from $params array
     * @var String	$paramsURLs     A collection of $paramsURL String from "prepare_" functions
     * @var Boolean $debug  	    Debug mode
     * @var Boolean $cache		    Local cache (test only)
     * @var Boolean $err		    Array to keep errors
     * @var Boolean $reqParams	    Required parameters
     * @var Array	$where		    Translation for "where" and "having" functions
     * @var Array 	$functions	    List of available functions
     * @var Array 	$column 	    List of available column for "select", "order_by", "where" and "having"
     * @var Boolean $quotedValue 	Internal flag to set whether a value need to be quoted
     * @var Boolean $is_prepare     is it _get or _prepare call
     * @var Boolean $checking       flag to enable/disable column & function checking
     * @var Array 	$curlInfo 	    An array of curl informations
     */
    private $apiURL = 'http://apiv2.ahrefs.com';
    private $params;
    private $token;
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
    private $quotedValue;
    private $is_prepare = false;
    private $checking = true;
    private $curlInfo = array();
    /**
     * Constructing class
     * @param string $token Application API Token from ahrefs website
     * @param boolean $debug Debug status
     * @param boolean $apiUrl URL to API Server
     * @param boolean $checking Enable column checking
     */
    public function __construct($token = '', $debug = false, $apiUrl = '', $checking = true) {
        if (trim($token) == '')
            throw new Exception("API token is required.");
        $this->token = $token;
        $this->params['output'] = 'json';
        $this->debug = $debug;
        $this->checking = $checking;
        $this->where = ArrayRules::$where;
        $this->functions = ArrayRules::$functions;
        $this->columns = ArrayRules::$columns;
        $this->quotedValue = ArrayRules::$quotedValue;
        if ($apiUrl != '')
            $this->apiURL = $apiUrl;
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
        $content = $this->getContent($multi);
        $this->displayDebug();
        return $content;
    }
    /**
     * Checking parameters column for "select", "order_by", "where" and "having"
     * @return boolean
     */
    private function checkColumns() {
        //is checking enabled
        if (!$this->checking)
            return true;

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
        return $this->paramsURL.'&from='.$from.'&token='.$this->token;
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
            case "params":
                $arguments = array($args[0], $args[1]);
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
    private function set_param($param, $condition) {
        if (in_array($param, array('where', 'having')) && is_array($condition)) {
            $this->oriParams[$param][] = $condition;

            $column = $condition[1];
            $operator = $condition[0];
            $value = $condition[2];

            //if the operator is lte, lt, gt, gte, eq, ne
            if (strlen($operator) < 4) {
                //quote the value depends on the column type
                $value = $this->wrapValue($value, $column);

                $condition = $column.$this->where[$operator].$value;
            } else {
                //quote the value depends on the operator/function type
                $value = $this->wrapValue($value, $operator);

                $condition = "$operator($column,$value)";
            }

            if (!isset($this->params[$param]))
                $this->params[$param] = $condition;
            else
                $this->params[$param] .= ','.$condition;
        } else {
            if (!in_array($param, array('where', 'having')))
                $this->params[$param] = $condition;
            else {
                if (isset($this->params[$param]))
                    $this->params[$param] .= ','.$condition;
                else
                    $this->params[$param] = $condition;
            }
        }

        if ($param == 'from')
            return $this->fetch();
        else if ($param == 'prepare')
            return $this->prepare();
        else
            return $this;
    }


    private function wrapValue($value, $type) {
        //if we need to quote this value
        if (in_array($type, $this->quotedValue))
            $value = '"'.addslashes($value).'"';
        else {
            foreach($this->columns as $val) {
                if (isset($val[$type])) {
                    if (in_array($val[$type][0], array('string','date'))) {
                        $value = '"'.addslashes($value).'"';
                        return $value;
                    } else if (gettype($value) == 'boolean') {
                        if ($value)
                            return 'true';
                        else
                            return 'false';
                    }
                }
            }
        }
        return $value;
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
            curl_setopt($ch[$key], CURLOPT_URL, $this->apiURL.'/?'.$params.'&token='.$this->token);
            curl_setopt($ch[$key], CURLOPT_HEADER, 0);
            curl_setopt($ch[$key], CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$key], CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch[$key], CURLOPT_TIMEOUT, 240); //timeout in seconds
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

        $this->curlInfo = array();
        foreach ($links as $key => $params) {
            curl_multi_remove_handle($mh, $ch[$key]);
            //getting the output
            $results[$key] = curl_multi_getcontent($ch[$key]);
            $this->curlInfo[] = curl_getinfo($ch[$key]);

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
            'output' => $this->params['output'],
            'target' => $this->params['target'],
            'mode' => $this->params['mode'],
        );
        $this->oriParams = array();
    }

    /**
     * When debug is TRUE, this function will print out debug messages
     */
    private function displayDebug() {
        if ($this->debug) {
            $infos = $this->getCurlInfo();
            foreach ($infos as $info) {
                echo "<div>";
                echo "<b>API link:</b> $info[url]<br>";
                echo "<b>Execution time:</b> $info[total_time] seconds.<br>";
                echo "</div><br>";
            }
        }
    }

    /**
     * Checking if the function exist in this file
     * @param String $call The name of the prefix
     * @param String $name the name of the function
     * @return Error string
     */
    private function isFunction($call, $name) {
        //is checking enabled
        if (!$this->checking)
            return true;

        if (!(isset($this->functions[$call]) && in_array($name, $this->functions[$call]))) {
            throw new Exception("Function <b>{$call}_{$name}</b> not found.");
        }
    }

    /**
     * Get an array of curlinfo
     * @return curlInfo Array
     */
    public function getCurlInfo() {
        return $this->curlInfo;
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
