<?php

/*
 * This file is part of Marko Cupic IVM Package.
 *
 * (c) Marko Cupic, 19.03.2019
 * @author Marko Cupic <https://github.com/markocupic/ivm_import>
 * @contact m.cupic@gmx.ch
 * @license Commercial
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
    'Markocupic\Ivm\TableConfig'         => 'system/modules/ivm_import/classes/TableConfig.php',
    'Markocupic\Ivm\IvmImport'         => 'system/modules/ivm_import/classes/IvmImport.php',
    'Markocupic\Ivm\IvmTemplateHelper' => 'system/modules/ivm_import/classes/IvmTemplateHelper.php',

    // Hooks
    'Markocupic\Ivm\InitializeSystem'  => 'system/modules/ivm_import/hooks/InitializeSystem.php',

));

