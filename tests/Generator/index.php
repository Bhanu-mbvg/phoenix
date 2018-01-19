<?php
require('iframe.php');
/// Purpose : Reads Swagger YAML Files and generate PhpUnit Test files for those calls.

$html = new HTML;

$swagger_file = '/mnt/x/Data/www/Projects/Phoenix/api/swagger/swagger.yaml';
$api = yaml_parse(file_get_contents($swagger_file));
$api_base_path = 'http://localhost/Projects/Phoenix/public';

$path = i($QUERY, 'path', '/users/{user_id}');
$verb = i($QUERY, 'verb', 'get');
$test_type = i($QUERY, 'test_type', 'single');
$action = i($QUERY, 'action');

$all_paths = array();
foreach ($api['paths'] as $path => $data) {
	$all_paths[$path] = $path;
}

$templates = array();
$templates['data-assertion'] = "\$this->assertEquals(%DATA-PATH%, '%DATA-VALUE%'');\n";
$templates['single'] = file_get_contents('code/single.txt');
$templates['single-not-found'] = file_get_contents('code/single-404.txt');

$tables = ['User', 'Group', 'City', 'Class', 'Batch', 'Level', 'Center', 'Student'];

$variables = parsePath($path);

if($action == 'Generate Tests') {
	$replaced_url = i($QUERY, 'replaced_url');
	$root = preg_replace('/^\/([^\/]+)\/?.*/', '$1', $replaced_url);
	$table = findTable($root);
	
	// $response = load($api_base_path . $replaced_url);
	// print "URL: " . $api_base_path . $replaced_url . "\n";
	// print "Response: " . $response . "\n";
	// $data = json_decode($response);

	$data_paths = i($QUERY, 'data-path');
	$data_value = i($QUERY, 'data-value');

	$assertions = '';
	for($i = 0; $i < count($data_paths); $i++) {
		if(!$data_paths[$i]) continue;

		$assertion_replaces = array(
			'%DATA-PATH%'	=> $data_paths[$i],
			'%DATA-VALUE%'	=> $data_value[$i],
		);
		$assertions .= str_replace(
							array_keys($assertion_replaces), 
							array_values($assertion_replaces), 
							$templates['data-assertion']);
	}

	$replaces = array(
		'%URL%'			=> $replaced_url,
		'%TABLE%'		=> $table,
		'%DATA-ASSERTIONS%'	=> $assertions,
	);

	$code = str_replace(
				array_keys($replaces), 
				array_values($replaces), 
				$templates['single']);

	render('output.php');
	exit;
}

render();

// Taken from API.php in exdon/inclues/classes
function parsePath($url) {
	$vars = array();

	if(preg_match_all('#\{(\w+)\}#', $url, $matches)) {
		// First we convert the action route with its {variable_name} format to a preg-able string...
		for($i=0; $i<count($matches[0]); $i++) {
			$str = $matches[0][$i];
			$vars[] = $matches[1][$i]; // Get the list of variables in the route into a different array.
		}

		// // Match - assign the values to the assoc array for return.
		// $url_variables = array();
		// for($i=0; $i<count($vars); $i++) {
		// 	$url_variables[$vars[$i]] = $route_matches[$i+1][0];
		// }
	}

	return $vars;
}

function findTable($root) {
	global $tables;

	// Figure out which table this belongs to.
	$max_match = 0;
	$table = false;
	foreach ($tables as $t) {
		$similarity = similar_text($t, $root);
		if($max_match < $similarity) {
			$max_match = $similarity;
			$table = $t;
		}
	}

	return $table;
}