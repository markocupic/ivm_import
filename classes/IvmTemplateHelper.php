<?php

/*
 * This file is part of Marko Cupic IVM Package.
 *
 * (c) Marko Cupic, 19.03.2019
 * @author Marko Cupic <https://github.com/markocupic/ivm_import>
 * @contact m.cupic@gmx.ch
 * @license Commercial
 */

namespace Markocupic\Ivm;

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
     * @return array
     */
    public static function getFlatIdFromWid($wid)
    {
        $objWohnung = \Database::getInstance()->prepare('SELECT * FROM is_wohnungen WHERE wid=?')->execute($wid);
        if ($objWohnung->numRows)
        {
            return $objWohnung->flat_id;
        }
        return null;
    }

    /**
     * @param $wid
     * @return array
     */
    public static function getGalleryArrayByWid($wid)
    {
        $arrGal = array();
        $objWohnung = \Database::getInstance()->prepare('SELECT * FROM is_wohnungen WHERE wid=?')->execute($wid);
        if ($objWohnung->numRows)
        {
            $arrGallery = self::deserialize($objWohnung->gallery_img);
            if (!empty($arrGallery) && is_array($arrGallery))
            {
                foreach ($arrGallery as $image)
                {
                    $arrGal[] = array(
                        'flat_id' => $objWohnung->flat_id,
                        'name'    => $image['name'],
                        'caption' => $image['info_text'],
                        'path'    => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'img', $image['name']),
                        'thumb'   => sprintf(static::$remoteGalleryFolder, $objWohnung->flat_id, 'th', $image['name']),
                    );
                }
            }
        }
        return $arrGal;
    }

    /**
     * @param $wid
     * @return bool
     */
    public static function hasGallery($wid)
    {
        $objWohnung = \Database::getInstance()->prepare('SELECT * FROM is_wohnungen WHERE wid=?')->execute($wid);
        if ($objWohnung->numRows)
        {
            $arrGallery = self::deserialize($objWohnung->gallery_img, true);
            if (count($arrGallery >= 1))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $strArray
     * @param bool $blnForce
     * @return array|null|string
     */
    private static function deserialize($strArray, $blnForce = false)
    {
        if (version_compare(VERSION, 4.0, '<'))
        {
            return deserialize($strArray, $blnForce);
        }
        else
        {
            return \StringUtil::deserialize($strArray, $blnForce);
        }
    }

}