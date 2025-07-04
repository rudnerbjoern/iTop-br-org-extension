<?php

/**
 * @copyright   Copyright (C) 2021-2025 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2025-06-12
 *
 * iTop module definition file
 */

/** @disregard P1009 Undefined type SetupWebPage */
SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-org-extension/3.2.0',
    array(
        // Identification
        //
        'label' => 'Organization Description Field',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-config-mgmt/3.2.0',
        ),
        'mandatory' => false,
        'visible' => true,
        'installer' => 'OrgExtensionInstaller',

        // Components
        //
        'datamodel' => array(),
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

if (!class_exists('OrgExtensionInstaller')) {
    /**
     * Class OrgExtensionInstaller
     *
     * @since v3.1.4
     */
    class OrgExtensionInstaller extends ModuleInstallerAPI
    {

        public static function BeforeWritingConfig(Config $oConfiguration)
        {
            // If you want to override/force some configuration values, do it here
            return $oConfiguration;
        }
        public static function AfterDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
        {
            if (version_compare($sPreviousVersion, '3.1.4', '<')) {

                SetupLog::Info("|- Upgrading br-org-extension from '$sPreviousVersion' to '$sCurrentVersion'.");

                $oSearch = DBSearch::FromOQL('SELECT Organization WHERE parent_id = 0');
                $oSet = new DBObjectSet($oSearch, array(), array());
                if ($oSet->Count() > 0) {
                    while ($oOrg = $oSet->Fetch()) {
                        $sOrgName = $oOrg->Get('name');
                        $oOrg->i_NameChanged = true;
                        $oOrg->SetNicename();
                        $oOrg->DBUpdate();
                        $sOrgNicename = $oOrg->Get('nicename');
                        SetupLog::Info("|  |- Organization '$sOrgName' Nicename changed to '$sOrgNicename'.");
                    }
                }
            }
        }
    }
}
