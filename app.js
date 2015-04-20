(function(){
	var app = angular.module('geosearch', ['uiGmapgoogle-maps']);

	app.controller('areaInfo', function($http){
		var areaInfo = this;

		this.spinner = false;

		//user inputs
		this.state = '';
		this.city = '';

		this.areaInfoJSON = {}; //create empty object
		this.areaInfoRan = false;
		this.areaInfoError = false;

		this.fetchAreaInfo = function(){
			areaInfo.spinner = true; //start spinner
			if((areaInfo.state != '' && areaInfo.state !== undefined) && (areaInfo.city != '' && areaInfo.city !== undefined)){
				//strip spaces from user inputs
				var state = areaInfo.state.replace(' ', '%20');
				var city = areaInfo.city.replace(' ', '%20');

				//make get request to proxy PHP file, fetching
				//data from Zillow API
				$http.get('/live/geo/geosearch-proxy.php?state='+state+'&city='+city).success(function(data){
					areaInfo.areaInfoRan = true;
					if(data != false){
						areaInfo.spinner = false; //stop spinner

						//store data in controller
						areaInfo.areaInfoJSON = data;
						
						//build Google map
						areaInfo.map = {center: {latitude: areaInfo.areaInfoJSON.geo.lat, longitude: areaInfo.areaInfoJSON.geo.lng}, zoom: 15};
        				areaInfo.options = {scrollwheel: false};
						
					}else{ //throw error if proxy file returns string "false"
						areaInfo.spinner = false; //stop spinner

						areaInfo.areaInfoError = 'Couldn\'t retrieve information';
					}
				}).error(function(){
					areaInfo.spinner = false; //stop spinner

					areaInfo.areaInfoRan = true;
					areaInfo.areaInfoError = 'Couldn\'t retrieve information';
				});
			}else{ //throw error if input is empty
				areaInfo.spinner = false; //stop spinner
				
				areaInfo.areaInfoRan = true;
				areaInfo.areaInfoError = 'Please fill in the fields';
			}
		};
	});
})()