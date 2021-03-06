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
namespace APF\core\filter;

use APF\core\benchmark\BenchmarkTimer;
use APF\core\frontcontroller\FrontController;
use APF\core\http\mixins\GetRequestResponse;
use APF\core\pagecontroller\APFObject;
use APF\core\singleton\Singleton;

/**
 * Implements an input filter that resolves the URL layout of the front and page
 * controller in standard url mode.
 * <p/>
 * The APF url layout includes generic parameter mapping and any number of front
 * controller actions encoded into the url. Rewritten urls can define any number
 * of slash-separated params (e.g. foo/bar) that is translated to the $_REQUEST
 * and $_GET super-global. Further, front controller actions separated from normal
 * parameters are analyzed and applied to the front controller as action to execute.
 * <p/>
 * Since the front controller url layout resolving mechanism includes the page
 * controller behaviour, release 1.14 ships only one filter.
 * <p/>
 * In order to create your own url layout resolver, implement the
 * <em>ChainedContentFilter</em> interface and add it to the reset filter
 * chain/prepend it. Details and examples can be found within the manual.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 21.03.2011 (Initial migration from 1.13 concept)<br />
 * Version 0.2, 09.04.2013 (Split original class into two input filter that either resolve normal (this one) or a rewrite url format)<br />
 */
class ChainedStandardInputFilter implements ChainedContentFilter {

   use GetRequestResponse;

   /**
    * Defines the action keyword.
    *
    * @var string $FC_ACTION_KEYWORD
    */
   protected static $FC_ACTION_KEYWORD = '-action';

   public function filter(FilterChain &$chain, $input = null) {

      /* @var $t BenchmarkTimer */
      $t = Singleton::getInstance(BenchmarkTimer::class);

      $id = get_class($this);
      $t->start($id);

      $fC = $this->getFrontController();
      $tokens = $fC->getActionUrlMappingTokens();

      foreach ($this->getRequest()->getParameters() as $key => $value) {

         // ID#63: re-map action instructions according to registered aliases
         if (in_array($key, $tokens)) {
            $mapping = $fC->getActionUrlMapping($key);
            $key = str_replace('\\', '_', $mapping->getNamespace()) . self::$FC_ACTION_KEYWORD . ':' . $mapping->getName();
         }

         if (substr_count($key, self::$FC_ACTION_KEYWORD . ':') > 0) {

            // get namespace and class from the REQUEST key
            $actionName = substr($key, strpos($key, ':') + 1);
            $actionNamespace = substr($key, 0, strpos($key, '-'));

            // initialize the input params
            $inputParams = [];

            // create param array
            $params = explode('|', $value);

            $count = count($params);
            for ($i = 0; $i < $count; $i++) {

               $pairs = explode(':', $params[$i]);

               // ID#240: allow "0" values to be passed as within front controller action input value.
               if (isset($pairs[0]) && isset($pairs[1])
                     && !empty($pairs[0]) && (!empty($pairs[1]) || (string)$pairs[1] === '0')
               ) {
                  $inputParams[$pairs[0]] = $pairs[1];
               }
            }

            // add action to the front controller
            $fC->addAction($actionNamespace, $actionName, $inputParams);
         }
      }

      $t->stop($id);

      // delegate further filtering to the applied chain
      $chain->filter($input);
   }

   /**
    * @return FrontController|APFObject
    */
   protected function getFrontController() {
      return Singleton::getInstance(FrontController::class);
   }

}
