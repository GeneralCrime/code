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
namespace APF\tests\suites\tools\form\taglib;

use APF\core\pagecontroller\ParserException;
use APF\tools\form\FormException;
use APF\tools\form\taglib\Html5EmailFieldTag;
use PHPUnit\Framework\TestCase;

/**
 * Tests rendering of HTML 5 e-mail field.
 */
class Html5EmailFieldTagTest extends TestCase {

   public function setUp() {
      $_REQUEST = [];
   }

   /**
    * @throws ParserException
    * @throws FormException
    */
   public function testHtmlGeneration() {

      $tag = new Html5EmailFieldTag();
      $tag->setAttributes([
            'name' => 'foo',
            'minlength' => '10',
            'maxlength' => '100',
            'bar' => 'baz'
      ]);
      $tag->onParseTime();
      $tag->onAfterAppend();

      $html = $tag->transform();

      $this->assertEquals('<input type="email" name="foo" minlength="10" maxlength="100" />', $html);

   }

   /**
    * @throws FormException
    * @throws ParserException
    */
   public function testVisibility() {

      $tag = new Html5EmailFieldTag();
      $tag->setAttributes([
            'name' => 'foo'
      ]);
      $tag->onParseTime();
      $tag->onAfterAppend();

      $tag->hide();

      $this->assertEmpty($tag->transform());

   }

}
