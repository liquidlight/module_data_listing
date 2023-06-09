<?php

/**
 * Render the fe_users table
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Controller;

use LiquidLight\ModuleDataListing\Controller\DatatableController;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Response;

class FeUsersController extends DatatableController
{
	/**
	 * Table
	 *
	 * @var array
	 * @access protected
	 */
	protected $table = 'fe_users';

	/**
	 * Table headers
	 *
	 * @var array
	 * @access protected
	 */
	protected $headers = [
		'fe_users.uid' => 'ID',
		'fe_users.username' => 'Username',
		'fe_users.usergroup' => 'Group',
		'fe_users.title' => 'Title',
		'fe_users.first_name' => 'First Name',
		'fe_users.last_name' => 'Last Name',
		'fe_users.email' => 'Email',
	];
	
	/**
	 * Unix timestamp columns to be processed
	 *
	 * @var array
	 */
	protected $dateColumns = [
		'tstamp',
		'starttime',
		'endtime',
		'crdate',
		'lastlogin',
		'is_online',
	];

	protected $moduleName = 'tx_moduledatalisting';

	/**
	 * Init view
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		// Load the JS
		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/ModuleDataListing/FeUsersDataTable');
		}
	}

	/**
	 * Render DataTables ajax call
	 */
	public function renderAjax(ServerRequestInterface $request): Response
	{
		$params = $request->getQueryParams();
		$uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

		// Get the data
		$tableData = parent::getTableData($params);

		// Get the data count for pagination
		$count = parent::getCount($params);

		// Format data
		$data = [];
		foreach ($tableData as $row) {
			// Build the edit link
			$returnUrl = $uriBuilder->buildUriFromRoute('datalisting_ModuleDataListingTxModuleDataListingFeusers', []);

			$uriParameters = [
				'edit' => [
					$this->table => [
						$row['uid'] => 'edit',
					],
				],
				'returnUrl' => $returnUrl->getPath() . '?' . $returnUrl->getQuery(),
			];
			$editLink = $uriBuilder->buildUriFromRoute('record_edit', $uriParameters);

			// Wrap the uid in the edit link
			$row['uid'] = '<a href="' . $editLink . '" title="Edit record">' . $row['uid'] . '</a>';

			// Lookup the usergroups and replace IDs with titles
			$usergroups = [];
			foreach (explode(',', $row['usergroup']) as $usergroupUid) {
				$usergroups[] = $this->usergroupLookup($usergroupUid);
			}
			$row['usergroup'] = implode(', ', $usergroups);
			
			// Format unix timestamp fields
			foreach ($this->dateColumns as $dateColumn) {
				$row[$dateColumn] = isset($row[$dateColumn]) ? date('d/m/Y H:i:s', $row[$dateColumn]) : 'N/A';
			}

			$data[] = array_values($row);
		}

		$return = [
			"draw" => $params['draw'],
			"recordsTotal" => $count,
			"recordsFiltered" => $count,
			"data" => $data,
		];
		$response = new Response();

		$response->getBody()->write(json_encode($return));

		return $response;
	}

	/**
	 * Default action: index
	 */
	public function indexAction(): void
	{
		$this->view->assignMultiple([
			'headers' => array_values(parent::getHeaders($this->headers)),
			'groups' => $this->getUsergroups(),
		]);
	}

	/**
	 * Get the usergroups to filter by
	 */
	private function getUsergroups(): array
	{
		$connection = $this->getConnection('fe_groups');
		$queryBuilder = $connection->createQueryBuilder();

		$usergroups = $queryBuilder
			->select('title', 'uid')
			->from('fe_groups')
			->execute()
			->fetchAll()
		;

		return $usergroups;
	}

	/**
	 * Lookup a usergroup
	 */
	private function usergroupLookup(int $uid): string
	{
		static $cache = [];

		if (isset($cache[$uid])) {
			return $cache[$uid];
		}

		$connection = $this->getConnection('fe_groups');
		$queryBuilder = $connection->createQueryBuilder();

		$usergroup = $queryBuilder
			->select('title')
			->from('fe_groups')
			->where(
				$queryBuilder->expr()->eq('uid', $uid)
			)
			->execute()
			->fetchAll()
		;

		if (!$usergroup) {
			return false;
		}

		$cache[$uid] = $usergroup[0]['title'];

		return $cache[$uid];
	}
}
