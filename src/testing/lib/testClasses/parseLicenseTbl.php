<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/**
 * Given a fossology License Broswe page, parse it and return the
 * license table.  The rest of the page can be parsed by the browseMenu
 * class.
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array with  Can return an empty array indicating
 * nothing on the page to browse.
 *
 * @todo add in link fixups and adjust consumers
 *
 * @version "$Id: parseLicenseTbl.php 2865 2010-03-10 19:06:25Z rrando $"
 * Created on Aug 21, 2008
 */

class parseLicenseTbl
{
  public $page;
  private $test;

  function __construct($page)
  {
    if (empty ($page)) { return; }
    $this->page = $page;
  }
  /**
   * function parseLicenseTbl
   * given a fossology license histogram, parse it.
   *
   * @returns array of empty array if no license histogram on that page,
   * else returns an associative array of license names and the value is
   * the link to that license.
   */
  function parseLicenseTbl()
  {
    /*
     * old pattern
     * "|.*?align='right'.*?align='center'><a href='(.*?)'> (. *?)<. *? id='(.*?)'.*?a href=\"(.*?)\">(.*?)<|";
     */
    $pat = "|.*?align='right'.*?<a href='(.*?)'>(.*?)<.*?id='(.*?)'>(.*?)<|";
    $matches = preg_match_all($pat, $this->page, $tableEntries, PREG_PATTERN_ORDER);
    //print "PLTBL: tableEntries are:\n"; print_r($tableEntries) . "\n";
    $rtnList = array ();
    if ($matches > 0)
    {
      $numTblEntries = count($tableEntries[1]);
      for ($i = 0; $i <= $numTblEntries-1; $i++)
      {
      	$cleanName = trim($tableEntries[4][$i]);
        $rtnList[$cleanName] = $tableEntries[1][$i];
      }
      //print "PLTBL: returning this array:\n"; print_r($rtnList) . "\n";
      return ($rtnList);
    }
    else
    {
      return (array ());
    }
  }
}
?>
