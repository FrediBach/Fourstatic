<?php

// Load configurations:

require 'config.inc.php';

// Load our helper class that contains various tatic methods:

require 'fourstatic.class.php';

// Load the Twig, Markdown and YAML libraries:

require 'lib/Twig/Autoloader.php';
require 'lib/markdown.php';
require 'lib/yaml/Spyc.php';

// Initialize the Twig templating system:

chdir('../');

Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem(Fourstatic::getPageDirectories(DEVIP, DEVDIR, PAGESDIR));
$twig = new Twig_Environment($loader, array(
    'cache' => 'cache', 'debug' => true
));

// Find out which page and page slug we need to render:

$slug = '';
if (isset($_GET['page']) && $_GET['page'] != ''){
	$page = $_GET['page'];
	if (substr($page,-5) == '.html'){
		$pagesplit = explode('--', substr($page, 0, -5));
		if (file_exists(PAGESDIR.'/'.$pagesplit[0].'.html') || ($ip == DEVIP && file_exists(DEVDIR.'/'.$pagesplit[0].'.html'))){
			$page = $pagesplit[0].'.html';
			$slug = '';
			if (isset($pagesplit[1])){
				$slug = $pagesplit[1];
			}
		}
	}
} else {
	$page = 'index.html';
}

if (!file_exists(PAGESDIR.'/'.$page)){
	if (!($_SERVER['REMOTE_ADDR'] == DEVIP && file_exists(DEVDIR.'/'.$page))){
		$page = '404.html';
	}
}

// Include additional Twig Functions and Filters that are unique to Fourstatic:

require 'fourstatic.twig.php';

// Load all data we'll make available in our templates:

$data = Fourstatic::parseDataSources($page, Fourstatic::getPageDirectories(DEVIP, DEVDIR, PAGESDIR));

// Let Twig render the page:

$template = $twig->loadTemplate($page);
echo $template->render(array(
	'data' => $data,
	'globals' => $GLOBALS,
	'root' => ROOTURL,
	'page' => str_replace('index.html', '', $page),
	'slug' => $slug
));
