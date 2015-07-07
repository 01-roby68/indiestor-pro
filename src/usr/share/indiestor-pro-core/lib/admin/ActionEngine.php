<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

requireLibFile('admin/actions/EntityType.php');
requireLibFile('admin/args/ProgramActions.php');
requireLibFile('admin/etcfiles/all.php');
requireLibFile('admin/action-engine/ActionNamingConvention.php');
requireLibFile('admin/action-engine/InotifyWatchFolders.php');
requireLibFile('admin/action-engine/InotifyWait.php');
requireLibFile('admin/action-engine/json_encode_legacy.php');
requireLibFile('admin/afp.smb.config/AfpAvidConfigGenerator.php');
requireLibFile('admin/afp.smb.config/AfpGenericConfigGenerator.php');
requireLibFile('admin/afp.smb.config/SmbConfigGenerator.php');

class ActionEngine
{
	const indiestorGroupPrefix='pro_';
	const indiestorUserGroup='indiestor-pro-users';
	const indiestorSysUserName='indiestor-pro';

	static function error($messageCode,$parameters=array())
	{
		NoticeDefinitions::instance()->error($messageCode,$parameters);
	}

	static function warning($messageCode,$parameters=array())
	{
		NoticeDefinitions::instance()->warning($messageCode,$parameters);
	}

	static function notice($messageCode,$parameters=array())
	{
		NoticeDefinitions::instance()->notice($messageCode,$parameters);
	}

	static function notify($entityType,$actionDone)
	{
		$entityType=strtolower($entityType);
		echo "$entityType/$actionDone executed.\n";
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

	static function isValidCharactersInFolderName($folder)
	{
		//a valid folder may only contain the following characters 
		return preg_match('/^[-a-zA-Z0-9_\/]*$/',$folder);
	}

        static function execute()
        {
		if(ProgramOptions::$verbose)
		{
			echo ProgramActions::toString();
		}

                $className=actionCamelCaseName(ProgramActions::$entityType);
                $scriptName='actions/'.$className.'.php';
                requireLibFile("admin/$scriptName");
                $className::execute();
        }

        static function generateImportSpecFiles() {
                self::generateImportSpecFilesAvid();
                self::generateImportSpecFilesGeneric();
        }

        static function generateImportSpecFilesAvid() {
                $wsconf=new EtcWorkspaces('avid');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //absolute path
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;
                        self::generateImportSpecFileAvid($workspace,$pathAbs);       
                }                
        }

        static function generateImportSpecFileAvid($workspace,$pathAbs) {
                $specs=[ 'type' => 'avid' ];
                file_put_contents("$pathAbs/indiestor.workspace.conf",json_encode($specs));
        }

        static function generateImportSpecFilesGeneric() {
                $wsconf=new EtcWorkspaces('generic');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //absolute path
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;
                        self::generateImportSpecFileGeneric($workspace,$pathAbs);       
                }                
        }

        static function generateImportSpecFileGeneric($workspace,$pathAbs) {
                $rwGroupName='generic_rw_'.$workspace;
                $roGroupName='generic_ro_'.$workspace;
                $etcGroup=EtcGroup::instance();
                $rwGroup=$etcGroup->findGroup($rwGroupName);    
                if($rwGroup===null ) $rwList='';
                else if($rwGroup->members===null) $rwList='';
                else $rwList=join(',',$rwGroup->members);           

                $roGroup=$etcGroup->findGroup($roGroupName); 
                if($roGroup===null) $roList='';
                else if($roGroup->members===null) $roList='';
                else $roList=join(',',$roGroup->members);           

                $specs=[ 'type' => 'generic', 'rw'=>$rwList, 'ro'=>$roList ];
                file_put_contents("$pathAbs/indiestor.workspace.conf",json_encode($specs));
        }

        static function generateAfpSmbConfig()
        {
                self::generateAfpConfig();
                self::generateSmbConfig();
                self::generateImportSpecFiles();
        }

        static function generateAfpConfig()
        {
                $buffer ="; ==========================\n";
                $buffer.="; Avid workspaces\n";
                $buffer.="; ==========================\n";

                $buffer.= AfpAvidConfigGenerator::generate() . "\n";

                $buffer.="; ==========================\n";
                $buffer.="; Generic workspaces\n";
                $buffer.="; ==========================\n";

                $buffer.= AfpGenericConfigGenerator::generate() . "\n";

                file_put_contents('/etc/indiestor-pro/indie.afp.conf',$buffer);
        }

        static function generateSmbConfig()
        {
                SmbConfigGenerator::generate();
        }

	static function restartWatching()
	{
		EtcGroup::reset();
		EtcPasswd::reset();
		Incrontab::generate();
		InotifyWait::startWatchingAll();
	}
}

