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
namespace APF\core\exceptionhandler;

use APF\core\exceptionhandler\model\ExceptionPageViewModel;
use APF\core\logging\entry\SimpleLogEntry;
use APF\core\logging\LogEntry;
use APF\core\logging\Logger;
use APF\core\pagecontroller\Page;
use APF\core\registry\Registry;
use APF\core\singleton\Singleton;
use Throwable;

/**
 * Implements the default APF exception handler for uncaught exceptions.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 21.02.2009<br />
 */
class DefaultExceptionHandler implements ExceptionHandler {

   /**
    * The number of the exception.
    *
    * @var int $exceptionNumber
    */
   protected $exceptionNumber = null;

   /**
    * The message of the exception.
    *
    * @var string $exceptionMessage
    */
   protected $exceptionMessage = null;

   /**
    * The file, the exception occurs in.
    *
    * @var string $exceptionFile
    */
   protected $exceptionFile = null;

   /**
    * The line, the exception occurs in.
    *
    * @var int $exceptionLine
    */
   protected $exceptionLine = null;

   /**
    * The exception type (name of the class).
    *
    * @var string $exceptionType
    */
   protected $exceptionType = null;

   /**
    * The exception trace.
    *
    * @var string[] $exceptionTrace
    */
   protected $exceptionTrace = [];

   public function handleException(Throwable $exception) {

      // fill attributes
      $this->exceptionNumber = $exception->getCode();
      $this->exceptionMessage = $exception->getMessage();
      $this->exceptionFile = $exception->getFile();
      $this->exceptionLine = $exception->getLine();
      $this->exceptionTrace = $exception->getTrace();
      $this->exceptionType = get_class($exception);

      // log exception
      $this->logException();

      // build nice exception page
      echo $this->buildExceptionPage();
   }

   /**
    * Creates a log entry containing the exception occurred.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 21.02.2009<br />
    */
   protected function logException() {
      $message = '[' . ($this->generateExceptionID()) . '] ' . $this->exceptionMessage . ' (Number: ' . $this->exceptionNumber . ', File: ' . $this->exceptionFile . ', Line: ' . $this->exceptionLine . ')';

      $log = Singleton::getInstance(Logger::class);
      /* @var $log Logger */
      $log->addEntry(
            new SimpleLogEntry(
            // use the configured log target to allow custom configuration of APF-internal log statements
            // to be written to a custom file/location
                  Registry::retrieve('APF\core', 'InternalLogTarget'),
                  $message,
                  LogEntry::SEVERITY_ERROR
            )
      );
   }

   /**
    * Creates the exception page.
    *
    * @return string the exception page content.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 21.02.2009<br />
    */
   protected function buildExceptionPage() {

      // create page
      $stackTrace = new Page();
      $stackTrace->loadDesign('APF\core\exceptionhandler\templates', 'exceptionpage');

      /* @var $model ExceptionPageViewModel */
      $model = Singleton::getInstance(ExceptionPageViewModel::class);

      // inject exception information into the document attributes array
      $model->setExceptionId($this->generateExceptionID());
      $model->setExceptionMessage($this->exceptionMessage);
      $model->setExceptionNumber($this->exceptionNumber);
      $model->setExceptionFile($this->exceptionFile);
      $model->setExceptionLine($this->exceptionLine);
      $model->setExceptionType($this->exceptionType);
      $model->setExceptionTrace(array_reverse($this->exceptionTrace));

      // create exception page
      return $stackTrace->transform();
   }

   /**
    * Generates the exception id.
    *
    * @return string The unique exception id.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 21.02.2009<br />
    */
   protected function generateExceptionID() {
      return md5($this->exceptionMessage . $this->exceptionNumber . $this->exceptionFile . $this->exceptionLine);
   }

}
