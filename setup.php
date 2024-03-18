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

if (!defined("PLUGIN_GESTSTOCK_UPLOAD_DIR")) {
   define("PLUGIN_GESTSTOCK_UPLOAD_DIR", GLPI_PLUGIN_DOC_DIR."/geststock/upload/");
}

function plugin_init_geststock() {
   global $PLUGIN_HOOKS,$CFG_GLPI;

   $PLUGIN_HOOKS["helpdesk_menu_entry"]['geststock'] = true;

   $PLUGIN_HOOKS['csrf_compliant']['geststock'] = true;

   Plugin::registerClass('PluginGeststockProfile', ['addtabon' => 'Profile']);

   $PLUGIN_HOOKS['pre_item_update']['geststock'] = ['Ticket' => ['PluginGeststockTicket', 'beforeUpdate']];
   $PLUGIN_HOOKS['item_update']['geststock']     = ['Ticket' => ['PluginGeststockTicket', 'afterUpdate']];

   $plugin = new Plugin();
   if ($plugin->isActivated("geststock")) {
      $PLUGIN_HOOKS['config_page']['geststock'] = 'front/config.form.php';
   }
   include_once(Plugin::getPhpDir('geststock')."/inc/reservation.class.php");

   if ($plugin->isActivated("simcard")) {
      PluginGeststockReservation::registerType('PluginSimcardSimcard');
   }

   $type = new PluginGeststockReservation();
   foreach ($type::$types as $key) {
      $mod = $key."Model";
      Plugin::registerClass('PluginGeststockSpecification', ['addtabon' => $mod]);
   }

   $PLUGIN_HOOKS['change_profile']['geststock']   = ['PluginGeststockProfile','initProfile'];

   if (Session::getLoginUserID()) {
      if (Session::haveRight("plugin_geststock", READ)) {
         $PLUGIN_HOOKS['menu_toadd']['geststock'] = ['tools' => 'PluginGeststockMenu'];
         Plugin::registerClass('PluginGeststockReservation', ['addtabon' => 'Ticket']);
      }
      $PLUGIN_HOOKS['use_massive_action']['geststock'] = 1;
   }

   $PLUGIN_HOOKS['plugin_pdf']['PluginGeststockReservation'] = 'PluginGeststockReservationPDF';

   $PLUGIN_HOOKS['post_init']['geststock'] = 'plugin_geststock_postinit';
}


// Get the name and the version of the plugin - Needed
function plugin_version_geststock() {

   return ['name'           => __('Stock gestion', 'geststock'),
           'version'        => '2.1.0',
           'author'         => 'Nelly Mahu-Lasson',
           'license'        => 'GPLv3+',
           'homepage'       => 'https://github.com/yllen/geststock',
           'page'           => "/front/reservation.php",
           'minGlpiVersion' => '10.0.3',
           'requirements'   => ['glpi' => ['min' => '10.0.3',
                                           'max' => '11.0.0']]];
}


function plugin_geststock_check_config() {
   return true;
}

