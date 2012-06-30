<?php
/*
        Indiestor program

	Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        By Alex Gardiner, alex.gardiner@canterbury.ac.uk
*/

require_once('actions/EntityType.php');
require_once('args/ProgramActions.php');
require_once(dirname(dirname(__FILE__)).'/common/etcfiles/all.php');
require_once('syscommands/all.php');
require_once('sysqueries/all.php');
require_once('action_engine/ActionNamingConvention.php');
require_once('action_engine/UserReportRecord.php');
require_once('action_engine/UserReportRecords.php');
require_once('action_engine/DeviceQuota.php');
require_once('action_engine/BlockGBConvertor.php');
require_once('action_engine/Incrontab.php');

class ActionEngine
{
	const indiestorGroupPrefix='is_';
	const indiestorUserGroup='indiestor-users';
	const indiestorSysUserName='indiestor';

	static function error($messageCode,$parameters=array())
	{
		NoticeDefinitions::instance()->error($messageCode,$parameters);
	}

	static function warning($messageCode,$parameters=array())
	{
		NoticeDefinitions::instance()->warning($messageCode,$parameters);
	}

	static function notify($entityType,$actionDone)
	{
		$entityType=strtolower($entityType);
		echo "$entityType/$actionDone executed.\n";
	}

	static function sysGroupName($indieStorGroupName)
	{
		return self::indiestorGroupPrefix.$indieStorGroupName;
	}

	static function isSysGroupIndiestorGroup($sysGroupName)
	{
		$lenISGPrefix=strlen(self::indiestorGroupPrefix);
                if(strlen($sysGroupName)>= $lenISGPrefix) 
			$prefix=substr($sysGroupName,0,$lenISGPrefix);
		else return false;
                if($prefix==self::indiestorGroupPrefix)
			return true;
		else return false;
	}

	static function isIndiestorSysUserName($userName)
	{
		return $userName==self::indiestorSysUserName;
	}

	static function indiestorGroupName($sysGroupName)
	{
		$lenISGPrefix=strlen(self::indiestorGroupPrefix);
		if(!self::isSysGroupIndiestorGroup($sysGroupName)) return '';
                return substr($sysGroupName,$lenISGPrefix);
	}

	static function isValidCharactersInName($name)
	{
		//a valid name must start with a letter
		//and be followed by a letter of a digit, a dash or an underscore
		return preg_match('/^[a-z][-a-z0-9_]*$/',$name);
	}

	static function isValidCharactersInVolume($volume)
	{
		//a valid volume may only contain the following characters 
		return preg_match('/^[-a-z0-9_\/]*$/',$volume);
	}

	static function isValidCharactersInFolderName($folder)
	{
		//a valid folder may only contain the following characters 
		return preg_match('/^[-a-z0-9_\/]*$/',$folder);
	}

	static function failOnOpenVZ($device)
	{
		if($device=='/dev/simfs')
			ActionEngine::error("Device '$device' is an openvz/virtuozzo filesystem. ".
				"We do not support the openvz/virtuozzo second-level user quota system",
				ERRNUM_VOLUME_OPENVZ_UNSUPPORTED);
	}

        static function execute()
        {
		if(ProgramOptions::$verbose)
		{
			echo ProgramActions::toString();
		}

                $className=actionCamelCaseName(ProgramActions::$entityType);
                $scriptName='actions/'.$className.'.php';
                require_once($scriptName);
                $className::execute();
        }

	static function regenerateIncrontab()
	{
		Incrontab::generate();
	}
}

