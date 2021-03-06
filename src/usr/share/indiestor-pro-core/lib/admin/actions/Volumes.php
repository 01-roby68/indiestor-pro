<?php


class Volumes extends EntityType
{

	static function getVolumes()
	{
		return ShellCommand::query("
		((
			df -hT | grep 'xfs' | awk '{ print $7, $4, $6, $3, toupper($2); }'
			zpool list -H | awk '{ print $1, $3, $7, $4, \"ZFS\"; }'
		) | sort | uniq )");
	}

	static function show()
	{
		$raw = self::getVolumes();
		// pretty or json
		if(ProgramActions::actionExists('json')) {
			$rows = explode("\n", $raw);
			$data = array();
			foreach ($rows as $row) {
				if(empty($row[0]))
					continue;
				$cols = explode(" ", $row);
				$data[] = array(
					'volume'=>$cols[0],
					'used'=>$cols[1],
					'ratio'=>$cols[2],
					'size'=>$cols[3],
					'filesystem'=>$cols[4]
				);
			}
			$output = json_encode_legacy($data)."\n";
		} else {
			$output = "Volume\t\tUsed\t%\tVolumeSize\tFilesystem\n";
			$format = "%-10s\t%-6s\t%-6s\t%-6s\t\t%-6s\n";
			$rows = explode("\n", $raw);
			foreach ($rows as $row) {
				if(empty($row[0]))
					continue;
				$cols = explode(" ", $row);
				$output .= sprintf($format, $cols[0], $cols[1], $cols[2], $cols[3], $cols[4]);
			}
		}
		echo $output;
	}

	static function json()
	{
		//handled by show
		return;
	}
}
