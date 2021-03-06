<?php
/***************************************************************
 Copyright (C) 2018 Siemens AG
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
 ***************************************************************/
/**
 * @file
 * @brief Controller for search queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\SearchResult;

require_once dirname(dirname(__DIR__)) . "/search-helper.php";

/**
 * @class SearchController
 * @brief Controller for Search model
 */
class SearchController extends RestController
{
  /**
   * Perform a search on FOSSology
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */

  /**
   * Get query parameter name for page listing
   */
  const PAGE_PARAM = "page";

  /**
   * Get query parameter name for limiting listing
   */
  const LIMIT_PARAM = "limit";

  /**
   * Limit of uploads in get query
   */
  const SEARCH_FETCH_LIMIT = 100;

  public function performSearch($request, $response, $args)
  {
    $searchType = $request->getHeaderLine("searchType");
    $filename = $request->getHeaderLine("filename");
    $tag = $request->getHeaderLine("tag");
    $filesizeMin = $request->getHeaderLine("filesizemin");
    $filesizeMax = $request->getHeaderLine("filesizemax");
    $license = $request->getHeaderLine("license");
    $copyright = $request->getHeaderLine("copyright");
    $uploadId = $request->getHeaderLine("uploadId");
    $retVal = null;

    $page = $request->getHeaderLine(self::PAGE_PARAM);
    if (! empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        $info = new Info(400, "page should be positive integer > 0",
          InfoType::ERROR);
        $retVal = $response->withJson($info->getArray(), $info->getCode());
      }
    } else {
      $page = 1;
    }

    $limit = $request->getHeaderLine(self::LIMIT_PARAM);
    if (! empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        $info = new Info(400, "limit should be positive integer > 1",
          InfoType::ERROR);
        $retVal = $response->withJson($info->getArray(), $info->getCode());
      }
    } else {
      $limit = self::SEARCH_FETCH_LIMIT;
    }

    // set searchtype to search allfiles by default
    if (empty($searchType)) {
      $searchType = "allfiles";
    }

    // set uploadId to 0 - search in all files
    if (empty($uploadId)) {
      $uploadId = 0;
    }

    /*
     * check if at least one parameter was given
     */
    if (empty($filename) && empty($tag) && empty($filesizeMin) &&
      empty($filesizeMax) && empty($license) && empty($copyright)) {
      $returnVal = new Info(400,
        "Bad Request. At least one parameter, containing a value is required",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    /*
     * check if filesizeMin && filesizeMax are numeric, if existing
     */
    if ((! empty($filesizeMin) && (! is_numeric($filesizeMin) || $filesizeMin < 0)) ||
      (! empty($filesizeMax) && (! is_numeric($filesizeMax) || $filesizeMax < 0))) {
      $returnVal = new Info(400,
        "Bad Request. filesizemin and filesizemax need to be positive integers!",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    if ($retVal !== null) {
      return $retVal;
    }

    $item = GetParm("item", PARM_INTEGER);
    list($results, $totalPages) = GetResults($item, $filename, $uploadId, $tag, $page - 1,
      $filesizeMin, $filesizeMax, $searchType, $license, $copyright,
      $this->restHelper->getUploadDao(), $this->restHelper->getGroupId(),
      $GLOBALS['PG_CONN'], $limit);

    $totalPages = intval(ceil($totalPages / $limit));
    if ($page > $totalPages) {
      $info = new Info(400, "Can not exceed total pages: $totalPages",
        InfoType::ERROR);
      return $response->withHeader("X-Total-Pages", $totalPages)
        ->withJson($info->getArray(), $info->getCode());
    }

    $searchResults = [];
    // rewrite it and add additional information about it's parent upload
    for ($i = 0; $i < sizeof($results); $i ++) {
      $currentUpload = $this->dbHelper->getUploads(
        $this->restHelper->getUserId(), $this->restHelper->getGroupId(), 1, 1,
        $results[$i]["upload_fk"], null, true)[1];
      if (! empty($currentUpload)) {
        $currentUpload = $currentUpload[0];
      } else {
        continue;
      }
      $uploadTreePk = $results[$i]["uploadtree_pk"];
      $filename = $this->dbHelper->getFilenameFromUploadTree($uploadTreePk);
      $currentResult = new SearchResult($currentUpload, $uploadTreePk, $filename);
      $searchResults[] = $currentResult->getArray();
    }
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($searchResults, 200);
  }
}
