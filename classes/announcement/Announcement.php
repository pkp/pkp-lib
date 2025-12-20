<?php

/**
 * @file classes/announcement/Announcement.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Announcement
 *
 * @brief Basic class describing announcement existing in the system.
 */

namespace PKP\announcement;

use APP\core\Application;
use APP\file\PublicFileManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\core\traits\ModelWithSettings;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

/**
 * @method static \Illuminate\Database\Eloquent\Builder withContextIds(array $contextIds) accepts valid context IDs or PKPApplication::SITE_CONTEXT_ID as an array values
 * @method static \Illuminate\Database\Eloquent\Builder withTypeIds(array $typeIds) filters results by announcement type IDs
 * @method static \Illuminate\Database\Eloquent\Builder withSearchPhrase(?string $searchPhrase) filters results by a search phrase
 * @method static \Illuminate\Database\Eloquent\Builder withActiveByDate(string $date = '') filters active announcements by date
 */
class Announcement extends Model
{
    use ModelWithSettings;

    // The subdirectory where announcement images are stored
    public const IMAGE_SUBDIRECTORY = 'announcements';

    protected $table = 'announcements';
    protected $primaryKey = 'announcement_id';
    public const CREATED_AT = 'date_posted';
    public const UPDATED_AT = null;
    protected string $settingsTable = 'announcement_settings';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['announcementId', 'id', 'datePosted'];

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_ANNOUNCEMENT;
    }

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

    /**
     * Add Model-level defined multilingual properties
     */
    public function getMultilingualProps(): array
    {
        return array_merge(
            $this->multilingualProps,
            [
                'fullTitle',
            ]
        );
    }

    /**
     * Delete announcement, also allows to delete multiple announcements by IDs at once with destroy() method
     *
     * @return bool|null
     */
    public function delete()
    {
        $deleted = parent::delete();
        if ($deleted) {
            $this->deleteImage();
        }

        return $deleted;
    }

    /**
     * @return bool
     *
     * @hook Announcement::add [[$this]]
     */
    public function save(array $options = [])
    {
        $newlyCreated = !$this->exists;
        $saved = parent::save($options);

        if (!$saved) {
            return $saved;
        }

        Hook::call('Announcement::add', [$this]);

        $hasNewImage = $this?->image?->temporaryFileId ?? null;

        // if announcement is being inserted and includes new image, upload it
        if ($newlyCreated) {
            if ($hasNewImage) {
                $this->handleImageUpload();
            }
            return $saved;
        }

        // The announcement is being updated, check if it contains new image
        if ($hasNewImage) {
            $this->deleteImage();
            $this->handleImageUpload();
        }

        // If there is no new image and image data exists in DB and it's now removed as part of update
        // need to delete the image for this announcement model instance
        if (!$hasNewImage && !$this?->image && $this->fresh()->image) {
            $this->deleteImage();
            DB::table($this->getSettingsTable())
                ->where($this->getkeyName(), $this->getKey())
                ->where('setting_name', 'image')
                ->delete();
        }

        return $saved;
    }

    /**
     * Filter by context IDs, accepts PKPApplication::SITE_CONTEXT_ID as a parameter array value if include site wide
     */
    protected function scopeWithContextIds(EloquentBuilder $builder, array $contextIds): EloquentBuilder
    {
        $filteredIds = [];
        $siteWide = false;
        foreach ($contextIds as $contextId) {
            if ($contextId == PKPApplication::SITE_CONTEXT_ID) {
                $siteWide = true;
                continue;
            }

            $filteredIds[] = $contextId;
        }

        return $builder->where('assoc_type', Application::get()->getContextAssocType())
            ->whereIn('assoc_id', $filteredIds)
            ->when($siteWide, fn (EloquentBuilder $builder) => $builder->orWhereNull('assoc_id'));
    }

    /**
     * Filter by announcement type IDs
     */
    protected function scopeWithTypeIds(EloquentBuilder $builder, array $typeIds): EloquentBuilder
    {
        return $builder->whereIn('type_id', $typeIds);
    }

    /**
     * Limit returned announcements to those that have not expired by the specified date.
     *
     * @param string $date Optionally filter announcements by those
     *    not expired until $date
     */
    protected function scopeWithActiveByDate(EloquentBuilder $builder, Carbon $date = null): EloquentBuilder
    {
        return $builder->where('date_expire', '>', $date ?? Carbon::now())
            ->orWhereNull('date_expire');
    }

    /**
     * Filter announcements by those matching a search query
     */
    protected function scopeWithSearchPhrase(EloquentBuilder $builder, ?string $searchPhrase): EloquentBuilder
    {
        if (is_null($searchPhrase)) {
            return $builder;
        }

        $words = explode(' ', $searchPhrase);
        if (!count($words)) {
            return $builder;
        }

        return $builder->whereIn('announcement_id', function ($builder) use ($words) {
            $builder->select('announcement_id')->from($this->getSettingsTable());
            foreach ($words as $word) {
                $word = strtolower(addcslashes($word, '%_'));
                $builder->where(function ($builder) use ($word) {
                    $builder->where(function ($builder) use ($word) {
                        $builder->where('setting_name', 'title');
                        $builder->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                    })
                        ->orWhere(function ($builder) use ($word) {
                            $builder->where('setting_name', 'descriptionShort');
                            $builder->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                        })
                        ->orWhere(function ($builder) use ($word) {
                            $builder->where('setting_name', 'description');
                            $builder->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                        });
                });
            }
        });
    }

    /**
     * Get the full title
     */
    protected function fullTitle(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
                $multilingualTitle = $attributes['title'];
                if (!isset($attributes['typeId'])) {
                    return $multilingualTitle;
                }

                $type = $announcementTypeDao->getById($attributes['typeId']);
                $typeName = $type->getData('name');

                $multilingualFullTitle = $multilingualTitle;
                foreach ($multilingualTitle as $locale => $title) {
                    if (isset($typeName[$locale])) {
                        $multilingualFullTitle[$locale] = $typeName . ': ' . $title;
                    }
                }

                return $multilingualFullTitle;
            }
        );
    }

    /**
     * Get announcement's image URL
     */
    protected function imageUrl(bool $withTimestamp = true): Attribute
    {
        return Attribute::make(
            get: function () use ($withTimestamp) {
                if (!$this->hasAttribute('image')) {
                    return '';
                }
                $image = $this->getAttribute('image');

                $filename = $image->uploadName;
                if ($withTimestamp) {
                    $filename .= '?' . strtotime($image->dateUploaded);
                }

                $publicFileManager = new PublicFileManager();

                return join('/', [
                    Application::get()->getRequest()->getBaseUrl(),
                    $this->hasAttribute('assocId')
                        ? $publicFileManager->getContextFilesPath($this->getAttribute('assocId'))
                        : $publicFileManager->getSiteFilesPath(),
                    static::IMAGE_SUBDIRECTORY,
                    $filename
                ]);
            }
        );
    }

    /**
     * Get alternative text of the image
     */
    protected function imageAltText(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image?->altText ?? ''
        );
    }

    /**
     * Delete the image related to announcement
     */
    protected function deleteImage(): void
    {
        $image = $this->fresh()->image;

        if ($image && isset($image->uploadName)) {
            $publicFileManager = new PublicFileManager();
            $filesPath = $this->hasAttribute('assocId')
                ? $publicFileManager->getContextFilesPath($this->getAttribute('assocId'))
                : $publicFileManager->getSiteFilesPath();

            $publicFileManager->deleteByPath(
                join('/', [
                    $filesPath,
                    static::IMAGE_SUBDIRECTORY,
                    $image->uploadName,
                ])
            );
        }
    }

    /**
     * Handle image uploads
     *
     * @throws StoreTemporaryFileException Unable to store temporary file upload
     */
    protected function handleImageUpload(): void
    {
        $image = $this->getAttribute('image');

        if (!isset($image?->temporaryFileId)) {
            return;
        }

        $user = Application::get()->getRequest()->getUser();
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile((int) $image->temporaryFileId, $user?->getId());
        $filePath = static::IMAGE_SUBDIRECTORY . '/' . $this->getImageFilename($temporaryFile);
        if (!$this->isValidImage($temporaryFile)) {
            throw new StoreTemporaryFileException($temporaryFile, $filePath, $user, $this);
        }

        if ($this->storeTemporaryFile($temporaryFile, $filePath, $user->getId())) {
            $this->setAttribute(
                'image',
                $this->getImageData($temporaryFile)
            );
            $this->save();
        } else {
            $this->delete();
            throw new StoreTemporaryFileException($temporaryFile, $filePath, $user, $this);
        }
    }

    /**
     * Get the data array for a temporary file that has just been stored
     *
     * @return array Data about the image, like the upload name, alt text, and date uploaded
     */
    protected function getImageData(TemporaryFile $temporaryFile): array
    {
        $image = $this->image;

        return [
            'name' => $temporaryFile->getOriginalFileName(),
            'uploadName' => $this->getImageFilename($temporaryFile),
            'dateUploaded' => Core::getCurrentDate(),
            'altText' => $image->altText ?? '',
        ];
    }

    /**
     * Get the filename of the image upload
     */
    protected function getImageFilename(TemporaryFile $temporaryFile): string
    {
        $fileManager = new FileManager();

        return $this->id
            . $fileManager->getImageExtension($temporaryFile->getFileType());
    }

    /**
     * Check that temporary file is an image
     */
    protected function isValidImage(TemporaryFile $temporaryFile): bool
    {
        if (getimagesize($temporaryFile->getFilePath()) === false) {
            return false;
        }
        $extension = pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION);
        $fileManager = new FileManager();
        $extensionFromMimeType = $fileManager->getImageExtension(
            PKPString::mime_content_type($temporaryFile->getFilePath())
        );
        if ($extensionFromMimeType !== '.' . $extension) {
            return false;
        }

        return true;
    }

    /**
     * Store a temporary file upload in the public files directory
     *
     * @param string $newPath The new filename with the path relative to the public files directoruy
     *
     * @return bool Whether or not the operation was successful
     */
    protected function storeTemporaryFile(TemporaryFile $temporaryFile, string $newPath, int $userId): bool
    {
        $publicFileManager = new PublicFileManager();
        $temporaryFileManager = new TemporaryFileManager();

        if ($assocId = $this->assocId) {
            $result = $publicFileManager->copyContextFile(
                $assocId,
                $temporaryFile->getFilePath(),
                $newPath
            );
        } else {
            $result = $publicFileManager->copySiteFile(
                $temporaryFile->getFilePath(),
                $newPath
            );
        }

        if (!$result) {
            return false;
        }

        $temporaryFileManager->deleteById($temporaryFile->getId(), $userId);

        return $result;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dateExpire' => 'datetime',
            'datePosted' => 'datetime',
        ];
    }
}
