<?php

namespace WPStarter;

use Exception;

class ConstructionPlan {
  public static function fromConfig($config) {
    if (!array_key_exists('name', $config)) {
      throw new Exception('No Module specified.');
    }

    $config['data'] = [];

    if (array_key_exists('dataFilter', $config)) {
      $args = [ $config['data'] ];
      if (array_key_exists('dataFilterArgs', $config)) {
        $args = array_merge($args, $config['dataFilterArgs']);
      }
      $config['data'] = apply_filters_ref_array($config['dataFilter'], $args);
    }

    if (array_key_exists('customData', $config)) {
      // custom data overwrites original data
      $config['data'] = array_merge($config['data'], $config['customData']);
    }

    $moduleName = $config['name'];

    // iterate areas and recursively map child module data
    if (array_key_exists('areas', $config) && !empty($config['areas'])) {
      $config['areas'] = apply_filters("WPStarter/dynamicSubmodules?name={$moduleName}", $config['areas'], $config['data']);
      $config['areas'] = array_map(function($modules) {
        return array_map(function($module) {
          return self::fromConfig($module);
        }, $modules);
      }, $config['areas']);
    }

    unset($config['dataFilter']);
    unset($config['dataFilterArgs']);
    unset($config['customData']);

    if (empty($config['areas'])) {
      unset($config['areas']);
    }

    return $config;
  }
  //
  // public static function fromConfigFile($configName) {
  //   $configPath = apply_filters('WPStarter/configPath', $configName);
  //   $config = json_decode(file_get_contents($configPath), true);
  //   return new self($config);
  // }
  //
}
