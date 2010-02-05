<?php

require_once('workspace.php');

class SWORDAPPServiceDocument {

	// The URL of this Service Document
	public $sac_url;
	
	// The HTTP status code returned
	public $sac_status;
	
	// The XML of the service doucment
	public $sac_xml;
	
	// The human readable status code
	public $sac_statusmessage;

	// The version of the SWORD server
	public $sac_version;

	// Whether or not verbose output is supported
	public $sac_verbose;

	// Whether or not the noOp command is supported
	public $sac_noop;

	// The max upload size of deposits
	public $sac_maxuploadsize;
	
	// Workspaces in the servicedocument
	public $sac_workspaces;

	// Construct a new servicedocument by passing in the http status code
	function __construct($sac_theurl, $sac_newstatus, $sac_thexml = '') {
		// Store the URL
		$this->sac_url = $sac_theurl;
		
		// Store the status
		$this->sac_status = $sac_newstatus;
		
		// Store the raw xml
		$this->sac_xml = $sac_thexml;

		// Store the status message
		switch($this->sac_status) {
			case 200:
				$this->sac_statusmessage = "OK";
				break;
			case 401:
				$this->sac_statusmessage = "Unauthorized";
				break;
			case 404:
				$this->sac_statusmessage = "Service document not found";
				break;
			default:
				$this->sac_statusmessage = "Unknown erorr (status code " . $this->sac_status . ")";
				break;
		}
		
		// Parse the xml if there is some
		if ($sac_thexml != '') {
			$sac_xml = @new SimpleXMLElement($sac_thexml);
        	        $sac_ns = $sac_xml->getNamespaces(true);
			$this->sac_version = $sac_xml->children($sac_ns['sword'])->version;
	                $this->sac_verbose = $sac_xml->children($sac_ns['sword'])->verbose;
        	        $this->sac_noop = $sac_xml->children($sac_ns['sword'])->noOp;
                	$this->sac_maxuploadsize = $sac_xml->children($sac_ns['sword'])->maxUploadSize;
				
			// Build the workspaces
			$sac_ws = $sac_xml->children($sac_ns['app']);
			foreach ($sac_ws as $sac_workspace) {
				$sac_newworkspace = new Workspace(
			        	            $sac_workspace->children($sac_ns['atom'])->title);
				$sac_newworkspace->buildhierarchy($sac_workspace->children($sac_ns['app']), $sac_ns);
				$this->sac_workspaces[] = $sac_newworkspace;
			}
		}
	}
}

?>
