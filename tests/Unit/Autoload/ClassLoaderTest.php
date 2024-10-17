<?php

use Render\Autoload\ClassLoader;

beforeEach( function () {
	$this->manager = Mockery::mock( ClassLoader::class )->makePartial();
	$this->manager->shouldReceive( 'normalizePath' )->andReturnUsing( fn( $path ) => $path );
} );

afterEach( function () {
	Mockery::close();
} );

describe( 'ClassLoader::setProjectDepsVersionsFromData', function () {

	it( 'sets versions correctly for multiple packages', function () {
		$repos = [];
		$data  = [
			'vendor/package1' => [ 'version' => '1.2.3' ],
			'vendor/package2' => [ 'version' => '2.3.4' ],
			'vendor/package3' => [ 'version' => '3.4.5' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 1002003000, // 1.2.3 as integer
			'vendor/package2' => 2003004000, // 2.3.4 as integer
			'vendor/package3' => 3004005000, // 3.4.5 as integer
		] );
	} );

	it( 'ignores packages without version information', function () {
		$repos = [];
		$data  = [
			'vendor/package1' => [ 'version' => '1.2.3' ],
			'vendor/package2' => [ 'no_version' => 'test' ],
			'vendor/package3' => [ 'version' => '3.4.5' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 1002003000, // 1.2.3 as integer
			'vendor/package3' => 3004005000, // 3.4.5 as integer
		] );
	} );

	it( 'handles empty input data', function () {
		$repos = [];
		$data  = [];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBeEmpty();
	} );

	it( 'preserves existing repo entries', function () {
		$repos = [
			'existing/package' => 9000000000, // 9.0.0 as integer
		];
		$data  = [
			'vendor/package1' => [ 'version' => '1.2.3' ],
			'vendor/package2' => [ 'version' => '2.3.4' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'existing/package' => 9000000000, // 9.0.0 as integer
			'vendor/package1'  => 1002003000, // 1.2.3 as integer
			'vendor/package2'  => 2003004000, // 2.3.4 as integer
		] );
	} );

	it( 'overwrites existing repo entries with new version data', function () {
		$repos = [
			'vendor/package1' => 1000000000, // 1.0.0 as integer
			'vendor/package2' => 2000000000, // 2.0.0 as integer
		];
		$data  = [
			'vendor/package1' => [ 'version' => '1.2.3' ],
			'vendor/package2' => [ 'version' => '2.3.4' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 1002003000, // 1.2.3 as integer
			'vendor/package2' => 2003004000, // 2.3.4 as integer
		] );
	} );

	it( 'handles complex version strings', function () {
		$repos = [];
		$data  = [
			'vendor/package1' => [ 'version' => 'v1.2.3-alpha' ],
			'vendor/package2' => [ 'version' => '2.3.4-beta.1' ],
			'vendor/package3' => [ 'version' => '3.4.5-rc.2+build.1234' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 1002000000, // 1.2.0 as integer
			'vendor/package2' => 2003000000, // 2.3.0 as integer
			'vendor/package3' => 3004000000, // 3.4.0 as integer
		] );
	} );

	it( 'handles packages with only major version', function () {
		$repos = [];
		$data  = [
			'vendor/package1' => [ 'version' => '1' ],
			'vendor/package2' => [ 'version' => '2.0' ],
			'vendor/package3' => [ 'version' => '3.0.0' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 1000000000, // 1.0.0 as integer
			'vendor/package2' => 2000000000, // 2.0.0 as integer
			'vendor/package3' => 3000000000, // 3.0.0 as integer
		] );
	} );

	it( 'ignores invalid version strings', function () {
		$repos = [];
		$data  = [
			'vendor/package1' => [ 'version' => 'invalid' ],
			'vendor/package2' => [ 'version' => '2.3.4' ],
			'vendor/package3' => [ 'version' => 'not a version' ],
		];

		$result = $this->manager->setProjectDepsVersionsFromData( $repos, $data );

		expect( $result )->toBe( [
			'vendor/package1' => 0, // 0.0.0 as integer
			'vendor/package2' => 2003004000, // 2.3.4 as integer
			'vendor/package3' => 0, // 0.0.0 as integer
		] );
	} );
} );

describe( 'ClassLoader::setProjectDepsVersionsFromComposerJson', function () {

	beforeEach( function () {
		$this->fixturesDir = __DIR__ . '/fixtures/';
		if ( !file_exists( $this->fixturesDir ) ) {
			mkdir( $this->fixturesDir, 0777, TRUE );
		}
	} );

	afterEach( function () {
		delete_dir( $this->fixturesDir );
	} );

	it( 'sets version from composer.json when file exists', function () {
		$repos = [];
		mkdir( $rootPath = $this->fixturesDir . 'project1/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file
		file_put_contents( $composerJson, json_encode( [
			'name'    => 'vendor/project1',
			'version' => '1.2.3',
		] ) );

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBe( [
			'vendor/project1' => 1002003000, // 1.2.3 as integer
		] );
	} );

	it( 'ignores __root__ when name is not specified in composer.json', function () {
		$repos = [];
		mkdir( $rootPath = $this->fixturesDir . 'project2/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file without name
		file_put_contents( $composerJson, json_encode( [
			'version' => '2.3.4',
		] ) );

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBe( [] );
	} );

	it( 'uses version from project array when not specified in composer.json', function () {
		$repos = [];
		mkdir( $rootPath = $this->fixturesDir . 'project3/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file without version
		file_put_contents( $composerJson, json_encode( [
			'name' => 'vendor/project3',
		] ) );

		$project = [
			'versions' => [
				'__root__' => [
					'version' => '3.4.5',
				],
			],
		];

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath, $project );

		expect( $result )->toBe( [
			'vendor/project3' => 3004005000, // 3.4.5 as integer
		] );
	} );

	it( 'uses 0.0.0 when version is not specified anywhere', function () {
		$repos = [];
		mkdir( $rootPath = $this->fixturesDir . 'project4/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file without version
		file_put_contents( $composerJson, json_encode( [
			'name' => 'vendor/project4',
		] ) );

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBe( [
			'vendor/project4' => 0, // 0.0.0 as integer
		] );
	} );

	it( 'handles non-existent composer.json file', function () {
		$repos    = [];
		$rootPath = __DIR__ . '/fixtures/non-existent-project/';

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBeEmpty();
	} );

	it( 'removes __root__ entry from repos', function () {
		$repos = [
			'__root__' => 1000000000,
		];
		mkdir( $rootPath = $this->fixturesDir . 'project5/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file
		file_put_contents( $composerJson, json_encode( [
			'name'    => 'vendor/project5',
			'version' => '5.6.7',
		] ) );

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBe( [
			'vendor/project5' => 5006007000, // 5.6.7 as integer
		] );
		expect( $result )->not->toHaveKey( '__root__' );
	} );

	it( 'preserves existing repos entries', function () {
		$repos = [
			'existing/package' => 1000000000,
		];
		mkdir( $rootPath = $this->fixturesDir . 'project6/', 0777, TRUE );
		$composerJson = $rootPath . 'composer.json';

		// Create a temporary composer.json file
		file_put_contents( $composerJson, json_encode( [
			'name'    => 'vendor/project6',
			'version' => '6.7.8',
		] ) );

		$result = $this->manager->setProjectDepsVersionsFromComposerJson( $repos, $rootPath );

		expect( $result )->toBe( [
			'existing/package' => 1000000000,
			'vendor/project6'  => 6007008000, // 6.7.8 as integer
		] );
	} );
} );

describe( 'ClassLoader::getLatestVersions', function () {

	it( 'returns an empty array when repos is empty', function () {
		$repos = [];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBeEmpty();
	} );

	it( 'returns the latest version for each package across all repos', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 2000000000, // v2.0.0
				'vendor/package2' => 1000000000, // v1.0.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBe( [
			'vendor/package1' => 2000000000, // v2.0.0
			'vendor/package2' => 2000000000, // v2.0.0
		] );
	} );

	it( 'ignores packages with only one version across all repos', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 3000000000, // v3.0.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBe( [
			'vendor/package2' => 3000000000, // v3.0.0
		] );
	} );

	it( 'handles multiple projects with different package sets', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package2' => 3000000000, // v3.0.0
				'vendor/package3' => 1000000000, // v1.0.0
			],
			'/path/to/project3/' => [
				'vendor/package1' => 2000000000, // v2.0.0
				'vendor/package3' => 2000000000, // v2.0.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBe( [
			'vendor/package1' => 2000000000, // v2.0.0
			'vendor/package2' => 3000000000, // v3.0.0
			'vendor/package3' => 2000000000, // v2.0.0
		] );
	} );

	it( 'correctly handles packages with the same version across all repos', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBeEmpty();
	} );

	it( 'handles repos with no common packages', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package3' => 3000000000, // v3.0.0
				'vendor/package4' => 4000000000, // v4.0.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBeEmpty();
	} );

	it( 'correctly identifies the latest version when there are multiple versions', function () {
		$repos = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 2000000000, // v2.0.0
			],
			'/path/to/project3/' => [
				'vendor/package1' => 1000500000, // v1.5.0
			],
		];

		$result = $this->manager->getLatestVersions( $repos );

		expect( $result )->toBe( [
			'vendor/package1' => 2000000000, // v2.0.0
		] );
	} );
} );

describe( 'ClassLoader::getWeights', function () {

	it( 'calculates weights correctly for a single project', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 1000000000, // v1.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [ '/path/to/project1/' => 1 ] );
	} );

	it( 'assigns higher weights to projects with more up-to-date packages', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 1000000000, // v1.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 1000000000, // v1.0.0
			'vendor/package2' => 2000000000, // v2.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [
			'/path/to/project1/' => 2,
			'/path/to/project2/' => -999999999,
		] );
	} );

	it( 'handles projects with packages not in latestVersions', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
			],
			'/path/to/project2/' => [
				'vendor/package3' => 1000000000, // v1.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 1000000000, // v1.0.0
			'vendor/package2' => 2000000000, // v2.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [
			'/path/to/project1/' => 2,
			'/path/to/project2/' => 1,
		] );
	} );

	it( 'calculates negative weights for outdated packages', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 2000000000, // v2.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [
			'/path/to/project1/' => -1000000000,
		] );
	} );

	it( 'returns an empty array when repos is empty', function () {
		$repos          = [];
		$latestVersions = [
			'vendor/package1' => 1000000000, // v1.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBeEmpty();
	} );

	it( 'handles projects with multiple packages correctly', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
				'vendor/package2' => 2000000000, // v2.0.0
				'vendor/package3' => 3000000000, // v3.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 2000000000, // v2.0.0
				'vendor/package2' => 1000000000, // v1.0.0
				'vendor/package3' => 3000000000, // v3.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 2000000000, // v2.0.0
			'vendor/package2' => 2000000000, // v2.0.0
			'vendor/package3' => 3000000000, // v3.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [
			'/path/to/project1/' => -999999998,
			'/path/to/project2/' => -999999998,
		] );
	} );

	it( 'sorts weights in descending order', function () {
		$repos          = [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000, // v1.0.0
			],
			'/path/to/project2/' => [
				'vendor/package1' => 2000000000, // v2.0.0
			],
			'/path/to/project3/' => [
				'vendor/package1' => 3000000000, // v3.0.0
			],
		];
		$latestVersions = [
			'vendor/package1' => 3000000000, // v3.0.0
		];

		$result = $this->manager->getWeights( $repos, $latestVersions );

		expect( $result )->toBe( [
			'/path/to/project3/' => 1,
			'/path/to/project2/' => -1000000000,
			'/path/to/project1/' => -2000000000,
		] );
	} );
} );

describe( 'ClassLoader::getClassMaps', function () {

	it( 'returns an empty array when both class maps and weights are empty', function () {
		$classMaps = [];
		$weights   = [];

		$result = $this->manager->getClassMaps( $classMaps, $weights );

		expect( $result )->toBeArray()->toBeEmpty();
	} );

	it( 'prioritizes class maps based on higher weight when there are conflicts', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'ClassA' => '/path/to/project1/ClassA.php',
			],
			'/path/to/project2/' => [
				'ClassA' => '/path/to/project2/ClassA.php',
			],
		];

		$weights = [
			'/path/to/project2/' => 2,
			'/path/to/project1/' => 1,
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );

		expect( $result )->toBeArray()->toHaveKey( 'ClassA' );
		expect( $result['ClassA'] )->toBe( '/path/to/project2/ClassA.php' );
	} );

	it( 'merges class maps from multiple projects, favoring higher-weighted projects', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'ClassA' => '/path/to/project1/ClassA.php',
				'ClassB' => '/path/to/project1/ClassB.php',
			],
			'/path/to/project2/' => [
				'ClassB' => '/path/to/project2/ClassB.php',
				'ClassC' => '/path/to/project2/ClassC.php',
			],
		];

		$weights = [
			'/path/to/project2/' => 2,
			'/path/to/project1/' => 1,
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );

		expect( $result )->toBeArray()->toHaveKeys( [ 'ClassA', 'ClassB', 'ClassC' ] );
		expect( $result['ClassA'] )->toBe( '/path/to/project1/ClassA.php' );
		expect( $result['ClassB'] )->toBe( '/path/to/project2/ClassB.php' );
		expect( $result['ClassC'] )->toBe( '/path/to/project2/ClassC.php' );
	} );

	it( 'maintains the order of class maps based on descending weight values', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'Class1' => '/path/to/project1/Class1.php',
				'Class2' => '/path/to/project1/Class2.php',
			],
			'/path/to/project2/' => [
				'Class3' => '/path/to/project2/Class3.php',
				'Class4' => '/path/to/project2/Class4.php',
			],
		];

		$weights = [
			'/path/to/project2/' => 10,
			'/path/to/project1/' => 5,
		];

		$expected = [
			'Class3' => '/path/to/project2/Class3.php',
			'Class4' => '/path/to/project2/Class4.php',
			'Class1' => '/path/to/project1/Class1.php',
			'Class2' => '/path/to/project1/Class2.php',
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );
		expect( $result )->toBe( $expected );
	} );

	it( 'handles overlapping class names by using the highest weighted project', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'CommonClass' => '/path/to/project1/CommonClass.php',
				'Class1'      => '/path/to/project1/Class1.php',
			],
			'/path/to/project2/' => [
				'CommonClass' => '/path/to/project2/CommonClass.php',
				'Class2'      => '/path/to/project2/Class2.php',
			],
		];

		$weights = [
			'/path/to/project2/' => 10,
			'/path/to/project1/' => 5,
		];

		$expected = [
			'CommonClass' => '/path/to/project2/CommonClass.php',
			'Class2'      => '/path/to/project2/Class2.php',
			'Class1'      => '/path/to/project1/Class1.php',
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );
		expect( $result )->toBe( $expected );
	} );

	it( 'handles projects with no class maps', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'Class1' => '/path/to/project1/Class1.php',
			],
			'/path/to/project2/' => [],
		];

		$weights = [
			'/path/to/project2/' => 10,
			'/path/to/project1/' => 5,
		];

		$expected = [
			'Class1' => '/path/to/project1/Class1.php',
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );
		expect( $result )->toBe( $expected );
	} );

	it( 'ignores projects with weights but no class maps', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'Class1' => '/path/to/project1/Class1.php',
			],
		];

		$weights = [
			'/path/to/project2/' => 10,
			'/path/to/project1/' => 5,
		];

		$expected = [
			'Class1' => '/path/to/project1/Class1.php',
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );
		expect( $result )->toBe( $expected );
	} );

	it( 'handles projects with class maps but no weights', function () {
		$classMaps = [
			'/path/to/project1/' => [
				'Class1' => '/path/to/project1/Class1.php',
			],
			'/path/to/project2/' => [
				'Class2' => '/path/to/project2/Class2.php',
			],
		];

		$weights = [
			'/path/to/project1/' => 5,
		];

		$expected = [
			'Class1' => '/path/to/project1/Class1.php',
		];

		$result = $this->manager->getClassMaps( $classMaps, $weights );
		expect( $result )->toBe( $expected );
	} );
} );

describe( 'ClassLoader::downgradePrereleaseVersions', function () {

	it( 'returns an empty array when input is empty', function () {
		$input  = [];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBeArray()->toBeEmpty();
	} );

	it( 'keeps integer values unchanged', function () {
		$input  = [ 1, 2, 3 ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 3 ] );
	} );

	it( 'converts non-integer values to 0', function () {
		$input  = [ '1', '2', 'alpha' ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 0 ] );
	} );

	it( 'handles mixed integer and non-integer values', function () {
		$input  = [ 1, '2', 'beta', 4 ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 0, 0 ] );
	} );

	it( 'converts all values to 0 after encountering the first non-integer', function () {
		$input  = [ 1, 2, 'alpha', 3, 4 ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 0, 0, 0 ] );
	} );

	it( 'handles leading zeros in string representations of integers', function () {
		$input  = [ '01', '02', '03' ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 3 ] );
	} );

	it( 'treats empty string as non-integer', function () {
		$input  = [ 1, 2, '', 4 ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 0, 0 ] );
	} );

	it( 'handles large integer values', function () {
		$input  = [ 1, 2, 9999999999 ];
		$result = $this->manager->downgradePrereleaseVersions( $input );
		expect( $result )->toBe( [ 1, 2, 9999999999 ] );
	} );
} );

describe( 'ClassLoader::getVersionAsInt', function () {

	it( 'converts basic version strings to integers', function () {
		$version = '1.2.3';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1002003000 );
	} );

	it( 'handles version strings with leading "v"', function () {
		$version = 'v1.2.3';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1002003000 );
	} );

	it( 'converts incomplete version strings to integers', function () {
		$version = '1';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1000000000 );

		$version = '1.2';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1002000000 );
	} );

	it( 'handles complex version strings with pre-release or build metadata', function () {
		$version = '1.2.3-alpha';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1002000000 );
	} );

	it( 'returns 0 for invalid version strings', function () {
		$version = 'invalid';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 0 );

		$version = '1.2.3.4.5';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 1002003004 );
	} );

	it( 'returns 0 for an empty version string', function () {
		$version = '';
		$result  = $this->manager->getVersionAsInt( $version );
		expect( $result )->toBe( 0 );
	} );

} );

describe( 'ClassLoader::getProjectFallback', function () {

	it( 'returns the original repos array when allRawData is empty', function () {
		$repos      = [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		];
		$allRawData = [];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		] );
	} );

	it( 'adds new projects from allRawData to repos', function () {
		$repos      = [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project2/' ],
				'versions' => [
					'vendor/package2' => [ 'version' => '2.0.0' ],
				],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
			'/path/to/project2/' => [ 'vendor/package2' => 2000000000 ],
		] );
	} );

	it( 'does not overwrite existing projects in repos', function () {
		$repos      = [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project1/' ],
				'versions' => [
					'vendor/package1' => [ 'version' => '2.0.0' ],
				],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		] );
	} );

	it( 'handles multiple projects in allRawData', function () {
		$repos      = [];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project1/' ],
				'versions' => [
					'vendor/package1' => [ 'version' => '1.0.0' ],
				],
			],
			[
				'root'     => [ 'install_path' => '/path/to/project2/' ],
				'versions' => [
					'vendor/package2' => [ 'version' => '2.0.0' ],
				],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
			'/path/to/project2/' => [ 'vendor/package2' => 2000000000 ],
		] );
	} );

	it( 'handles projects with multiple packages', function () {
		$repos      = [];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project1/' ],
				'versions' => [
					'vendor/package1' => [ 'version' => '1.0.0' ],
					'vendor/package2' => [ 'version' => '2.0.0' ],
				],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [
				'vendor/package1' => 1000000000,
				'vendor/package2' => 2000000000,
			],
		] );
	} );

	it( 'ignores projects without version information', function () {
		$repos      = [];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project1/' ],
				'versions' => [
					'vendor/package1' => [ 'version' => '1.0.0' ],
					'vendor/package2' => [ 'no_version' => 'test' ],
				],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/project1/' => [ 'vendor/package1' => 1000000000 ],
		] );
	} );

	it( 'handles projects with composer.json but no installed.php', function () {
		$repos      = [
			'/path/to/existing/' => [ 'existing/package' => 9000000000 ],
		];
		$allRawData = [
			[
				'root'     => [ 'install_path' => '/path/to/project1/' ],
				'versions' => [],
			],
		];

		$result = $this->manager->getProjectFallback( $repos, $allRawData );

		expect( $result )->toBe( [
			'/path/to/existing/' => [ 'existing/package' => 9000000000 ],
			'/path/to/project1/' => [],
		] );
	} );

} );