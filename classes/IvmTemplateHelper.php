<?php

declare(strict_types=1);

/*
 * This file is part of Marko Cupic IVM Package.
 *
 * (c) Marko Cupic, 19.03.2019
 * @author Marko Cupic <https://github.com/markocupic/ivm_import>
 * @contact m.cupic@gmx.ch
 * @license Commercial
 */

namespace Markocupic\Ivm;

use Contao\Database;
use Contao\StringUtil;
use Contao\System;

/**
 * Class IvmTemplHelper
 * @package Markocupic\Ivm
 */
class IvmTemplateHelper
{

    /**
     * @var string
     */
    protected static $downloadFolder = 'files/Wohnungsangebote';

    /**
     * @var string
     */
    protected static $remoteGalleryFolder = 'https://wg-dessau.ivm-professional.de/_img/gallery/%s/%s_%s';

    /**
     * @param $wid
     * @return int|null
     */
    public static function getFlatIdFromWid($wid): ?int
    {
        $objWohnung = Database::getInstance()
            ->prepare('SELECT * FROM is_wohnungen WHERE wid=?')
            ->execute($wid);
        if ($objWohnung->numRows) {
            return (int)$objWohnung->flat_id; 
        }

        return null;
    }

    /**
     * @param $wid
     * @return array
     */
    public static function getGalleryArrayByWid($wid): array
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $arrImages = [];
        $objWohnung = Database::getInstance()
            ->prepare('SELECT * FROM is_wohnungen WHERE wid=?')
            ->limit(1)
            ->execute($wid);
        if ($objWohnung->numRows) {
            $arrGallery = StringUtil::deserialize($objWohnung->gallery_img, true);

            foreach ($arrGallery as $image) {
                $path = sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'img', $image['name']);
                $thumb = sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'th', $image['name']);
                if (file_exists($projectDir.'/'.$path) && file_exists($projectDir.'/'.$thumb)) {
                    $arrImages[] = [
                        'flat_id' => $objWohnung->flat_id,
                        'name'    => $image['name'],
                        'caption' => $image['info_text'],
                        'path'    => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'img', $image['name']),
                        'thumb'   => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'th', $image['name']),
                    ];
                }
            }
        }

        return $arrImages;
    }

    /**
     * @param $flatId
     * @return array
     */
    public static function getGalleryArrayByFlatId($flatId): array
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $arrImages = [];
        $objWohnung = Database::getInstance()
            ->prepare('SELECT * FROM is_wohnungen WHERE flat_id=?')
            ->limit(1)
            ->execute($flatId);
        if ($objWohnung->numRows) {
            $arrGallery = StringUtil::deserialize($objWohnung->gallery_img, true);

            foreach ($arrGallery as $image) {
                $path = sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'img', $image['name']);
                $thumb = sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'th', $image['name']);
                if (file_exists($projectDir.'/'.$path) && file_exists($projectDir.'/'.$thumb)) {
                    $arrImages[] = [
                        'flat_id' => $objWohnung->flat_id,
                        'name'    => $image['name'],
                        'caption' => $image['info_text'],
                        'path'    => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'img', $image['name']),
                        'thumb'   => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'th', $image['name']),
                    ];
                }
            }
        }

        return $arrImages;
    }

    /**
     * @param $flatId
     * @return bool
     */
    public static function hasGalleryByFlatId($flatId): bool
    {
        if (count(static::getGalleryArrayByFlatId($flatId)) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param $flatId
     * @return bool
     */
    public static function hasGalleryByWid($wid): bool
    {
        if (count(static::getGalleryArrayByWid($wid)) > 0) {
            return true;
        }

        return false;
    }

}
