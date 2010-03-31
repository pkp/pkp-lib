<?php

require("swordappservicedocument.php");
require("swordappentry.php");
require("swordapperrordocument.php");
require("swordapplibraryversion.php");

class SWORDAPPClient {
	
	// Request a servicedocument at the specified url, with the specified credentials,
	// and on-behalf-of the specified user.
	function servicedocument($sac_url, $sac_u, $sac_p, $sac_obo) {
		// Get the service document
		$sac_curl = curl_init();
		
		curl_setopt($sac_curl, CURLOPT_RETURNTRANSFER, true);
		// To see debugging infomation, un-comment the following line
		//curl_setopt($sac_curl, CURLOPT_VERBOSE, 1);	
		
		curl_setopt($sac_curl, CURLOPT_URL, $sac_url);
		if(!empty($sac_u) && !empty($sac_p)) {
	                curl_setopt($sac_curl, CURLOPT_USERPWD, $sac_u . ":" . $sac_p);
	        }
		$headers = array();
		global $sal_version;
		array_push($headers, "User-Agent: SWORDAPP PHP library (version " . $sal_version . ") " .
		                     "http://php.swordapp.org/");
		if (!empty($sac_obo)) {
			array_push($headers, "X-On-Behalf-Of: " . $sac_obo);
	        }
	        curl_setopt($sac_curl, CURLOPT_HTTPHEADER, $headers);	
		$sac_resp = curl_exec($sac_curl);
	        $sac_status = curl_getinfo($sac_curl, CURLINFO_HTTP_CODE);
		curl_close($sac_curl);
		
		// Parse the result
		if ($sac_status == 200) {
			try {
				$sac_sdresponse = new SWORDAPPServiceDocument($sac_url, $sac_status, $sac_resp);
			} catch (Exception $e) {
                		throw new Exception("Error parsing service document (" . $e->getMessage() . ")");
                	}
		} else {
			$sac_sdresponse = new SWORDAPPServiceDocument($sac_url, $sac_status);
		}	

		// Return the servicedocument object
		return $sac_sdresponse;
	}

	// Perform a deposit to the specified url, with the sepcified credentials,
	// on-behlf-of the specified user, and with the given file and formatnamespace and noop setting
	function deposit($sac_url, $sac_u, $sac_p, $sac_obo, $sac_fname, 
	                 $sac_packaging= '', $sac_contenttype = '', 
			 $sac_noop = false, $sac_verbose = false) {
		// Perform the deposit
		$sac_curl = curl_init();

		// To see debugging infomation, un-comment the following line
		//curl_setopt($sac_curl, CURLOPT_VERBOSE, 1);	
		
		curl_setopt($sac_curl, CURLOPT_URL, $sac_url);
		curl_setopt($sac_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($sac_curl, CURLOPT_POST, true);
		if(!empty($sac_u) && !empty($sac_p)) {
	                curl_setopt($sac_curl, CURLOPT_USERPWD, $sac_u . ":" . $sac_p);
	        }
		$headers = array();
		global $sal_version;
		array_push($headers, "User-Agent: SWORDAPP PHP library (version " . $sal_version . ") " .
		                     "http://php.swordapp.org/");
		$sac_md5 = md5_file($sac_fname);
		array_push($headers, "Content-MD5: " . $sac_md5);
		if (!empty($sac_obo)) {
			array_push($headers, "X-On-Behalf-Of: " . $sac_obo);
	        }
		if (!empty($sac_packaging)) {
			array_push($headers, "X-Packaging: " . $sac_packaging);
	        }
		if (!empty($sac_contenttype)) {
			array_push($headers, "Content-Type: " . $sac_contenttype);
	        }
		array_push($headers, "Content-Length: " . filesize($sac_fname));
		if ($sac_noop == true) {
			array_push($headers, "X-No-Op: true");
	        }
		if ($sac_verbose == true) {
			array_push($headers, "X-Verbose: true");
	        }
		$index = strpos(strrev($sac_fname), '/');
                if ($index) {
                        $index = strlen($sac_fname) - $index;
                        $sac_fname_trimmed = substr($sac_fname, $index);
                } else {
                        $sac_filename_trimmed = $sac_fname;
	        }
		array_push($headers, "Content-Disposition: filename=" . $sac_fname_trimmed);
		curl_setopt($sac_curl, CURLOPT_READDATA, fopen($sac_fname, 'rb'));
	        curl_setopt($sac_curl, CURLOPT_HTTPHEADER, $headers);
		
		$sac_resp = curl_exec($sac_curl);
	        $sac_status = curl_getinfo($sac_curl, CURLINFO_HTTP_CODE);
		curl_close($sac_curl);

		// Parse the result
		$sac_dresponse = new SWORDAPPEntry($sac_status, $sac_resp);

		// Was it a succesful result?
		if (($sac_status >= 200) || ($sac_status < 300)) {
			try {
				// Get the deposit results
				$sac_xml = @new SimpleXMLElement($sac_resp);
		        	$sac_ns = $sac_xml->getNamespaces(true);

				// Build the deposit response object
				$sac_dresponse->buildhierarchy($sac_xml, $sac_ns);
			} catch (Exception $e) {
			    throw new Exception("Error parsing response entry (" . $e->getMessage() . ")");
			}
		} else {
			try {
				// Parse the result
				$sac_dresponse = new SWORDAPPErrorDocument($sac_status, $sac_resp);
				
				// Get the deposit results
				$sac_xml = @new SimpleXMLElement($sac_resp);
		        	$sac_ns = $sac_xml->getNamespaces(true);

				// Build the deposit response object
				$sac_dresponse->buildhierarchy($sac_xml, $sac_ns);
			} catch (Exception $e) {
			    throw new Exception("Error parsing error document (" . $e->getMessage() . ")");
			}
		}

		// Return the deposit object
		return $sac_dresponse;
	}
}

?>
