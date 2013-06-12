<?php

// The **orderby** filter orders that data based on the alphabetical order of certain keys, for example "data.links|orderby_title":

$orderbyglobal = '';
$filter = new Twig_SimpleFilter('orderby_*', function ($orderby, $data) {
	
	global $orderbyglobal;
	$orderbyglobal = explode('_', $orderby);
	
	function alphaSort($a, $b)
	{
		global $orderbyglobal;
	    return strcmp(strtolower($a[$orderbyglobal[0]]), strtolower($b[$orderbyglobal[0]]));
	}
	usort($data, "alphaSort");
	
	if ($orderbyglobal[1] == 'desc'){
		$data = array_reverse($data);
	}
	
	return $data;
});
$twig->addFilter($filter);

// The **where** filter reduces the data set by the content of a specific key, for example "data.links|where_visible_is_yes":

$filter = new Twig_SimpleFilter('where_*_is_*', function ($var, $state, $data) {
	
	if ($state == 'false') $state = false;
	if ($state == 'true') $state = true;
	
	$newdata = array();
	if (count($data) > 0){
		foreach($data as $k => $v){
			if (isset($v[$var]) && $v[$var] == $state){
				$newdata[] = $v;
			}
		}
	}
	
	return $newdata;
});
$twig->addFilter($filter);

// The **autolink** filter converts text links into real clickable HTML links:

$filter = new Twig_SimpleFilter('autolink', function ($string) {
	
	if (is_string($string)){
		$ret = ' ' . $string;
	    $ret = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t<]*)#ise", "'\\1<a href=\"\\2\" target=\"_blank\">\\2</a>'", $ret);
	    $ret = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r<]*)#ise", "'\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>'", $ret);
	    $ret = preg_replace("#(^|[\n ])([a-z0-9&\-_\.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a href=\"mailto:\\2@\\3\">\\2@\\3</a>", $ret);
	    $ret = substr($ret, 1);
		$string = $ret;
	}
	
	return $string;
}, array('pre_escape' => 'html', 'is_safe' => array('html')));
$twig->addFilter($filter);

// The **human_filesize** filter converts an integer representing bytes into a human readable format:

$filter = new Twig_SimpleFilter('human_filesize', function ($bytes) {
	
	$types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
    return( round( $bytes, 2 ) . " " . $types[$i] );
	
});
$twig->addFilter($filter);

// The **paginate** filter selects a certain number of entries with a certain offset based on the slug:

$filter = new Twig_SimpleFilter('paginate_*', function ($limit, $data) {
	
	global $slug, $pagination;
	$page = $slug;
	
	if (is_numeric($limit)){
	
		if (!is_numeric($page)){
			$page = 1;
		}
	
		$from = ($page-1) * $limit;
		
		$pagination['limit'] = $limit;
		$pagination['data'] = $data;
		$pagination['page'] = $page;
		
		return array_slice($data, $from, $limit);
	
	} else {
		return $data;
	}
	
});
$twig->addFilter($filter);

// Test if data is numeric:

function is_numeric_test($item){
    return is_numeric($item);
}
$twig->addTest('numeric', new Twig_Test_Function('is_numeric_test'));

// The **prev** function create a link for the previous page in a pagination:

$function = new Twig_SimpleFunction('prev', function () {
	
	global $page, $slug, $pagination;
	
	$newpage = false;
	
	if (is_numeric($slug) && $slug > 1 && isset($pagination['page'])){
		if ($slug > 2){
			$newpage =  str_replace('.html', '--'.($slug-1).'.html', $page);
		} else {
			$newpage = $page;
		}
	}
	
	return $newpage;
	
});
$twig->addFunction($function);

// The **next** function create a link for the next page in a pagination:

$function = new Twig_SimpleFunction('next', function () {
	
	global $page, $slug, $pagination;
	
	$newpage = false;
	
	if (isset($pagination['page'])){
		if (is_numeric($slug) && ($slug)*$pagination['limit'] < count($pagination['data'])){
			$newpage =  str_replace('.html', '--'.($slug+1).'.html', $page);
		} else if ($slug == '' && count($pagination['data']) > $pagination['limit']){
			$newpage =  str_replace('.html', '--2.html', $page);
		}
	}
	
	return $newpage;
	
});
$twig->addFunction($function);

// The **exists** function checks if a file exists:

$function = new Twig_SimpleFunction('exists', function ($res) {
	
	global $page;
	$fromdev = false;
	$exists = false;
	
	$pageparts = pathinfo($page);
	if ($pageparts['dirname'] != './'){
		$file = $res;
	} else {
		$file = $pageparts['dirname'].'/'.$res;
	}
	
	if ($_SERVER['REMOTE_ADDR'] == DEVIP && file_exists(DEVDIR.'/'.$file)){
		$fromdev = true;
		$exists = true;
	} else {
		$file = PAGESDIR.'/'.$file;
		if (file_exists($file)){
			$exists = true;
		}
	}
	
	if (!$fromdev){
		if ($pageparts['dirname'] != '.'){
			if (file_exists($pageparts['dirname'].'/'.$res)){
				$exists = true;
			} else {
				$res = '../'.$res;
				$parentdir = dirname($pageparts['dirname']);
				if ($parentdir != '.'){
					if (file_exists($parentdir.'/'.$res)){
						$exists = true;
					}
				}
			}
		}
	}
	
	return $exists;
	
});
$twig->addFunction($function);

// The **resource** function makes sure that the right resource will be loaded and checks parent directories if needed. Additionally it parses LESS, SASS and CoffeeScript files if needed:

$function = new Twig_SimpleFunction('resource', function ($res, $opt = '' ) {
	
	global $page;
	
	$fromdev = false;
	
	$pageparts = pathinfo($page);
	if ($pageparts['dirname'] != './'){
		$file = $res;
	} else {
		$file = $pageparts['dirname'].'/'.$res;
	}
	
	if ($_SERVER['REMOTE_ADDR'] == DEVIP && file_exists(DEVDIR.'/'.$file) && $opt != 'page'){
		$fromdev = true;
		$res = ROOTURL.DEVDIR.'/'.$res;
		$file = DEVDIR.'/'.$file;
	} else {
		$file = PAGESDIR.'/'.$file;
	}
	
	$fileparts = pathinfo($file);
	if (isset($fileparts['extension'])){
		if ($fileparts['extension'] == 'sass' || $fileparts['extension'] == 'scss'){
		
			$cssfile = $fileparts['dirname'].'/'.$fileparts['filename'].'.css';
		
			if ((file_exists($cssfile) && filemtime($cssfile) < filemtime($file)) || !file_exists($cssfile)){
			
				require 'lib/phpsass/SassParser.php';
				$options = array(
					'style' => 'expanded',
					'syntax' => $fileparts['extension'],
					'cache' => FALSE,
					'debug' => FALSE
				);
				$parser = new SassParser($options);
				$data = $parser->toCss($file);
				file_put_contents($cssfile, $data);

			}
		
			$res = str_replace('.'.$fileparts['extension'], '.css', $res);
		
		} else if ($fileparts['extension'] == 'less'){
		
			$cssfile = $fileparts['dirname'].'/'.$fileparts['filename'].'.css';
		
			if ((file_exists($cssfile) && filemtime($cssfile) < filemtime($file)) || !file_exists($cssfile)){
		
				require 'lib/lessphp/lessc.inc.php';
				$less = new lessc;
				$data = $less->compileFile($file);
				file_put_contents($cssfile, $data);
			
			}
		
			$res = str_replace('.less', '.css', $res);
		
		} else if ($fileparts['extension'] == 'coffee'){
		
			$jsfile = $fileparts['dirname'].'/'.$fileparts['filename'].'.js';
		
			if ((file_exists($jsfile) && filemtime($jsfile) < filemtime($file)) || !file_exists($jsfile)){
		
				require 'lib/CoffeeScript/Init.php';
				CoffeeScript\Init::load();
				$coffee = file_get_contents($file);
				$data = CoffeeScript\Compiler::compile($coffee, array('filename' => $file));
				file_put_contents($jsfile, $data);
			
			}
		
			$res = str_replace('.coffee', '.js', $res);
		
		} else if ($fileparts['extension'] == 'md'){
		
			$htmlfile = $fileparts['dirname'].'/'.$fileparts['filename'].'.html';
		
			if ((file_exists($htmlfile) && filemtime($htmlfile) < filemtime($file)) || !file_exists($htmlfile)){
			
				$data = Markdown(file_get_contents($file));
				file_put_contents($htmlfile, $data);
			
			}
		
			$res = str_replace('.md', '.css', $res);
		
		} else if ($fileparts['extension'] == 'jpg' || $fileparts['extension'] == 'jpeg' || $fileparts['extension'] == 'png' || $fileparts['extension'] == 'gif'){
		
			$preoptsplit = explode(':', $opt);
		
			if (count($preoptsplit) == 2){
			
				$method = $preoptsplit[0];
				$optsplit = explode('x', $preoptsplit[1]);
			
				if (count($optsplit) == 2){
			
					$thumbfile = $fileparts['dirname'].'/'.$fileparts['filename'].'.'.$method.'.'.$optsplit[0].'x'.$optsplit[1].'.jpg';
					
					if ((file_exists($thumbfile) && filemtime($thumbfile) < filemtime($file)) || !file_exists($thumbfile)){
			
						require_once 'lib/phpthumb/ThumbLib.inc.php';
						$thumb = PhpThumbFactory::create($file);
						
						if ($method == 'resize'){
							$thumb->resize($optsplit[0], $optsplit[1])->save($thumbfile);
						} else if ($method == 'resize-adaptive'){
							$thumb->adaptiveResize($optsplit[0], $optsplit[1])->save($thumbfile);
						} else if ($method == 'resize-crop'){
							$thumb->cropFromCenter($optsplit[0], $optsplit[1])->save($thumbfile);
						}
				
					}
			
					$res = str_replace('.'.$fileparts['extension'], '.'.$method.'.'.$optsplit[0].'x'.$optsplit[1].'.jpg', $res);
			
				}
			}
		
		}
	}
	
	
	if (!$fromdev){
		$res = fixPath($res, $pageparts['dirname']);
	}
	
	return $res;
	
});
$twig->addFunction($function);


function fixPath($res, $dir){
	if ($dir != '.' && !file_exists($dir.'/'.$res)){
		$res = '../'.$res;
		$parentdir = dirname($dir);
		$res = fixPath($res, $parentdir);
	}
	return $res;
}