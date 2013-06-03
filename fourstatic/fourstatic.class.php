<?php 

// The Fourstatic helper class contains various static methods that help us generate our pages.

class Fourstatic {
	
	// The **getPageDirectories** method creates an array of all directories that Twig should consider. The order
	// is actually important, or the development directory would never overwrite the pages directory.
	
	public static function getPageDirectories($devip, $devdir, $pagesdir){
		$ip = $_SERVER['REMOTE_ADDR'];

		$pagedirs = array();
		if ($ip == $devip){
			$pagedirs[] = $devdir;
		}
		$pagedirs[] = $pagesdir;
		
		return $pagedirs;
	}
	
	// The **readDataFiles** method reads data from local files and external JSON API's for all keys that have the "-file" or "-jsonapi" suffixes.
	
	public static function readDataFiles($dir, $data){
		
		if (is_array($data) && count($data) > 0){
			foreach($data as $k => $v){
				
				if (substr($k, -5) == '-file'){

					$fileparts = pathinfo($v);
					$newk = substr($k, 0, -5);

					if ($fileparts['extension'] == 'php'){
						$data[$newk] = file_get_contents(ROOTURL.'/'.$dir.'/'.$v);
					} else {
						$data[$newk] = file_get_contents($dir.'/'.$v);
					}

					if ($fileparts['extension'] == 'md'){
						$data[$newk] = Markdown($data[$newk]);
					}
					
				} else if (substr($k, -6) == '-files'){
					
					$newk = substr($k, 0, -6);
					$files = scandir($dir.'/'.$v);
					
					$subdata = array();
					if (count($files) > 0){
						foreach($files as $f){
							if ($f != '.' && $f != '..'){
								$fp = pathinfo($f);
								$subdata[] = array(
									'file' => $f,
									'filename' => $fp['filename'],
									'extension' => $fp['extension'],
									'size' => filesize($dir.'/'.$v.'/'.$f),
									'modified' => filemtime($dir.'/'.$v.'/'.$f)
								);
							}
						}
					}
					
					$data[$newk] = $subdata;
					
				} else if (substr($k, -8) == '-jsonapi'){
					
					$newk = substr($k, 0, -8);
					$cachefile = 'cache/api/'.md5($v).'.json';
					
					$expiretime = time() - 1*60*60; // Expire Time (1h)
					if (!file_exists($cachefile) || filectime($cachefile) <= $expiretime){
						$stream = @file_get_contents($v);
						$json = @json_decode(trim($stream), true);

						if ($json != NULL){
							$data[$newk] = $json;
							file_put_contents($cachefile, $stream);
						}

					} else {
						$data[$newk] = @json_decode(trim(file_get_contents($cachefile)), true);
					}

				}
				
				if (is_array($v) && count($v) > 0){
					$data[$k] = self::readDataFiles($dir, $v);
				}

			}
		}
		
		return $data;
		
	}
	
	// The **parseJSONFile** method parses JSON files.

	public static function parseJSONFile($path, $file){
		$data = array();

		if (file_exists($path.'/'.$file)){
			$json = json_decode(file_get_contents($path.'/'.$file), true);
			if ($json != NULL){
				$data = self::readDataFiles($path, $json);
			}
		}

		return $data;
	}
	
	// The **parseYAMLFile** method parses YAML files with the help of the Spyc YAML library.

	public static function parseYAMLFile($path, $file){
		$data = array();
		
		if (file_exists($path.'/'.$file)){
			$yaml = Spyc::YAMLLoad($path.'/'.$file);
			if (is_array($yaml)){
				$data = self::readDataFiles($path, $yaml);
			}
		}
		
		return $data;
	}
	
	// The **parsePHPFile** method loads data from an array called "data" inside of a PHP file.

	public static function parsePHPFile($path, $file){

		if (file_exists($path.'/'.$file)){
			try {
				include $path.'/'.$file;
				if (is_array($data)){
					return self::readDataFiles($path, $data);
				}
			} catch (Exception $e) {
			    // no error output needed
			}
		}

		return array();
	}
	
	// The **parseXMLFile** method parses XML files.

	public static function parseXMLFile($path, $file){
		$data = array();

		if (file_exists($path.'/'.$file)){

			$xml = simplexml_load_string(file_get_contents($path.'/'.$file));
			$json = json_encode($xml);
			$data = json_decode($json, true);

			if (is_array($data)){
				$data = self::readDataFiles($path, $data);
			}
		}

		return $data;
	}
	
	// The **parseCSVFile** method parses CSV files.
	
	public static function parseCSVFile($path, $file){

		if (file_exists($path.'/'.$file)){
			
			$csv = self::importCSV($path.'/'.$file, true, ';', 10000);

			if (is_array($csv)){
				$csv = self::readDataFiles($path, $csv);
			}
		}

		return $csv;
	}
	
	// The **importCSV** method imports data from a CSV file, it's used by the **parseCSVFile** method.

	public static function importCSV($file, $head=false, $delim=",", $len=1000){ 
	    $return = false;
		
	    $handle = fopen($file, "r"); 
	    if ($head) { 
	        $header = fgetcsv($handle, $len, $delim); 
	    }
		
	    while (($data = fgetcsv($handle, $len, $delim)) !== FALSE) { 
	        if ($head AND isset($header)) { 
	            foreach ($header as $key=>$heading) { 
	                $row[$heading]=(isset($data[$key])) ? $data[$key] : ''; 
	            } 
	            $return[]=$row;
	        } else { 
	            $return[]=$data; 
	        } 
	    }
		
	    fclose($handle);
		
	    return $return; 
	}
	
	// The **parseDataSources** method collects all the various data thats inside our different directories.
	
	public static function parseDataSources($page, $pagedirs){
		
		$data = array();
		$pageparts = pathinfo($page);
		$dirs = explode('/', '/'.$pageparts['dirname']);
		
		foreach(array_reverse($pagedirs) as $path){
			foreach($dirs as $dir){
				if ($dir != '.'){
					if ($dir != ''){
						$path .= '/'.$dir;
					}
					
					if (file_exists($path.'/data.json')){
						$data = array_merge($data, self::parseJSONFile($path, 'data.json'));
					}

					if (file_exists($path.'/data.yaml')){
						$data = array_merge($data, self::parseYAMLFile($path, 'data.yaml'));
					}

					if (file_exists($path.'/data.php')){
						$data = array_merge($data, self::parsePHPFile($path, 'data.php'));
					}

					if (file_exists($path.'/data.csv')){
						$data = array_merge($data, self::parseCSVFile($path, 'data.csv'));
					}

					if (file_exists($path.'/data.xml')){
						$data = array_merge($data, self::parseXMLFile($path, 'data.xml'));
					}

					if (file_exists($path.'/data') && is_dir($path.'/data')){
						$datafiles = scandir($path.'/data');
						foreach($datafiles as $datafile){
							if ($datafile != '.' && $datafile != '..'){

								if (is_dir($path.'/data/'.$datafile)){

									if (file_exists($path.'/data/'.$datafile.'/data.json')){
										$data = array_merge($data, array($datafile => self::parseJSONFile($path.'/data/'.$datafile, 'data.json')));
									}
									if (file_exists($path.'/data/'.$datafile.'/data.yaml')){
										$data = array_merge($data, array($datafile => self::parseYAMLFile($path.'/data/'.$datafile, 'data.yaml')));
									}
									if (file_exists($path.'/data/'.$datafile.'/data.php')){
										$data = array_merge($data, array($datafile => self::parsePHPFile($path.'/data/'.$datafile, 'data.php')));
									}
									if (file_exists($path.'/data/'.$datafile.'/data.csv')){
										$data = array_merge($data, array($datafile => self::parseCSVFile($path.'/data/'.$datafile, 'data.csv')));
									}
									if (file_exists($path.'/data/'.$datafile.'/data.xml')){
										$data = array_merge($data, array($datafile => self::parseXMLFile($path.'/data/'.$datafile, 'data.xml')));
									}

								} else {

									$dfsplit = explode('.', $datafile);

									if ($dfsplit[1] == 'json'){
										$data = array_merge($data, array($dfsplit[0] => self::parseJSONFile($path.'/data', $datafile)));
									}
									if ($dfsplit[1] == 'yaml'){
										$data = array_merge($data, array($dfsplit[0] => self::parseYAMLFile($path.'/data', $datafile)));
									}
									if ($dfsplit[1] == 'php'){
										$data = array_merge($data, array($dfsplit[0] => self::parsePHPFile($path.'/data', $datafile)));
									}
									if ($dfsplit[1] == 'csv'){
										$data = array_merge($data, array($dfsplit[0] => self::parseCSVFile($path.'/data', $datafile)));
									}
									if ($dfsplit[1] == 'xml'){
										$data = array_merge($data, array($dfsplit[0] => self::parseXMLFile($path.'/data', $datafile)));
									}

								}

							}
						}
					}

				}
			}
		}
		
		return $data;
		
	}
	
	
}