<?php

/**
 * Include the AhrefsAPI class
 */
require_once 'vendor/autoload.php';

use ahrefs\AhrefsApiPhp\AhrefsAPI;

	/**
	 * Create an AhrefsAPI class instance
	 * @param String APItoken from https://ahrefs.com/api/
	 * @param Boolean $debug Debug message
	 */
	$Ahrefs = new AhrefsAPI('[YOURTOKEN]', $debug = true);
	if (class_exists('\\GuzzleHttp\\Client')) {
		/**
		 * Use Guzzle HTTP Client mode.
		 * @param Boolean $use True - use Guzzle, false - use cURL
		 */
		$Ahrefs->useGuzzle(true);
	}
	/**
	 * Specify the aim of the request. The mode defines how the target will be interpreted. Example:
	 * set_target('ahrefs.com/api/')
	 * mode_exact()		 ->  ahrefs.com/api/	
	 * mode_domain() 	 ->  ahrefs.com/*	
	 * mode_subdomains()  ->  *ahrefs.com/*
	 * mode_prefix()	 ->  ahrefs.com/api/*	
	 * for more details, visit https://ahrefs.com/api/documentation.php
	 */
	$Ahrefs->set_target('ahrefs.com')->mode_domain();

/**
	 * Query the results.
	 * select('column_a','column_b','column_c')    							 //specify a comma-separated list of columns for the service to return. If the parameter is not set or is equal to '*', all columns of the table are returned.
	 																		 //Only the columns that can appear in the 'having' filter will be returned.
	 																		 
	 * where_[eq|ne|gt|lt|gte|lte|substring|word]('column_a', 'value_a')   	 //eq (equal '='), ne(not equal '<>'), gt(greater than '>='), lt(less than '<='), gte(greater than or equal '>='), lte(less than or equal '<=').
	   Sample usage :
	   $Ahrefs->where_eq('url', 'ahrefs.com') 								 //returning rows with url="ahrefs.com".
	   $Ahrefs->where_eq('url', 'ahrefs.com')->where_gt('ahrefs_rank', 3)    //returning rows with url="ahrefs.com" AND ahrefs_rank>3.
     *
     *
     * where('raw_conditions')                                               //raw where conditions
	 
	 * having_[eq|ne|gt|lt|gte|lte|substring|word]('column_a', 'value_a')    //similar to where, the difference is for some tables, the returned data is implicitly grouped before being returned. 
	 																		 //The 'where' filter applies to the data before grouping, and the 'having' filter applies to the grouped data.
	 																		 //See column descriptions under documentations for the particular table to decide whether to use 'where' or 'having' for filtering.
	 																		 
	 * order_by('column_a:desc','column_b','column_c:desc')					 //sorting the result based on the columns, ascending by default. For descending usage, add :desc after the column name.
	 * set_limit() 															 //limit the amount of retrieved data, the default is 1000.
	 * set_offset()															 //number of row the data start to be retrieved, default is 0 (first data).
	 * to_xml()																 //format the returned data in XML
	 * to_php()																 //format the returned data in serialized PHP data
	 */	
	$Ahrefs->select('date','type','refdomain','domain_rating')->order_by('domain_rating:desc','refdomain')->where_gt('date','2013-11-24')->where_eq('type','lost')->set_limit(10);
	/**
	 * Trigger the call and get "Ahrefs Rank" based on above settings.
	 * 	get_ahrefs_rank();						https://ahrefs.com/api/documentation/ahrefs-rank
	 * 	get_anchors();							https://ahrefs.com/api/documentation/anchors
	 * 	get_anchors_refdomains();				https://ahrefs.com/api/documentation/anchors-refdomains
	 * 	get_backlinks();						https://ahrefs.com/api/documentation/backlinks
	 * 	get_backlinks_new_lost();				https://ahrefs.com/api/documentation/backlinks-new-lost
	 * 	get_backlinks_new_lost_counters();		https://ahrefs.com/api/documentation/backlinks-new-lost-counters
	 * 	get_domain_rating();					https://ahrefs.com/api/documentation/domain-rating
     * 	get_linked_anchors();					https://ahrefs.com/api/documentation/linked-anchors
     * 	get_linked_domains();					https://ahrefs.com/api/documentation/linked-domains
	 * 	get_metrics();							https://ahrefs.com/api/documentation/metrics
	 * 	get_metrics_extended();					https://ahrefs.com/api/documentation/metrics-extended
	 * 	get_pages();							https://ahrefs.com/api/documentation/pages
	 * 	get_pages_extended();					https://ahrefs.com/api/documentation/pages-extended
	 * 	get_refdomains();						https://ahrefs.com/api/documentation/refdomains
	 * 	get_refdomains_new_lost();				https://ahrefs.com/api/documentation/refdomains-new-lost
	 * 	get_refdomains_new_lost_counters();		https://ahrefs.com/api/documentation/refdomains-new-lost-counters
	 */	
	$result = $Ahrefs->get_refdomains_new_lost();
	
	/**
	 * It is also possible to combine the above chains into a chain like below. Make sure to call the get_(something) at the end of the chain.
	 */	
	//$result = $Ahrefs->set_target('ahrefs.com')->mode_domain()->select('url','ahrefs_rank')->order_by('url:desc','ahrefs_rank')->having_eq('ahrefs_rank',14)->set_limit(20)->get_ahrefs_rank();
	
	/**
	 * Print out the result.
	 */	
	echo $result;
	$result = $Ahrefs->set_limit(10)->get_ahrefs_rank();	
	echo $result;
	
	
	
	/**
	 * 	Another option to call the API is to use the prepare_ functions. Instead of calling the API one by one using get_, we get all the data at the same time using curl_multi_exec function
	 *  Example, setting up a query (the token, target and mode is set above).
	 */	
	$Ahrefs->select('date','type','refdomain','domain_rating')->order_by('domain_rating:desc','refdomain')->where_gt('date','2013-11-24')->where_eq('type','lost')->set_limit(10);
	
	/**
	 * Prepare the call based on above setup.
	 */
	$Ahrefs->prepare_refdomains_new_lost();
	
	/**
	 * Prepare another type of call, please note that the parameters are reset after each prepare_ function call. 
	 */
	$Ahrefs->set_limit(5)->prepare_ahrefs_rank();
	
	/**
	 * Run the calls, this will trigger 2 calls that is set above. 
	 */
	$result = $Ahrefs->run();
	
	/**
	 * Print out the result.
	 */	
	print_r($result);