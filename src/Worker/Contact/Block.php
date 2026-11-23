<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Worker\Contact;

use Friendica\Core\Protocol;
use Friendica\Core\Worker;
use Friendica\Model\Contact;

class Block
{
	public const WORKER_DEFER_LIMIT = 5;

	/**
	 * Issue asynchronous block message to remote servers.
	 *
	 * @param int $cid Target public contact (uid = 0) id
	 * @param int $uid Source local user id
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public static function execute(int $cid, int $uid)
	{
		$contact = Contact::getById($cid);
		if (empty($contact)) {
			return;
		}

		$result = Protocol::block($contact, $uid);
		if ($result === false) {
			Worker::defer(self::WORKER_DEFER_LIMIT);
		}
	}

	/**
	 * Add contact data via probe for multiple contacts
	 * @param array $urls Array of contact links
	 * @param int   $uid  User ID
	 */
	public static function addByArray(array $urls, int $uid)
	{
		$blocked  = 0;
		$failed = 0;
		foreach ($urls as $url) {
			$url = trim((string) $url, '@');
			if (str_contains($url, '@') || Network::isValidHttpUrl($url) || Network::isValidAtUrl($url)) {
				$cid = Contact::getIdForURL($url);
				Worker::add(Worker::PRIORITY_HIGH, 'Contact\Block', $cid, $uid);
				$blocked++;
			} else {
				DI::logger()->notice('Invalid account', ['url' => $url]);
				$failed++;
			}
		}
		DI::logger()->notice('Import done', ['blocked' => $blocked, 'failed' => $failed]);
	}
}
