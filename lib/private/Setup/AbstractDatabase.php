<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Manish Bisht <manish.bisht490@gmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Pulzer <t.pulzer@kniel.de>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC\Setup;

use OC\AllConfig;
use OC\DB\ConnectionFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ISecureRandom;

abstract class AbstractDatabase {

	/** @var \OC_L10N */
	protected $trans;
	/** @var string */
	protected $dbDefinitionFile;
	/** @var string */
	protected $dbUser;
	/** @var string */
	protected $dbPassword;
	/** @var string */
	protected $dbName;
	/** @var string */
	protected $dbHost;
	/** @var string */
	protected $dbPort;
	/** @var string */
	protected $tablePrefix;
	/** @var AllConfig */
	protected $config;
	/** @var ILogger */
	protected $logger;
	/** @var ISecureRandom */
	protected $random;

	public function __construct($trans, $dbDefinitionFile, IConfig $config, ILogger $logger, ISecureRandom $random) {
		$this->trans = $trans;
		$this->dbDefinitionFile = $dbDefinitionFile;
		$this->config = $config;
		$this->logger = $logger;
		$this->random = $random;
	}

	public function validate($config) {
		$errors = array();
		if(empty($config['dbuser']) && empty($config['dbname'])) {
			$errors[] = $this->trans->t("%s enter the database username and name.", array($this->dbprettyname));
		} else if(empty($config['dbuser'])) {
			$errors[] = $this->trans->t("%s enter the database username.", array($this->dbprettyname));
		} else if(empty($config['dbname'])) {
			$errors[] = $this->trans->t("%s enter the database name.", array($this->dbprettyname));
		}
		if(substr_count($config['dbname'], '.') >= 1) {
			$errors[] = $this->trans->t("%s you may not use dots in the database name", array($this->dbprettyname));
		}
		return $errors;
	}

	public function initialize($config) {
		$dbUser = $config['dbuser'];
		$dbPass = $config['dbpass'];
		$dbName = $config['dbname'];
		$dbHost = !empty($config['dbhost']) ? $config['dbhost'] : 'localhost';
		$dbPort = !empty($config['dbport']) ? $config['dbport'] : '';
		$dbTablePrefix = isset($config['dbtableprefix']) ? $config['dbtableprefix'] : 'oc_';

		$this->config->setSystemValues([
			'dbname'		=> $dbName,
			'dbhost'		=> $dbHost,
			'dbport' => $dbPort,
			'dbtableprefix'	=> $dbTablePrefix,
		]);

		$this->dbUser = $dbUser;
		$this->dbPassword = $dbPass;
		$this->dbName = $dbName;
		$this->dbHost = $dbHost;
		$this->dbPort = $dbPort;
		$this->tablePrefix = $dbTablePrefix;
	}

	/**
	 * @param array $configOverwrite
	 * @return \OC\DB\Connection
	 */
	protected function connect(array $configOverwrite = []) {
		$connectionParams = array(
			'host' => $this->dbHost,
			'user' => $this->dbUser,
			'password' => $this->dbPassword,
			'tablePrefix' => $this->tablePrefix,
		);

		// adding port support through installer
		if (!empty($this->dbPort)) {
			if (ctype_digit($this->dbPort)) {
				$connectionParams['port'] = $this->dbPort;
			} else {
				$connectionParams['unix_socket'] = $this->dbPort;
			}
		} else if (strpos($this->dbHost, ':')) {
			// Host variable may carry a port or socket.
			list($host, $portOrSocket) = explode(':', $this->dbHost, 2);
			if (ctype_digit($portOrSocket)) {
				$connectionParams['port'] = $portOrSocket;
			} else {
				$connectionParams['unix_socket'] = $portOrSocket;
			}
			$connectionParams['host'] = $host;
		}

		$connectionParams = array_merge($connectionParams, $configOverwrite);
		$cf = new ConnectionFactory();
		return $cf->getConnection($this->config->getSystemValue('dbtype', 'sqlite'), $connectionParams);
	}

	/**
	 * @param string $userName
	 */
	abstract public function setupDatabase($userName);
}
