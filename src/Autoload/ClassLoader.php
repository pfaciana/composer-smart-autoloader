<?php

namespace Render\Autoload;

/**
 * Class ClassLoader
 *
 * Handles class autoloading and manages class maps for different projects.
 *
 * @package Render\Autoload
 */
class ClassLoader
{
	/** @var array<string, string> Array of class maps. */
	public array $classMaps = [];

	/**
	 * Get the singleton instance of ClassLoader.
	 *
	 * This method ensures that only one instance of ClassLoader is created and returned.
	 * If an instance doesn't exist, it creates one and optionally runs the loader.
	 *
	 * @param bool $run Whether to run the loader immediately after instantiation.
	 * @return self The singleton instance of ClassLoader.
	 */
	public static function getInstance ( bool $run = TRUE ): self
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static( $run );
		}

		return $instance;
	}

	/**
	 * Constructor for ClassLoader.
	 *
	 * Initializes the ClassLoader by registering the loadClass method with spl_autoload_register.
	 * Optionally runs the loader immediately after initialization.
	 *
	 * @param bool $run Whether to run the loader immediately after initialization.
	 */
	protected function __construct ( bool $run = TRUE )
	{
		spl_autoload_register( [ $this, 'loadClass' ], TRUE, TRUE );
		$run && $this->run();
	}

	/**
	 * Run the class loader to initialize class maps.
	 *
	 * This method performs the following steps:
	 * 1. Retrieves registered Composer loaders
	 * 2. Gathers project information
	 * 3. Collects raw data from Composer
	 * 4. Determines the latest versions of dependencies
	 * 5. Calculates weights for each project
	 * 6. Generates the final class maps
	 *
	 * @return array<string, string> The final class maps associating class names with file paths.
	 */
	public function run (): array
	{
		spl_autoload_unregister( [ $this, 'loadClass' ] );
		spl_autoload_register( [ $this, 'loadClass' ], TRUE, TRUE );
		$loaders           = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
		$projects          = $this->getProjects( $loaders );
		$allRawData        = \Composer\InstalledVersions::getAllRawData();
		$projects['repos'] = $this->getProjectFallback( $projects['repos'], $allRawData );
		$latestVersions    = $this->getLatestVersions( $projects['repos'] );
		$weights           = $this->getWeights( $projects['repos'], $latestVersions );
		$this->classMaps   = $this->getClassMaps( $projects['class_maps'], $weights );

		return $this->classMaps;
	}

	/**
	 * Load a class using the generated class maps.
	 *
	 * Attempts to load a class by its fully qualified name using the pre-generated class maps.
	 * If the class is found in the maps, it includes the corresponding file.
	 *
	 * @param string $class The fully qualified class name to load.
	 * @return bool True if the class was successfully loaded, false otherwise.
	 */
	public function loadClass ( string $class ): bool
	{
		if ( empty( $this->classMaps ) || !array_key_exists( $class, $this->classMaps ) ) {
			return FALSE;
		}

		includeFile( $this->classMaps[$class] );

		return TRUE;
	}

	/**
	 * Gather project information from registered Composer loaders.
	 *
	 * Collects class maps and repository information for each project found in the registered loaders.
	 * It also attempts to load additional information from the composer/installed.php file if available.
	 *
	 * @param array<string, \Composer\Autoload\ClassLoader> $loaders Registered Composer loaders.
	 * @return array{
	 *     class_maps: array<string, array<string, string>>,
	 *     repos: array<string, array<string, int>>
	 * } Collected project data including class maps and repository information.
	 */
	public function getProjects ( array $loaders ): array
	{
		$projects = [];

		foreach ( $loaders as $dir => $loader ) {
			$vendorPath = $this->normalizePath( $dir );
			$rootPath   = $this->normalizePath( "{$vendorPath}../" );

			$projects['class_maps'][$rootPath] = $loader->getClassMap();
			$projects['repos'][$rootPath]      ??= [];

			if ( file_exists( $installedFile = "{$vendorPath}composer/installed.php" ) ) {
				$project  = include $installedFile;
				$root     = $project['root'];
				$rootPath = $this->normalizePath( $root['install_path'] );

				$projects['repos'][$rootPath] = $this->setProjectDepsVersionsFromData( $projects['repos'][$rootPath], $project['versions'] );
				$projects['repos'][$rootPath] = $this->setProjectDepsVersionsFromComposerJson( $projects['repos'][$rootPath], $rootPath, $project );
			}
			else {
				$projects['repos'][$rootPath] = $this->setProjectDepsVersionsFromComposerJson( $projects['repos'][$rootPath], $rootPath );
			}
		}

		return $projects;
	}

	/**
	 * Retrieve fallback project data for missing repositories.
	 *
	 * Processes raw Composer data to fill in any missing repository information
	 * that wasn't captured by the initial project gathering process.
	 *
	 * @param array<string, array<string, int>> $repos      Existing repository data.
	 * @param array<int, array{
	 *     root: array{install_path: string},
	 *     versions: array<string, array{version: string}>
	 * }>                                       $allRawData All raw data from Composer.
	 * @return array<string, array<string, int>> Updated repository data with fallback information.
	 */
	public function getProjectFallback ( array $repos, array $allRawData ): array
	{
		foreach ( $allRawData as $project ) {
			$root     = $project['root'];
			$rootPath = $this->normalizePath( $root['install_path'] );
			if ( !array_key_exists( $rootPath, $repos ) ) {
				$repos[$rootPath] ??= [];
				$repos[$rootPath] = $this->setProjectDepsVersionsFromData( $repos[$rootPath], $project['versions'] );
				$repos[$rootPath] = $this->setProjectDepsVersionsFromComposerJson( $repos[$rootPath], $rootPath, $project );
			}
		}

		return $repos;
	}

	/**
	 * Set project dependencies versions from provided version data.
	 *
	 * Updates the repository data with version information extracted from the provided data array.
	 *
	 * @param array<string, int>                     $repos Existing repository data.
	 * @param array<string, array{version?: string}> $data  Version data to process.
	 * @return array<string, int> Updated repository data with new version information.
	 */
	public function setProjectDepsVersionsFromData ( array $repos, array $data = [] ): array
	{
		foreach ( $data as $repo => $details ) {
			if ( isset( $details['version'] ) ) {
				$repos[$repo] = $this->getVersionAsInt( $details['version'] );
			}
		}

		return $repos;
	}

	/**
	 * Set project dependencies versions from composer.json file.
	 *
	 * Reads the composer.json file of a project to extract and set version information
	 * for the project and its dependencies.
	 *
	 * @param array<string, int>                                          $repos    Existing repository data.
	 * @param string                                                      $rootPath Root path of the project.
	 * @param array{versions?: array{__root__?: array{version?: string}}} $project  Project data.
	 * @return array<string, int> Updated repository data with versions from composer.json.
	 */
	public function setProjectDepsVersionsFromComposerJson ( array $repos, string $rootPath, array $project = [] ): array
	{
		if ( file_exists( $composerJson = "{$rootPath}composer.json" ) ) {
			$config = json_decode( file_get_contents( $composerJson ), TRUE );

			$repo    = $config['name'] ?? '__root__';
			$version = $config['version'] ?? $project['versions']['__root__']['version'] ?? '0.0.0';

			$repos[$repo] = $this->getVersionAsInt( $version );
		}

		unset( $repos['__root__'] );

		return $repos;
	}

	/**
	 * Determine the latest versions of all repositories.
	 *
	 * Analyzes the version information across all projects to identify
	 * the latest version for each unique repository.
	 *
	 * @param array<string, array<string, int>> $repos Repository data from all projects.
	 * @return array<string, int> Latest versions for each unique repository.
	 */
	public function getLatestVersions ( array $repos ): array
	{
		$latestVersions = [];
		$repoVersions   = [];

		foreach ( $repos as $details ) {
			foreach ( $details as $repo => $version ) {
				$repoVersions[$repo][] = $version;
			}
		}

		foreach ( $repoVersions as $repo => $versions ) {
			if ( count( $versions = array_unique( $versions ) ) < 2 ) {
				unset( $repoVersions[$repo] );
				continue;
			}
			rsort( $versions, SORT_NUMERIC );
			$latestVersions[$repo] = $versions[0];
		}

		return $latestVersions;
	}

	/**
	 * Calculate weights for repositories based on version differences.
	 *
	 * Assigns a weight to each project based on how close its dependency versions
	 * are to the latest known versions. Higher weights indicate more up-to-date dependencies.
	 *
	 * @param array<string, array<string, int>> $repos          Repository data from all projects.
	 * @param array<string, int>                $latestVersions Latest known versions for each repository.
	 * @return array<string, int> Calculated weights for each project.
	 */
	public function getWeights ( array $repos, array $latestVersions ): array
	{
		$weights = [];

		foreach ( $repos as $rootPath => $details ) {
			$score = 0;
			foreach ( $details as $repo => $version ) {
				if ( array_key_exists( $repo, $latestVersions ) ) {
					$score -= ( ( $latestVersions[$repo] - $version ) ?: -1 );
				}
				else {
					$score += 1;
				}
			}
			$weights[$rootPath] = $score;
		}

		ksort( $weights );
		arsort( $weights );

		return $weights;
	}

	/**
	 * Generate final class maps by merging project class maps based on weights.
	 *
	 * Combines class maps from different projects, prioritizing those with higher weights.
	 * This ensures that the most up-to-date class definitions are used in case of conflicts.
	 *
	 * @param array<string, array<string, string>> $classMaps Class maps for each project.
	 * @param array<string, int>                   $weights   Weights calculated for each project.
	 * @return array<string, string> Final merged class maps.
	 */
	public function getClassMaps ( array $classMaps, array $weights ): array
	{
		$finalClassMaps = [];

		foreach ( $weights as $rootPath => $weight ) {
			if ( !empty( $classMaps[$rootPath] ) ) {
				$finalClassMaps += $classMaps[$rootPath];
			}
		}

		return $finalClassMaps;
	}

	/**
	 * Downgrade prerelease version parts to ensure proper version comparison.
	 *
	 * Converts version parts to integers, replacing non-numeric parts with zeros.
	 * This helps in comparing versions that include prerelease identifiers.
	 *
	 * @param array<int|string> $input Version parts to process.
	 * @return array<int> Downgraded version parts as integers.
	 */
	public function downgradePrereleaseVersions ( array $input ): array
	{
		$result          = [];
		$foundNonInteger = FALSE;
		foreach ( $input as $item ) {
			if ( $foundNonInteger || ( is_string( $item ) && !ctype_digit( $item ) ) ) {
				$foundNonInteger = TRUE;
				$result[]        = 0;
			}
			else {
				$result[] = (int) $item;
			}
		}

		return $result;
	}

	/**
	 * Convert a version string to an integer representation for easy comparison.
	 *
	 * Transforms a semantic version string into a single integer,
	 * allowing for simple numerical comparison of versions.
	 *
	 * @param string $version Version string to convert.
	 * @return int Integer representation of the version.
	 */
	public function getVersionAsInt ( string $version ): int
	{
		$parts = explode( '.', ltrim( $version, 'v' ) );

		$parts = $this->downgradePrereleaseVersions( $parts );

		return (int) ( ( $parts[0] ?? 0 ) * 1e9 + ( $parts[1] ?? 0 ) * 1e6 + ( $parts[2] ?? 0 ) * 1e3 + ( $parts[3] ?? 0 ) );
	}

	/**
	 * Normalize a file path for consistent handling across different systems.
	 *
	 * Resolves relative paths, normalizes directory separators,
	 * and ensures a trailing slash for directory paths.
	 *
	 * @param string $path     File path to normalize.
	 * @param bool   $realpath Whether to resolve the path using `realpath()`.
	 * @return string Normalized absolute path.
	 */
	public function normalizePath ( string $path, bool $realpath = TRUE ): string
	{
		if ( $realpath ) {
			$path = realpath( $path );
		}

		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		$path = rtrim( $path, '/\\' ) . '/';

		return $path;
	}
}

/**
 * Include a file isolated from any scope.
 *
 * @param string $file File path to include.
 * @return void
 */
function includeFile ( string $file ): void
{
	include $file;
}
