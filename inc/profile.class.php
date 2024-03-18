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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


class PluginGeststockProfile extends Profile {

   static $rightname = "profile";



   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == 'Profile') {
         if ($item->getField('id')
             && ($item->getField('interface') != 'helpdesk')) {
            return PluginGeststockReservation::gettypeName(1);
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='Profile') {
         $ID = $item->getID();
         $prof = new self();

         self::addDefaultProfileInfos($ID, ['plugin_geststock' => 0]);
         $prof->showForm($ID);
      }
      return true;
   }


   static function createFirstAccess($ID) {
      self::addDefaultProfileInfos($ID, ['plugin_geststock' => 511], true);
   }


   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {

      $profileRight = new ProfileRight();
      $dbu          = new DbUtils();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                        ['profiles_id' => $profiles_id,
                                         'name'        => $right])
             && $drop_existing) {

            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id,
                                             'name'        => $right]);
         }

         if (!$dbu->countElementsInTable('glpi_profilerights',
                                         ['profiles_id' => $profiles_id,
                                          'name'        => $right])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }


   function showForm($ID, $options=[]) {

      $profile = new Profile();

      if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))) {
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile->getFromDB($ID);
      if ($profile->getField('interface') == 'central') {
         $rights = $this->getAllRights();
         $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                       'default_class' => 'tab_bg_2',
                                                       'title'         => __('General')]);
      }

      if ($canedit) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::submit(_sx('button', 'Update'), ['name'  => 'update',
                                                     'class' => 'btn btn-primary']);
         echo "</div>\n";
         Html::closeForm();
      }
   }


   static function getAllRights($all=false) {

      $rights = [['itemtype'  => 'PluginGeststockReservation',
                  'label'     => __('Stock reservation', 'geststock'),
                  'field'     => 'plugin_geststock',
                  'rights'    => [PluginGeststockGestion::GESTION   => __('Stock gestion', 'geststock'),
                                  PluginGeststockGestion::TRANSFERT => __('Transfert'),
                                  CREATE                            => __('Create'),
                                  READ                              => __('Read'),
                                  UPDATE                            => __('Update'),
                                  DELETE                            => __('Delete'),
                                  PURGE                             => __('Delete permanently'),
                                  READNOTE                          => __('Read notes'),
                                  UPDATENOTE                        => __('Update notes')]]];

      return $rights;
   }


   static function initProfile() {
      global $DB;

      $profile = new self();
      $dbu     = new DbUtils();

      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights() as $data) {
         if ($dbu->countElementsInTable("glpi_profilerights",
                                        ['name' => $data['field']]) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

      foreach ($DB->request('glpi_profilerights',
                            ['profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                             'name'        => ['LIKE', '%plugin_geststock%']]) as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }


   static function removeRightsFromSession() {

      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }


   static function install() {

      self::initProfile();
      self::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   }


   static function uninstall() {
      global $DB;

      //Delete rights associated with the plugin
      $query = "DELETE
                FROM `glpi_profilerights`
                WHERE `name` LIKE 'plugin_geststock%'";
      $DB->queryOrDie($query, $DB->error());

      $profileRight = new ProfileRight();
      foreach (self::getAllRights() as $right) {
         $profileRight->deleteByCriteria(['name' => $right['field']]);
      }
      self::removeRightsFromSession();

   }
}
