#!/usr/bin/env php
<?php
/***********************************************************
 Copyright (C) 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Db\DbManager;

require_once 'dbmigrate_3.5-3.6.php';
require_once 'fossinit-common.php';

/**
 * @file
 * @brief Migrate DB to for Software Heritage by calculating SHA256 of pfiles
 * tables.
 */

/**
 * @brief Check if current user can access conf files.
 *
 * Check if current user is root or fossy
 * @returns True if user can access the files, false otherwise.
 */
function checkCorrectUser()
{
  $processUser = posix_getpwuid(posix_geteuid());
  if ($processUser['uid'] !== 0 && $processUser['name'] != "fossy") {
    return false;
  }
  return true;
}

function explainPfileUsage()
{
  global $argv;

  $usage = "Usage: " . basename($argv[0]) . " [options]
  Calculate the SHA256 hash of files pointed by pfile table. Options are:
  -c  path to fossology configuration files
  -d  {database name} default is 'fossology'
  -h  this help usage";
  print "$usage\n";
  exit(0);
}

/* Condition must be satisfied */
if (!checkCorrectUser()) {
  echo "Error: " . basename($argv[0]) . " must either run by root or fossy " .
    "user!\n";
  exit(-2);
}

/* Note: php 5 getopt() ignores options not specified in the function call, so add
 * dummy options in order to catch invalid options.
 */
$AllPossibleOpts = "abc:d:efghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

/* defaults */
$DatabaseName = "fossology";
$sysconfdir = "/usr/local/etc/fossology";
$force = true;

/* command-line options */
$Options = getopt($AllPossibleOpts);
foreach($Options as $optKey => $optVal)
{
  switch($optKey)
  {
    case 'c': /* set SYSCONFIDR */
      $sysconfdir = $optVal;
      break;
    case 'd': /* optional database name */
      $DatabaseName = $optVal;
      break;
    case 'h': /* help */
      explainPfileUsage();
      break;
    default:
      echo "Invalid Option \"$optKey\".\n";
      explainUsage();
  }
}

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap($sysconfdir);
$SysConf["DBCONF"]["dbname"] = $DatabaseName;
$GLOBALS["SysConf"] = array_merge($GLOBALS["SysConf"], $SysConf);
$projectGroup = $SysConf['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';
$gInfo = posix_getgrnam($projectGroup);
posix_setgid($gInfo['gid']);
$groups = `groups`;
if (!preg_match("/\s$projectGroup\s/",$groups) && (posix_getgid() != $gInfo['gid']))
{
  print "FATAL: You must be in group '$projectGroup'.\n";
  exit(1);
}

require_once("$MODDIR/vendor/autoload.php");
require_once("$MODDIR/lib/php/common-db.php");
require_once("$MODDIR/lib/php/common-container.php");
require_once("$MODDIR/lib/php/common-sysconfig.php");

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

$dbManager = $GLOBALS["container"]->get("db.manager");

updatePfileSha256($dbManager, $force);
