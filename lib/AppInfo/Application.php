<?php
/**
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Extract\AppInfo;

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;

class Application extends App  implements IBootstrap {

	public function __construct(array $urlParams = []) {
		$infoXml = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../../appinfo/info.xml'));
		$this->appName = (string)$infoXml->id;
		parent::__construct($this->appName, $urlParams);
	}

	// Called later than "register".
	public function boot(IBootContext $context): void {
		$context->injectFn(function(IEventDispatcher $dispatcher) {
			Util::addScript($this->appName, 'extraction' );
			Util::addStyle($this->appName, 'style' );
		});
	}

	// Called earlier than boot, so anything initialized in the
	// "boot()" method must not be used here.
	public function register(IRegistrationContext $context): void {
		// if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
		// 	throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
		// }
	}
}
