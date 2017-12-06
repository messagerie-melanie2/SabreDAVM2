<?php
/**
 * Ce fichier est développé pour la gestion de la librairie Mélanie2
 * Cette Librairie permet d'accèder aux données sans avoir à implémenter de couche SQL
 * Des objets génériques vont permettre d'accèder et de mettre à jour les données
 *
 * ORM M2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace LibMelanie\Lib;

use LibMelanie\Config\MappingMelanie;
use LibMelanie\Log\M2Log;

/**
 * Objet magic pour les getter et setter en fonction des requêtes SQL
 *
 * @author PNE Messagerie/Apitech
 * @package Librairie Mélanie2
 * @subpackage Lib
 */
abstract class MagicObject {
	/**
	 * Stockage des données cachées
	 * @var array
	 */
	protected $data = [];
	/**
	 * Défini si les propriété ont changé pour les requêtes SQL
	 * @var array
	 */
	protected $haschanged = [];
	/**
	 * Est-ce que l'objet existe
	 * @var bool
	 */
	protected $isExist = null;
	/**
	 * Type d'objet, lié au mapping
	 * @var string
	 */
	protected $objectType;
	/**
	 * Les clés primaires de l'objet
	 * @var mixed
	 */
	protected $primaryKeys;
	/**
	 * Classe courante
	 * @var string
	 */
	protected $get_class;

	/**
	 * Remet à 0 le haschanged
	 * @ignore
	 */
	protected function initializeHasChanged () {
		foreach (array_keys($this->haschanged) as $key) $this->haschanged[$key] = false;
	}

	/**
	 * Return data array
	 * @return array:
	 */
	public function __get_data() {
	    return $this->data;
	}

	/**
	 * Copy l'objet depuis un autre
	 * @param MagicObject $object
	 * @return boolean
	 */
	public function __copy_from($object) {
	    if (method_exists($object, "__get_data")) {
	        $this->data = $object->__get_data();
	        return true;
	    }
	    return false;
	}

	/**
	 * PHP magic to set an instance variable
	 *
	 * @access public
	 * @return
	 * @ignore
	*/
	public function __set($name, $value) {
        $lname = strtolower($name);
        // Récupèration des données de mapping
        if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
                && isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])) {
            // Typage
            if (!is_null($value) /* MANTIS 3642: Impossible de remettre à zéro le champ "event_recurenddate" */
                    && !is_array($value)
                    && isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::type])) {
                switch (MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::type]) {
                    // INTEGER
                    case MappingMelanie::integer:
                        $value = intval($value);
                        break;
                    // DOUBLE
                    case MappingMelanie::double:
                        $value = doubleval($value);
                        break;
                    // STRING
                    case MappingMelanie::string:
                        // Gérer la taille des strings dans la BDD
                        if (isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::size])) {
                            $value = mb_substr($value, 0, MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::size]);
                        }
                        break;
                    // DATE
                    case MappingMelanie::date:
                        try {
                            if ($value instanceof \DateTime) {
                                if (isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::format]))
                                    $value = $value->format(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::format]);
                                else
                                    $value = $value->format('Y-m-d H:i:s');
                            } else {
                                if (isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::format]))
                                    $value = date(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::format], strtotime($value));
                                else
                                    $value = date('Y-m-d H:i:s', strtotime($value));
                            }
                        }
                        catch (Exception $ex) {
                            M2Log::Log(M2Log::LEVEL_ERROR, "MagicObject->__set($name, $value) : Exception dans le format de date, utilisation de la valeur par defaut");
                            // Une erreur s'est produite, on met une valeur par défaut pour le pas bloquer la lecture des données
                            $value = "1970-01-01 00:00:00";
                        }

                        break;
                    // TIMESTAMP
                    case MappingMelanie::timestamp:
                        if ($value instanceof \DateTime) {
                            $value = $value->getTimestamp();
                        } else {
                            $value = intval($value);
                        }
                        break;
                }
            }
            $lname = MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::name];
        }
        if (isset($this->data[$lname]) && is_scalar($value) && !is_array($value) && $this->data[$lname] === $value)
            return false;

        $this->data[$lname] = $value;
        $this->haschanged[$lname] = true;
	}

	/**
	 * PHP magic to get an instance variable
	 * if the variable was not set previousely, the value of the
	 * Unsetdata array is returned
	 *
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __get($name) {
		$lname = strtolower($name);
		// Récupèration des données de mapping
		if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
				&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])) {
			$lname = MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::name];
		}
		if (isset($this->data[$lname])) return $this->data[$lname];
		// Récupération de la valeur par défaut
		$lname = strtolower($name);
		if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
    			&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])
    			&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::defaut]))
			return MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::defaut];
		return null;
	}

	/**
	 * PHP magic to check if an instance variable is set
	 *
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __isset($name) {
		$lname = strtolower($name);
		// Récupèration des données de mapping
		if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
				&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])) {
			$lname = MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::name];
		}
		$isset = isset($this->data[$lname]);
		// Récupération de la valeur par défaut pour déterminer si une valeur existe
		if (!$isset) {
			$lname = strtolower($name);
			$isset = isset(MappingMelanie::$Data_Mapping[$this->objectType])
						&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])
						&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::defaut]);
		}
		return $isset;
	}

	/**
	 * PHP magic to remove an instance variable
	 *
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __unset($name) {
		$lname = strtolower($name);
		// Récupèration des données de mapping
		if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
				&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$lname])) {
			$lname = MappingMelanie::$Data_Mapping[$this->objectType][$lname][MappingMelanie::name];
		}

		if (isset($this->data[$lname])) {
			unset($this->data[$lname]);
			$this->haschanged[$lname] = true;
		}
	}

	/**
	 * PHP magic to implement any getter, setter, has and delete operations
	 * on an instance variable.
	 * Methods like e.g. "SetVariableName($x)" and "GetVariableName()" are supported
	 *
	 * @access public
	 * @return mixed
	 * @ignore
	 */
	public function __call($name, $arguments) {
		$name = strtolower($name);
		$operator = substr($name, 0,3);
		$var = substr($name,3);

		// Récupèration des données de mapping
		if (isset(MappingMelanie::$Data_Mapping[$this->objectType])
				&& isset(MappingMelanie::$Data_Mapping[$this->objectType][$var])) {
			$var = MappingMelanie::$Data_Mapping[$this->objectType][$var][MappingMelanie::name];
		}

		if ($operator == "set" && count($arguments) == 1){
			$this->$var = $arguments[0];
			return true;
		}

		if ($operator == "set" && count($arguments) == 2 && $arguments[1] === false){
			$this->data[$var] = $arguments[0];
			return true;
		}

		// getter without argument = return variable, null if not set
		if ($operator == "get" && count($arguments) == 0) {
			return $this->$var;
		}

		// getter with one argument = return variable if set, else the argument
		else if ($operator == "get" && count($arguments) == 1) {
			if (isset($this->$var)) {
				return $this->$var;
			}
			else
				return $arguments[0];
		}

		if ($operator == "has" && count($arguments) == 0)
			return isset($this->$var);

		if ($operator == "del" && count($arguments) == 0) {
			unset($this->$var);
			return true;
		}
	}
}
