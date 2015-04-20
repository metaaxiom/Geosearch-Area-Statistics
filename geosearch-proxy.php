<?php
//app key to constant
class Validate {

	/* TO DO 

	validate city function?
	sanitize user input
	*/

	protected function validateState($state){
		$state_abbr_arr = array(
			'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ',
			'Arkansas' => 'AR', 'California' => 'CA', 'Colorado' => 'CO',
			'Connecticut' => 'CT', 'Delaware' => 'DE', 'Florida' => 'FL',
			'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
			'Illinois' => 'IL', 'Indiana' => 'IA', 'Kansas' => 'KS',
			'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME',
			'Maryland' => 'MD', 'Massachusetts' => 'MA', 'Michigan' => 'MI',
			'Minnesota' => 'MN', 'Mississippi' => 'MS', 'Missouri' => 'MO',
			'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
			'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM',
			'New York' => 'NY', 'North Carolina' => 'NC', 'North Dakota' => 'ND',
			'Ohio' => 'OH', 'Oklahoma' => 'OK', 'Oregon' => 'OR',
			'Pennsylvania' => 'PA', 'Rhoda Island' => 'RI', 'South Carolina' => 'SC',
			'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX',
			'Utah' => 'UT', 'Vermont' => 'VT', 'Virginia' => 'VA',
			'Washington' => 'WA', 'West Virginia' => 'WV', 'Wisconsin' => 'WI',
			'Wyoming' => 'WY'
		);

		$match = false;
		//user state input is abbreviation
		if(strlen($state) == 2){
			$state = strtoupper($state);
			foreach($state_abbr_arr as $stateName => $stateAbbr){
				if($state == $stateAbbr){
					$match = true;
					break;
				}
			}
		}else{ //user state input is full state name
			$state = strtolower($state); //convert to lowecase
			$state = ucwords($state); //capitalize first letter
			foreach($state_abbr_arr as $stateName => $stateAbbr){
				if($state == $stateName){
					$match = true;
					$state = $stateAbbr;
					break;
				}
			}
		}

		if($match){
			return $state;
		}else{
			return false;
		}

	}

	protected function checkQuery($status_key, $status_val){
		if($status_key == $status_val){
			return true;
		}
	}

	public function compileToJSON($data_sets){
		$data_sets_arr = array();
		if(is_array($data_sets)){
			foreach($data_sets as $data_set_key => $data_set_val){
				if($data_set_val['success']){
					$data_sets_arr[$data_set_key] = $data_set_val;
				}
			}

			if(!empty($data_sets_arr)){
				return json_encode($data_sets_arr);
			}
		}else{
			return false;
		}
	}

	protected function formatCityUnderscore($city){
		$city = ucwords($city);
		//if city name more than one word
		if(str_word_count($city) > 1){
			//replace spaces with underscores
			$city = str_replace(' ', '_', $city);
		}
		return $city;
	}

	protected function formatCityURL($city){
		if(str_word_count($city) > 1){
			//replace spaces with pluses
			$city = str_replace(' ', '%20', $city);
		}
		return $city;
	}
}

class UserInput extends Validate {

	public $state;
	public $city;
	public $input_valid;

	/* Validates User Input */
	public function __construct(){
		if(isset($_GET['state']) && isset($_GET['city'])){
			if(!empty($_GET['state']) && !empty($_GET['city'])){
				//sanitize
				$state = htmlspecialchars($_GET['state']);
				$city = htmlspecialchars($_GET['city']);

				//validate state convert + convert to abbreviation
				$this->state = $this->validateState($state);
				$this->city = $city; //no need to validate city
				if($this->state){
					$this->input_valid = true;
				}else{
					$this->input_valid = false;
				}
			}else{
				$this->input_valid = false;
			}
		}else{
			$this->input_valid = false;
		}
	}

}

class Datasets extends Validate {

	const ZILLOW_KEY = '';
	const CENSUS_KEY = '';
	const WUNDERGROUND_KEY = '';

	public $geo_arr;
	public $fips_arr;
	public $timezone_arr;
	public $climate_arr;
	public $real_estate_arr;
	public $state_population_arr;
	public $city_population_arr;
	public $final_json;

	public $allowed_real_estate_charts = array(
		'Zillow Home Value Index Distribution',
		'Dollars Per Square Feet',
		'Year Built',
		'Home Size in Square Feet'
	);

	function __construct(){
		$userInput = new UserInput();
		if($userInput->input_valid){
			$this->fetchGeo($userInput->state, $userInput->city); //fill geo_arr on success

			if($this->geo_arr['success']){
				//add geo_arr to final datasets
				$final_arr = array(
					'geo' => $this->geo_arr
				);

				/* FETCH DATASETS THAT REQUIRE GEO ARRAY INFO */
				$this->fetchTimezone($this->geo_arr['lat'], $this->geo_arr['lng']);
				$this->fetchRealEstate($this->geo_arr['state'], $this->geo_arr['city']);
				$this->fetchClimate($this->geo_arr['state'], $this->geo_arr['city']);

				if($this->timezone_arr['success']){
					//add timezone_arr to final datasets
					$final_arr['timezone'] = $this->timezone_arr;
				}
				if($this->real_estate_arr['success']){
					//add real_estate_arr to final datasets
					$final_arr['realEstate'] = $this->real_estate_arr;
				}
				if($this->climate_arr['success']){
					$final_arr['climate'] = $this->climate_arr;
				}

				/* FETCH DATASETS THAT REQUIRE FIPS CODES */
				$this->fetchFIPS($this->geo_arr['lat'], $this->geo_arr['lng']); //fill fips_arr on success
				if($this->fips_arr['success']){
					//add fips_arr to final datasets
					$final_arr['fips'] = $this->fips_arr;

					$this->fetchStatePopulation($this->fips_arr['state']);
					if($this->state_population_arr['success']){
						//add state_population_arr to final datasets
						$final_arr['stateData'] = $this->state_population_arr;
					}

					$this->fetchCityData($this->fips_arr['state'], $this->fips_arr['county'], $this->fips_arr['place']);
					if($this->city_data_arr['success']){
						//add city_data_arr to final datasets
						$final_arr['cityData'] = $this->city_data_arr;
					}
				}
				
				/* VALIDATE & COMPILE FINAL ARR */
				$final_json = $this->compileToJSON($final_arr);
				if($final_json != false){
					$this->final_json = $final_json;
				}else{
					$this->final_json = false;
				}

			}else{
				$final_arr = false;
			}
		}else{
			$final_arr = false;
		}
	}

	public function fetchGeo($state, $city){
		
		//query google API for lat/lng
		//pass in formated city string
		$google_geo_json = @file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$this->formatCityURL($city).',+'.$state);
		$google_geo_obj = json_decode($google_geo_json); //convert json to obj

		if($google_geo_obj->status == 'OK'){

			$this->geo_arr = array(
				'formattedLocation' => $google_geo_obj->results[0]->formatted_address,
				'state' => $state,
				'county' => $google_geo_obj->results[0]->address_components[1]->long_name,
				'city' => $city,
				'lat' => $google_geo_obj->results[0]->geometry->location->lat,
				'lng' => $google_geo_obj->results[0]->geometry->location->lng,
				'success' => true
			);
		}else{
			$this->geo_arr = array(
				'success' => false
			);
		}
	}

	public function fetchFIPS($lat, $lng){
		//query code for america for FIPS
		$query_fips_json = @file_get_contents('http://census.codeforamerica.org/areas?lat='.$lat.'&lon='.$lng.'&include_geom=false&layers=place,county');
		$query_fips_obj = json_decode($query_fips_json); //convert json to obj

		if(!empty($query_fips_obj->features)){
			//use loop b/c query result often alternates order
			foreach($query_fips_obj->features as $queryVal){
				if(isset($queryVal->properties->STATEFP)){
					$this->fips_arr['state'] = $queryVal->properties->STATEFP;
				}
				if(isset($queryVal->properties->COUNTYFP)){
					$this->fips_arr['county'] = $queryVal->properties->COUNTYFP;
				}
				if(isset($queryVal->properties->PLACEFP)){
					$this->fips_arr['place'] = $queryVal->properties->PLACEFP;
				}
			}
			//make sure each index is defined
			if(isset($this->fips_arr['state']) && isset($this->fips_arr['county']) && isset($this->fips_arr['place'])){
				$this->fips_arr['success'] = true;
			}else{
				$this->fips_arr['success'] = false;
			}
		}else{
			$this->fips_arr['success'] = false;
		}
	}

	public function fetchTimezone($lat, $lng){
		//query google API for timezone
		$google_timezone_json = @file_get_contents('https://maps.googleapis.com/maps/api/timezone/json?location='.$lat.','.$lng.'&timestamp='.time());
	    $google_timezone_obj = json_decode($google_timezone_json); //convert json to obj

	    if($this->checkQuery($google_timezone_obj->status, 'OK')){
	    	$this->timezone_arr = array(
	    		'name' => $google_timezone_obj->timeZoneName,
	    		'success' => true
	    	);
	    }else{
	    	$this->timezone_arr = array(
	    		'success' => false
	    	);
	    }
	}

	public function fetchRealEstate($state, $city){
		$city = $this->formatCityURL($city);

		//get Zillow info (only xml format available)
		//pass in formatted city string
	    $zillow_xml_str = @file_get_contents('http://www.zillow.com/webservice/GetDemographics.htm?zws-id='.self::ZILLOW_KEY.'&state='.$state.'&city='.$city);
	    $zillow_xml = simplexml_load_string($zillow_xml_str); //convert xml obj into string
	    $zillow_json = json_encode($zillow_xml); //encode as JSON
	    $zillow_obj = json_decode($zillow_json); //decode JSON to obj

	    if($this->checkQuery($zillow_obj->message->code, 0)){
	    	$charts = array();
	    	$counter = 1;
	    	$arr_set = 1;
	    	foreach($zillow_obj->response->charts->chart as $chart){
	    		//if divisible by 3, increase chart set by 1
	    		//(two charts per set)
	    		if(($counter % 3)==0){
	    			$arr_set++;
	    		}

	    		//if chart is allowed, add it to charts array
	    		if(in_array($chart->name, $this->allowed_real_estate_charts)){
	    			$charts[$arr_set][] = array(
	    				'url' => $chart->url,
	    				'name' => $chart->name
	    			);
	    		}
	    		$counter++;
	    	}
	    	$this->real_estate_arr = array(
	    		'chartSets' => $charts,
	    		'success' => true
	    	);
	    }else{
	    	$this->real_estate_arr = array(
	    		'success' => false
	    	);
	    }
	}

	public function fetchStatePopulation($state_fips){
		$response_json = @file_get_contents('http://api.census.gov/data/2014/pep/natstprc?get=POP,DENSITY,BIRTHS,DEATHS,SUMLEV&for=state:'.$state_fips.'&DATE=6&key='.self::CENSUS_KEY);
		$response_arr = json_decode($response_json); //index [1] holds response

		if($response_arr != NULL){
			//$response_arr[1] holds the response
			$this->state_population_arr = array(
				'total' => $response_arr[1][0],
				'density' => $response_arr[1][1],
				'births' => $response_arr[1][2],
				'deaths' => $response_arr[1][3],
				'success' => true
			);
		}else{
			$this->state_population_arr = array(
				'success' => false
			);
		}
	}

	public function fetchCityData($fips_state, $fips_county, $fips_place){
		$query_city_data_json = @file_get_contents('http://api.census.gov/data/2013/pep/subcty?get=NAME,POP&for=place:'.$fips_place.'&in=state:'.$fips_state.'+county:'.$fips_county.'&DATE=6&key='.self::CENSUS_KEY);
		$query_city_data_obj = json_decode($query_city_data_json);

		if($query_city_data_obj != NULL){
			//$response_arr[1] holds the response
			$this->city_data_arr = array(
				'name' => $query_city_data_obj[1][0],
				'population' => $query_city_data_obj[1][1],
				'success' => true
			);
		}else{
			$this->city_data_arr = array(
				'success' => false
			);
		}
	}

	public function fetchClimate($state, $city){
		//format city for query, no need to format state abbr.
		$city = $this->formatCityURL($city);

		$query_climate_json = @file_get_contents('http://api.wunderground.com/api/'.self::WUNDERGROUND_KEY.'/almanac/q/'.$state.'/'.$city.'.json');
		$query_climate_obj = json_decode($query_climate_json);

		//if climate query doesn't throw error
		if(empty($this->climate_arr->response->error) && isset($query_climate_obj->almanac)){
			$this->climate_arr = array(
				'temp' => array(
					'highNormal' => $query_climate_obj->almanac->temp_high->normal->F,
					'highRecord' => $query_climate_obj->almanac->temp_high->record->F,
					'lowNormal' => $query_climate_obj->almanac->temp_low->normal->F,
					'lowRecord' => $query_climate_obj->almanac->temp_low->record->F
				),
				'success' => true
			);
		}else{
			$this->climate_arr = array(
				'success' => false
			);
		}
	}

}

$datasets = new Datasets();
echo $datasets->final_json;
?>