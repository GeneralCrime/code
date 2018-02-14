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
namespace APF\modules\usermanagement\pres\documentcontroller\role;

use APF\modules\usermanagement\biz\model\UmgtPermission;
use APF\modules\usermanagement\pres\documentcontroller\UmgtBaseController;
use APF\tools\form\taglib\MultiSelectBoxTag;
use APF\tools\form\taglib\SelectBoxOptionTag;

/**
 * Let's you remove permissions to a role.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 08.09.2011<br />
 */
class RemovePermissionFromRoleController extends UmgtBaseController {

   public function transformContent() {

      $form = $this->getForm('Permissions');
      $uM = $this->getManager();

      $role = $uM->loadRoleByID($this->getRequest()->getParameter('roleid'));
      $form->getLabel('display-name')->setPlaceHolder('display-name', $role->getDisplayName());

      $permissions = $uM->loadPermissionsWithRole($role);

      if (count($permissions) === 0) {
         $template = $this->getTemplate('NoMorePermissions');
         $template->getLabel('message-1')->setPlaceHolder('display-name', $role->getDisplayName());
         $template->getLabel('message-2')->setPlaceHolder('role-view-link', $this->generateLink(['mainview' => 'role', 'roleview' => null, 'roleid' => null]));
         $template->transformOnPlace();

         return;
      }

      /* @var $permissionControl MultiSelectBoxTag */
      $permissionControl = $form->getFormElementByName('Permissions');
      foreach ($permissions as $permission) {
         $permissionControl->addOption($permission->getDisplayName(), $permission->getObjectId());
      }

      if ($form->isSent() && $form->isValid()) {

         $options = $permissionControl->getSelectedOptions();
         $permissionsToAdd = [];
         foreach ($options as $option) {
            /* @var $option SelectBoxOptionTag */
            $permissionToAdd = new UmgtPermission();
            $permissionToAdd->setObjectId($option->getValue());
            $permissionsToAdd[] = $permissionToAdd;
            unset($permissionToAdd);
         }

         $uM->detachPermissionsFromRole($permissionsToAdd, $role);
         $this->getResponse()->forward($this->generateLink(['mainview' => 'role', 'roleview' => null, 'roleid' => null]));

      } else {
         $form->transformOnPlace();
      }

   }

}
