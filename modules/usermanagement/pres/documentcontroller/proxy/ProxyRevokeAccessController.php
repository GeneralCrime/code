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
namespace APF\modules\usermanagement\pres\documentcontroller\proxy;

use APF\modules\usermanagement\biz\model\UmgtGroup;
use APF\modules\usermanagement\biz\model\UmgtUser;
use APF\modules\usermanagement\biz\model\UmgtVisibilityDefinition;
use APF\modules\usermanagement\pres\documentcontroller\UmgtBaseController;

/**
 * Revokes a visibility permission for a given user or group.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 05.06.2010<br />
 */
class ProxyRevokeAccessController extends UmgtBaseController {

   public function transformContent() {

      $uM = &$this->getManager();

      $objectId = self::getRequest()->getParameter('objectid');
      $objectType = self::getRequest()->getParameter('objecttype');
      $proxyId = self::getRequest()->getParameter('proxyid');

      $proxy = new UmgtVisibilityDefinition();
      $proxy->setObjectId($proxyId);

      $class = 'APF\modules\usermanagement\biz\model\Umgt' . $objectType;
      $object = new $class;
      /* @var $object UmgtUser|UmgtGroup */
      $object->setObjectId($objectId);

      $formYes = &$this->getForm('RevokeYes');
      $formNo = &$this->getForm('RevokeNo');

      if ($formYes->isSent()) {

         if ($objectType == 'User') {
            $uM->detachUsersFromVisibilityDefinition($proxy, array($object));
         } else {
            $uM->detachGroupsFromVisibilityDefinition($proxy, array($object));
         }

      } elseif ($formNo->isSent()) {
      } else {

         $label = &$this->getLabel('intro-text');

         $labels = $this->getConfiguration('APF\modules\usermanagement\pres', 'labels.ini')
               ->getSection($this->getLanguage())->getSection('frontend')->getSection('proxy')
               ->getSection('revoke-access')->getSection('object-type');

         if ($objectType == 'User') {
            $object = $this->getManager()->loadUserByID($objectId);
            $label->setPlaceHolder('object-type', $labels->getSection('user')->getValue('label'));
         } else {
            $object = $this->getManager()->loadGroupByID($objectId);
            $label->setPlaceHolder('object-type', $labels->getSection('group')->getValue('label'));
         }
         $label->setPlaceHolder('display-name', $object->getDisplayName());
         $label->setPlaceHolder('proxy-id', $proxyId);

         $proxyType = $uM->loadVisibilityDefinitionType($proxy);
         $label->setPlaceHolder('proxy-type', $proxyType->getAppObjectName());

         $formYes->transformOnPlace();
         $formNo->transformOnPlace();

         return;
      }

      self::getResponse()->forward($this->generateLink(
            array(
                  'mainview'  => 'proxy',
                  'proxyview' => 'details',
                  'proxyid'   => $proxyId
            )
      )
      );

   }

}
