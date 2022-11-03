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

include ("../../../inc/includes.php");

Session::checkRightsOr('plugin_geststock',
                       [CREATE, UPDATE, DELETE, PURGE, PluginGeststockGestion::GESTION]);

if (!isset($_GET["id"])) {
   $_GET["id"] = '';
}

$ri      = new PluginGeststockReservation_Item();
$resa    = new PluginGeststockReservation();
$fup     = new PluginGeststockFollowup();
$nbre    = new PluginGeststockReservation_Item_Number();
$config  = new PluginGeststockConfig();

$config->getFromDB(1);
if (isset($_POST["upload"])) {
   // injection with fic
   if (isset($_FILES)) {
      foreach ($_FILES as $name => $file) {
         if ($file['error'] > 0) {
            $erreur = "Error during the transfer";
         }
         $extensions_valides = ['csv', 'txt'];
         $extension_upload = strtolower(substr(strrchr($file['name'], '.'), 1));
         if (!empty($file['name'])) {
            if (!in_array($extension_upload, $extensions_valides)) {
               echo "Incorrect extension (only .csv and .txt)";
            }
            $nom      = strtolower(substr($file['name'], 0,
                                  strpos($file['name'], ".")))."-".$name.".".$extension_upload;
            $resultat = move_uploaded_file($file['tmp_name'], PLUGIN_GESTSTOCK_UPLOAD_DIR.$nom);
            if (!$resultat) {
               echo "Error during the transfer";
            }
            $ligne = 1;
            $fic   = fopen(PLUGIN_GESTSTOCK_UPLOAD_DIR.$nom, "r");

            $tabid   = [];
            $ri->getFromDB($name);
            $item    = new $ri->fields['itemtype']();
            $table   = $item->getTable();
            $itemsid = strtolower($ri->fields['itemtype'])."models_id";

            while ($itemfic = fgetcsv($fic, 1024, ';')) {
               $value   = $field   = '';
               // controle numéro inventaire existant dans item
               if (!empty($itemfic[0])) {
                  $field = 'otherserial';
                  $value = $itemfic[0];
               } else if (!empty($itemfic[1])) {
                  $field = 'serial';
                  $value = $itemfic[1];
               }
               if (!empty($value)) {
                  $req = $DB->request($table,
                                      [$field        => $value,
                                       'entities_id' => $config->fields['entities_id_stock'],
                                       $itemsid      => $ri->fields['models_id']]);
                  $find = false;
                  foreach ($req as $data) {
                     $find = true;
                     // stock id of item
                      if ($item->getFromDB($data['id'])
                          && ($item->getField('states_id') == $config->fields['stock_status'])) {
                         $tabid[] = $data['id'];
                      } else {
                         Session::addMessageAfterRedirect(__('The item with this number is not free',
                                                             'geststock'), false, ERROR);
                      }
                  }
                  if ($find == false) {
                     Session::addMessageAfterRedirect(sprintf(__('Item not found with this %s number',
                                                                 'geststock'), $field." ".$value),
                                                      false, ERROR);
                  }
               }
            }
            fclose($fic);
            // controle item dans fichier et item réservés
            if (count($tabid) == $ri->fields['nbrereserv']) {
               // ajout dans table des numeros
               $input = ['plugin_geststock_reservations_items_id' => $ri->fields['id'],
                         'itemtype'                               => $ri->fields['itemtype'],
                         'models_id'                              => $ri->fields['models_id'],
                         'locations_id_stock'                     => $ri->fields['locations_id_stock'],
                         'otherserial'                            => $tabid,
                         'users_id'                               => Session::getLoginUserID()];
               $newID = $nbre->add($input);
               // change status of item
               foreach ($tabid as $id) {
                  $item->update(['id'        => $id,
                                 'states_id' => $config->fields['transit_status']]);
               }

            } else {
               Session::addMessageAfterRedirect(__('Number selected different from number reserved',
                                                   'geststock'),
                                                false, ERROR);
            }
         }
      }
   }
   Html::back();

// insert done by dropdown
} else if (isset($_POST["addotherserial"])) {
   foreach ($DB->request("glpi_plugin_geststock_reservations_items",
                         ['plugin_geststock_reservations_id' => $_POST['reservations_id']]) as $resait) {
      $resaitem = $resait['id'];
      // not in post, so deleted
      if (!isset($_POST['itemtype'][$resaitem])) {
         if ($nbre->getFromDBByRequest(['WHERE' => ['plugin_geststock_reservations_items_id' => $resaitem]])) {
            $num  = importArrayFromDB($nbre->fields['otherserial']);
            $type = $nbre->fields['itemtype'];
            $item = new $type();
            // item back to Disponible
            foreach ($num as $id => $itemid) {
               if ($item->getFromDB($itemid)) {
                  $item->update(['id'            => $itemid,
                                 'states_id'     => $config->fields['stock_status']]);
               }
            }
            // delete itemnumber in database
            $nbre->delete($nbre->fields);

         }
      } else {
         $itemtype = $_POST['itemtype'][$resaitem];
         foreach ($itemtype as $type => $model) {
            foreach ($model as $mod => $location) {
               foreach ($location as $val => $data) {
                  if (count($data) == $resait['nbrereserv']) {
                     //check if number already exists
                     $num = [];
                     if (!$nbre->getFromDBByRequest(['WHERE' => ['plugin_geststock_reservations_items_id' => $resaitem]])) {
                        $input = ['plugin_geststock_reservations_items_id' => $resaitem,
                                  'itemtype'                               => $type,
                                  'models_id'                              => $mod,
                                  'locations_id_stock'                     => $val,
                                  'otherserial'                            => $data,
                                  'users_id'                               => Session::getLoginUserID()];
                        $newID = $nbre->add($input);
                     } else  {
                        $nbre->getFromDB($nbre->getID());
                        $num = importArrayFromDB($nbre->fields['otherserial']);
                        $nbre->update(['id'            => $nbre->getID(),
                                       'otherserial'   => $data,
                                       'users_id'      => Session::getLoginUserID()]);

                        $item = new $type();
                        foreach ($num as $id => $itemid) {
                           if (!in_array($itemid, $data)
                               && $item->getFromDB($itemid)) {
                              $item->update(['id'            => $itemid,
                                             'states_id'     => $config->fields['stock_status']]);
                           }
                        }
                     }
                     $item = new $type();
                     foreach ($data as $id => $itemid) {
                        if (!in_array($itemid, $num)
                            && $item->getFromDB($itemid)) {
                           $item->update(['id'            => $itemid,
                                          'states_id'     => $config->fields['transit_status']]);
                        }
                     }
                  } else {
                     Session::addMessageAfterRedirect(__('Number selected different from number reserved',
                                                         'geststock'),
                                                      false, ERROR);
                  }
               }
            }
         }
      }
   }
   Html::back();

} else if (isset($_POST["additem"])) {
   if (empty($_POST['_itemtype'])) {
      Session::addMessageAfterRedirect(__('Thanks to specify an asset item type', 'geststock'),
                                       false, ERROR);
      Html::back();
   }
   if (!isset($_POST['model']) || empty($_POST['model'])) {
      Session::addMessageAfterRedirect(__('Thanks to specify an asset model', 'geststock'),
                                       false, ERROR);
      Html::back();
   }

   if ($_POST['_itemtype'] && ($_POST['model'] > 0) && $_POST['_nbrereserv']) {
      foreach ($_POST['_nbrereserv'] as $lieu => $val) {
         if ($val > 0) {
            $input = ['plugin_geststock_reservations_id' => $_POST['reservations_id'],
                      'models_id'                        => $_POST['model'],
                      'itemtype'                         => $_POST['_itemtype'],
                      'nbrereserv'                       => $val,
                      'locations_id_stock'               => $lieu];

            $resa->check($_POST['reservations_id'], UPDATE);
            $newID = $ri->add($input);

            $fup->add(['plugin_geststock_reservations_id'       => $_POST['reservations_id'],
                       'plugin_geststock_reservations_items_id' => $newID,
                       'locations_id_old'                       => $lieu,
                       'users_id'                              => Session::getLoginUserID()]);
         }
      }
   }
   if ($_SESSION['glpibackcreated']) {
      Html::redirect($resa->getFormURL()."?id=".$_POST['reservations_id']);
   }
   Html::back();

} else if (isset($_GET["transfert"])) {
   $resa->transfertItem($_GET["transfert"]);
   Html::back();
}

Html::footer();
