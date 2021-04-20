<?php

/**
 * @file classes/note/Note.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Note
 * @ingroup note
 *
 * @see NoteDAO
 * @brief Class for Note.
 */


class Note extends \PKP\core\DataObject
{
    /**
     * get user id of the note's author
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * set user id of the note's author
     *
     * @param $userId int
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }

    /**
     * Return the user of the note's author.
     *
     * @return User
     */
    public function getUser()
    {
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        return $userDao->getById($this->getUserId(), true);
    }

    /**
     * get date note was created
     *
     * @return date (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateCreated()
    {
        return $this->getData('dateCreated');
    }

    /**
     * set date note was created
     *
     * @param $dateCreated date (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateCreated($dateCreated)
    {
        $this->setData('dateCreated', $dateCreated);
    }

    /**
     * get date note was modified
     *
     * @return date (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateModified()
    {
        return $this->getData('dateModified');
    }

    /**
     * set date note was modified
     *
     * @param $dateModified date (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateModified($dateModified)
    {
        $this->setData('dateModified', $dateModified);
    }

    /**
     * get note contents
     *
     * @return string
     */
    public function getContents()
    {
        return $this->getData('contents');
    }

    /**
     * set note contents
     *
     * @param $contents string
     */
    public function setContents($contents)
    {
        $this->setData('contents', $contents);
    }

    /**
     * get note title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getData('title');
    }

    /**
     * set note title
     *
     * @param $title string
     */
    public function setTitle($title)
    {
        $this->setData('title', $title);
    }

    /**
     * get note type
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * set note type
     *
     * @param $assocType int
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * get note assoc id
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * set note assoc id
     *
     * @param $assocId int
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }
}
