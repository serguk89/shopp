<?php
/**
 * ImageServer
 * Provides low-overhead image service support
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 12 December, 2009
 * @package shopp
 * @subpackage image
 **/

chdir(dirname(__FILE__));

require_once(realpath('DB.php'));
require_once('model/Error.php');
require_once('model/Settings.php');
require_once("model/Modules.php");

require_once("model/Meta.php");
require_once("model/Asset.php");

/**
 * ImageServer class
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package image
 **/
class ImageServer extends DatabaseObject {

	var $request = false;
	var $parameters = array();
	var $args = array('width','height','scale','sharpen','quality');
	var $scaling = array('fit','mattedfit','crop','width','height');
	var $width;
	var $height;
	var $scale = 0;
	var $sharpen = 0;
	var $quality = 80;
	var $valid = false;
	var $Image = false;
	
	function __construct () {
		define('SHOPP_PATH',sanitize_path(dirname(dirname(__FILE__))));
		$this->dbinit();
		$this->request();
		$this->settings();
		if ($this->load())
			$this->render();
		else $this->error();
	}

	/**
	 * Parses the request to determine the image to load
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function request () {
		foreach ($_GET as $key => $value) {
			if ($key == "siid") $this->request = $value;
			if (isset($key) && empty($value))
				$this->parameters = explode(',',$key);
				$this->valid = array_pop($this->parameters);
		}
		
		// Handle pretty permalinks
		if (preg_match('/\/images\/(\d+).*$/',$_SERVER['REQUEST_URI'],$matches)) 
			$this->request = $matches[1];

		foreach ($this->parameters as $index => $arg) {
			$this->{$this->args[$index]} = $arg;
		}
		
		if ($this->height == 0 && $this->width > 0) $this->height = $this->width;
		if ($this->width == 0 && $this->height > 0) $this->width = $this->height;
		$this->scale = $this->scaling[$this->scale];
	}

	/**
	 * Loads the requested image for display
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return boolean Status of the image load
	 **/
	function load () {
		$this->Image = new ImageAsset($this->request);
		if (max($this->width,$this->height) > 0) $this->loadsized();
		if (!empty($this->Image->id) || !empty($this->Image->data)) return true;
		else return false;
	}
	
	function loadsized () {
		// Same size requested, skip resizing
		if ($this->width > $this->Image->width) $this->width = $this->Image->width;
		if ($this->height > $this->Image->height) $this->height = $this->Image->height;
		if ($this->Image->width == $this->width && $this->Image->height == $this->height) return;
		
		$Cached = new ImageAsset(array(
				'parent' => $this->Image->id,
				'context'=>'image',
				'type'=>'image',
				'name'=>'cache_'.implode('_',$this->parameters)
		));

		// Use the cached version if it exists, otherwise resize the image
		if (!empty($Cached->id)) $this->Image = $Cached;
		else $this->resize(); // No cached copy exists, recreate

	}
	
	function resize () {
		$key = (defined('SECRET_AUTH_KEY') && SECRET_AUTH_KEY != '')?SECRET_AUTH_KEY:DB_PASSWORD;
		$message = $this->Image->id.','.implode(',',$this->parameters);
		if ($this->valid != crc32($key.$message)) {
			header("HTTP/1.1 404 Not Found");
			die('');
		}
		
		require_once(SHOPP_PATH."/core/model/Image.php");
		$Resized = new ImageProcessor($this->Image->retrieve(),$this->Image->width,$this->Image->height);

		$scaled = $this->Image->scaled($this->width,$this->height,$this->scale);
		$alpha = ($this->Image->mime == "image/png");
		$Resized->scale($scaled['width'],$scaled['height'],$this->scale,$alpha);

		// Post sharpen
		if ($this->sharpen !== false)
			$Resized->UnsharpMask($this->sharpen);
		
		$ResizedImage = new ImageAsset();
		$ResizedImage->copydata($this->Image,false,array());
		$ResizedImage->name = 'cache_'.implode('_',$this->parameters);
		$ResizedImage->filename = $ResizedImage->name.'_'.$ResizedImage->filename;
		$ResizedImage->parent = $this->Image->id;
		$ResizedImage->context = 'image';
		$ResizedImage->mime = "image/jpeg";
		$ResizedImage->id = false;

		$ResizedImage->data = $Resized->imagefile($this->quality);
		if (empty($ResizedImage->data)) return false;
		
		$ResizedImage->size = strlen($ResizedImage->data);
		$ResizedImage->store( $ResizedImage->data );

		$ResizedImage->save();
		$this->Image = $ResizedImage;
		
	}

	/**
	 * Output the image to the browser
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return void
	 **/
	function render () {
		if ($this->Image->notfound()) return $this->error();
		header('Last-Modified: '.date('D, d M Y H:i:s', $this->Image->created).' GMT');
		header("Content-type: {$this->Image->mime}");
		if (!empty($this->Image->filename))
			header("Content-Disposition: inline; filename=".$this->Image->filename); 
		else header("Content-Disposition: inline; filename=image-".$this->Image->id.".jpg");
		header("Content-Description: Delivered by WordPress/Shopp Image Server ({$this->Image->storage})");
		$this->Image->output();
		exit();
	}
	
	/**
	 * Output a default image when the requested image is not found
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return void
	 **/
	function error () {
		header("HTTP/1.1 404 Not Found");
		$notfound = sanitize_path(dirname(__FILE__)).'/ui/icons/notfound.png';
		if (defined('SHOPP_NOTFOUND_IMAGE') && file_exists(SHOPP_NOTFOUND_IMAGE))
			$notfound = SHOPP_NOTFOUND_IMAGE;
		if (!file_exists($notfound)) die('<h1>404 Not Found</h1>');
		else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Content-type: image/png");
			header("Content-Disposition: inline; filename=".basename($notfound).""); 
			header("Content-Description: Delivered by WordPress/Shopp Image Server");
			header("Content-length: ".@strlen($notfound)); 
			@readfile($notfound);
		}
		die();
	}
	
	function settings () {
		$this->Settings = new Settings();
	}

	/**
	 * Read the wp-config file to connect to the database
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return void
	 **/
	function dbinit () {
		global $table_prefix;
		$_ = array();
		$root = $_SERVER['DOCUMENT_ROOT'];
		$found = array();
		find_filepath('wp-config.php',$root,$root,$found);
		if (empty($found[0])) $this->error();
		$config = file_get_contents($root.$found[0]);
		
		// Evaluate all define macros
		preg_match_all('/^\s*?(define\(\s*?\'(.*?)\'\s*?,\s*?(.*?)\);)/m',$config,$defines,PREG_SET_ORDER);
		foreach($defines as $defined) if (!defined($defined[2])) {
			$defined[1] = preg_replace('/\_\_FILE\_\_/',"'$root{$found[0]}'",$defined[1]);
			eval($defined[1]);
		}
		chdir(ABSPATH.'wp-content');

		// Evaluate the $table_prefix variable
		preg_match('/\$table_prefix\s*?=\s*?[\'|"](.*?)[\'|"];/',$config,$match);
		$table_prefix = $match[1];

		$db = DB::get();
		$db->connect(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
		
		if(function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get"))
			@date_default_timezone_set(@date_default_timezone_get());
			
	}
	
} // end ImageServer class

/**
 * Find a target file starting at a given directory
 *
 * @author Jonathan Davis
 * @since 1.1
 * @param string $filename The target file to find
 * @param string $directory The starting directory
 * @param string $root The original starting directory
 * @param array $found Result array that matching files are added to
 **/
function find_filepath ($filename, $directory, $root, &$found) {
	if (is_dir($directory)) {
		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file,0,1) == "." || substr($file,0,1) == "_") continue;				// Ignore .dot files and _directories
				if (is_dir($directory.'/'.$file) && $directory == $root)		// Scan one deep more than root
					find_filepath($filename,$directory.'/'.$file,$root, $found);	// but avoid recursive scans
				elseif ($file == $filename)
					$found[] = substr($directory,strlen($root)).'/'.$file;		// Add the file to the found list
			}
			return true;
		}
	}
	return false;
}

/**
 * Stub for compatibility
 **/
if (!function_exists('mktimestamp')) {
	function mktimestamp () {}
}

if (!function_exists('floatvalue')) {
	function floatvalue ($number) { return $number; }
}

if (!function_exists('__')) {
	function __ ($string,$domain=false) {
		return $string;
	}
}

/**
 * Converts paths to a uniform separator
 **/
if(!function_exists('sanitize_path')){
	function sanitize_path ($path) {
		return str_replace('\\', '/', $path);
	}
}

// Start the server
new ImageServer();

?>