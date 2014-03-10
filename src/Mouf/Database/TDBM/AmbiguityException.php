<?php
namespace Mouf\Database\TDBM;

/**
 * An exception thrown when an ambiguity is detected in a query.
 * An ambiguity happens when 2 different paths can be taken to bind 2 tables (and when those path have the same length).
 *
 */
class AmbiguityException extends TDBMException {
	private $paths;
	private $tdbmService;

	public function __construct($msg, $paths, TDBMService $tdbmService) {
		parent::__construct($msg);
		$this->paths = $paths;
		$this->tdbmService = $tdbmService;
	}

	public function explainAmbiguity() {
		//var_dump($this->paths);
		//echo 'Yop<br>';
		//var_dump($this->getAllPossiblePathsRec($this->paths));
		//var_dump($this->getAllPossiblePaths());
		$all_paths = $this->getAllPossiblePaths();

		$i=0;
		$width_px = 0;
		$height_px = 0;
		$global_height_px = 0;
		foreach ($all_paths as $paths) {
			$tree = $this->tdbmService->getTablePathsTree($paths);
			echo $this->tdbmService->drawTree($tree, 0, $global_height_px, $width_px, $height_px);

			echo "<div style='position:absolute; left:".$width_px."px; top:".$global_height_px."px; width:600px; height:".$height_px."; background-color:#EEEEEE; color: black; text-align:left;'>If you want to use this schema, use the code below:<br/><br/><code>";

			ob_start();
			var_export($paths);
			$var = ob_get_clean();

			echo '$hint = '.$var.';';
			echo "</code><br/><br/>";
			echo 'Then, pass the $hint variable to your getObjects function.';
			echo "</div>";

			$global_height_px += $height_px+10;
			$i++;
		}

	}

	private function getAllPossiblePaths() {
		/*$demultiplied_paths = array();
		 foreach (AmbiguityException::getAllPossiblePathsRec($this->paths) as $path)
		 {
			$temp_path = array();
			$temp_path['name']=$this->paths[0]['name'];
			$temp_path = array_merge($temp_path, $path);
			$demultiplied_paths[] = $temp_path;
			}*/
		//foreach ($this->paths as $path) {
			
		//}
		//return $demultiplied_paths;
		return AmbiguityException::getAllPossiblePathsRec($this->paths);
	}

	private static function getAllPossiblePathsRec($sub_table_paths)
	{
		if (count($sub_table_paths)==0)
		return array();

		$table_path = array_shift($sub_table_paths);
		$possible_sub_paths =  AmbiguityException::getAllPossiblePathsRec($sub_table_paths);
		$return_table_paths = array();
		foreach ($table_path['paths'] as $path) {
			if (count($possible_sub_paths)>0)
			{
				foreach ($possible_sub_paths as $possible_sub_path)
				{
					$return_table_paths[] = array_merge(array(array('paths'=>array($path))), $possible_sub_path);
				}
			}
			else
			$return_table_paths[] = array(array('paths'=>array($path)));
		}
		return $return_table_paths;
	}
}
?>