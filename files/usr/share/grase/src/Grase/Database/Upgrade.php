<?php
/* Copyright 2008-2014 Timothy White */

/*  This file is part of GRASE Hotspot.

    http://grasehotspot.org/

    GRASE Hotspot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    GRASE Hotspot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with GRASE Hotspot.  If not, see <http://www.gnu.org/licenses/>.
*/
namespace Grase\Database;


class Upgrade
{
    protected $radius;
    protected $radmin;
    protected $DBF;

    protected $rowsUpdated = 0;

    public function __construct(Database $radius, Database $radmin, $databasefunctions)
    {
        $this->radius = $radius->conn;
        $this->radmin = $radmin->conn;
        $this->DBF = $databasefunctions;
    }

    public function upgradeDatabase($Settings)
    {
        $olddbversion = $Settings->getSetting("DBVersion");

        try {
            if ($olddbversion < 1.1) {
                $this->cleartextAttribute();
                $Settings->setSetting("DBVersion", 1.1);
            }

            if ($olddbversion < 1.2) {
                $this->onePointTwo($Settings);
                $Settings->setSetting("DBVersion", 1.2);
            }

            if ($olddbversion < 1.3) {
                $this->groupSimultaneousDefaults();
                $Settings->setSetting("DBVersion", 1.3);
            }

            if ($olddbversion < 1.4) {
                $this->defaultTemplates($Settings);
                $Settings->setSetting("DBVersion", 1.4);
            }

            if ($olddbversion < 1.5) {
                $this->defaultNetworkSettings($Settings);
                $Settings->setSetting("DBVersion", 1.5);
            }

            if ($olddbversion < 1.6) {
                $this->fixGroupAttributes();
                $Settings->setSetting("DBVersion", 1.6);
            }

            if ($olddbversion < 1.7) {
                $this->addAccessLevelColumn();
                $Settings->setSetting("DBVersion", 1.7);
            }

            if ($olddbversion < 1.8) {
                $this->defaultNetworkInterfaces($Settings);
                $this->walledGardenData();
                $Settings->setSetting("DBVersion", 1.8);
            }

            if ($olddbversion < 1.9) {
                $this->migrateLastBatch($Settings);
                $Settings->setSetting("DBVersion", 1.9);
            }

            if ($olddbversion < 2.0) {
                $this->migrateGroups($Settings);
                $Settings->setSetting("DBVersion", 2.0);
            }

            if ($olddbversion < 2.1) {
                $this->fixGroupNameIndex();
                $Settings->setSetting("DBVersion", 2.1);
            }

            if ($olddbversion < 2.2) {
                $this->fixPostAuthTable();
                $Settings->setSetting("DBVersion", 2.2);
            }

            if ($olddbversion < 2.3) {
                $this->fixServiceTypeOP();
                $Settings->setSetting("DBVersion", 2.3);
            }

            if ($olddbversion < 2.4) {
                $this->createAutocreatePassword($Settings);
                $Settings->setSetting("DBVersion", 2.4);
            }

            if ($olddbversion < 2.5) {
                $this->truncatePostAuth();
                $Settings->setSetting("DBVersion", 2.5);
            }
        } catch (PDOException $Exception) {
            return T_('Upgrading DB failed: ') . $Exception->getMessage() . ': ' . $Exception->getCode();
        }

        if ($this->rowsUpdated > 0) {
            return T_('Database upgraded') . ' ' . $this->rowsUpdated;
        }

        return false;
    }

    // < 1.1
    private function cleartextAttribute()
    {
        $count = $this->radius->exec(
            "UPDATE radcheck
                                SET Attribute='Cleartext-Password'
                                WHERE Attribute='Password'"
        );
        $this->rowsUpdated += $count;
    }

    // < 1.2
    private function onePointTwo($Settings)
    {
        try {
            // remove unique key from radreply
            $this->rowsUpdated += $this->radius->exec("DROP INDEX userattribute ON radreply");
        } catch (\PDOException $Exception) { // We want to ignore this exception as we don't care if the index exists
        }


        // Add Radius Config user for Coova Chilli Radconfig
        $this->rowsUpdated += $this->DBF->setUserPassword(RADIUS_CONFIG_USER, RADIUS_CONFIG_PASSWORD);

        // Set Radius Config user Service-Type to filter it out of normal users
        $result = $this->DBF->replace_radcheck_query(
            RADIUS_CONFIG_USER,
            'Service-Type',
            '==',
            'Administrative-User'
        );

        if (\PEAR::isError($result)) {
            return T_('Upgrading DB failed: ') . $result->toString();
        }

        $this->rowsUpdated += $result;

        // Add default macpasswd string
        $this->rowsUpdated += $this->DBF->setChilliConfigSingle('macpasswd', 'password');
        // Add default defidelsession
        $this->rowsUpdated += $this->DBF->setChilliConfigSingle('defidletimeout', '600');

        // Set last change time
        $Settings->setSetting('lastchangechilliconf', time());
        $this->rowsUpdated += 1;

        //Install default groups
        $dgroup["Staff"] = "+6 months";
        $dgroup["Ministry"] = "+6 months";
        $dgroup["Students"] = "+3 months";
        $dgroup["Visitors"] = "+1 months";
        $Settings->setSetting("groups", serialize($dgroup));
        $this->rowsUpdated += 1;
    }

    // < 1.3
    private function groupSimultaneousDefaults()
    {
        // Set default groups to not allow simultaneous use
        $this->rowsUpdated += $this->DBF->setGroupSimultaneousUse("Staff", 1);
        $this->rowsUpdated += $this->DBF->setGroupSimultaneousUse("Ministry", 1);
        $this->rowsUpdated += $this->DBF->setGroupSimultaneousUse("Students", 1);
        $this->rowsUpdated += $this->DBF->setGroupSimultaneousUse("Visitors", 1);
    }

    // < 1.4
    private function defaultTemplates($Settings)
    {
        // loginhelptext: displayed above login form in main portal page
        $Settings->setTemplate(
            'loginhelptext',
            '
                <p>By logging in, you are agreeing to the following:</p>
                <ul>
                    <li><strong>All network activity will be monitored, this includes: websites, bandwidth usage, protocols</strong></li>
                    <li><strong>You will not access sites containing explicit or inappropriate material</strong></li>
                    <li><strong>You will not attempt to access any system on this network</strong></li>
                </ul>
            '
        );

        // helptext: page contents of info & help file
        $Settings->setTemplate(
            'helptext',
            '<p>For payment and an account, please contact the Office during office hours.</p>
            <p>For a quick logout, bookmark <a href="http://10.1.0.1:3990/logoff">LOGOUT</a>, this link will instantly log you out, and return you to the Welcome page.<br/>
            To get back to the status page, bookmark ether the Non javascript version (<a href="./nojsstatus" target="grasestatus">Hotspot Status nojs</a>), or the preferred javascript version (<a href="javascript: loginwindow = window.open("http://10.1.0.1/grase/uam/mini", "grasestatus", "width=300,height=400,location=no,directories=no,status=yes,menubar=no,toolbar=no"); loginwindow.focus();">Hotspot Status</a>). You can just drag ether link to your bookmark bar to easily bookmark them.</p>

            <p>Your Internet usage is limit by the amount of data that flows to and from your computer, or the amount of time spent online (depending on what you account type is). To maximise your account, you may wish to do the following:</p>
            <ul>
                <li>Browse with images turned off</li>
                <li>Resize all photos before uploading (800x600 is a good size for uploading to the internet, or emailing)</li>
                <li>Ensure antivirus programs do not attempt to update the program (you probably still want them to update the virus definition files).</li>
                <li>Use a client program for email instead of using webmail.</li>
                <li>Ensure when you finish using the Internet, you click logout so that other users won\'t be able to use your account</li>
            </ul>
            '
        );

        // maincss: main css override for login portal
        $Settings->setTemplate('maincss', '');

        // loggedinnojshtml: html to show on successful login
        $Settings->setTemplate(
            'loggedinnojshtml',
            '
           <p>Your login was successful. Please click <a href="nojsstatus" target="grasestatus">HERE</a> to open a status window<br/>If you don\'t open a status window, then bookmark the link <a href="http://logout/">http://logout/</a> so you can logout when finished.</p>
           '
        );

        $this->rowsUpdated += 4;
    }

    // < 1.5
    private function  defaultNetworkSettings($Settings)
    {
        // Load default network settings (match old chilli config)
        $net['lanipaddress'] = '10.1.0.1';
        $net['networkmask'] = '255.255.255.0';
        $net['opendnsbogusnxdomain'] = true;
        $net['dnsservers'] = array('208.67.222.123', '208.67.220.123'); // OpenDNS Family Shield
        $net['bogusnx'] = array();

        $Settings->setSetting('networkoptions', serialize($net));
        $Settings->setSetting('lastnetworkconf', time());

        $this->rowsUpdated += 2;
    }

    // < 1.6
    private function fixGroupAttributes()
    {
        // Move groupAttributes to the correct table
        foreach ($this->DBF->getGroupAttributes() as $name => $group) {

            $this->DBF->setGroupAttributes($name, $group);
            $this->rowsUpdated++;
        }
    }

    // < 1.7
    private function addAccessLevelColumn()
    {
        $this->radmin->exec("ALTER TABLE auth DROP COLUMN accesslevel");

        $this->rowsUpdated += $this->radmin->exec("ALTER TABLE auth ADD COLUMN accesslevel INT NOT NULL DEFAULT 1");
    }

    // < 1.8
    private function defaultNetworkInterfaces($Settings)
    {
        $interfaces = \Grase\Util::getDefaultNetworkIFS();
        $networkoptions = unserialize($Settings->getSetting('networkoptions'));
        $networkoptions['lanif'] = $interfaces['lanif'];
        $networkoptions['wanif'] = $interfaces['wanif'];

        $Settings->setSetting('networkoptions', serialize($networkoptions));

        $Settings->setSetting('lastnetworkconf', time());
        $this->rowsUpdated += 2;
    }

    private function walledGardenData()
    {
        // New chilli settings for garden
        $this->rowsUpdated += $this->DBF->setChilliConfigSingle('nousergardendata', '');
    }

    // < 1.9
    private function migrateLastBatch($Settings)
    {
        // Get last batch and migrate it to new batch system
        $lastbatch = $Settings->getSetting('lastbatch');
        // Check if lastbatch is an array, if so then we migrate
        if (is_array(unserialize($lastbatch))) {
            $lastbatchusers = unserialize($lastbatch);
            $nextBatchID = $Settings->nextBatchID();
            $results += $Settings->saveBatch($nextBatchID, $lastbatchusers);
            // Lastbatch becomes an ID
            $Settings->setSetting('lastbatch', $nextBatchID);
        } else {
            $Settings->setSetting('lastbatch', 0);
        }
    }

    // < 2.0
    private function migrateGroups($Settings)
    {
        // Migrate groups to new system
        $groups = unserialize($Settings->getSetting('groups'));

        $groupattributes = $this->DBF->getGroupAttributes();

        foreach ($groups as $group => $expiry) {
            $attributes = array();
            $attributes['GroupName'] = clean_groupname($group);
            $attributes['GroupLabel'] = $group;
            $attributes['Expiry'] = @ $expiry;
            $attributes['MaxOctets'] = @ $groupattributes[$group]['MaxOctets'];
            $attributes['MaxSeconds'] = @ $groupattributes[$group]['MaxSeconds'];
            // No comment stored, but oh well
            $attributes['Comment'] = @ $groupattributes[$group]['Comment'];

            $this->rowsUpdated += $Settings->setGroup($attributes);
        }

        $Settings->setSetting('groups', serialize(''));
    }

    // < 2.1
    private function fixGroupNameIndex()
    {
        // Remove uniq index on radgroupcheck
        $this->rowsUpdated += $this->radius->exec("DROP INDEX GroupName ON radgroupcheck");

        $this->rowsUpdated += $this->radius->exec("ALTER TABLE radgroupcheck ADD KEY `GroupName` (`GroupName`(32))");
    }

    // < 2.2
    private function fixPostAuthTable()
    {
        $this->truncatePostAuth();

        // Just drop columns we are fixing, easier than checking for existance
        $this->rowsUpdated += $this->radius->exec(
            "
                        ALTER TABLE radpostauth
                          DROP COLUMN ServiceType,
                          DROP COLUMN FramedIPAddress,
                          DROP COLUMN CallingStationId"
        );

        // Add columns back in correctly
        $this->rowsUpdated += $this->radius->exec(
            "ALTER TABLE radpostauth
                ADD COLUMN ServiceType varchar(32) DEFAULT NULL,
                ADD COLUMN FramedIPAddress varchar(15) DEFAULT NULL,
                ADD COLUMN CallingStationId varchar(50) DEFAULT NULL"
        );
    }

    // < 2.3
    private function fixServiceTypeOP()
    {
        // Previously we incorrectly set Service-Type op to := instead of ==
        // Set Radius Config user Service-Type to filter it out of normal users
        $this->rowsUpdated += $this->DBF->replace_radcheck_query(
            RADIUS_CONFIG_USER,
            'Service-Type',
            '==',
            'Administrative-User'
        );
    }

    // < 2.4
    private function createAutocreatePassword($Settings)
    {
        // Create the autocreatepassword setting, with a random string if it
        // doesn't already exist
        // Check that setting doesn't already exist as changing an existing
        // password will lock users out
        if (!$Settings->getSetting("autocreatepassword")) {
            $Settings->setSetting("autocreatepassword", \Grase\Util::randomPassword(20));

            $this->rowsUpdated++;
        }
    }

    // < 2.5
    private function truncatePostAuth()
    {
        // Assume we are doing an upgrade from before postauth was
        // truncated and so we'll just truncate postauth to save time
        $this->rowsUpdated += $this->radius->exec("TRUNCATE radpostauth");
    }
} 