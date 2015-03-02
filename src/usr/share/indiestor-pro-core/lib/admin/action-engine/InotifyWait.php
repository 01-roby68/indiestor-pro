<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

function sysquery_pgrep($pattern)
{
	$pids=array();
	$query="pgrep -f '$pattern'";
	$result=ShellCommand::query($query,true);
	if($result->returnCode!=0) return $pids;
	$lines=explode("\n",$result->stdout);
	foreach($lines as $line)
		if(trim($line)!='')
			$pids[]=intval($line);
	return $pids;
}

class InotifyWait
{
	static function execBackground($cmd)
	{
		exec("$cmd >/dev/null 2>&1 &");
	}

	static function stopWatchingAll()
	{
                $conf=new EtcWorkspaces('avid');
                foreach($conf->workspaces as $workspace=>$path)
			self::stopWatching($workspace);
	}

	static function startWatchingAll()
	{
                $conf=new EtcWorkspaces('avid');
                foreach($conf->workspaces as $workspace=>$path)
			self::startWatching($workspace);
	}

       static function statusWatchingAll()
        {
                $conf=new EtcWorkspaces('avid');
                $countPids=0;
                foreach($conf->workspaces as $workspace=>$path)
			$countPids+=self::statusWatching($workspace);
                return $countPids;
        }

        static function statusWatching($workspace)
        {
		$pids=self::watchProcesses($workspace);
                return count($pids);
        }

	static function watchProcesses($workspace)
	{
		return array_merge(
			self::watchProcessesForWatchType($workspace,'main'),
			self::watchProcessesForWatchType($workspace,'avp')
		);
	}

	static function watchProcessesForWatchType($workspace,$watchType)
	{
		$pidsPrg=sysquery_pgrep("^/bin/sh /usr/bin/indiestor-pro-watch-avid-workspace $workspace $watchType");
		$pidsInotifyWait=sysquery_pgrep("^inotifywait --exclude __{$workspace}__{$watchType}__ ");
		return array_merge($pidsPrg,$pidsInotifyWait);
	}

	static function stopWatching($workspace)
	{
		$pids=self::watchProcesses($workspace);
		foreach($pids as $pid) {
			posix_kill($pid,SIGKILL);
                        $posix_error=posix_get_last_error();
                        if($posix_error!=0)
                                echo "posix error: $posix_error\n";
                }
	}

	static function startWatching($workspace)
	{
		self::stopWatching($workspace);

                $groupName='avid_'.$workspace;                        
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                
		if(count($group->members)<2) return;

		$foldersMain=InotifyWatchFolders::watchesMain($workspace);
		if(count($foldersMain)>0)
			self::execBackground("indiestor-pro-watch-avid-workspace $workspace main");

		$foldersAVP=InotifyWatchFolders::watchesAVP($workspace);
		if(count($foldersAVP)>0)
			self::execBackground("indiestor-pro-watch-avid-workspace $workspace avp");
	}
}

