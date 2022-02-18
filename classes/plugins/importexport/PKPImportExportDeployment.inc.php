<?php
/**
 * @defgroup classes_plugins_importexport import/export deployment
 */

/**
 * @file classes/plugins/importexport/PKPImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPImportExportDeployment
 * @ingroup plugins_importexport
 *
 * @brief Base class configuring the import/export process to an
 * application's specifics.
 */

namespace PKP\plugins\importexport;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;

class PKPImportExportDeployment
{
    /** @var array Array of possible validation errors */
    private $xmlValidationErrors = [];

    /** @var bool Indicator that the import/export process has failed */
    private $processFailed = false;

    /** @var mixed The import/export process result */
    public $processResult = null;

    /** @var Context The current import/export context */
    private $_context;

    /** @var User The current import/export user */
    private $_user;

    /** @var Submission The current import/export submission */
    private $_submission;

    /** @var PKPPublication The current import/export publication */
    private $_publication;

    /** @var array The processed import objects IDs */
    private $_processedObjectsIds = [];

    /** @var array Warnings keyed by object IDs */
    private $_processedObjectsErrors = [];

    /** @var array Errors keyed by object IDs */
    private $_processedObjectsWarnings = [];

    /** @var array Connection between the file from the XML import file and the new IDs after they are imported */
    private $_fileDBIds;

    /** @var array Connection between the submission file IDs from the XML import file and the new IDs after they are imported */
    private $_submissionFileDBIds;

    /** @var array Connection between the author id from the XML import file and the DB file IDs */
    private $_authorDBIds;

    /** @var string Base path for the import source */
    private $_baseImportPath = '';

    /** @var array A list of imported root elements to display to the user after the import is complete */
    private $_importedRootEntities;

    /** @var array A list of exported root elements to display to the user after the export is complete */
    private $_exportRootEntities;

    /**
     * Constructor
     *
     * @param Context $context
     * @param User $user optional
     */
    public function __construct($context, $user = null)
    {
        $this->setContext($context);
        $this->setUser($user);
        $this->setSubmission(null);
        $this->setPublication(null);
        $this->setFileDBIds([]);
        $this->setSubmissionFileDBIds([]);
        $this->_processedObjectsIds = [];
        $this->_importedRootEntities = [];
    }

    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the submission node name
     *
     * @return string
     */
    public function getSubmissionNodeName()
    {
        assert(false);
    }

    /**
     * Get the submissions node name
     *
     * @return string
     */
    public function getSubmissionsNodeName()
    {
        assert(false);
    }

    /**
     * Get the representation node name
     */
    public function getRepresentationNodeName()
    {
        assert(false);
    }

    /**
     * Get the namespace URN
     *
     * @return string
     */
    public function getNamespace()
    {
        assert(false);
    }

    /**
     * Get the schema filename.
     *
     * @return string
     */
    public function getSchemaFilename()
    {
        assert(false);
    }


    //
    // Getter/setters
    //
    /**
     * Set the import/export context.
     *
     * @param Context $context
     */
    public function setContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Get the import/export context.
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Set the import/export submission.
     *
     * @param Submission $submission
     */
    public function setSubmission($submission)
    {
        $this->_submission = $submission;
        if ($submission) {
            $this->addProcessedObjectId(ASSOC_TYPE_SUBMISSION, $submission->getId());
        }
    }

    /**
     * Get the import/export submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Set the import/export publication.
     *
     * @param PKPPublication $publication
     */
    public function setPublication($publication)
    {
        $this->_publication = $publication;
        if ($publication) {
            $this->addProcessedObjectId(ASSOC_TYPE_PUBLICATION, $publication->getId());
        }
    }

    /**
     * Get the import/export publication.
     *
     * @return PKPPublication
     */
    public function getPublication()
    {
        return $this->_publication;
    }

    /**
     * Add the processed object ID.
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId
     */
    public function addProcessedObjectId($assocType, $assocId)
    {
        $this->_processedObjectsIds[$assocType][] = $assocId;
    }

    /**
     * Add the error message to the processed object ID.
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId
     * @param string $errorMsg
     */
    public function addError($assocType, $assocId, $errorMsg)
    {
        $this->_processedObjectsErrors[$assocType][$assocId][] = $errorMsg;
    }

    /**
     * Add the warning message to the processed object ID.
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId
     * @param string $warningMsg
     */
    public function addWarning($assocType, $assocId, $warningMsg)
    {
        $this->_processedObjectsWarnings[$assocType][$assocId][] = $warningMsg;
    }

    /**
     * Get the processed objects IDs.
     *
     * @param int $assocType ASSOC_TYPE_...
     *
     * @return array
     */
    public function getProcessedObjectsIds($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsIds)) {
            return $this->_processedObjectsIds[$assocType];
        }
        return null;
    }

    /**
     * Get the processed objects errors.
     *
     * @param int $assocType ASSOC_TYPE_...
     *
     * @return array
     */
    public function getProcessedObjectsErrors($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsErrors)) {
            return $this->_processedObjectsErrors[$assocType];
        }
        return null;
    }
    /**
     * Get the processed objects errors.
     *
     * @param int $assocType ASSOC_TYPE_...
     *
     * @return array
     */

    public function getProcessedObjectsWarnings($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsWarnings)) {
            return $this->_processedObjectsWarnings[$assocType];
        }
        return null;
    }

    /**
     * Remove the processed objects.
     *
     * @param int $assocType ASSOC_TYPE_...
     */
    public function removeImportedObjects($assocType)
    {
        switch ($assocType) {
            case ASSOC_TYPE_SUBMISSION:
                $processedSubmissionsIds = $this->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION);
                if (!empty($processedSubmissionsIds)) {
                    foreach ($processedSubmissionsIds as $submissionId) {
                        if ($submissionId) {
                            Repo::submission()->dao->deleteById($submissionId);
                        }
                    }
                }
                break;
        }
    }

    /**
     * Set the import/export user.
     *
     * @param User $user
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    /**
     * Get the import/export user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Get the array of the inserted file DB Ids.
     *
     * @return array
     */
    public function getFileDBIds()
    {
        return $this->_fileDBIds;
    }

    /**
     * Set the array of the inserted file DB Ids.
     *
     * @param array $fileDBIds
     */
    public function setFileDBIds($fileDBIds)
    {
        return $this->_fileDBIds = $fileDBIds;
    }

    /**
     * Get the file DB Id.
     *
     * @param int $fileId The old file id
     *
     * @return int The new file id
     */
    public function getFileDBId($fileId)
    {
        if (array_key_exists($fileId, $this->_fileDBIds)) {
            return $this->_fileDBIds[$fileId];
        }
        return null;
    }

    /**
     * Set the file DB Id.
     *
     * @param int $fileId The old file id
     * @param int $DBId The new file id
     */
    public function setFileDBId($fileId, $DBId)
    {
        return $this->_fileDBIds[$fileId] = $DBId;
    }

    /**
     * Get the array of the inserted submission file DB Ids.
     *
     * @return array
     */
    public function getSubmissionFileDBIds()
    {
        return $this->_submissionFileDBIds;
    }

    /**
     * Set the array of the inserted submission file DB Ids.
     *
     * @param array $submissionFileDBIds
     */
    public function setSubmissionFileDBIds($submissionFileDBIds)
    {
        return $this->_submissionFileDBIds = $submissionFileDBIds;
    }

    /**
     * Get the submission file DB Id.
     *
     * @return int The new submission file id
     */
    public function getSubmissionFileDBId($submissionFileDBId)
    {
        if (array_key_exists($submissionFileDBId, $this->_submissionFileDBIds)) {
            return $this->_submissionFileDBIds[$submissionFileDBId];
        }
        return null;
    }

    /**
     * Set the submission file DB Id.
     *
     * @param int $submissionFileDBId The old submission file id
     * @param int $DBId The new submission file id
     */
    public function setSubmissionFileDBId($submissionFileDBId, $DBId)
    {
        return $this->_submissionFileDBIds[$submissionFileDBId] = $DBId;
    }

    /**
     * Set the array of the inserted author DB Ids.
     *
     * @param array $authorDBIds
     */
    public function setAuthorDBIds($authorDBIds)
    {
        return $this->_authorDBIds = $authorDBIds;
    }

    /**
     * Get the array of the inserted author DB Ids.
     *
     * @return array
     */
    public function getAuthorDBIds()
    {
        return $this->_authorDBIds;
    }

    /**
     * Get the author DB Id.
     *
     * @param int $authorId
     *
     * @return int?
     */
    public function getAuthorDBId($authorId)
    {
        if (array_key_exists($authorId, $this->_authorDBIds)) {
            return $this->_authorDBIds[$authorId];
        }

        return null;
    }

    /**
     * Set the author DB Id.
     *
     * @param int $authorId
     * @param int $DBId
     */
    public function setAuthorDBId($authorId, $DBId)
    {
        return $this->_authorDBIds[$authorId] = $DBId;
    }

    /**
     * Set the directory location for the import source
     *
     * @param string $path
     */
    public function setImportPath($path)
    {
        $this->_baseImportPath = $path;
    }

    /**
     * Get the directory location for the import source
     *
     * @return string
     */
    public function getImportPath()
    {
        return $this->_baseImportPath;
    }

    /**
     * Add the imported root entities.
     *
     * @param int $assocType ASSOC_TYPE_...
     */
    public function addImportedRootEntity($assocType, $entity)
    {
        $this->_importedRootEntities[$assocType][] = $entity;
    }

    /**
     * Get the imported root entities.
     *
     * @param int $assocType ASSOC_TYPE_...
     */
    public function getImportedRootEntities($assocType)
    {
        if (array_key_exists($assocType, $this->_importedRootEntities)) {
            return $this->_importedRootEntities[$assocType];
        }

        return null;
    }

    /**
     * Set export root entities
     *
     * @param array $exportRootEntities
     */
    public function setExportRootEntities($exportRootEntities)
    {
        $this->_exportRootEntities = $exportRootEntities;
    }

    /**
     * Get export root entities
     *
     * @return array
     */
    public function getExportRootEntities()
    {
        return $this->_exportRootEntities;
    }

    /**
     * Wraps the import process
     *
     * @param string $rootFilter
     * @param string $importXml
     */
    public function import($rootFilter, $importXml)
    {
        $dbConnection = DB::connection();
        try {
            $currentFilter = PKPImportExportFilter::getFilter($rootFilter, $this);

            $dbConnection->beginTransaction();

            libxml_use_internal_errors(true);

            $result = $currentFilter->execute($importXml);

            $this->xmlValidationErrors = array_filter(libxml_get_errors(), function ($a) {
                return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
            });

            libxml_clear_errors();

            $dbConnection->commit();

            $this->processResult = $result;
        } catch (Error | Exception $e) {
            $this->addError(PKPApplication::ASSOC_TYPE_NONE, 0, $e->getMessage());
            $dbConnection->rollBack();

            $this->processFailed = true;
        }
    }

    /**
     * Wraps the export process
     *
     * @param string $rootFilter
     * @param array $exportObjects
     * @param array $opts
     */
    public function export($rootFilter, $exportObjects, $opts = [])
    {
        try {
            $this->setExportRootEntities($exportObjects);

            $currentFilter = PKPImportExportFilter::getFilter($rootFilter, $this, $opts);

            libxml_use_internal_errors(true);
            $result = $currentFilter->execute($exportObjects, true);

            $this->xmlValidationErrors = array_filter(libxml_get_errors(), function ($a) {
                return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
            });

            libxml_clear_errors();

            if (!$result) {
                $this->addError(PKPApplication::ASSOC_TYPE_NONE, 0, 'Export result is empty.');
                $this->processFailed = true;
            }

            $this->processResult = $result;
        } catch (Error | Exception $e) {
            $this->addError(PKPApplication::ASSOC_TYPE_NONE, 0, $e->getMessage());

            $this->processFailed = true;
        }
    }

    /**
     * Getter method for XMLValidation Errors
     *
     * @return array
     */
    public function getXMLValidationErrors()
    {
        return $this->xmlValidationErrors;
    }

    /**
     * Get all public objects, with their
     * respective names as array values.
     *
     * @return array
     */
    protected function getObjectTypes()
    {
        $objectTypes = [
            PKPApplication::ASSOC_TYPE_NONE => __('plugins.importexport.native.common.any'),
            PKPApplication::ASSOC_TYPE_SUBMISSION => __('submission.submission'),
            PKPApplication::ASSOC_TYPE_AUTHOR => __('user.role.author'),
            PKPApplication::ASSOC_TYPE_PUBLICATION => __('submission.publication'),
        ];

        return $objectTypes;
    }

    /**
    * Get object type string.
    *
    * @param mixed $assocType int or null (optional)
    *
    * @return mixed string or array
    */
    public function getObjectTypeString($assocType = null)
    {
        $objectTypes = $this->getObjectTypes();

        if (is_null($assocType)) {
            return $objectTypes;
        } else {
            if (isset($objectTypes[$assocType])) {
                return $objectTypes[$assocType];
            } else {
                assert(false);
            }
        }
    }

    /**
     * Get possible Warnings and Errors from the import/export process
     *
     * @return array
     */
    public function getWarningsAndErrors()
    {
        $problems = [];
        $objectTypes = $this->getObjectTypes();
        foreach ($objectTypes as $assocType => $name) {
            $foundWarnings = $this->getProcessedObjectsWarnings($assocType);
            if (!empty($foundWarnings)) {
                $problems['warnings'][$name][] = $foundWarnings;
            }

            $foundErrors = $this->getProcessedObjectsErrors($assocType);
            if (!empty($foundErrors)) {
                $problems['errors'][$name][] = $foundErrors;
            }
        }

        return $problems;
    }

    /**
     * Get import entities with their names
     *
     * @return array
     */
    public function getImportedRootEntitiesWithNames()
    {
        $rootEntities = [];
        $objectTypes = $this->getObjectTypes();
        foreach ($objectTypes as $assocType => $name) {
            $entities = $this->getImportedRootEntities($assocType);
            if (!empty($entities)) {
                $rootEntities[$name][] = $entities;
            }
        }

        return $rootEntities;
    }

    /**
     * Returns an indication that the import/export process has failed
     *
     * @return bool
     */
    public function isProcessFailed()
    {
        if ($this->processFailed || count($this->xmlValidationErrors) > 0) {
            return true;
        }

        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\importexport\PKPImportExportDeployment', '\PKPImportExportDeployment');
}
