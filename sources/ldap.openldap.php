<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      ldap.openldap.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Models\OpenLDAP\Group;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Get the user's AD groups.
 *
 * @param string $userDN
 * @param Connection $connection
 * @param array $SETTINGS
 *
 * @return array
 */
function getUserADGroups(string $userDN, Connection $connection, array $SETTINGS): array
{
    // init
    $groupsArr = [];
    
    try {
        Container::addConnection($connection);
        
        // get id attribute
        if (isset($SETTINGS['ldap_guid_attibute']) ===true && empty($SETTINGS['ldap_guid_attibute']) === false) {
            $idAttribute = $SETTINGS['ldap_guid_attibute'];
        } else {
            $idAttribute = 'objectguid';
        }

        // Get user groups from AD
        $user = User::find($userDN);
        $groups = $user->groups()->get();
        foreach ($groups as $group) {
            array_push(
                $groupsArr,
                $group[$idAttribute][0]
            );
        }
    } catch (\LdapRecord\Auth\BindException $e) {
        // Do nothing
    }

    return [
        'error' => false,
        'message' => '',
        'userGroups' => $groupsArr,
    ];
}


function getADGroups(Connection $connection, array $SETTINGS): array
{
    // init
    $groupsArr = [];
    
    try {
        Container::addConnection($connection, $connectionName = 'default');
        
        // get id attribute
        if (isset($SETTINGS['ldap_guid_attibute']) ===true && empty($SETTINGS['ldap_guid_attibute']) === false) {
            $idAttribute = $SETTINGS['ldap_guid_attibute'];
        } else {
            $idAttribute = 'objectguid';
        }

        // Get user groups from AD
        $groups = \LdapRecord\Models\OpenLDAP\Group::get();
        $groups = Group::on($connectionName)->get();
        print_r($groups);
        foreach ($groups as $group) {
            array_push(
                $groupsArr,
                $group[$idAttribute][0]
            );
        }
        
    } catch (\LdapRecord\Auth\BindException $e) {
        // Do nothing
    }

    return [
        'error' => false,
        'message' => '',
        'userGroups' => $groups,
    ];
}