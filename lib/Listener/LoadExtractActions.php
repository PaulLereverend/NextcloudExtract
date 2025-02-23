<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Extract\Listener;

use OCA\Extract\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadExtractActions implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        Util::addInitScript(Application::APP_ID, 'extract-init');
        Util::addScript(Application::APP_ID, 'extract-extractAction');
        Util::addStyle(Application::APP_ID, 'style');
    }
}
