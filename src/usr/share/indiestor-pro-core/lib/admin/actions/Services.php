<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

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

                //workspace must not exist ==> use workspace/add

                echo "to be implemented\n";
        }

        static function importAvidWorkspace($workspace) {
        }

        static function importGenericWorkspace($workspace,$rwUsers,$roUsers) {
        }

        static function workspace($commandAction)
        {
                //handled by import
        }

}

