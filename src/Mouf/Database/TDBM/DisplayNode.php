<?php
namespace Mouf\Database\TDBM;

/*
 Copyright (C) 2006-2009 David Négrier - THE CODING MACHINE

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


/**
 * A class to display easily a table.
 * Can be used in cas of error to view dependencies.
 * 
 * @author David Negrier
 */
class DisplayNode {
	public static $left_start = 0;
	public static $top_start = 0;
	public static $box_width = 250;
	public static $box_height = 30;
	public static $interspace_width = 10;
	public static $interspace_height = 50;
	public static $text_height=13;
	public static $border =2;


	private $parent_node;
	public $table_name;
	public $link_type;
	public $keyParent;
	public $keyNode;
	private $children;

	public $width;

	public function __construct($table_name, $parent_node=null, $link_type=null, $keyParent=null, $keyNode=null) {
		$this->table_name = $table_name;
		if ($parent_node !== null) {
			$this->parent_node = $parent_node;
			$parent_node->children[] = $this;
			$this->link_type = $link_type;
			$this->keyParent = $keyParent;
			$this->keyNode = $keyNode;
		}
	}

	public function getChildren() {
		return $this->children;
	}

	public function displayText() {
		if ($this->parent_node !== null)
		{
			if ($this->link_type == "*1")
			{
				echo "Table $this->table_name points to table ".$this->parent_node->table_name." through its foreign key on column $this->keyNode that points to column $this->keyParent<br />";
			}
			else if ($this->link_type == "1*")
			{
				echo "Table $this->table_name is pointed by table ".$this->parent_node->table_name." by its foreign key on column $this->keyParent that points to column $this->keyNode<br />";
			}
		}

			
		if (is_array($this->children)) {
			foreach ($this->children as $child) {
				$child->displayText();
			}
		}
	}

	public function computeWidth() {
		if (!is_array($this->children) || count($this->children)==0) {
			$this->width = 1;
			return 1;
		} else {
			$sum = 0;
			foreach ($this->children as $child) {
				$sum += $child->computeWidth();
			}
			$this->width = $sum;
			return $sum;
		}
	}

	public function computeDepth($my_depth) {
		if (!is_array($this->children) || count($this->children)==0) {
			return $my_depth+1;
		} else {
			$max = 0;
			foreach ($this->children as $child) {
				$depth = $my_depth + $child->computeDepth($my_depth);
				if ($depth > $max) {
					$max = $depth;
				}
			}
			return $max;
		}
	}

	public function draw($x, $y, $left_px, $top_px) {

		$mybox_width_px = $this->width*DisplayNode::$box_width + ($this->width-1)*DisplayNode::$interspace_width;
		$my_x_px = $left_px + DisplayNode::$left_start + $x*(DisplayNode::$box_width + DisplayNode::$interspace_width);
		$my_y_px = $top_px + DisplayNode::$top_start + $y*(DisplayNode::$box_height + DisplayNode::$interspace_height);

		// White background first
		$str = "<div style='position:absolute; left:".$my_x_px."px; top:".$my_y_px."px; width:".$mybox_width_px."px; height:".DisplayNode::$box_height."; background-color:gray; color: white; text-align:center; border:".DisplayNode::$border."px solid black'>\n<b>".$this->table_name."</b></div>";

		if ($this->keyParent != null) {
			$my_x_px_line = $my_x_px + DisplayNode::$box_width/2;
			$my_y_px_line = $my_y_px - DisplayNode::$interspace_height;
			$str .= "<div style='position:absolute; left:".$my_x_px_line."px; top:".($my_y_px_line+DisplayNode::$border)."px; width:2px; height:".(DisplayNode::$interspace_height-DisplayNode::$border)."; background-color:black; '></div>\n";

			$top_key = ($this->link_type=='1*')?'* fk:':'1 pk:';
			$top_key .= '<i>'.$this->keyParent.'</i>';


			$bottom_key = ($this->link_type=='*1')?'* fk:':'1 pk:';
			$bottom_key .= '<i>'.$this->keyParent.'</i>';

			$str .= "<div style='position:absolute; left:".($my_x_px_line+2)."px; top:".($my_y_px_line+DisplayNode::$border*2)."px; background-color:#EEEEEE; font-size: 10px'>$top_key</div>\n";
			$str .= "<div style='position:absolute; left:".($my_x_px_line+2)."px; top:".($my_y_px_line+DisplayNode::$interspace_height-DisplayNode::$text_height)."px; background-color:#EEEEEE; font-size: 10px'>$bottom_key</div>\n";
		}
		//echo '<div style="position:absolute; left:100; top:70; width:2; height:20; background-color:blue"></div>';

		if (is_array($this->children)) {
			$x_new = $x;
			foreach ($this->children as $child) {
				$str .= $child->draw($x_new, $y+1, $left_px, $top_px);
				$x_new += $child->width;
			}
		}
		return $str;
	}
}
?>