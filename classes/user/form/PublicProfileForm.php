<?php

/**
 * @file classes/user/form/PublicProfileForm.php
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

namespace PKP\user\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use PKP\core\Core;
use PKP\user\User;

class PublicProfileForm extends BaseProfileForm
{
    public const PROFILE_IMAGE_MAX_WIDTH = 150;
    public const PROFILE_IMAGE_MAX_HEIGHT = 150;

    /**
     * Constructor.
     *
     * @param User $user
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
     * @return bool True iff success.
     */
    public function uploadProfileImage()
    {
        if (!Application::get()->getRequest()->checkCSRF()) {
            throw new \Exception('CSRF mismatch!');
        }

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

        if ($width > self::PROFILE_IMAGE_MAX_WIDTH || $height > self::PROFILE_IMAGE_MAX_HEIGHT || $width <= 0 || $height <= 0) {
            $userSetting = null;
            $user->setData('profileImage', $userSetting);
            Repo::user()->edit($user, ['profileImage']);
            $publicFileManager->removeSiteFile($filePath);
            return false;
        }

        $user->setData('profileImage', [
            'name' => $publicFileManager->getUploadedFileName('uploadedFile'),
            'uploadName' => $uploadName,
            'width' => $width,
            'height' => $height,
            'dateUploaded' => Core::getCurrentDate(),
        ]);
        Repo::user()->edit($user, ['profileImage']);
        return true;
    }

    /**
     * Delete a profile image.
     *
     * @return bool True iff success.
     */
    public function deleteProfileImage()
    {
        if (!Application::get()->getRequest()->checkCSRF()) {
            throw new \Exception('CSRF mismatch!');
        }

        $user = $this->getUser();
        $profileImage = $user->getData('profileImage');
        if (!$profileImage) {
            return false;
        }

        $publicFileManager = new PublicFileManager();
        if ($publicFileManager->removeSiteFile($profileImage['uploadName'])) {
            $user->setData('profileImage', null);
            Repo::user()->edit($user, ['profileImage']);
            return true;
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
            'profileImage' => $request->getUser()->getData('profileImage'),
            'profileImageMaxWidth' => self::PROFILE_IMAGE_MAX_WIDTH,
            'profileImageMaxHeight' => self::PROFILE_IMAGE_MAX_HEIGHT,
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\PublicProfileForm', '\PublicProfileForm');
}
