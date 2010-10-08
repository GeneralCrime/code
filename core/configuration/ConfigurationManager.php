<?php
   /**
    * <!--
    * This file is part of the adventure php framework (APF) published under
    * http://adventure-php-framework.org.
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

   /**
    * @package core::configuration
    * @class Configuration
    *
    * Defines the scheme, a APF configuration object must have. Each configuration
    * provider can define it's own configuration instance based on this interface.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.09.2010<br />
    */
   interface Configuration {

      /**
       * @return string
       */
      function getValue($name);

      /**
       * @return Configuration
       */
      function getSection($name);

      function setValue($name, $value);

      function setSection($name, Configuration $section);

      /**
       * Enumerates the names of the current configuration keys.
       *
       * @return string[] The names of the config keys.
       */
      function getValueNames();

      /**
       * Enumerates the names of the current configuration sections.
       *
       * @return string[] The names of the section keys.
       */
      function getSectionNames();
   }

   /**
    * @package core::configuration
    * @class ConfigurationProvider
    *
    * Defines the scheme, a APF configuration provider must have. A configuration
    * provider represents a configuration format (e.g. ini, xml, ...) and can be
    * added to the ConfigurationManager to support multiple formats at the same time.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.09.2010<br />
    */
   interface ConfigurationProvider {

      /**
       * Returns the configuration specified by the given params.
       *
       * @param string $namespace The namespace of the configuration.
       * @param string $context The current application's context.
       * @param string $language The current application's language.
       * @param string $environment The environment, the applications runs on.
       * @param string $name The name of the configuration to load including it's extension.
       * @return Configuration The desired configuration.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      function loadConfiguration($namespace, $context, $language, $environment, $name);

      /**
       * Saves the configuration applied as an argument to the file specified by the given params.
       *
       * @param string $namespace The namespace of the configuration.
       * @param string $context The current application's context.
       * @param string $language The current application's language.
       * @param string $environment The environment, the applications runs on.
       * @param string $name The name of the configuration to load including it's extension.
       * @param Configuration $config The configuration to save.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      function saveConfiguration($namespace, $context, $language, $environment, $name, Configuration $config);

   }

   /**
    * @package core::configuration
    * @class ConfigurationException
    *
    * Represents a specialized exception for configuration errors.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.09.2010<br />
    */
   class ConfigurationException extends Exception {
   }

   /**
    * @package core::configuration
    * @class ConfigurationManager
    *
    * This class represents the central APF configuration facility introduced in release 1.13
    * to have a clean and flexible way of multi-extension configuration support.
    * <p/>
    * The ConfigurationManager allows you register any amount of configuration providers that
    * are delegated the configuration loading and saving. Each provider has it's own extension.
    * <p/>
    * While loading a configuration providing an unknown extension the manager falls back to
    * the first provider registered.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.09.2010<br />
    */
   final class ConfigurationManager {

      /**
       * Contains the registered providers as an associative array mapping the file extension
       * to the appropriate provider.
       * @var ConfigurationProvider[] The configuration provider instances.
       */
      private static $PROVIDER = array();

      /**
       * @public
       * @static
       *
       * Allows to register a configuration provider that is specified by the
       * ConfigurationProvider interface. Please note, that the extension is
       * the file extension of the configuration file.
       *
       * @param string $extension The file extension.
       * @param ConfigurationProvider $provider The provider to register.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function registerProvider($extension, ConfigurationProvider $provider) {
         self::$PROVIDER[strtolower($extension)] = $provider;
      }

      /**
       * @public
       * @static
       *
       * Allows to un-register a configuration provider specified by the extension.
       *
       * @param string $extension The file extension.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function removeProvider($extension) {
         unset(self::$PROVIDER[strtolower($extension)]);
      }
      
      /**
       * @public
       * @static
       *
       * Returns a list of registered providers containing the file extensions the providers
       * are registered for.
       *
       * @return string[] The registered providers.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function getRegisteredProviders() {
         return array_keys(self::$PROVIDER);
      }

      /**
       * @public
       * @static
       *
       * Returns the configuration provider specified by the given extension.
       *
       * @param string $extension The extension the provider is registered for.
       * @return ConfigurationProvider The desired configuration provider.
       * @throws ConfigurationException In case the provider is not registered.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function retrieveProvider($extension){
         return self::getProvider($extension);
      }

      /**
       * @public
       * @static
       *
       * Delegates configuration loading to the specified provider.
       *
       * @param string $namespace The namespace of the configuration.
       * @param string $context The current application's context.
       * @param string $language The current application's language.
       * @param string $environment The environment, the applications runs on.
       * @param string $name The name of the configuration to load including it's extension.
       * @return Configuration The desired configuration.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function loadConfiguration($namespace, $context, $language, $environment, $name) {
         return self::getProvider($name)->loadConfiguration($namespace, $context, $language, $environment, $name);
      }

      /**
       * @public
       * @static
       * 
       * Delegates the configuration saving to the specified provider.
       *
       * @param string $namespace The namespace of the configuration.
       * @param string $context The current application's context.
       * @param string $language The current application's language.
       * @param string $environment The environment, the applications runs on.
       * @param string $name The name of the configuration to load including it's extension.
       * @param Configuration $config The configuration to save.
       *
       * @author Christian Achatz
       * @version
       * Version 0.1, 27.09.2010<br />
       */
      public static function saveConfiguration($namespace, $context, $language, $environment, $name, Configuration $config) {
         return self::getProvider($name)->saveConfiguration($namespace, $context, $language, $environment, $name, $config);
      }

      /**
       * @private
       * @static
       *
       * Returns a configuration provider identified by the given file extension. In case no
       * provider can be found, the first registered provider is returned to have a fallack
       * for pre 1.13 configuration style and to enable the developer to specify fallback
       * providers by the order the providers are registered.
       * <p/>
       * In order you want to influence the fallback mechanism, the providers must be cleared
       * using the following code:
       * <code>foreach(ConfigurationManager::getRegisteredProviders() as $key){
       *    ConfigurationManager::removeProvider($key);
       * }</code>
       *
       * @param string $name The name of the configuration file to load.
       * @return ConfigurationProvider The desired configuration provider.
       * @throws ConfigurationException In case no provider can be found.
       */
      private static function getProvider($name) {

         // try to resolve the provider by it's file extension
         $extPos = strripos($name, '.');
         if ($extPos !== false) {
            $ext = strtolower(substr($name, $extPos + 1));
            if (isset(self::$PROVIDER[$ext])) {
               return self::$PROVIDER[$ext];
            }
         }

         // In case no specific provider can be found, fall back to the first provider
         // that is contained in the list. This is both necessary as fallback for
         // old style file name specifications (file name without extension) and for
         // re-mapping of ini configurations to other formats.
         $extensions = array_keys(self::$PROVIDER);
         if (count($extensions) > 0) {
            return self::$PROVIDER[$extensions[0]];
         }

         // In case, no fallback is possible, we have to end here.
         throw new ConfigurationException('Provider with extension "' . $ext . '" is not registered!',
                 E_USER_ERROR);
      }

   }
?>