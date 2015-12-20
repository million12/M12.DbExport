<?php
namespace M12\DbExport\Command;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Utility\Files;
use TYPO3\Flow\Package\PackageManagerInterface;

/**
 * Package command controller to handle packages from CLI (create/activate/deactivate packages)
 *
 * @Flow\Scope("singleton")
 */
class DbCommandController extends CommandController {

	/**
	 * Dump mode: dump all tables
	 */
	const DUMP_MODE_ALL = 'all';

	/**
	 * Dump mode: dump content only tables
	 */
	const DUMP_MODE_CONTENT_ONLY = 'content';
	

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject(setting="persistence.backendOptions", package="TYPO3.Flow")
	 * @var array
	 */
	protected $backendOptions;

	/**
	 * @Flow\Inject
	 * @var PackageManagerInterface
	 */
	protected $packageManager;

	

	/**
	 * Inject the settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}
	
	
	/**
	 * Export the database
	 * 
	 * @param string $packageKey Package key where the .sql file will be exported (into [PACKAGE_KEY]/Resources/Private/Content/ directory)
	 * @param string $sqlFile Relative path to .sql file
	 * @param string $mode Choose 'content' (default) for content only tables or 'all' for whole database dump.
	 */
	public function exportCommand($packageKey = NULL, $sqlFile = NULL, $mode = self::DUMP_MODE_CONTENT_ONLY) {
		$dbName = $this->backendOptions['dbname'];
		$tablesToExport = $this->getTablesToExport($mode);
		
		if ($packageKey) {
			$sqlFile = $this->getContentDirectoryForPackageKey($packageKey, TRUE) . $this->getFilenameForDump($mode);
			$sqlFile = str_replace(FLOW_PATH_ROOT, '', $sqlFile);
		} elseif ($sqlFile) {

		} else {
			$this->outputLine('You have to specify either "--package-key" or "--sql-file"');
			$this->quit(1);
		}
		
		$cmd = 'mysqldump -v' . $this->getSqlConnectionParams() . " $dbName $tablesToExport > $sqlFile";
		$result = $this->exec($cmd);
		$size = filesize($sqlFile);
		// Note: make it compatible with Flow 2.2 where Files::bytesToSizeString() is not available
		$sizeFormatted = method_exists('TYPO3\Flow\Utility\Files','bytesToSizeString') ? Files::bytesToSizeString($size) : round($size/1024).' kB';

		$this->outputLine();
		$this->outputLine("Database '$dbName' has been exported to '$sqlFile' file.");
		$this->outputLine("Exported $sqlFile file size: " . $sizeFormatted);
		$this->outputLine();
		$this->outputLine("Exported tables: " . ($tablesToExport ? $tablesToExport : '[all]') . '.');
		$this->outputLine();
		$this->sendAndExit($result);
	}
	
	/**
	 * Import the database from .sql file
	 *
	 * @param string $packageKey Package key from where the .sql file will be imported (it has to be in [PACKAGE_KEY]/Resources/Private/Content/ directory)
	 * @param string $sqlFile Relative path to the .sql file
	 * @param string $mode Applicable only with --package-key. Choose 'content' (default) to import content only tables or 'all' for whole database.
	 */
	public function importCommand($packageKey = NULL, $sqlFile = NULL, $mode = self::DUMP_MODE_CONTENT_ONLY) {
		$dbName = $this->backendOptions['dbname'];

		if ($packageKey) {
			$sqlFile = $this->getContentDirectoryForPackageKey($packageKey) . $this->getFilenameForDump($mode);
			$sqlFile = str_replace(FLOW_PATH_ROOT, '', $sqlFile);
		} elseif ($sqlFile) {

		} else {
			$this->outputLine('You have to specify either "--package-key" or "--sql-file"');
			$this->quit(1);
		}
		
		if (FALSE === file_exists($sqlFile)) {
			$this->outputLine("File '$sqlFile' could not be found.");
			$this->quit(2);
		}

		$this->outputLine();
		$this->outputLine("Importing '$sqlFile' into '$dbName' database...");

		$cmd = "mysql" . $this->getSqlConnectionParams() . " $dbName -e 'source $sqlFile'";
		$result = $this->exec($cmd);
		
		$this->outputLine("Done!");
		$this->sendAndExit($result);
	}

	/**
	 * Get tables list to export - or empty string, to export all tables
	 *
	 * @param string $mode: One of self::DUMP_MODE_*
	 * @return string
	 * @throws \TYPO3\Flow\Error\Exception
	 */
	protected function getTablesToExport($mode) {
		switch ($mode) {
			case self::DUMP_MODE_CONTENT_ONLY:
				return implode(' ', $this->settings['contentTables']);
			case self::DUMP_MODE_ALL:
				return '';
			default:
				throw new \TYPO3\Flow\Error\Exception("Invalid mode selected.", 1355480641);
		}
	}

	/**
	 * Gets filename for dump mode
	 * 
	 * @param string $mode: One of self::DUMP_MODE_*
	 * @return string
	 * @throws \TYPO3\Flow\Error\Exception
	 */
	protected function getFilenameForDump($mode) {
		switch ($mode) {
			case self::DUMP_MODE_CONTENT_ONLY:
				return 'Content.sql';
			case self::DUMP_MODE_ALL:
				return 'Dump.sql';
			default:
				throw new \TYPO3\Flow\Error\Exception("Invalid mode selected.", 1355480641);
		}
	}

	/**
	 * Build authenticate params to current database for mysql, mysqldump programs
	 * 
	 * @return string
	 */
	protected function getSqlConnectionParams() {
		$host = isset($this->backendOptions['host']) ? $this->backendOptions['host'] : '127.0.0.1';
		$user = isset($this->backendOptions['user']) ? $this->backendOptions['user'] : 'root';
		$pass = isset($this->backendOptions['password']) ? $this->backendOptions['password'] : '';
		$port = isset($this->backendOptions['port']) ? $this->backendOptions['port'] : 3306;
		if (!empty($pass)) {
			return " -h{$host} -u{$user} -p{$pass} -P{$port}";
		}
		return " -h{$host} -u{$user} -P{$port}";
	}

	/**
	 * Execute the command and return the exit code
	 * 
	 * @param string    $cmd Command to execute
	 * @param bool      $outputResults Set to TRUE to output the command result to the console
	 * @return int      != 0 when something went wrong, 0 if everything is OK
	 * @throws \TYPO3\Flow\Error\Exception
	 */
	protected function exec($cmd, $outputResults = TRUE) {
		$output = array();
		$result = null;
		exec($cmd, $output, $result);
		if ($result !== 0) {
			if (count($output) > 0) {
				$exceptionMessage = implode(PHP_EOL, $output);
			} else {
				$exceptionMessage = sprintf('Execution of *'.$cmd.'* failed with exit code %d without any further output. (Please check your PHP error log for possible Fatal errors)', $result);
			}
			throw new \TYPO3\Flow\Error\Exception($exceptionMessage, 1355480641);
		}
		
		if ($outputResults)
			$this->output(implode(PHP_EOL, $output));
		
		return $result;
	}

	/**
	 * Gets the directory for package where .SQL file will be stored
	 * 
	 * @param string    $packageKey
	 * @param bool      $createDirectoryStructure
	 * @return string
	 * @throws \TYPO3\Flow\Utility\Exception
	 */
	protected function getContentDirectoryForPackageKey($packageKey, $createDirectoryStructure = FALSE) {
		$resourcesPath = $this->packageManager->getPackage($packageKey)->getResourcesPath();
		$dir = Files::concatenatePaths(array($resourcesPath, 'Private/Content/'));
		
		if ($createDirectoryStructure)
			Files::createDirectoryRecursively($dir);
		
		return Files::getNormalizedPath($dir);
	}

	/**
	 * Make sure the package is available and active
	 * 
	 * @param string $packageKey
	 * @throws \TYPO3\Flow\Error\Exception
	 */
	protected function assertPackageIsActive($packageKey) {
		if (!$this->packageManager->isPackageActive($packageKey)) {
			throw new \TYPO3\Flow\Error\Exception(sprintf('Error: Package "%s" is not active.', $packageKey), 1420643757);
		}
	}
}
