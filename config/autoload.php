<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2018 Leo Feyer
 *
 * @license LGPL-3.0+
 */

/**
 * Register namespaces
 */
ClassLoader::addNamespace('Markocupic\Ivm');

/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
    // Classes
    'Markocupic\Ivm\IvmImport'         => 'system/modules/ivm_import/classes/IvmImport.php',
    'Markocupic\Ivm\IvmTemplateHelper' => 'system/modules/ivm_import/classes/IvmTemplateHelper.php',

));

