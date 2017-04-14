<?php
/**
 * Fichier de gestion des logs pour l'application SabreDAV
 * SabreDAVM2 Copyright © 2017 PNE Annuaire et Messagerie/MEDDE
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Lib\Log;

/**
 * Logging class:
 * - contains lfile, lwrite and lclose public methods
 * - lfile sets path and name of log file
 * - lwrite writes message to the log file (and implicitly opens log file)
 * - lclose closes log file
 * - first call of lwrite method will open log file implicitly
 * - message is written with the following format: [d/M/Y:H:i:s] (script name) message
 * see http://www.redips.net/php/write-to-log-file/
 * 
 * @package Lib
 * @subpackage Log
 */
class Logging {
	/**
	 * declare log file and file pointer as private properties
	 */
	private $log_file, $fp;
	//
	/**
	 * set log file (path and name)
	 * 
	 * @param string $path        	
	 */
	public function lfile($path) {
		$this->log_file = $path;
	}
	
	/**
	 * write message to the log file
	 * 
	 * @param string $message        	
	 */
	public function lwrite($message) {
		try {
			// if file pointer doesn't exist, then open log file
			if (! is_resource($this->fp)) {
				$this->lopen();
			}
			// define current time and suppress E_WARNING if using the system TZ settings
			// (don't forget to set the INI setting date.timezone)
			$time = @date('[d/M/Y:H:i:s]');
			// write current time, script name and message to the log file
			fwrite($this->fp, "$time $message" . PHP_EOL);
		}
		catch (Exception $ex) {
			echo "Exception: $ex";
			exit();
		}
	}
	
	/**
	 * close log file (it's always a good idea to close a file when you're done with it)
	 */
	public function lclose() {
		try {
			fclose($this->fp);
		}
		catch (Exception $ex) {
			echo "Exception: $ex";
			exit();
		}
	}
	
	/**
	 * open log file (private method)
	 */
	private function lopen() {
		try {
			// set default log file for Linux and other systems
			$log_file_default = '/tmp/logfile.log';
			// define log file from lfile method or use previously set default
			$lfile = $this->log_file ? $this->log_file : $log_file_default;
			// open log file for writing only and place file pointer at the end of the file
			// (if the file does not exist, try to create it)
			$this->fp = fopen($lfile, 'a') or exit("Can't open $lfile!");
		}
		catch (Exception $ex) {
			echo "Exception: $ex";
			exit();
		}		
	}
}