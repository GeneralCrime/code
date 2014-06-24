<?php
namespace APF\core\frontcontroller;

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
use APF\core\benchmark\BenchmarkTimer;
use APF\core\filter\InputFilterChain;
use APF\core\filter\OutputFilterChain;
use APF\core\pagecontroller\APFObject;
use APF\core\pagecontroller\Page;
use APF\core\registry\Registry;
use APF\core\service\DIServiceManager;
use APF\core\singleton\Singleton;
use Exception;
use InvalidArgumentException;

/**
 * @package APF\core\frontcontroller
 * @class Frontcontroller
 *
 * Implements the APF front controller. It enables the developer to execute actions
 * defined within the bootstrap file or the url to enrich a page controller application
 * with business logic.
 * <p/>
 * The controller has it's own timing model. Hence, he can be used for special jobs such
 * as image delivery or creation of the business layer components concerning the time
 * slots the actions are executed. Please refer to the documentation page for a
 * timing diagram.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 27.01.2007<br />
 * Version 0.2, 01.03.2007 (Input objects are now loaded by the front controller, too!)<br />
 * Version 0.3, 08.06.2007 (Now permanent actions defined within the bootstrap file are introduced.)<br />
 * Version 0.4, 01.07.2007 (Removed __createInputObject())<br />
 * Version 0.5. 20.08.2013 Jan Wiese (Added support for actions generated by thw DIServiceManager)<br />
 */
class Frontcontroller extends APFObject {

   /**
    * @protected
    * @var Action[] The front controller's action stack.
    */
   protected $actionStack = array();

   /**
    * @protected
    * @var string The keyword used in the url to indicate an action.
    */
   private $actionKeyword = 'action';

   /**
    * @protected
    * @var string Namespace delimiter within the action definition in url.
    */
   private $namespaceURLDelimiter = '_';

   /**
    * @protected
    * @var string Namespace to action keyword delimiter within the action definition in url.
    */
   private $namespaceKeywordDelimiter = '-';

   /**
    * @protected
    * @var string Delimiter between action keyword and action class within the action definition in url.
    */
   private $keywordClassDelimiter = ':';

   /**
    * @protected
    * @var string Delimiter between action keyword and action class within the action definition in url (url rewriting case!)
    */
   private $urlRewritingKeywordClassDelimiter = '/';

   /**
    * @protected
    * @var string Delimiter between input value couples.
    */
   private $inputDelimiter = '|';

   /**
    * @protected
    * @var string Delimiter between input value couples (url rewriting case!).
    */
   private $urlRewritingInputDelimiter = '/';

   /**
    * @protected
    * @var string Delimiter between input param name and value.
    */
   private $keyValueDelimiter = ':';

   /**
    * @protected
    * @var string Delimiter between input param name and value (url rewrite case!).
    */
   private $urlRewritingKeyValueDelimiter = '/';

   /**
    * @var string[][] The registered URL mappings for actions accessible via token.
    */
   private $urlMappingsByToken = array();

   /**
    * @var string[][] The registered URL mappings for actions accessible via namespace and name.
    */
   private $urlMappingsByNamespaceAndName = array();

   public function getActionKeyword() {
      return $this->actionKeyword;
   }

   public function getNamespaceURLDelimiter() {
      return $this->namespaceURLDelimiter;
   }

   public function getNamespaceKeywordDelimiter() {
      return $this->namespaceKeywordDelimiter;
   }

   public function getKeywordClassDelimiter() {
      return $this->keywordClassDelimiter;
   }

   public function getURLRewritingKeywordClassDelimiter() {
      return $this->urlRewritingKeywordClassDelimiter;
   }

   public function getInputDelimiter() {
      return $this->inputDelimiter;
   }

   public function getURLRewritingInputDelimiter() {
      return $this->urlRewritingInputDelimiter;
   }

   public function getKeyValueDelimiter() {
      return $this->keyValueDelimiter;
   }

   public function getURLRewritingKeyValueDelimiter() {
      return $this->urlRewritingKeyValueDelimiter;
   }

   /**
    * @public
    *
    * Executes the desired actions and creates the page output.
    *
    * @param string $namespace Namespace of the templates.
    * @param string $template Name of the templates.
    *
    * @return string The content of the transformed page.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.01.2007<br />
    * Version 0.2, 27.01.2007<br />
    * Version 0.3, 31.01.2007<br />
    * Version 0.4, 03.02.2007 (Added permanent actions)<br />
    * Version 0.5, 08.06.2007 (Outsourced URL filtering to generic input filter)<br />
    * Version 0.6, 01.07.2007 (Removed permanentpre and permanentpost actions)<br />
    * Version 0.7, 29.09.2007 (Added further benchmark tags)<br />
    * Version 0.8, 21.06.2008 (Introduced Registry to retrieve URLRewrite configuration)<br />
    * Version 0.9, 13.10.2008 (Removed $URLRewriting parameter, because URL rewriting must be configured in the registry)<br />
    * Version 1.0, 11.12.2008 (Switched to the new input filter concept)<br />
    */
   public function start($namespace, $template) {

      // check if the context is set. If not, use the current namespace
      $context = $this->getContext();
      if (empty($context)) {
         $this->setContext($namespace);
      }

      // apply front controller input filter
      InputFilterChain::getInstance()->filter(null);

      // execute pre page create actions (see timing model)
      $this->runActions(Action::TYPE_PRE_PAGE_CREATE);

      // create new page
      $page = new Page();

      // set context
      $page->setContext($this->getContext());

      // set language
      $page->setLanguage($this->getLanguage());

      // load desired design
      $page->loadDesign($namespace, $template);

      // execute actions before transformation (see timing model)
      $this->runActions(Action::TYPE_PRE_TRANSFORM);

      // transform page
      $pageContent = OutputFilterChain::getInstance()->filter($page->transform());

      // execute actions after page transformation (see timing model)
      $this->runActions(Action::TYPE_POST_TRANSFORM);

      return $pageContent;
   }

   /**
    * @public
    *
    * Returns the action specified by the input param.
    *
    * @param string $actionName The name of the action to return.
    *
    * @return Action The desired action or null.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 05.02.2007<br />
    * Version 0.2, 08.02.2007<br />
    * Version 0.3, 11.02.2007<br />
    * Version 0.4, 01.03.2007<br />
    * Version 0.5, 01.03.2007<br />
    * Version 0.6, 08.03.2007 (Switched to new ConfigurationManager)<br />
    * Version 0.7, 08.06.2007<br />
    * Version 0.8, 08.11.2007 (Switched to new hash offsets)<br />
    */
   public function &getActionByName($actionName) {

      foreach ($this->actionStack as $offset => $DUMMY) {
         if ($this->actionStack[$offset]->getActionName() == $actionName) {
            return $this->actionStack[$offset];
         }
      }

      // return null, if action could not be found
      $null = null;

      return $null;
   }

   /**
    * @public
    *
    * Returns the action stack.
    *
    * @return Action[] The front controller action stack.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 05.02.2007<br />
    */
   public function &getActions() {
      return $this->actionStack;
   }

   /**
    * @private
    *
    * Creates the url representation of a given namespace.
    *
    * @param string $namespaceUrlRepresentation The url string.
    *
    * @return string The namespace of the action.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 03.06.2007<br />
    */
   protected function getActionNamespaceByURLString($namespaceUrlRepresentation) {
      return str_replace($this->namespaceURLDelimiter, '\\', $namespaceUrlRepresentation);
   }

   /**
    * @public
    *
    * Registers an action with the front controller. This includes action configuration using
    * the action params defined within the action mapping. Each action definition is expected
    * to be stored in the <em>{ENVIRONMENT}_actionconfig.ini</em> file under the namespace
    * <em>{$namespace}\{$this->context}.</em>
    * <p/>
    * Using the forth parameter, you can directly register an action URL mapping. URL mappings
    * allow you to shorten action URLs from e.g. <em>VENDOR_foo-action:bar=a:b|c:d</em> to
    * <em>bar=a:b|c:d</em>. For details, please refer to <em>Frontcontroller::registerActionUrlMapping()</em>.
    *
    * @param string $namespace Namespace of the action to register.
    * @param string $name Name of the action to register.
    * @param array $params (Input-) params of the action.
    * @param string $urlToken Name of the action URL mapping token to register along with the action.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 08.06.2007<br />
    * Version 0.2, 01.07.2007 (Action namespace is now translated at the addAction() method)<br />
    * Version 0.3, 01.07.2007 (Config params are now parsed correctly)<br />
    * Version 0.4, 27.09.2010 (Removed synthetic "actions" sub-namespace)<br />
    * Version 0.5, 19.03.2014 (Added implicit registration of action mapping)<br />
    */
   public function registerAction($namespace, $name, array $params = array(), $urlToken = null) {

      $config = $this->getConfiguration($namespace, 'actionconfig.ini');

      if ($config != null) {

         // separate param strings
         if (strlen(trim($config->getValue($name, 'InputParams'))) > 0) {

            // separate params
            $staticParams = explode($this->inputDelimiter, $config->getValue($name, 'InputParams'));

            for ($i = 0; $i < count($staticParams); $i++) {

               if (substr_count($staticParams[$i], $this->keyValueDelimiter) > 0) {

                  $pairs = explode($this->keyValueDelimiter, $staticParams[$i]);

                  // re-order and add to param list
                  if (isset($pairs[0]) && isset($pairs[1])) {
                     $params = array_merge($params, array($pairs[0] => $pairs[1]));
                  }
               }
            }
         }
      }

      $this->addAction($namespace, $name, $params);

      // register action URL mapping if desired
      if ($urlToken !== null) {
         $this->registerActionUrlMapping(new ActionUrlMapping($urlToken, $namespace, $name));
      }
   }

   /**
    * @public
    *
    * Adds an action to the front controller action stack. Please note, that the namespace of
    * the namespace of the action config is added the current context. The name of the
    * config file is concatenated by the current environment and the string
    * <em>*_actionconfig.ini</em>.
    *
    * @param string $namespace Namespace of the action.
    * @param string $name Name of the action (section key of the config file).
    * @param array $params (Input-)params of the action.
    *
    * @throws InvalidArgumentException In case the action cannot be found within the appropriate
    * configuration or the action implementation classes are not available.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 05.06.2007<br />
    * Version 0.2, 01.07.2007<br />
    * Version 0.3, 02.09.2007<br />
    * Version 0.4, 08.09.2007 (Bug-fix: input params from config are now evaluated)<br />
    * Version 0.5, 08.11.2007 (Changed action stack construction to hash offsets)<br />
    * Version 0.6, 21.06.2008 (Replaced APPS__ENVIRONMENT constant with a value from the Registry)<br />
    * Version 0.7, 27.09.2010 (Removed synthetic "actions" sub-namespace)<br />
    * Version 0.8, 09.04.2011 (Made input implementation optional, removed separate action and input class file definition)<br />
    * Version 0.9. 20.08.2013 Jan Wiese (Added support for actions generated by thw DIServiceManager)<br />
    */
   public function addAction($namespace, $name, array $params = array()) {

      // re-map namespace
      $namespace = $this->getActionNamespaceByURLString($namespace);

      // load the action configuration
      $config = $this->getConfiguration($namespace, 'actionconfig.ini');
      $actionConfig = $config->getSection($name);

      // throw exception, in case the action config is not present
      if ($actionConfig == null) {
         $env = Registry::retrieve('APF\core', 'Environment');
         throw new InvalidArgumentException('[Frontcontroller::addAction()] No config '
               . 'section for action key "' . $name . '" available in configuration file "' . $env
               . '_actionconfig.ini" in namespace "' . $namespace . '" and context "'
               . $this->getContext() . '"!', E_USER_ERROR);
      }


      // evaluate which method to use: simple object or di service
      $actionServiceName = $actionConfig->getValue('ActionServiceName');
      $actionServiceNamespace = $actionConfig->getValue('ActionServiceNamespace');

      if (!(empty($actionServiceName) || empty($actionServiceNamespace))) {
         // use di service
         try {
            $action = DIServiceManager::getServiceObject(
                  $actionServiceNamespace,
                  $actionServiceName,
                  $this->getContext(),
                  $this->getLanguage()
            );
         } catch (Exception $e) {
            throw new InvalidArgumentException('[Frontcontroller::addAction()] Action could not
            be created using DIServiceManager with service name "' . $actionServiceName . '" and service
            namespace "' . $actionServiceNamespace . '". Please check your action and service
            configuration files! Message from DIServiceManager was: ' . $e->getMessage(), $e->getCode());
         }

      } else {
         // use simple object

         // include action implementation
         $actionClass = $actionConfig->getValue('ActionClass');

         // check for class being present
         if (!class_exists($actionClass)) {
            throw new InvalidArgumentException('[Frontcontroller::addAction()] Action class with name "'
                  . $actionClass . '" could not be found. Please check your action configuration file!', E_USER_ERROR);
         }

         // init action
         $action = new $actionClass;
         /* @var $action Action */

         $action->setContext($this->getContext());
         $action->setLanguage($this->getLanguage());

      }

      // init action
      $action->setActionNamespace($namespace);
      $action->setActionName($name);

      // check for custom input implementation
      $inputClass = $actionConfig->getValue('InputClass');

      // include input implementation in case a custom implementation is desired
      if (empty($inputClass)) {
         $inputClass = 'APF\core\frontcontroller\FrontcontrollerInput';
      }

      // check for class being present
      if (!class_exists($inputClass)) {
         throw new InvalidArgumentException('[Frontcontroller::addAction()] Input class with name "' . $inputClass
               . '" could not be found. Please check your action configuration file!', E_USER_ERROR);
      }

      // init input
      $input = new $inputClass;
      /* @var $input FrontcontrollerInput */

      // merge input params with the configured params (params included in the URL are kept!)
      $input->setParameters(
            array_merge(
                  $this->generateParamsFromInputConfig($actionConfig->getValue('InputParams')),
                  $params
            )
      );

      $input->setAction($action);
      $action->setInput($input);

      // set the frontcontroller as a parent object to the action
      $action->setFrontController($this);

      // add the action as a child
      $this->actionStack[] = $action;

      // ID#83: Sort actions to allow prioritization of actions. This is done using
      // uksort() in order to both respect Action::getPriority()
      // and the order of registration for equivalence groups.
      uksort($this->actionStack, array($this, 'sortActions'));
   }

   /**
    * @public
    *
    * Registers an action URL mapping with the front controller.
    * <p/>
    * Action mappings allow to shorten action instructions within the URL from e.g.
    * <em>VENDOR_foo_bar-action:doIt</em> to <em>doId</em>.
    *
    * @param ActionUrlMapping $mapping The URL mapping to add for actions.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 12.03.2014<br />
    */
   public function registerActionUrlMapping(ActionUrlMapping $mapping) {
      // maintain two indexes for performance reasons
      $this->urlMappingsByToken[$mapping->getUrlToken()] = $mapping;
      $this->urlMappingsByNamespaceAndName[$mapping->getNamespace() . $mapping->getName()] = $mapping;
   }

   /**
    * @public
    *
    * Registers multiple action URL mappings with the front controller defined within a
    * configuration file specified by it's namespace and name.
    * <p/>
    * For details please refer to Frontcontroller::registerActionUrlMapping().
    *
    * @param string $namespace The configuration file namespace.
    * @param string $name The configuration file name.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.03.2014<br />
    */
   public function registerActionUrlMappings($namespace, $name) {
      $config = $this->getConfiguration($namespace, $name);

      foreach ($config->getSectionNames() as $urlToken) {
         $section = $config->getSection($urlToken);
         $this->registerActionUrlMapping(
               new ActionUrlMapping($urlToken, $section->getValue('ActionNamespace'), $section->getValue('ActionName'))
         );
      }

   }

   /**
    * @public
    *
    * Returns the list of registered URL tokens that are registered with the front controller.
    *
    * @return string[] The list of registered URL tokens.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 12.03.2014<br />
    */
   public function getActionUrlMappingTokens() {
      return array_keys($this->urlMappingsByToken);
   }

   /**
    * @public
    *
    * Returns the action URL mapping either by URL token or action namespace and name:
    * <code>
    * $fC->getActionUrlMapping('url-token');
    * $fC->getActionUrlMapping('VENDOR\actions', 'do-something');
    * </code>
    *
    * @param string $tokenOrNamespace The URL token of the mapping or the action namespace.
    * @param string $name The action name.
    *
    * @return ActionUrlMapping|null The desired URL mapping or <em>null</em> in case no mapping is registered..
    */
   public function getActionUrlMapping($tokenOrNamespace, $name = null) {

      // retrieve mapping by token
      if ($name === null) {
         return isset($this->urlMappingsByToken[$tokenOrNamespace])
               ? $this->urlMappingsByToken[$tokenOrNamespace]
               : null;
      }

      // retrieve mapping by action namespace and name
      return isset($this->urlMappingsByNamespaceAndName[$tokenOrNamespace . $name])
            ? $this->urlMappingsByNamespaceAndName[$tokenOrNamespace . $name]
            : null;
   }

   /**
    * @private
    *
    * Compares two actions to allow sorting of actions.
    * <p/>
    * Actions with a lower priority returned by <em>Action::getPriority()</em>
    * are executed prior to others as described in CR ID#83.
    *
    * @param int $a Offset one for comparison.
    * @param int $b Offset two for comparison.
    *
    * @return int <em>-1</em> in case action <em>$one</em> has lower priority, <em>1</em> in case <em>$two</em> has higher priority. <em>0</em> in case actions are equal.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 11.03.2014<br />
    */
   private function sortActions($a, $b) {
      if ($this->actionStack[$a]->getPriority() == $this->actionStack[$b]->getPriority()) {
         if ($a == $b) {
            return 0;
         }

         return $a > $b ? 1 : -1; // sort equals again to preserve order!
      }

      return $this->actionStack[$a]->getPriority() > $this->actionStack[$b]->getPriority() ? -1 : 1;
   }

   /**
    * @protected
    *
    * Create an array from a input param string (scheme: <code>a:b|c:d</code>).
    *
    * @param string $inputConfig The config string contained in the action config.
    *
    * @return string[] The resulting param-value array.
    *
    * @author Christian W. Schäfer
    * @version
    * Version 0.1, 08.09.2007<br />
    */
   protected function generateParamsFromInputConfig($inputConfig = '') {

      $inputParams = array();

      $inputConfig = trim($inputConfig);

      if (strlen($inputConfig) > 0) {

         // first: explode couples by "|"
         $paramsArray = explode($this->inputDelimiter, $inputConfig);

         for ($i = 0; $i < count($paramsArray); $i++) {

            // second: explode key and value by ":"
            $tmpAry = explode($this->keyValueDelimiter, $paramsArray[$i]);

            if (isset($tmpAry[0]) && isset($tmpAry[1]) && !empty($tmpAry[0]) && !empty($tmpAry[1])) {
               $inputParams[$tmpAry[0]] = $tmpAry[1];
            }
         }
      }

      return $inputParams;
   }

   /**
    * @protected
    *
    * Executes all actions with the given type. Possible types are
    * <ul>
    * <li>prepagecreate</li>
    * <li>pretransform</li>
    * <li>posttransform</li>
    * </ul>
    *
    * @param string $type Type of the actions to execute.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.01.2007<br />
    * Version 0.2, 31.01.2007<br />
    * Version 0.3, 03.02.2007 (Added benchmarker)<br />
    * Version 0.4, 01.07.2007 (Removed debug output)<br />
    * Version 0.5, 08.11.2007<br />
    * Version 0.6, 28.03.2008 (Optimized benchmarker call)<br />
    * Version 0.7, 07.08.2010 (Added action activation indicator to disable actions on demand)<br />
    */
   protected function runActions($type = Action::TYPE_PRE_PAGE_CREATE) {

      /* @var $t BenchmarkTimer */
      $t = & Singleton::getInstance('APF\core\benchmark\BenchmarkTimer');

      foreach ($this->actionStack as $offset => $DUMMY) {

         // only execute, when the current action has a suitable type
         if ($this->actionStack[$offset]->getType() == $type
               && $this->actionStack[$offset]->isActive()
         ) {

            $id = get_class($this->actionStack[$offset]) . '::run()';
            $t->start($id);

            $this->actionStack[$offset]->run();

            $t->stop($id);
         }
      }
   }

}
