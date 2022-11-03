<?php
/*
 -------------------------------------------------------------------------
 LICENSE

 This file is part of GestStock plugin for GLPI.

 GestStock is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 GestStock is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with GestStock. If not, see <http://www.gnu.org/licenses/>.

 @package   geststock
 @author    Nelly Mahu-Lasson
 @copyright Copyright (c) 2017-2022 GestStock plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link
 @since     version 1.0.0
 --------------------------------------------------------------------------
 */

class PluginGeststockMenu extends CommonGLPI {



   static function getMenuName() {

      if (Session::haveRight('plugin_geststock', PluginGeststockGestion::GESTION)) {
         return __('Stock gestion', 'geststock');
      }
      return __('Stock reservation', 'geststock');
   }


   static function getMenuContent() {

      $menu                      = [];
      $menu['title']             = self::getMenuName();
      $menu['page']              = "/plugins/geststock/front/reservation.php";
      $menu['links']['search']   = PluginGeststockReservation::getSearchURL(false);

      if (Session::haveRight('plugin_geststock', CREATE)) {
         $menu['links']['add'] = PluginGeststockReservation::getFormURL(false);
      }

      return $menu;
   }


   static function removeRightsFromSession() {

      if (isset($_SESSION['glpimenu']['tools']['types']['PluginGeststockMenu'])) {
         unset($_SESSION['glpimenu']['tools']['types']['PluginGeststockMenu']);
      }
      if (isset($_SESSION['glpimenu']['tools']['content']['pluginGeststockmenu'])) {
         unset($_SESSION['glpimenu']['tools']['content']['pluginGeststockmenu']);
      }
   }
}
