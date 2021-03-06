<?php
/**
 * <!--
 * This file is part of the adventure php framework (APF) published under
 * https://adventure-php-framework.org.
 *
 * The APF is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The APF is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with the APF. If not, see http://www.gnu.org/licenses/lgpl-3.0.txt.
 * -->
 */
namespace APF\core\configuration\provider;

use APF\core\configuration\ConfigurationException;
use APF\core\loader\RootClassLoader;
use Exception;

/**
 * Provides basic configuration provider functionality.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 09.10.2010<br />
 */
abstract class BaseConfigurationProvider {

   /**
    * Set to true, the context is omitted within the configuration file path.
    *
    * @var boolean $omitContext
    */
   protected $omitContext = false;

   /**
    * Set to true, the environment fallback will be activated.
    *
    * @var boolean $activateEnvironmentFallback
    */
   protected $activateEnvironmentFallback = false;

   /**
    * Set to true, the environment is omitted within the configuration file path.
    *
    * @var boolean $omitEnvironment
    */
   protected $omitEnvironment = false;

   /**
    * Set to true, the /config sub folder is skipped from the configuration file path.
    *
    * @var bool $omitConfigSubFolder
    */
   protected $omitConfigSubFolder = false;

   /**
    * The file extension of the provider.
    *
    * @var string $extension
    */
   protected $extension = null;

   /**
    * The file permission to use to create folders.
    *
    * @var int $folderPermission
    */
   protected $folderPermission = 0770;

   public function setOmitContext(bool $omitContext) {
      $this->omitContext = $omitContext;
   }

   public function setActivateEnvironmentFallback(bool $activateEnvironmentFallback) {
      $this->activateEnvironmentFallback = $activateEnvironmentFallback;
   }

   public function setOmitEnvironment(bool $omitEnvironment) {
      $this->omitEnvironment = $omitEnvironment;
   }

   public function setOmitConfigSubFolder(bool $omitConfigSubFolder) {
      $this->omitConfigSubFolder = $omitConfigSubFolder;
   }

   public function setExtension(string $extension) {
      $this->extension = $extension;
   }

   public function setFolderPermission(int $folderPermission) {
      $this->folderPermission = $folderPermission;
   }

   /**
    * @param string $namespace The namespace of the desired config.
    * @param string $context The current application's context.
    * @param string $language The current application's language.
    * @param string $environment The current environment.
    * @param string $name The name of the desired config.
    *
    * @return string The appropriate file path.
    * @throws ConfigurationException In case the root path cannot be determined using the applied namespace.
    */
   protected function getFilePath(string $namespace, string $context = null, string $language = null, string $environment = null, string $name) {

      // assemble the context
      $contextPath = ($this->omitContext || $context === null) ? '' : '/' . str_replace('\\', '/', $context);

      // assemble file name
      $fileName = ($this->omitEnvironment || $environment === null) ? '/' . $name : '/' . $environment . '_' . $name;

      // gather namespace and full(!) config name and use class loader to determine root path
      try {

         // ID#164: check whether we have a vendor-only namespace declaration to support
         // $this->getFilePath('APF', ...) calls.
         $vendorOnly = RootClassLoader::isVendorOnlyNamespace($namespace);
         if ($vendorOnly === true) {
            $classLoader = RootClassLoader::getLoaderByVendor($namespace);
         } else {
            $classLoader = RootClassLoader::getLoaderByNamespace($namespace);
         }

         $rootPath = $classLoader->getConfigurationRootPath();

         // Add config sub folder only if desired. Allows you to set up a separate
         // vendor-based config folder without another /config sub-folder (e.g.
         // /src/VENDOR/foo/bar/Baz.php and /config/VENDOR/foo/bar/DEFAULT_config.ini).
         if ($this->omitConfigSubFolder === false) {
            $rootPath .= '/config';
         }

         if ($vendorOnly === true) {
            $fqNamespace = '';
         } else {
            $vendor = $classLoader->getVendorName();
            $fqNamespace = '/' . str_replace('\\', '/', str_replace($vendor . '\\', '', $namespace));
         }

         return $rootPath . $fqNamespace . $contextPath . $fileName;

      } catch (Exception $e) {
         // in order to ease debugging, we are wrapping the class loader exception to a more obvious exception message
         throw new ConfigurationException('Class loader root path for namespace "' . $namespace . '" cannot be determined.'
               . ' Please double-check your configuration!', E_USER_ERROR, $e);
      }
   }

   /**
    * Creates the configuration file's path in case if does not exist. This is used for
    * saving configurations as <em>file_put_contents()</em> does not create missing folders.
    *
    * @param string $fileName The fully qualified name of the configuration file.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 21.11.2010<br />
    */
   protected function createFilePath(string $fileName) {
      $path = dirname($fileName);
      if (!file_exists($path)) {
         mkdir($path, $this->folderPermission, true);
      }
   }

}
