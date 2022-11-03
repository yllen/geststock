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


class PluginGeststockGestion extends CommonDBTM {

   public $dohistory     = true;
   static $rightname     = 'plugin_geststock';
   const GESTION         = 128;
   const TRANSFERT       = 256;


   static function getTypeName($nb=0) {
      return __('Stock gestion', 'geststock');
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (Session::haveRight("plugin_geststock", READ)) {
         return self::getTypeName(1);
      }
      return '';
   }


   function defineTabs($options=[]) {

      $ong = [];
      $this->addDefaultFormTab($ong)
         ->addStandardTab('PluginGeststockReservation_Item', $ong, $options)
         ->addStandardTab('Notepad', $ong, $options)
         ->addStandardTab('Log', $ong, $options);

      return $ong;
   }


   static function showSummary() {

      $dbu = new DbUtils();

      echo "<div class='center'><table class='tab_cadre' cellpadding='5' width='50%'>";
      echo "<tr><th>".__('Summary')."</th></tr>";

      echo "<tr class='tab_bg_1'><td>";
      if ($dbu->countElementsInTable('glpi_plugin_geststock_configs') > 0) {
         echo "<a href='gestion.php'>".__('See stock', 'geststock')."</a>";
      } else {
         echo "<a href='config.form.php'>".__('Plugin not configurated', 'geststock')."</a>";
      }
      echo "</td></tr>";

      echo "</table></div>";
   }


   /**
    *
    * @param $entity   entity stock defined in config
   **/
   static function showStock($entity) {
      global $DB, $CFG_GLPI;

      $dbu = new DbUtils();
      $buttons = [];
      $buttons["gestion.php?generate=1"]= __('Export models in stock', 'geststock');
      Html::displayTitle('', '', '', $buttons);

      echo "<table class='tab_cadre_fixe'>";
      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      foreach ($DB->request('glpi_locations',
                            ['entities_id' => $entity]) as $location) {

         $head  = "<div class='spaced'><tr>";
         $head .= "<td class='b blue center' colspan='7'>".
                    sprintf(__('%1$s: %2$s'), __('Location'), $location['completename']);

         $head .= "</tr><tr class='tab_bg_2'>";
         $head .= "<th>".__('Type')."</th>";
         $head .= "<th>".__('Model')."</th>";
         $head .= "<th>".__('Number in stock', 'geststock')."</th>";
         $head .= "<th>".__('Reserved in stock', 'geststock')."</th>";
         $head .= "<th>".__('Number free', 'geststock')."</th>";
         $head .= "<th>".__('Reserved in transit', 'geststock')."</th></tr>";

         $nb = $totnb = 0;

         foreach ($CFG_GLPI["asset_types"] as $type) {
            $tabl = strtolower($type);
            $item = new $type();

            $nb = $dbu->countElementsInTable($item->getTable(),
                                             ['is_deleted'   => 0,
                                              'is_template'  => 0,
                                              'entities_id'  => $entity,
                                              'locations_id' => $location['id'],
                                              'states_id'    => [$config->fields['stock_status'],
                                                                 $config->fields['transit_status']]]);
            $totnb += $nb;
            if ($nb > 0) {
               if ($head) {
                  echo $head;
                  $head = false;
               }
               echo "<tr class='tab_bg_1'>";
               echo "<td>".sprintf(__('%1$s (%2$s)'),
                                   "<span class ='b'>".$item->getTypeName($totnb)."</span>", $nb).
                    "</td></tr>";

               foreach ($DB->request("glpi_".$tabl."models") as $data) {
                  $nbstock = PluginGeststockReservation_Item::countStock($type, $data['id'],
                                                                         $entity, $location['id']);
                  if ($nbstock > 0) {
                     echo "<tr class='tab_bg_1'>";
                     echo "<td>&nbsp;</td><td>".$data['name']."</td>";
                     echo "<td class='center'>".$nbstock."</td>";
                     echo "<td class='center'>";
                     echo PluginGeststockReservation_Item::countReserved($type, $data['id'],
                                                                         $entity, $location['id']);
                     echo "</td>";
                     echo "<td class='center'>";
                     echo PluginGeststockReservation_Item::countAvailable($type, $data['id'],
                           $entity, $location['id']);
                     echo "</td>";
                     echo "<td class='center'>";
                     echo PluginGeststockReservation_Item::countTransit($type, $data['id'],
                                                                        $entity, $location['id']);
                     echo "</td></tr>";
                  }
               }
            }
         }
         if (!$head) {
            echo "</div>";
         }
      }
      echo "</table>";
   }


   /**
    *
    * @param $entity   entity stock defined in config
   **/
   static function GenerateReport($entity) {
      global $DB;

      $text    = '';
      $dbu     = new DbUtils();
      $config  = new PluginGeststockConfig();
      $config->getFromDB(1);
      $types = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];
      foreach ($types as $type) {
         $tabl       = strtolower($type);
         $item       = new $type();
         $tablmodel  = "glpi_".$tabl."models";
         $modelid    = $tabl."models_id";

         foreach ($DB->request($tablmodel) as $model) {
            if ($model['id']) {
               $nb = $dbu->countElementsInTable($item->getTable(),
                                                ['is_deleted'  => 0,
                                                 'is_template' => 0,
                                                 'entities_id' => $entity,
                                                 $modelid      => $model['id'],
                                                 'states_id'   => [$config->fields['stock_status'],
                                                                   $config->fields['transit_status']]]);

               if ($nb > 0) {
                  $code = strtolower(substr(strrchr(Dropdown::getDropdownName($tablmodel, $model['id']),
                                                    '-'), 1));
                  $text .= $code."\t";
                  $text .= Dropdown::getDropdownName($tablmodel, $model['id'])."\t";

                  foreach ($DB->request("glpi_plugin_geststock_specifications",
                                        ['models_id' => $model['id'],
                                         'itemtype'  => $type.'Model']) as $specs) {
                     if ($specs['weight'] > 0) {
                        $text .= str_replace(".", ",",str_pad($specs['weight'], 7, "0", STR_PAD_LEFT))."\t";
                     }
                     $text .= $specs['length']."*".$specs['width']."*".$specs['height'];
                  }
                  $text .="\n";
               }
            }
         }
      }
      file_put_contents(GLPI_PLUGIN_DOC_DIR."/geststock/tova.txt", $text);
   }

}
