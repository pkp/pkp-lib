<?php

/**
 * @file classes/user/form/PublicProfileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user's public profile.
 */

use APP\file\PublicFileManager;
use APP\template\TemplateManager;

import('lib.pkp.classes.user.form.BaseProfileForm');

define('PROFILE_IMAGE_MAX_WIDTH', 150);
define('PROFILE_IMAGE_MAX_HEIGHT', 150);

class PublicProfileForm extends BaseProfileForm
{
    /**
     * Constructor.
     *
     * @param $user User
     */
    public function __construct($user)
    {
        parent::__construct('user/publicProfileForm.tpl', $user);

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
        $this->addCheck(new \PKP\form\validation\FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
    }

    /**
     * @copydoc BaseProfileForm::initData()
     */
    public function initData()
    {
        $user = $this->getUser();

        $this->_data = [
            'orcid' => $user->getOrcid(),
            'userUrl' => $user->getUrl(),
            'biography' => $user->getBiography(null), // Localized
        ];

        parent::initData();
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'orcid', 'userUrl', 'biography',
        ]);
    }

    /**
     * Upload a profile image.
     *
     * @return boolean True iff success.
     */
    public function uploadProfileImage()
    {
        $publicFileManager = new PublicFileManager();

        $user = $this->getUser();
        $type = $publicFileManager->getUploadedFileType('uploadedFile');
        $extension = $publicFileManager->getImageExtension($type);
        if (!$extension) {
            return false;
        }

        $uploadName = 'profileImage-' . (int) $user->getId() . $extension;
        if (!$publicFileManager->uploadSiteFile('uploadedFile', $uploadName)) {
            return false;
        }
        $filePath = $publicFileManager->getSiteFilesPath();
        [$width, $height] = getimagesize($filePath . '/' . $uploadName);

        if ($width > PROFILE_IMAGE_MAX_WIDTH || $height > PROFILE_IMAGE_MAX_HEIGHT || $width <= 0 || $height <= 0) {
            $userSetting = null;
            $user->updateSetting('profileImage', $userSetting);
            $publicFileManager->removeSiteFile($filePath);
            return false;
        }

        $user->updateSetting('profileImage', [
            'name' => $publicFileManager->getUploadedFileName('uploadedFile'),
            'uploadName' => $uploadName,
            'width' => $width,
            'height' => $height,
            'dateUploaded' => Core::getCurrentDate(),
        ]);
        return true;
    }

    /**
     * Delete a profile image.
     *
     * @return boolean True iff success.
     */
    public function deleteProfileImage()
    {
        $user = $this->getUser();
        $profileImage = $user->getSetting('profileImage');
        if (!$profileImage) {
            return false;
        }

        $publicFileManager = new PublicFileManager();
        if ($publicFileManager->removeSiteFile($profileImage['uploadName'])) {
            return $user->updateSetting('profileImage', null);
        } else {
            return false;
        }
    }

    /**
     * @copydoc BaseProfileForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $publicFileManager = new PublicFileManager();
        $templateMgr->assign([
            'profileImage' => $request->getUser()->getSetting('profileImage'),
            'profileImageMaxWidth' => PROFILE_IMAGE_MAX_WIDTH,
            'profileImageMaxHeight' => PROFILE_IMAGE_MAX_HEIGHT,
            'publicSiteFilesPath' => $publicFileManager->getSiteFilesPath(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $user->setOrcid($this->getData('orcid'));
        $user->setUrl($this->getData('userUrl'));
        $user->setBiography($this->getData('biography'), null); // Localized

        parent::execute(...$functionArgs);
    }
}
