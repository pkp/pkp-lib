<?php

/**
 * @file classes/services/PKPBaseEntityPropertyService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPBaseEntityPropertyService
 * @ingroup services_entity_properties
 *
 * @brief This is a base class which implements EntityPropertyInterface.
 */

namespace PKP\Services\EntityProperties;

use \PKP\Services\Exceptions\InvalidServiceException;

abstract class PKPBaseEntityPropertyService implements EntityPropertyInterface {

	/** @var object $service */
	protected $service = null;

	/**
	 * Constructor
	 * @param object $service
	 * @throws PKP\Services\Exceptions\InvalidServiceException
	 */
	public function __construct($service) {
		$serviceNamespace = (new \ReflectionObject($service))->getNamespaceName();
		if (!in_array($serviceNamespace, array('PKP\Services', 'OJS\Services', 'OMP\Services'))) {
			throw new InvalidServiceException();
		}

		$this->service = $service;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	abstract public function getProperties($entity, $props, $args = null);

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	abstract public function getSummaryProperties($entity, $args = null);

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	abstract public function getFullProperties($entity, $args = null);

	/**
	 * Build a URL to an object in the API
	 *
	 * This method builds the correct URL depending on whether disable_path_info
	 * is enabled in the config file.
	 *
	 * @param Request $request
	 * @param string $contextPath
	 * @param string $apiVersion
	 * @param string $baseEndpoint Example: 'submissions'
	 * @param string $endpointParams Example: '1', '1/galleys'
	 * @return string
	 */
	public function getAPIHref($request, $contextPath, $apiVersion, $baseEndpoint = '', $endpointParams = '') {

		$fullBaseEndpoint = sprintf('/%s/api/%s/%s', $contextPath, $apiVersion, $baseEndpoint);

		$baseUrl = $request->getBaseUrl();
		if (!$request->isRestfulUrlsEnabled()) {
			$baseUrl .= '/index.php';
		}

		if ($request->isPathInfoEnabled()) {
			return sprintf('%s%s/%s', $baseUrl, $fullBaseEndpoint, $endpointParams);
		}

		return sprintf('%s?journal=%s&endpoint=%s/%s', $baseUrl, $contextPath, $fullBaseEndpoint, $endpointParams);
	}
}
