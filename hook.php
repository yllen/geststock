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
 @copyright Copyright (c) 2017-2018 GestStock plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link
 @since     version 1.0.0
 --------------------------------------------------------------------------
 */

function plugin_geststock_install() {
   global $DB;

   $migration = new Migration(10);

   include_once(GLPI_ROOT."/plugins/geststock/inc/gestion.class.php");

   include_once(GLPI_ROOT."/plugins/geststock/inc/config.class.php");
   PluginGeststockConfig::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/followup.class.php");
   PluginGeststockFollowup::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/reservation.class.php");
   PluginGeststockReservation::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/reservation_item.class.php");
   PluginGeststockReservation_Item::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/profile.class.php");
   PluginGeststockProfile::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/specification.class.php");
   PluginGeststockSpecification::install($migration);

   include_once(GLPI_ROOT."/plugins/geststock/inc/reservation_item_number.class.php");
   PluginGeststockReservation_Item_Number::install($migration);


   if (!is_dir(GLPI_PLUGIN_DOC_DIR.'/geststock')) {
      mkdir(GLPI_PLUGIN_DOC_DIR.'/geststock');
   }
   if (!is_dir(PLUGIN_GESTSTOCK_UPLOAD_DIR)) {
      mkdir(PLUGIN_GESTSTOCK_UPLOAD_DIR);
   }

   return true;
}


function plugin_geststock_uninstall() {
   global $DB;

   $tables = ['glpi_plugin_geststock_reservations',
              'glpi_plugin_geststock_reservations_items',
              'glpi_plugin_geststock_specifications'];

   foreach($tables as $table) {
      $DB->query("DROP TABLE `$table`");
   }

   include_once(GLPI_ROOT."/plugins/geststock/inc/config.class.php");
   PluginGeststockConfig::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/followup.class.php");
   PluginGeststockFollowup::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/reservation.class.php");
   PluginGeststockReservation::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/reservation_item.class.php");
   PluginGeststockReservation_Item::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/profile.class.php");
   PluginGeststockProfile::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/specification.class.php");
   PluginGeststockSpecification::uninstall();

   include_once(GLPI_ROOT."/plugins/geststock/inc/menu.class.php");
   PluginGeststockMenu::removeRightsFromSession();

   if (is_dir(GLPI_PLUGIN_DOC_DIR.'/geststock')) {
      Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR.'/geststock');
   }

   $itemtypes = ['DisplayPreference', 'Bookmark', 'Log', 'Notepad'];
   foreach ($itemtypes as $itemtype) {
      $item = new $itemtype;
      $item->deleteByCriteria(array('itemtype' => 'PluginGeststockReservation'));
   }

   return true;
}


function plugin_geststock_giveItem($type, $ID, $data, $num) {
   global $DB;

   $searchopt = &Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   $dbu = new DbUtils();

   switch ($table.'.'.$field) {
      case "glpi_plugin_geststock_reservations_items.models_id" :
         $resa_id       = $data['id'];
         $query_device = ['SELECT DISTINCT' => 'itemtype',
                          'FROM'            => 'glpi_plugin_geststock_reservations_items',
                          'WHERE'           => ['plugin_geststock_reservations_id' => $resa_id],
                          'ORDER'           => 'itemtype'];
         $result_device = $DB->request($query_device);
         $number_device = count($result_device);
         $out           = '';
         if ($number_device > 0) {
            for ($y=0 ; $y < $number_device ; $y++) {
               $column = "name";
               $row    = $result_device->next();
               $type   = $row['itemtype'];
               if (!($item = $dbu->getItemForItemtype($type))) {
                  continue;
               }
               $table = "glpi_".strtolower($item->getType())."models";
               $plugin = new Plugin();
               if ($plugin->isActivated("simcard")
                   && ($type == "PluginSimcardSimcard")) {
                 $table = 'glpi_plugin_simcard_simcardtypes';
               }
               if (!empty($table)) {
                  $query = "SELECT `".$table."`.`$column`, `nbrereserv`
                            FROM `glpi_plugin_geststock_reservations_items`, `".$table."`
                            WHERE `".$table."`.`id`
                                       = `glpi_plugin_geststock_reservations_items`.`models_id`
                                  AND `glpi_plugin_geststock_reservations_items`.`itemtype`
                                       = '".$type."'
                                  AND `glpi_plugin_geststock_reservations_items`.`plugin_geststock_reservations_id`
                                       = '".$resa_id."'
                            ORDER BY `$table`.`$column`";

                  if ($result_linked = $DB->query($query)) {
                     if ($DB->numrows($result_linked)) {
                        while ($data = $DB->fetch_assoc($result_linked)) {
                           $out .= $data['nbrereserv']. " ".$item->getTypeName($data['nbrereserv'])." - ".$data['name']."<br>";

                        }
                     }
                  }
               }
            }
         }
         return $out;

      case "glpi_plugin_geststock_reservations.entities_id_deliv" :
         $out  = '';
         foreach($DB->request($table, ['id' => $data['id']]) as $resa) {
            foreach($DB->request('glpi_entities', ['id' => $resa['entities_id_deliv']]) as $ent) {
               $out .= $ent['completename']."<br>";
            }
         }
         return $out;

      case "glpi_plugin_geststock_reservations.locations_id" :
         $out  = '';
         foreach($DB->request($table, ['id' => $data['id']]) as $resa) {
            foreach($DB->request('glpi_locations', ['id' => $resa['locations_id']]) as $loc) {
               $out .= $loc['completename']."<br>";
            }
         }
         return $out;

      case "glpi_plugin_geststock_followups.locations_id_new" :
         $out  = '';
         foreach($DB->request($table, ['plugin_geststock_reservations_id' => $data['id']]) as $fups) {
            $out .= dropdown::getDropdownName('glpi_locations', $fups['locations_id_old']);
            $out .= "<br />";
         }
         return $out;

   }
   return "";
}


function plugin_geststock_postinit() {
   global $PLUGIN_HOOKS;

   $type = new PluginGeststockReservation();
   foreach ($type::$types as $key) {
      $mod = $key."Model";
      if (class_exists('PluginSimcardSimcard')) {
         $mod = 'PluginSimcardSimcardType';
      }
      Plugin::registerClass('PluginGeststockSpecification', ['addtabon' => $mod]);
   }
}


function plugin_geststock_getAddSearchOptions($itemtype) {
   global $CFG_GLPI;

   $tab = [];
    $obj = substr($itemtype, 0, -5);
    if (in_array($obj, $CFG_GLPI['asset_types'])) {
       $tab[] = ['id'         => '3',
                 'table'      => 'glpi_plugin_geststock_specifications',
                 'field'      =>  'length',
                 'name'       =>  __('Length', 'geststock'),
                 'datatype'   =>  'number',
                 'joinparams' => ['jointype'  => 'child',
                                  'condition' => "AND NEWTABLE.`itemtype` = '". $obj."'",
                                  'linkfield' => 'models_id']];

       $tab[] = ['id'         => '4',
                 'table'      => 'glpi_plugin_geststock_specifications',
                 'field'      => 'width',
                 'name'       => __('Width', 'geststock'),
                 'datatype'   => 'number'];

       $tab[] = ['id'         => '5',
                 'table'      => 'glpi_plugin_geststock_specifications',
                 'field'      => 'height',
                 'name'       => __('Height', 'geststock'),
                 'datatype'   => 'number'];

       $tab[] = ['id'         => '6',
                 'table'      => 'glpi_plugin_geststock_specifications',
                 'field'      => 'weight',
                 'name'       => __('Weight', 'geststock'),
                 'datatype'   => 'decimal'];

       $tab[] = ['id'         => '7',
                 'table'      => 'glpi_plugin_geststock_specifications',
                 'field'      => 'volume',
                 'name'       => __('Volume', 'geststock'),
                 'datatype'   => 'decimal'];
    }
    return $tab;
}


function plugin_geststock_addWhere($link, $nott, $type, $id, $val) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$id]["table"];
   $field = $searchopt[$id]["field"];

   switch ($type) {
      case 'PluginGeststockReservation' :
         if ($table == 'glpi_plugin_geststock_reservations_items') {
            if ($field == 'locations_id_stock') {
               return $link." `$table`.`$field` = $val";
            }
         }
         break;
   }
   return "";
}