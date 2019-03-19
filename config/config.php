<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 19.03.2019
 * Time: 07:55
 */

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('Markocupic\Ivm\IvmImport','importDatabase');