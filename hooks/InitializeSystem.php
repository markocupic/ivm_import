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
     *
     * Import database from https://wg-dessau.ivm-professional.de
     * Contao initializeSystem Hook
     * Normally the script will be launched by a cronjob
     * https://yourhost.de?ivmImport=true or https://yourhost.de?ivmImport=true&force=true or https://yourhost.de?ivmImport=true&force=true&purgeDownloadFolder=true
     * If you run the cronjob with the force=true parameter, downloads (images, flat_plots, exposes) will be downloaded again, even if the file already exists on the destination host
     * Best practice:
     * If script execution time exceeds php max_execution_time, then call the script each day in steps:
     * First call: https://yourhost.de?ivmImport=true&page=1&force=true&purgeDownloadFolder=true
     * Second call: https://yourhost.de?ivmImport=true&page=2&force=true
     * Third call: https://yourhost.de?ivmImport=true&page=3&force=true
     * Fourth call: https://yourhost.de?ivmImport=true&page=4&force=true
     *
     * Afterwards you call the script hourly without the force/page/purgeDownloadFolder parameters:
     * https://yourhost.de?ivmImport=true
     *
     *
     */
    public function importIvmDatabase()
    {
        if (strlen(Input::get('ivmImport')))
        {
            $page = Input::get('page') >= 1 ? Input::get('page') : '';
            $blnForce = Input::get('force') == true ? true : false;
            $blnPurgeDownloadFolder = Input::get('purgeDownloadFolder') == 'true' ? true : false;

            // Instantiate IvmImport
            $objImport = new IvmImport();
            $objImport->importIvmDatabase($page, $blnForce, $blnPurgeDownloadFolder);

            exit();
        }
    }
}