<?php

/**
 * @copyright   Copyright (C) 2021 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2021-04-15
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-org-extension/0.1.0',
    array(
        // Identification
        //
        'label' => 'Organization Description Field',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-portal/0.0.0',
        ),
        'mandatory' => false,
        'visible' => true,

        // Components
        //
        'datamodel' => array(
            'model.br-org-extension.php'
        ),
        'webservice' => array(),
        'data.struct' => array(
            // add your 'structure' definition XML files here,
        ),
        'data.sample' => array(
            // add your sample data XML files here,
        ),

        // Documentation
        //
        'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
        'doc.more_information' => '', // hyperlink to more information, if any

        // Default settings
        //
        'settings' => array(
            // Module specific settings go here, if any
        ),
    )
);