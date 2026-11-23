<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Settings;

use Friendica\App;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Model\APContact;
use Friendica\Model\Contact;
use Friendica\Module\BaseSettings;
use Friendica\Module\Response;
use Friendica\Navigation\SystemMessages;
use Friendica\Network\HTTPClient\Capability\ICanSendHttpRequests;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Friendica\Network\HTTPClient\Client\HttpClientOptions;
use Friendica\Network\HTTPClient\Client\HttpClientRequest;
use Friendica\Network\HTTPException;
use Friendica\Protocol\ActivityPub;
use Friendica\Util\Profiler;
use Friendica\Core\Worker;
use Friendica\Worker\Block;
use Psr\Log\LoggerInterface;

/**
 * Module to import user block data
 **/

class BlockImport extends BaseSettings
{
	public function __construct(private readonly ICanSendHttpRequests $httpClient, protected SystemMessages $systemMessages, private readonly IManageConfigValues $config, IHandleUserSessions $session, App\Page $page, L10n $l10n, App\BaseURL $baseUrl, App\Arguments $args, LoggerInterface $logger, Profiler $profiler, Response $response, array $server, array $parameters = [])
	{
		parent::__construct($session, $page, $l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);
	}

	protected function post(array $request = [])
	{
		if (!$this->session->getLocalUserId()) {
			throw new HTTPException\ForbiddenException($this->l10n->t('Permission denied.'));
		}

		self::checkFormSecurityTokenRedirectOnError($this->args->getQueryString(), 'contactblockimport');

		parent::post();

		//Receive single column of contact addresses
		if (!empty($request['importcontactblock-submit']) && isset($_FILES['importcontactblock-filename']) && !empty($_FILES['importcontactblock-filename']['tmp_name'])) {
			//Verify request route
			try {
				// Extract blocked contacts from CSV file.
				$this->contactblocklist = $this->extractFromCSVFile($_FILES['importcontactblock-filename']['tmp_name']);
				if (empty($this->contactblocklist)) {
					$this->notices[] = $this->t('No valid contacts found in CSV file.');
				}
				return;
			} catch (\Throwable $e) {
				$this->notices[] = $this->t('Error importing contact file: %s', $e->getMessage());
				return;
			}
			if (isset($request['importserverblock-submit'])) {
				$this->import($request['legacy_contact']);
				$this->systemMessages->addInfo($this->l10n->t('Importing Contact blocks done'));
			}

			// Import Blocked Accounts extracted from CSV file
			$uid = $this->session->getLocalUserId();
			Block::addByArray($this->contactblocklist, $uid);
			$this->systemMessages->addInfo($this->l10n->t('Importing contact blocks done'));
			// delete temp file
			unlink($_FILES['importcontactblock-filename']['tmp_name']);
		}
		$this->baseUrl->redirect($this->args->getQueryString());
	}

	protected function content(array $request = []): string
	{
		if (!$this->session->getLocalUserId()) {
			throw new HTTPException\ForbiddenException($this->l10n->t('Permission denied.'));
		}

		parent::content();

		$tpl = Renderer::getMarkupTemplate('settings/blockimport.tpl');
		return Renderer::replaceMacros($tpl, [
			'$title'                 => $this->l10n->t('Import Contact Blocks'),
			'$submit'                => $this->l10n->t('Save Settings'),
			'$form_security_token'   => self::getFormSecurityToken('contactblockimport'),
			'$importcontactblock_text'    => $this->l10n->t('Upload a CSV file that contains the handle of your blocked accounts in the first column you exported from the old account. Your blocks will be added in the background. This can take some time.'),
			'$importcontactblock_button'  => $this->l10n->t('Upload File'),
			'$importcontactblock_maxsize' => $this->config->get('system', 'max_csv_file_size', 30720),
		]);
	}

	private function extractFromCSVFile(string $filename): array
	{
		$fp = fopen($filename, 'r');
		if ($fp === false) {
			$this->logger->notice('Contact CSV file upload error', ['error' => $_FILES['importcontactblock-filename']['error']]);
		}

		$blocklist = [];
		while (($data = fgetcsv($fp, 1000)) !== false) {
			$item = [
				'url'    => $data[0] ?? '',
			];

			if (!empty($item['url']) && !in_array($item, $blocklist)) {
				$blocklist[] = $item;
			}
		}

		fclose($fp);
		return $blocklist;
	}
}
