<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

require_once('User.php');
require_once('Workspace.php');
require_once('AvidWorkspace.php');
require_once('GenericWorkspace.php');

class Services extends EntityType
{
        static function startIncron($commandAction)
        {
                self::upstartServiceAction('incron','start');
        }

        static function stopIncron($commandAction)
        {
                self::upstartServiceAction('incron','stop');
                sleep(1);
                /* incron may refuse to stop on ubuntu precise */
                $pid=ShellCommand::query("ps ax | grep incron | head -n1 | awk '{ print $1}'");
                if($pid!='') ShellCommand::exec("kill -9 $pid");
        }

        static function upstartServiceAction($serviceName,$action)
        {
                ShellCommand::exec("service $serviceName $action");
        }

        static function upstartServiceStatus($serviceName)
        {
                $stdout=ShellCommand::query("service $serviceName status");
                if(preg_match('/not|stop/',$stdout)) return false;
                else return true;
        }

        static function netatalkServiceStatus($serviceName)
        {
                $stdout=ShellCommand::query("ps aux | grep afp");
                if(preg_match("/afpd/",$stdout)) return true;
                else return false;
        }

        static function status()
        {
                {
                $status=array();
                $status['samba']=self::upstartServiceStatus('samba');
                $status['incron']=self::upstartServiceStatus('incron');
                $status['netatalk']=self::netatalkServiceStatus('netatalk');
                $countPids=InotifyWait::statusWatchingAll();
                if($countPids>0)
                        $status['watching']=true;
                else    $status['watching']=false;
                return $status;
                }

        }

        static function show($commandAction)
        {
                if(ProgramActions::actionExists('json'))
                        self::showJSON();
                else
                        self::showCLI();
        }

        static function showCLI()
        {
                $status=self::status();
                foreach($status as $service=>$serviceStatus) 
                {
                        if ($serviceStatus == 1)
                        {
                                echo "$service running\n";
                        }
                        else
                                echo "$service not running\n";
                }
        }

        static function showJSON()
        {
                echo json_encode_legacy(self::status())."\n";
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

	static function startWatching($commandAction)
	{
        	ShellCommand::exec_fail_if_error("incrontab -u indienotify-pro --remove");
        	ShellCommand::exec_fail_if_error("echo '/var/spool/indiestor-pro ".
                        "IN_CREATE /usr/bin/indiestor-pro-inotify-locked-start >> /dev/null 2>&1' | incrontab -u indienotify-pro -");
		InotifyWait::startWatchingAll();
	}

	static function stopWatching($commandAction)
	{
        	ShellCommand::exec_fail_if_error("incrontab -u indienotify-pro --remove");
		InotifyWait::stopWatchingAll();
	}

        static function refreshShareDefinitions($commandAction)
        {
                ActionEngine::generateAfpSmbConfig();
        }

        static function import($commandAction)
        {
                //collect folder and workspace
                $folder=$commandAction->actionArg;
                //remove trailing slash
                $folder=rtrim($folder, '/'); 

                $workspaceAction=ProgramActions::findByName('workspace');
                if($workspaceAction==null) {
                        //should not happen
                        //this would be a bug in the commandline arg parser
                       ActionEngine::err("workspace not supplied");
                }
                $workspace=$workspaceAction->actionArg;

                //folder must exist
                if(!is_dir($folder)) {
                       ActionEngine::err("'$folder' not a valid folder");
                }

                $configFile="$folder/indiestor.workspace.conf";

                //config file must exist
                if(!file_exists($configFile)) {
                       ActionEngine::err("config file '$configFile' not found");
                }

                //config file must be valid json & valid structure
                //avid: {"type":"avid"}
                //generic: {"type":"generic","rw":"kk1,kk2,kk3,kk4","ro":"kk5"}

                $importConfig=json_decode(file_get_contents($configFile),true);
                if($importConfig==null) {
                       ActionEngine::err("config file '$configFile' not a valid JSON file");
                }

                if(!isset($importConfig['type'])) {
                       ActionEngine::err("config file '$configFile' has no 'type' field");
                }

                $type=$importConfig['type'];

                if(substr($folder,0,1)!=='/')
                        $pathAbs="/$folder";
                else $pathAbs=$folder;
                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));
                if($fileSystem=='zfs') {
                        $pathArg=substr($pathAbs, 1);
                } else {
                        $pathArg=$pathAbs;
                }


                switch($type) {
                        case 'avid': self::importAvidWorkspace($workspace,$folder,$pathArg); break;
                        case 'generic': self::importGenericWorkspace($workspace,$folder,$pathArg,$importConfig); break;
                        default: ActionEngine::err("Invalid workspace type '$type' in '$configFile'");
                }

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
                //startwatching
                InotifyWait::startWatching($workspace);
        }

        static function importAvidWorkspace($workspace,$folder,$pathArg) {

                //add workspace
                AvidWorkspace::addWithParms($workspace,$pathArg);

                //recover users
                $foldersGlobbed=glob("$folder/*",GLOB_ONLYDIR);
                $users=[];
                foreach($foldersGlobbed as $folder) {
                        $basename=basename($folder);
                        if($basename!='CNID') {
                                $users[$basename]=$basename;
                        }
                }

                //remove symlinks
                ShellCommand::exec("find $folder -type l -exec rm {} \;");

                //add users
	        $etcPasswd=EtcPasswd::instance();
                foreach($users as $user) {
		        if(!$etcPasswd->exists($user)) {
                                if($result=User::validateUserName($user)!='OK') {
                                        echo "$result. Failed to add username: $user\n";
                                } else {
                                        User::addWithParms($user);
                                }
                        }
                        AvidWorkspace::addUserWithParms($workspace,$user,false);
                }
                AvidWorkspace::reshareWithParms($workspace);
        }

        static function importGenericWorkspace($workspace,$folder,$pathArg,$importConfig) {

                //add workspace
                GenericWorkspace::addWithParms($workspace,$pathArg);

                //recover users

                if(!isset($importConfig['rw'])) {
                       ActionEngine::err("config file '$configFile' has no 'rw' field");
                }
                
                $rwUsersCSV=$importConfig['rw'];
                $rwUsers=explode(',',$rwUsersCSV);
                
                if(!isset($importConfig['ro'])) {
                       ActionEngine::err("config file '$configFile' has no 'ro' field");
                }

                $roUsersCSV=$importConfig['ro'];
                $roUsers=explode(',',$roUsersCSV);

                //remove symlinks
                ShellCommand::exec("find $folder -type l -exec rm {} \;");

                //add rw users
	        $etcPasswd=EtcPasswd::instance();
                foreach($rwUsers as $user) {
		        if(!$etcPasswd->exists($user)) {
                                if($result=User::validateUserName($user)!='OK') {
                                        echo "$result. Failed to add read-write user: $user\n";
                                } else {
                                        User::addWithParms($user);
                                }
                        }
                        GenericWorkspace::addWriteUserWithParms($workspace,$user);
                }

                //add ro users
	        $etcPasswd=EtcPasswd::instance();
                foreach($roUsers as $user) {
		        if(!$etcPasswd->exists($user)) {
                                if($result=User::validateUserName($user)!='OK') {
                                        echo "$result. Failed to add read-only user: $user\n";
                                } else {
                                        User::addWithParms($user);
                                }       
                 }
                        GenericWorkspace::addReadOnlyUserWithParms($workspace,$user);
                }
        }

        static function workspace($commandAction)
        {
                //handled by import
        }

}

