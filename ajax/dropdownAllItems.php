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
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

// Make a select box

if (isset($_POST["itemtype"])) {
  PluginGeststockReservation::showAllModels( $_POST['myname'], $_POST['itemtype']);
}
