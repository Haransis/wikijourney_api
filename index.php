<?php
/*
============================ WIKIJOURNEY API =========================
Version Beta 1.1.2
======================================================================

See documentation on http://api.wikijourney.eu/documentation.php
*/

require 'multiCurl.php';

error_reporting(0); // No need error reporting, or else it will crash the JSON export
header('Content-Type: application/json'); // Set the header to UTF8
$wikiSupportedLanguages = array('aa','ab','ace','ady','af','ak','als','am','an','ang','ar','arc','arz','as','ast','av','ay','az','azb','ba','bar','bat-smg','bcl','be','be-x-old','bg','bh','bi','bjn','bm','bn','bo','bpy','br','bs','bug','bxr','ca','cbk-zam','cdo','ce','ceb','ch','cho','chr','chy','ckb','co','cr','crh','cs','csb','cu','cv','cy','da','de','diq','dsb','dv','dz','ee','el','eml','en','eo','es','et','eu','ext','fa','ff','fi','fiu-vor','fj','fo','fr','frp','frr','fur','fy','ga','gag','gan','gd','gl','glk','gn','gom','got','gu','gv','ha','hak','haw','he','hi','hif','ho','hr','hsb','ht','hu','hy','hz','ia','id','ie','ig','ii','ik','ilo','io','is','it','iu','ja','jbo','jv','ka','kaa','kab','kbd','kg','ki','kj','kk','kl','km','kn','ko','koi','kr','krc','ks','ksh','ku','kv','kw','ky','la','lad','lb','lbe','lez','lg','li','lij','lmo','ln','lo','lrc','lt','ltg','lv','mai','map-bms','mdf','mg','mh','mhr','mi','min','mk','ml','mn','mo','mr','mrj','ms','mt','mus','mwl','my','myv','mzn','na','nah','nap','nds','nds-nl','ne','new','ng','nl','nn','no','nov','nrm','nso','nv','ny','oc','om','or','os','pa','pag','pam','pap','pcd','pdc','pfl','pi','pih','pl','pms','pnb','pnt','ps','pt','qu','rm','rmy','rn','ro','roa-rup','roa-tara','ru','rue','rw','sa','sah','sc','scn','sco','sd','se','sg','sh','si','simple','sk','sl','sm','sn','so','sq','sr','srn','ss','st','stq','su','sv','sw','szl','ta','te','tet','tg','th','ti','tk','tl','tn','to','tpi','tr','ts','tt','tum','tw','ty','tyv','udm','ug','uk','ur','uz','ve','vec','vep','vi','vls','vo','wa','war','wo','wuu','xal','xh','xmf','yi','yo','za','zea','zh','zh-classical','zh-min-nan','zh-yue','zu');

// ============> Connect to DB. If unreachable, the script will work anyway.
try {
	$dbh = new PDO('mysql:host=localhost;dbname=wikijourney_cache', 'wikijourney_web', '');
} catch (PDOException $e) {
	$dbh = 0;
}

// ============> INFO SECTION
$output['infos']['source'] = 'WikiJourney API';
$output['infos']['link'] = 'http://wikijourney.eu/';
$output['infos']['api_version'] = 'Beta 1.1.2';

// ============> FAKE ERROR
if (isset($_GET['fakeError']) && $_GET['fakeError'] == 'true') {
	$error = 'Error ! If you want to see all the error messages that can be sent by our API, please refer to the source code on our GitHub repository.';
} 
else {

// ============> REQUIRED INFORMATIONS
	if (isset($_GET['place'])) {
		// If it's a place

		$name = strval($_GET['place']);
		$osm_array_json = file_get_contents('http://nominatim.openstreetmap.org/search?format=json&q="'.urlencode($name).'"'); // Contacting Nominatim API to have coordinates
		$osm_array = json_decode($osm_array_json, true);

		if (!isset($osm_array[0]['lat'])) {
			$error = "Location doesn't exist";
		} else {
			$user_latitude = $osm_array[0]['lat'];
			$user_longitude = $osm_array[0]['lon'];
		}
	} else {
		// Else it's long/lat
		if (!(is_numeric($user_longitude) && is_numeric($user_latitude))) {
			$error = 'Error : latitude and longitude should be numeric values.';
		}

		if (isset($_GET['lat'])) {
			$user_latitude = floatval($_GET['lat']);
		} else {
			$error = 'Latitude missing';
		}

		if (isset($_GET['long'])) {
			$user_longitude = floatval($_GET['long']);
		} else {
			$error = 'Longitude missing';
		}

	}

// ============> OPTIONNAL PARAMETERS
	
	//==> Range
	if (isset($_GET['range'])) {
		$range = intval($_GET['range']);
	} else {
		$range = 1;
	}

	//==> Max POI
	if (isset($_GET['maxPOI'])) {
		$maxPOI = intval($_GET['maxPOI']);
	} else {
		$maxPOI = 10;
	}
	
	//==> Display images, wikivoyage support and thumbnail width
	$displayImg = (isset($_GET['displayImg']) && $_GET['displayImg'] == 1) ? 1 : 0;
	$wikivoyageSupport = (isset($_GET['wikivoyage']) && $_GET['wikivoyage'] == 1) ? 1 : 0;
	if (isset($_GET['thumbnailWidth'])) {
		$thumbnailWidth = intval($_GET['thumbnailWidth']);
	} else {
		$thumbnailWidth = 500;
	}

	if (!(is_numeric($range) && is_numeric($maxPOI) && is_numeric($thumbnailWidth))) {
		$error = 'Error : maxPOI, thumbnailWidth and range should be numeric values.';
	}

	//==> Languages 
	if(isset($_GET['lg']))
	{

		if (in_array($_GET['lg'], $wikiSupportedLanguages)) 
		{
			$language = $_GET['lg'];

			$table = 'cache_'.$language;

			if($dbh)
			{
				// ==> We create the table if it doesn't exist

				$stmt = $dbh->prepare("CREATE TABLE IF NOT EXISTS $table ("
					."`id` bigint(9) NOT NULL,"
					."`latitude` float NOT NULL,"
					."`longitude` float NOT NULL,"
					."`name` text COLLATE utf8_bin NOT NULL,"
					."`sitelink` text COLLATE utf8_bin NOT NULL,"
					."`type_name` text COLLATE utf8_bin NOT NULL,"
					."`type_id` bigint(9) NOT NULL,"
					."`image_url` text COLLATE utf8_bin NOT NULL,"
					."`lastupdate` date NOT NULL,"
					."PRIMARY KEY (`id`)"
					.") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin");

				if (!$stmt->execute()) {
				   print_r($stmt->errorInfo());
				}
				unset($stmt);
			}
		}
		else
			$error = "Error : language is not supported.";
	}
	else
		$language = "en";
	
}

// ============> INFO POINT OF INTEREST & WIKIVOYAGE GUIDES
if (!isset($error)) {
	// ==================================> Put in the output the user location (can be useful)
	$output['user_location']['latitude'] = $user_latitude;
	$output['user_location']['longitude'] = $user_longitude;

	// ==================================> Wikivoyage requests : find travel guides around
	if ($wikivoyageSupport == 1) {
		if ($displayImg == 1) {
			// We add description and image

			$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
.'prop=coordinates|info|pageterms|pageimages&' // Props list
.'piprop=thumbnail&pithumbsize=144&pilimit=50&inprop=url&wbptterms=description' // Properties dedicated to image, url and description
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch
		} else {
			// Simplified request

			$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
.'prop=coordinates|info&' // Props list
.'inprop=url' // Properties dedicated to url
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch

		}

		$wikivoyage_json = file_get_contents($wikivoyageRequest); // Request is sent to WikiVoyage API

		if ($wikivoyage_json == false) {
			$error = 'API Wikivoyage is not responding.';
		} 
		else 
		{
			$wikivoyage_array = json_decode($wikivoyage_json, true);

			if (isset($wikivoyage_array['query']['pages'])) 
			{
				// If there's guides around
				$realCount = 0;

				$wikivoyage_clean_array = array_values($wikivoyage_array['query']['pages']); // Reindexing the array (because it's initially indexed by pageid)

				for ($i = 0; $i < count($wikivoyage_clean_array); ++$i) 
				{
					++$realCount;

					$wikivoyage_output_array[$i]['pageid'] = $wikivoyage_clean_array[$i]['pageid'];
					$wikivoyage_output_array[$i]['title'] = $wikivoyage_clean_array[$i]['title'];
					$wikivoyage_output_array[$i]['sitelink'] = $wikivoyage_clean_array[$i]['fullurl'];

					if (isset($wikivoyage_clean_array[$i]['coordinates'][0]['lat'])) {
						// If there are coordinates
						$wikivoyage_output_array[$i]['latitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lat']; // Warning : could be null
						$wikivoyage_output_array[$i]['longitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lon']; // Warning : could be null
					}

					if (isset($wikivoyage_clean_array[$i]['thumbnail']['source'])) 
					{ // If we can find an image
						$wikivoyage_output_array[$i]['thumbnail'] = $wikivoyage_clean_array[$i]['thumbnail']['source'];
					}
				}
				$output['guides']['nb_guides'] = $realCount;
				if ($realCount != 0) 
				{
					$output['guides']['guides_info'] = array_values($wikivoyage_output_array);
				}
			} 
			else 
			{ // Case we're in the middle of Siberia
				$output['guides']['nb_guides'] = 0;
			}
		}
	}

	// ==================================> End Wikivoyage requests

	// ==================================> Wikidata requests : find wikipedia pages around

	$poi_id_array_json = file_get_contents("http://wdq.wmflabs.org/api?q=around[625,$user_latitude,$user_longitude,$range]"); // Returns a $poi_id_array_clean array with a list of wikidata pages ID within a $range km range from user location
	if ($poi_id_array_json == false) {
		$error = "API WMFlabs isn't responding.";
	} 
	else {
		$poi_id_array = json_decode($poi_id_array_json, true);
		$poi_id_array_clean = $poi_id_array['items'];
		$nb_poi = count($poi_id_array_clean);

		for ($i = 0; $i < min($nb_poi, $maxPOI); ++$i) {
			$id = $poi_id_array_clean[$i];

			// =============> We check if the db is online. If not, then bypass the cache.
			if ($dbh) {
				// ==> We look in the cache to know if the POI is there
				$stmt = $dbh->prepare('SELECT * FROM '.$table.' WHERE id = ?');
				$stmt->execute([$id]);
				$dataPOI = $stmt->fetch(PDO::FETCH_ASSOC);

				// ==> If we have it we can display it
				if ($dataPOI != null) {
					$poi_array[$i] = $dataPOI;
					$poi_array[$i]['cache'] = true;
				}
				
				unset($stmt);
			}

			// =============> If the POI is not in the cache, or if the database is unreachable, then contact APIs.
			if (!isset($poi_array[$i])) {
				
				// =============> First call, we're gonna fetch geoloc infos, type ID, description and sitelink

				$URL_list = [
					// Geoloc infos
					'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&entity=Q'.$poi_id_array_clean["$i"].'&property=P625',
					// Type ID
					'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&entity=Q'.$poi_id_array_clean["$i"].'&property=P31',
					// Description
					'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q'.$poi_id_array_clean["$i"]."&props=labels&languages=$language",
					// Sitelink
					'https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q'.$poi_id_array_clean["$i"]."&sitefilter=$language&props=sitelinks/urls&format=json",
				];

				$curl_return = reqMultiCurls($URL_list); // Using multithreading to fetch urls

				// ==> Get geoloc infos
					$temp_geoloc_array_json = $curl_return[0];
				if ($temp_geoloc_array_json == false) {
					$error = "API Wikidata isn't responding on request 1.";
					break;
				}
				$temp_geoloc_array = json_decode($temp_geoloc_array_json, true);
				$temp_latitude = $temp_geoloc_array['claims']['P625'][0]['mainsnak']['datavalue']['value']['latitude'];
				$temp_longitude = $temp_geoloc_array['claims']['P625'][0]['mainsnak']['datavalue']['value']['longitude'];

				// ==> Get type id
					$temp_poi_type_array_json = $curl_return[1];
				if ($temp_poi_type_array_json == false) {
					$error = "API Wikidata isn't responding on request 2.";
					break;
				}
				$temp_poi_type_array = json_decode($temp_poi_type_array_json, true);
				$temp_poi_type_id = $temp_poi_type_array['claims']['P31'][0]['mainsnak']['datavalue']['value']['numeric-id'];

				// ==> Get description
					$temp_description_array_json = $curl_return[2];
				if ($temp_description_array_json == false) {
					$error = "API Wikidata isn't responding on request 3.";
					break;
				}
				$temp_description_array = json_decode($temp_description_array_json, true);
				$name = $temp_description_array['entities']['Q'.$poi_id_array_clean["$i"]]['labels']["$language"]['value'];

				// ==> Get sitelink
					$temp_sitelink_array_json = $curl_return[3];
				if ($temp_sitelink_array_json == false) {
					$error = "API Wikidata isn't responding on request 4.";
					break;
				}
				$temp_sitelink_array = json_decode($temp_sitelink_array_json, true);
				$temp_sitelink = $temp_sitelink_array['entities']['Q'.$poi_id_array_clean["$i"]]['sitelinks'][$language.'wiki']['url'];

				// =============> Now we make a second call to fetch images and types' titles

				// ==> With the sitelink, we make the image's url
				$temp_url_explode = explode('/', $temp_sitelink);
				$temp_url_end = $temp_url_explode[count($temp_url_explode) - 1];

				// ==> Calling APIs
					$URL_list = [
						// Type
						'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q'.$temp_poi_type_id."&props=labels&languages=$language",
						// Images
						'https://'.$language.'.wikipedia.org/w/api.php?action=query&prop=pageimages&format=json&pithumbsize='.$thumbnailWidth.'&pilimit=1&titles='.$temp_url_end,
				];

				$curl_return = reqMultiCurls($URL_list);

				// ==> Get type
					$temp_description_type_array_json = $curl_return[0];
				if ($temp_description_type_array_json == false) {
					$error = "API Wikidata isn't responding on request 5.";
					break;
				}
				$temp_description_type_array = json_decode($temp_description_type_array_json, true);
				$type_name = $temp_description_type_array['entities']['Q'.$temp_poi_type_id]['labels']["$language"]['value'];

				// ==> Get image
					$temp_image_json = $curl_return[1];
				if ($temp_image_json == false) {
					$error = "API Wikidata isn't responding on request 6.";
					break;
				}
					// We put an @ because it can be null (case there is no image for this article)
					$image_url = @array_values(json_decode($temp_image_json, true)['query']['pages'])[0]['thumbnail']['source'];

				// =============> And now we can make the output
				if ($name != null) {
					$poi_array[$i]['latitude'] = $temp_latitude;
					$poi_array[$i]['longitude'] = $temp_longitude;
					$poi_array[$i]['name'] = $name;
					$poi_array[$i]['sitelink'] = $temp_sitelink;
					$poi_array[$i]['type_name'] = $type_name;
					$poi_array[$i]['type_id'] = $temp_poi_type_id;
					$poi_array[$i]['id'] = $poi_id_array_clean[$i];
					$poi_array[$i]['image_url'] = $image_url;
					$poi_array[$i]['cache'] = false;

					if ($dbh) {
						// Insert this POI in the cache

						$stmt = $dbh->prepare('INSERT INTO '.$table.' (id, latitude, longitude, name, sitelink, type_name, type_id, image_url, lastupdate)'
											 .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
						$stmt->execute([$id, $temp_latitude, $temp_longitude, $name, $temp_sitelink, $type_name, $temp_poi_type_id, $image_url]);
					}
				}
			}
		}
	}
	$output['poi']['nb_poi'] = count($poi_array);
	$output['poi']['poi_info'] = array_values($poi_array); // Output
}

if (isset($error)) {
	$output['err_check']['value'] = true;
	$output['err_check']['err_msg'] = $error;
} else {
	$output['err_check']['value'] = false;
}

echo json_encode($output); // Encode in JSON. (user will get it by file_get_contents, curl, wget, or whatever)

unset($dbh); // Close the database.

// Next line is a legacy, please don't touch.
/* yolo la police */