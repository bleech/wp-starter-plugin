<?php
/**
 * Class ConstructionPlanTest
 *
 * @package Wp_Starter_Plugin
 */

/**
 * Construction plan test case.
 */

require_once dirname(__DIR__) . '/lib/WPStarter/ConstructionPlan.php';

use WPStarter\TestCase;
use WPStarter\ConstructionPlan;
use Brain\Monkey\WP\Filters;

class ConstructionPlanTest extends TestCase {

  function setUp() {
    parent::setUp();

    $this->moduleList = [
      'DynamicModule' => '',
      'SingleModule' => '',
      'ModuleWithArea' => '',
      'NestedModuleWithArea' => '',
      'ModuleInConfigFile' => '',
      'ChildModuleInConfigFile' => '',
      'GrandChildA' => '',
      'GrandChildB' => '',
      'GrandChildC' => ''
    ];
  }

  function testThrowErrorOnEmptyConfig() {
    $this->expectException(Exception::class);
    $cp = ConstructionPlan::fromConfig([], $this->moduleList);
  }

  function testThrowErrorIfConfigIsAnObject() {
    $this->expectException(Exception::class);
    $cp = ConstructionPlan::fromConfig(new StdClass(), $this->moduleList);
  }

  function testThrowErrorIfConfigIsAString() {
    $this->expectException(Exception::class);
    $cp = ConstructionPlan::fromConfig('string', $this->moduleList);
  }

  function testThrowErrorIfConfigIsANumber() {
    $this->expectException(Exception::class);
    $cp = ConstructionPlan::fromConfig(0, $this->moduleList);
  }

  function testConfigCanBeLoadedFromFile() {
    $cp = ConstructionPlan::fromConfigFile('exampleConfig.json', $this->moduleList);
    $this->assertEquals($cp, [
      'name' => 'ModuleInConfigFile',
      'data' => [
        'test' => 'test'
      ],
      'areas' => [
        'area51' => [
          0 => [
            'name' => 'ChildModuleInConfigFile',
            'data' => []
          ]
        ]
      ]
    ]);
  }

  function testThrowsErrorWhenConfigFileDoesntExist() {
    $fileName = 'exceptionTest.json';

    Filters::expectApplied('WPStarter/configPath')
    ->once()
    ->with(TestHelper::getTemplateDirectory() . '/config/', $fileName)
    ->andReturn('/not/a/real/folder/');

    $this->expectException(Exception::class);

    $cp = ConstructionPlan::fromConfigFile($fileName, $this->moduleList);
  }

  function testThrowsErrorWhenModuleIsNotRegistered() {
    $this->expectException(Exception::class);
    ConstructionPlan::fromConfig([
      'name' => 'ThisModuleIsNotRegistered'
    ], $this->moduleList);
  }

  function testConfigFileLoaderUsesFilterHook() {
    Filters::expectApplied('WPStarter/configFileLoader')
    ->with(null, TestHelper::getConfigPath() . 'exampleConfig.yml')
    ->once()
    ->andReturn(['name' => 'SingleModule']);

    $cp = ConstructionPlan::fromConfigFile('exampleConfig.yml', $this->moduleList);
  }

  function testModuleWithoutDataIsValid() {
    $module = TestHelper::getCustomModule('SingleModule', ['name', 'areas']);
    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);
    $this->assertEquals($cp, [
      'name' => 'SingleModule',
      'data' => []
    ]);
  }

  function testModuleDataIsFiltered() {
    $moduleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs = false, returnDuplicate = false
    TestHelper::registerDataFilter($moduleName);

    $module = TestHelper::getCustomModule($moduleName, ['name', 'dataFilter', 'areas']);
    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $moduleName,
      'data' => [
        'test' => 'result'
      ]
    ]);
  }

  function testDataFilterArgumentsAreUsed() {
    $moduleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($moduleName, true, false);

    $module = TestHelper::getCustomModule($moduleName, ['name', 'dataFilter', 'dataFilterArgs', 'areas']);
    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $moduleName,
      'data' => [
        'test' => 'result'
      ]
    ]);
  }

  function testCustomDataIsAddedToModule() {
    $moduleName = 'SingleModule';

    // this simulates add_filter with return data:
    $module = TestHelper::getCustomModule($moduleName, ['name', 'customData', 'areas']);
    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $moduleName,
      'data' => [
        'test0' => 0,
        'test1' => 'string',
        'test2' => [
          'something strange'
        ],
        'duplicate' => 'newValue'
      ]
    ]);
  }

  function testDataIsFilteredAndCustomDataIsAdded() {
    $moduleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($moduleName, false, true);

    $module = TestHelper::getCustomModule($moduleName, ['name', 'dataFilter', 'customData', 'areas']);
    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $moduleName,
      'data' => [
        'test' => 'result',
        'test0' => 0,
        'test1' => 'string',
        'test2' => [
          'something strange'
        ],
        'duplicate' => 'newValue'
      ]
    ]);
  }

  function testNestedModuleIsAddedToArea() {
    $parentModuleName = 'ModuleWithArea';
    $childModuleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($childModuleName, true, true);

    $module = TestHelper::getCustomModule($parentModuleName, ['name', 'areas']);

    $module['areas'] = [
      'Area51' => [
        TestHelper::getCompleteModule($childModuleName)
      ]
    ];

    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $parentModuleName,
      'data' => [],
      'areas' => [
        'Area51' => [
          [
            'name' => $childModuleName,
            'data' => [
              'test' => 'result',
              'test0' => 0,
              'test1' => 'string',
              'test2' => [
                'something strange'
              ],
              'duplicate' => 'newValue'
            ]
          ]
        ]
      ]
    ]);
  }

  function testParentModuleDataIsNotAddedToChildModule() {
    $parentModuleName = 'ModuleWithArea';
    $childModuleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($parentModuleName, false, false);

    $module = TestHelper::getCustomModule($parentModuleName, ['name', 'dataFilter', 'areas']);

    $module['areas'] = [
      'area51' => [
        TestHelper::getCustomModule($childModuleName, ['name'])
      ]
    ];

    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $parentModuleName,
      'data' => [
        'test' => 'result',
      ],
      'areas' => [
        'area51' => [
          [
            'name' => $childModuleName,
            'data' => []
          ]
        ]
      ]
    ]);
  }

  function testDeeplyNestedModulesCreateValidConstructionPlan() {
    $parentModuleName = 'ModuleWithArea';
    $childModuleName = 'NestedModuleWithArea';
    $grandChildModuleNameA = 'GrandChildA';
    $grandChildModuleNameB = 'GrandChildB';
    $grandChildModuleNameC = 'GrandChildC';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($parentModuleName, false, false);

    $module = TestHelper::getCustomModule($parentModuleName, ['name', 'dataFilter', 'areas']);

    $module['areas'] = [
      'area51' => [
        TestHelper::getCustomModule($childModuleName, ['name', 'areas'])
      ]
    ];

    $module['areas']['area51'][0]['areas'] = [
      'district9' => [
        TestHelper::getCustomModule($grandChildModuleNameA, ['name'])
      ],
      'alderaan' => [
        TestHelper::getCustomModule($grandChildModuleNameB, ['name']),
        TestHelper::getCustomModule($grandChildModuleNameC, ['name'])
      ]
    ];

    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $parentModuleName,
      'data' => [
        'test' => 'result',
      ],
      'areas' => [
        'area51' => [
          [
            'name' => $childModuleName,
            'data' => [],
            'areas' => [
              'district9' => [
                [
                  'name' => $grandChildModuleNameA,
                  'data' => []
                ]
              ],
              'alderaan' => [
                [
                  'name' => $grandChildModuleNameB,
                  'data' => []
                ],
                [
                  'name' => $grandChildModuleNameC,
                  'data' => []
                ]
              ]
            ]
          ]
        ]
      ]
    ]);
  }

  function testDynamicSubmodulesCanBeAddedWithAFilter() {
    $moduleName = 'ModuleWithArea';
    $dynamicModuleName = 'SingleModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($moduleName, false, false);

    $module = TestHelper::getCustomModule($moduleName, ['name', 'dataFilter', 'areas']);
    $dynamicModule = TestHelper::getCustomModule($dynamicModuleName, ['name']);

    Filters::expectApplied("WPStarter/dynamicSubmodules?name={$moduleName}")
    ->with([],  ['test' => 'result'], [])
    ->once()
    ->andReturn(['area51' => [ $dynamicModule ]]);

    $cp = ConstructionPlan::fromConfig($module, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $moduleName,
      'data' => [
        'test' => 'result'
      ],
      'areas' => [
        'area51' => [
          [
            'name' => $dynamicModuleName,
            'data' => []
          ]
        ]
      ]
    ]);
  }

  function testDynamicSubmodulesReceiveParentData() {
    $parentModuleName = 'ModuleWithArea';
    $childModuleName = 'NestedModuleWithArea';
    $childSubmoduleName = 'SingleModule';
    $dynamicModuleName = 'DynamicModule';

    // Params: ModuleName, hasFilterArgs, returnDuplicate
    TestHelper::registerDataFilter($parentModuleName, false, false);

    $parentModule = TestHelper::getCustomModule($parentModuleName, ['name', 'dataFilter', 'areas']);
    $childModule = TestHelper::getCustomModule($childModuleName, ['name']);
    $childSubmodule = TestHelper::getCustomModule($childSubmoduleName, ['name']);
    $dynamicModule = TestHelper::getCustomModule($dynamicModuleName, ['name']);

    $childModule['areas'] = [
      'childArea' => [ $childSubmodule ]
    ];
    $parentModule['areas'] = [
      'parentArea' => [ $childModule ]
    ];

    Filters::expectApplied("WPStarter/dynamicSubmodules?name={$childSubmoduleName}")
    ->with([],  [], ['test' => 'result'])
    ->once()
    ->andReturn(['area51' => [ $dynamicModule ]]);

    $cp = ConstructionPlan::fromConfig($parentModule, $this->moduleList);

    $this->assertEquals($cp, [
      'name' => $parentModuleName,
      'data' => [
        'test' => 'result'
      ],
      'areas' => [
        'parentArea' => [
          [
            'name' => $childModuleName,
            'data' => [],
            'areas' => [
              'childArea' => [
                [
                  'name' => $childSubmoduleName,
                  'data' => [],
                  'areas' => [
                    'area51' => [
                      [
                        'name' => $dynamicModuleName,
                        'data' => []
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ]);
  }
}
