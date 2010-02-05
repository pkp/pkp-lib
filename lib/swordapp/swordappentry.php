<?php

require_once("utils.php");

class SWORDAPPEntry {

	// The HTTP status code returned
	public $sac_status;
	
	// The XML returned by the deposit
	public $sac_xml;

	// The human readable status code
	public $sac_statusmessage;

	// The atom:id identifier
	public $sac_id;

	// The atom:content value
	public $sac_content_src;
	public $sac_content_type;
	
	// The authors
	public $sac_authors;

	// The contributors
	public $sac_contributors;

	// The links
	public $sac_links;

	// The title
	public $sac_title;

	// The summary
	public $sac_summary;

	// The rights
	public $sac_rights;

	// The update date
	public $sac_updated;

	// The packaging format used
	public $sac_packaging;

	// The generator
	public $sac_generator;
	public $sac_generator_uri;

	// The user agent
	public $sac_useragent;

	// The noOp status
	public $sac_noOp;

	// Construct a new deposit response by passing in the http status code
	function __construct($sac_newstatus, $sac_thexml) {
		// Store the status
		$this->sac_status = $sac_newstatus;

		// Store the xml
		$this->sac_xml = $sac_thexml;

		// Store the status message
		switch($this->sac_status) {
			case 201:
				$this->sac_statusmessage = "Created";
				break;
			case 401:
				$this->sac_statusmessage = "Unauthorized";
				break;
			case 412:
				$this->sac_statusmessage = "Precondition failed";
				break;
			case 413:
				$this->sac_statusmessage = "Request entity too large";
				break;
			case 415:
				$this->sac_statusmessage = "Unsupported media type";
				break;
			default:
				$this->sac_statusmessage = "Unknown erorr (status code " . $this->sac_status . ")";
				break;
		}

		// Initalise arrays
		$this->sac_authors = array();
		$this->sac_contributors = array();
		$this->sac_links = array();

		// Assume noOp is false unless we change it later
		$this->sac_noOp = false;
	}

	// Build the workspace hierarchy
	function buildhierarchy($sac_dr, $sac_ns) {
		// Set the default namespace
		$sac_dr->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
		
		// Parse the results
		$this->sac_id = $sac_dr->children($sac_ns['atom'])->id;
		$sac_contentbits = $sac_dr->xpath("atom:content"); 
		if (!empty($sac_contentbits)) {
			$this->sac_content_src = $sac_contentbits[0]['src'];
			$this->sac_content_type = $sac_contentbits[0]['type'];
		}

		// Store the authors
		foreach ($sac_dr->children($sac_ns['atom'])->author as $sac_author) {
			$sac_theauthor = $sac_author->children($sac_ns['atom'])->name . "";
			$this->sac_authors[] = $sac_theauthor;
		}
		
		// Store the contributors
		foreach ($sac_dr->children($sac_ns['atom'])->contributor as $sac_contributor) {
			$sac_thecontributor = $sac_contributor->children($sac_ns['atom'])->name . "";
			$this->sac_contributors[] = $sac_thecontributor;
		}
		
		// Store the links
		foreach ($sac_dr->xpath("atom:link") as $sac_link) {
			$this->sac_links[] = sac_clean($sac_link[0]['href']);
		}

		// Store the title and summary
		$this->sac_title = sac_clean($sac_dr->children($sac_ns['atom'])->title);
		$this->sac_summary = sac_clean($sac_dr->children($sac_ns['atom'])->summary);

		// Store the updated date
		$this->sac_updated = $sac_dr->children($sac_ns['atom'])->updated;

		// Store the rights
		$this->sac_rights = sac_clean($sac_dr->children($sac_ns['atom'])->rights);

		// Store the format namespace
		$this->sac_packaging = $sac_dr->children($sac_ns['sword'])->packaging;

		// Store the generator
		$this->sac_generator = sac_clean($sac_dr->children($sac_ns['atom'])->generator);
		$sac_gen = $sac_dr->xpath("atom:generator");
		if (!empty($sac_gen)) { $this->sac_generator_uri = $sac_gen[0]['uri']; }

		// Store the user agent
		$this->sac_useragent = sac_clean($sac_dr->children($sac_ns['sword'])->userAgent);

		// Store the noOp status
		if (strtolower((string)$sac_dr->children($sac_ns['sword'])->noOp) == 'true') {
			$this->sac_noOp = true;
		}
	}
}

?>
