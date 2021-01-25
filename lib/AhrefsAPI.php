<?php

namespace ahrefs\AhrefsApiPhp;

use \GuzzleHttp\Client as GuzzleClient;
use \GuzzleHttp\Promise as GuzzlePromize;
use \GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\RequestOptions as GuzzleRequestOptions;

/**
 * AhrefsAPI v.0.1. Ahrefs.com API V2 wrapper for PHP
 *
 *
 * - https://ahrefs.com/api/documentation
 *
 *
 */

/**
 * Class AhrefsAPI
 * @method AhrefsAPI set_limit(int)
 * @method AhrefsAPI set_offset(int)
 * @method AhrefsAPI set_output(string)
 * @method AhrefsAPI set_target(string)
 * @method AhrefsAPI mode_domain(string)
 * @method AhrefsAPI mode_exact(string)
 * @method AhrefsAPI mode_subdomains(string)
 * @method AhrefsAPI mode_prefix(string)
 * @method AhrefsAPI order_by(string)
 * @method AhrefsAPI where_eq(string)
 * @method AhrefsAPI where_ne(string)
 * @method AhrefsAPI where_lt(string)
 * @method AhrefsAPI where_lte(string)
 * @method AhrefsAPI where_gt(string)
 * @method AhrefsAPI where_gte(string)
 * @method AhrefsAPI having_eq(string)
 * @method AhrefsAPI having_ne(string)
 * @method AhrefsAPI having_lt(string)
 * @method AhrefsAPI having_lte(string)
 * @method AhrefsAPI having_gt(string)
 * @method AhrefsAPI having_gte(string)
 * @method AhrefsAPI select(string)
 * @method mixed get_subscription_info()
 * @method mixed get_broken_links()
 * @method mixed get_ahrefs_rank()
 * @method mixed get_anchors()
 * @method mixed get_anchors_refdomains()
 * @method mixed get_backlinks()
 * @method mixed get_backlinks_new_lost()
 * @method mixed get_backlinks_new_lost_counters()
 * @method mixed get_broken_backlinks()
 * @method mixed get_domain_rating()
 * @method mixed get_linked_anchors()
 * @method mixed get_linked_domains()
 * @method mixed get_metrics()
 * @method mixed get_metrics_extended()
 * @method mixed get_pages()
 * @method mixed get_pages_extended()
 * @method mixed get_refdomains()
 * @method mixed get_refdomains_by_type()
 * @method mixed get_refdomains_new_lost()
 * @method mixed get_refdomains_new_lost_counters()
 * @method mixed get_refips()
 * @method mixed get_positions_metrics()
 * @method AhrefsAPI prepare()
 * @method AhrefsAPI prepare_subscription_info()
 * @method mixed prepare_broken_links()
 * @method mixed prepare_ahrefs_rank()
 * @method mixed prepare_anchors()
 * @method mixed prepare_anchors_refdomains()
 * @method mixed prepare_backlinks()
 * @method mixed prepare_backlinks_new_lost()
 * @method mixed prepare_backlinks_new_lost_counters()
 * @method mixed prepare_broken_backlinks()
 * @method mixed prepare_domain_rating()
 * @method mixed prepare_linked_anchors()
 * @method mixed prepare_linked_domains()
 * @method mixed prepare_metrics()
 * @method mixed prepare_metrics_extended()
 * @method mixed prepare_pages()
 * @method mixed prepare_pages_extended()
 * @method mixed prepare_refdomains()
 * @method mixed prepare_refdomains_by_type()
 * @method mixed prepare_refdomains_new_lost()
 * @method mixed prepare_refdomains_new_lost_counters()
 * @method mixed prepare_refips()
 * @method mixed prepare_positions_metrics()
 */
class AhrefsAPI
{

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
     * @var Boolean	$useGuzzle	    Use Guzzle Http Client instead of cURL
     * @var \Exception|null	$lastGuzzleError	Last error returned by Guzzle HTTP Client
     */
    private $apiURL = 'http://apiv2.ahrefs.com';
    private $params;
    private $token;
    private $oriParams;
    private $paramsURL;
    private $paramsURLs;
    private $debug;
    private $err = array();
    private $reqParams = array('target', 'mode');
    private $where;
    private $functions;
    private $columns;
    private $quotedValue;
    private $is_prepare = false;
    private $checking = true;
    private $curlInfo = array();
    private $post = 0;
    private $withOriginalStats = 0;
    private $lastMessageError;
    private $useGuzzle = false;
    private $lastGuzzleError;

    /**
     * Constructing class
     * @param string $token Application API Token from ahrefs website
     * @param boolean $debug Debug status
     * @param string $apiUrl URL to API Server
     * @param boolean $checking Enable column checking
     * @throws \Exception
     */
    public function __construct($token = '', $debug = false, $apiUrl = '', $checking = true)
    {
        if (trim($token) == '')
            throw new \Exception('API token is required.');
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
     * @throws \Exception
     */
    private function fetch()
    {
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
        } else {
            throw new \Exception($this->lastMessageError);
        }
    }

    /**
     * Batch run all the prepared API calls
     * @param boolean $multi
     * @return output from getContent()
     */
    public function run($multi = true)
    {
        $content = $this->getContent($multi);
        $this->displayDebug();
        return $content;
    }
    /**
     * Checking parameters column for "select", "order_by", "where" and "having"
     * @return boolean
     */
    private function checkColumns()
    {
        //is checking enabled
        if (!$this->checking) {
            return true;
        }

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
     * @throws \Exception
     */
    private function buildURL()
    {
        //no need target and mode for subscription_info
        if (isset($this->params['from']) && $this->params['from'] != 'subscription_info') {
            foreach ($this->reqParams as $reqParam) {
                if (!isset($this->params[$reqParam]))
                    throw new \Exception("<b>$reqParam</b> is required.");
            }
        }
        $paramStr = array();
        foreach ($this->params as $k => $v) {
            $paramStr[] = "$k=".urlencode($v);
        }
        $this->paramsURL = implode('&',$paramStr);
    }

    /**
     * Get paramsURL parameter from params array
     * @throws \Exception
     */
    public function getURL($from)
    {
        $this->buildURL();
        return $this->paramsURL.'&from='.$from.'&token='.$this->token;
    }


    /**
     * Display any error $this->err
     * @return boolean
     */
    private function isError()
    {
        if (!count($this->err)) {
            return false; //no error
        }

        $errorMessage = '';
        foreach ($this->err as $error) {
            $errorMessage .= sprintf('Error: %s, ', $error);
        }
        $this->lastMessageError = $errorMessage;
        return true;

    }

    /**
     * Magic method to catch all functions and parse it to set_param function
     * @param string $method The name of the function
     * @param array $args Arguments passed to the function
     * @return mixed
     * @throws \Exception
     */
    public function __call($method,$args)
    {
        $method = explode('_', $method);
        $call = $method[0];
        if (count($method) > 1) {
            unset($method[0]);
            $method = implode('_',$method);
            $this->isFunction($call, $method);
        } else {
            $method = $method[0];
        }

        $fn = array($this,'set_param');
        switch ($call) {
            case 'set':
                $arguments = array_merge(array($method),$args);
                break;
            case 'to':
                $arguments = array('output',$method);
                break;
            case 'get':
                $arguments = array('from',$method);
                break;
            case 'prepare':
                $this->is_prepare = true;
                $arguments = array('from',$method);
                break;
            case 'mode':
                $arguments = array('mode',$method);
                break;
            case 'select':
                $arguments = array('select',implode(',',$args));
                break;
            case 'order':
                $arguments = array('order_by',implode(',',$args));
                break;
            case 'where':
                $arguments = array('where',array_merge(array($method),$args));
                break;
            case 'having':
                $arguments = array('having',array_merge(array($method),$args));
                break;
            case 'params':
                if ($args[0] == 'post') {
                    $this->post = $args[1];
                } else if ($args[0] == 'withOriginalStats') {
                    $this->withOriginalStats = $args[1];
                } else {
                    $arguments = array($args[0], $args[1]);
                }
                break;
            default:
                throw new \Exception(sprintf('Function <b>%s</b> not found', $method));
        }
        if (isset($arguments)) {
            return call_user_func_array($fn,$arguments);
        }
    }

    /**
     * Function to take and build the parameters needed to pass to the API
     * @param string $param The name of the parameter
     * @param string $condition The value of the parameter
     * @return $this
     */
    private function set_param($param, $condition)
    {
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

        if ($param === 'from') {
            return $this->fetch();
        } else if ($param === 'prepare') {
            return $this->prepare();
        } else {
            return $this;
        }
    }


    private function wrapValue($value, $type)
    {
        //if we need to quote this value
        if (in_array($type, $this->quotedValue)) {
            $value = '"' . addslashes($value) . '"';
        } else {
            foreach($this->columns as $val) {
                if (isset($val[$type])) {
                    if (in_array($val[$type][0], array('string','date'))) {
                        $value = '"'.addslashes($value).'"';
                        return $value;
                    } else if (gettype($value) === 'boolean') {
                        if ($value) {
                            return 'true';
                        } else {
                            return 'false';
                        }
                    }
                }
            }
        }
        return $value;
    }

    /**
     * Send the parameters to Ahrefs server and get the json/xml/php return
     * @param boolean $multi
     * @return string|array json|xml|php data
     */
    private function getContent($multi = false)
    {
        $links = $this->paramsURLs;
        if (!$multi) {
            $links[0] = $this->paramsURL;
            if ($this->withOriginalStats) {
                parse_str($links[0], $links[1]);
                $links[1]['limit'] = 1;
                $links[1] = http_build_query($links[1]);
            }
        }

        if ( $this->useGuzzle ) {
            $results = $this->getContentGuzzle( $links );
        } else {
            $results = $this->getContentCurl( $links );
        }

        if (!$multi) {
            if (count($results) > 1) {
                $results[0] = json_decode($results[0], true);
                $results[0]['originalStats'] = json_decode($results[1], true)['stats'];
                $results[0] = json_encode($results[0]);
            }
            return $results[0];
        }
        return $results;
    }

    /**
     * Execute requests using cURL
     * @param array $links
     * @return array json|xml|php data
     */
    private function getContentCurl($links)
    {
        $results = array();
        $mh = curl_multi_init();
        foreach ($links as $key => $params) {
            $ch[$key] = curl_init();
            //setting the links
            curl_setopt($ch[$key], CURLOPT_URL, $this->apiURL.'/?'.$params.'&token='.$this->token);
            curl_setopt($ch[$key], CURLOPT_HEADER, 0);
            if ($this->post) {
                curl_setopt($ch[$key], CURLOPT_POST, 1);
                curl_setopt($ch[$key], CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
                curl_setopt($ch[$key], CURLOPT_POSTFIELDS, $this->post);
                curl_setopt($ch[$key], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                curl_setopt($ch[$key], CURLOPT_LOW_SPEED_LIMIT, 1);
                curl_setopt($ch[$key], CURLOPT_LOW_SPEED_TIME, 2400);
                $this->post = 0;
            }
            curl_setopt($ch[$key], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch[$key], CURLOPT_SSL_VERIFYPEER, 0);
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
            if (curl_multi_select($mh) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $this->curlInfo = array();
        foreach ($links as $key => $params)             {
            curl_multi_remove_handle($mh, $ch[$key]);
            //getting the output
            $results[$key] = curl_multi_getcontent($ch[$key]);
            $cinfo = curl_getinfo($ch[$key]);
            if (curl_errno($ch[$key]))
            {
                if (is_array($cinfo))
                {
                    $cinfo['curl_error'] = curl_error($ch[$key]);
                }
                else
                {
                    $cinfo = curl_error($ch[$key]);
                }
            }
            $this->curlInfo[] = $cinfo;
        }
        curl_multi_close($mh);
        return $results;
    }

    /**
     * Ececute requests using Guzzle HTTP Client
     * @param array $links
     * @return array json|xml|php data
     */
    private function getContentGuzzle($links)
    {
        try {
            $results = array();
            $this->lastGuzzleError = null;
            $this->curlInfo = array();
            $default_options = [
                GuzzleRequestOptions::VERIFY => false,
                GuzzleRequestOptions::VERSION => 1.0,
                GuzzleRequestOptions::CONNECT_TIMEOUT => 20,
                GuzzleRequestOptions::TIMEOUT => 240, //timeout in seconds
                GuzzleRequestOptions::DECODE_CONTENT => true,
                GuzzleRequestOptions::HEADERS => ['Accept-Encoding' => 'gzip,deflate'],
            ];
            $client   = new GuzzleClient( $default_options );
            $promises = array();
            $infoKeys = array();
            foreach ($links as $key => $params) {
                $this->curlInfo[] = null; // fill for backward compability.
                $info = & $this->curlInfo[count($this->curlInfo) - 1];
                $infoKeys[$key] = count($this->curlInfo) - 1;
                $options = [
                    GuzzleRequestOptions::ON_STATS => function( $stats ) use ( &$info ) {
                        $info = $stats->getHandlerStats();
                        if (is_array($info)&&isset($info['error'])) {
                            $info['curl_error'] = $info['error'];
                        }
                    },
                ];
                // Initiate each request but do not block.
                if ($this->post) {
                    $post_options = [
                        GuzzleRequestOptions::HEADERS => ['Accept-Encoding' => 'gzip,deflate', 'Content-Type' => 'text/plain'],
                        GuzzleRequestOptions::BODY    => $this->post,
                        'curl' => [
                            CURLOPT_LOW_SPEED_LIMIT => 1,
                            CURLOPT_LOW_SPEED_TIME => 2400,
                        ],

                    ];
                    $promises[ $key ] = $client->postAsync( $this->apiURL.'/?'.$params.'&token='.$this->token, $options + $post_options );
                } else {
                    $promises[ $key ] = $client->getAsync( $this->apiURL.'/?'.$params.'&token='.$this->token, $options );
                }
            }

            $responses = array();
            try {
                // Wait for the requests to complete, even if some of them fail.
                $responses = GuzzlePromize\Utils::settle( $promises )->wait();
            } catch ( GuzzleConnectException $e ) {
                $this->lastGuzzleError = $e;
                // return empty result.
                return array_map(
                    function( $value ) {
                        return '';
                    },
                    $links
                );
            }

            foreach ( $responses as $key => $response ) {
                if ( 'fulfilled' === $response['state'] ) {
                    $results[ $key ] = (string) $response['value']->getBody();
                } else {
                    $results[ $key ] = '';
                    if ( $response['reason'] instanceof GuzzleRequestException ) {
                        $this->curlInfo[$infoKeys[$key]] = $response['reason']->getHandlerContext();
                    }
                }
            }
        } catch ( \Exception $e ) {
            $this->lastGuzzleError = $e;
        }
        return $results;
    }

    /**
     * Reset params parameter
     */
    public function reset()
    {
        if (!isset($this->params['target']))
            $this->params['target'] = '';
        if (!isset($this->params['mode']))
            $this->params['mode'] = '';
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
    private function displayDebug()
    {
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
     * @return bool
     * @throws \Exception
     */
    private function isFunction($call, $name)
    {
        //is checking enabled
        if (!$this->checking) {
            return true;
        }

        if (!(isset($this->functions[$call]) && in_array($name, $this->functions[$call]))) {
            throw new \Exception("Function <b>{$call}_{$name}</b> not found.");
        }
    }

    /**
     * Get an array of curlinfo
     * @return curlInfo Array
     */
    public function getCurlInfo()
    {
        return $this->curlInfo;
    }

    /**
     * Get last error from Guzzle HTTP Client
     * @return \Exception|null
     */
    public function getlastGuzzleError()
    {
        return $this->lastGuzzleError;
    }

    /**
     * Use Guzzle HTTP Client or cUrl
     * @param Boolean $use True - use Guzzle, false - use cURL
    */
    public function useGuzzle( $use = true )
    {
        $this->useGuzzle = $use;
    }
}
