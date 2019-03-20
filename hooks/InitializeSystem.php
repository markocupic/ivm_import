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

use Contao\Input;

/**
 * Class InitializeSystem
 * @package Markocupic\Ivm
 */
class InitializeSystem
{

    /**
     * InitializeSystem Hook
     */
    public function importIvmDatabase()
    {
        if (strlen(Input::get('ivmImport')))
        {
            $page = Input::get('page') > 1 ? Input::get('page') : 1;
            $blnForce = Input::get('force') == true ? true : false;
            $blnPurgeDownloadFolder = Input::get('purgeDownloadFolder') == true ? true : false;

            // Instantiate IvmImport
            $objImport = new IvmImport();
            $objImport->importIvmDatabase($page, $blnForce, $blnPurgeDownloadFolder);

            exit();
        }
    }
}