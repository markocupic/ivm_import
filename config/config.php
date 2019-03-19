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
 * Hooks
 */
$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('Markocupic\Ivm\IvmImport', 'importDatabase');