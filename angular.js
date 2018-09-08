/*
	This is a condensed example of an AngularJS app to handle a single-page entity editor.
*/

var editor = angular.module('editor', ['ngCookies', 'dndLists', 'ngFileUpload', 'textAngular', 'bcherny/formatAsCurrency', 'angular.filter', 'bm.uiTour']);

editor.filter('isntEmpty', function () {
    var bar;
    return function (obj) {
        for (bar in obj) {
            if (obj.hasOwnProperty(bar)) {
                return true;
            }
        }
        return false;
    };
});

editor.filter("html", ['$sce', function($sce) {
	return function(htmlCode) {
		return $sce.trustAsHtml(htmlCode);
	}
}]);

// set options for textAngular
editor.config(function($provide) {
    $provide.decorator('taOptions', ['taRegisterTool', '$delegate', '$window', function(taRegisterTool, taOptions, $window) { // $delegate is the taOptions we are decorating
    	taRegisterTool('hr', {
        	iconclass: 'fa fa-bars',
        	action: function() {
        		return this.$editor().wrapSelection('insertHTML', "<hr>", true);
        	}
        });

        taOptions.toolbar = [
            ['p', 'h2', 'h3', 'h4'],
            ['bold', 'italics', 'underline'],
            ['ul', 'ol', 'indent', 'outdent'],
            ['hr', 'insertLink'],
            ['undo', 'redo', 'clear']
        ];

        return taOptions;
    }]);
})

editor.config(function(TourConfigProvider) {
	TourConfigProvider.enableNavigationInterceptors();
});

editor.directive('staticInclude', function($http, $templateCache, $compile) {
    return function(scope, element, attrs) {
        var templatePath = attrs.staticInclude;
        $http.get(templatePath, { cache: $templateCache }).success(function(response) {
            var contents = element.html(response).contents();
            $compile(contents)(scope);
        });
    };
});

editor.directive('appAlert', function() {
	return {
		restrict: 'EA',
		templateUrl: '/partials/alert.tpl.html',
		scope: {
			type: '@',
			title: '@',
			text: '@',
			option: '@',
		}
	}
})

editor.service('api', function($rootScope, $q, $http, $cookies, $timeout, Upload) {
	function parseResult(input) {
		var output = [];

		// return null for empty objects/arrays, for easier handling within angular views
		if ((typeof(input) === 'object' && JSON.stringify(input) == "[]") || input == null) {
			return null;
		}

		for(var key in input) {
			if (typeof(input[key]) !== 'object') {
				return input;
			}

			if (input[key] === null) {
				return input;
			}

			if (typeof(input[key]['id']) === 'undefined') {
				return input;
			}

			output.push(input[key]);
		}

		return output;
	}

	var call = function(verb, method, data, silent) {
		if (typeof(silent) === 'undefined') {
			var silent = false;
		}

		$rootScope.isError = null;

		return $q(function(resolve, reject) {

			var request = {
				method: verb.toUpperCase(),
				url: app.root_url+method,
				headers: {'Content-Type': "application/x-www-form-urlencoded"}
			};

			if (typeof(data) !== 'undefined') {
				if (verb == 'get') {
					request.url += "/params";

					for(var k in data) {
						request.url += "/"+k+"/"+data[k];
					}
				} else {
					data.csrf_token = app.csrf_token;
					request.data = $.param(data);
				}
			}

			$http(request).then(function success(resp) {
				if (typeof(resp.data.error) !== 'undefined') {
					$rootScope.isError = resp.data.error_message;

					if (typeof(resp.data.data) !== 'undefined' && typeof(resp.data.data.error_list) !== 'undefined') {
						$rootScope.isError += "<ul><li>"+resp.data.data.error_list.join("<li>")+"</ul>";
					}

					if (typeof(resp.data.data) !== 'undefined' && typeof(resp.data.data.go_to_tab) !== 'undefined') {
						$rootScope.current_tab = resp.data.data.go_to_tab;
					}

					if (typeof(resp.data.data) !== 'undefined' && typeof(resp.data.data.go_to_panel) !== 'undefined') {
						$rootScope.panel = resp.data.data.go_to_panel;
					}

					reject();
				} else if(resp.data == "") {
					$rootScope.isError = "We had trouble communicating with the server.";

					console.log("[Empty Response]");
					console.log(resp);

					reject();
				} else {
					if ($cookies.get('app_token') && $cookies.get('app_user')) {
						$rootScope.isAuthenticated = true;
					}

					if (typeof(resp.data.data.message) !== 'undefined' && !silent) {
						$rootScope.isSuccess = resp.data.data.message;
					}

					resolve(parseResult(resp.data.data));
				}
			}, function failure(resp) {
				$rootScope.isError = "We had trouble communicating with the server.";

				console.log("[Network Error]");
				console.log(resp);
				reject();
			});
		});
	}

	var upload = function(files, callback) {
		// if files were sent
		if (files && files.length && typeof(files[0]) !== 'undefined') {
			// loop through files
			for (var i = 0; i < files.length; i++) {
				var file = files[i];

				if (file) {
					if (!file.$error) {
						Upload.upload({
							url: app.root_url+'/upload_photo',
							data: {
								file: file,
								property_guid: app.property.guid,
								csrf_token: app.csrf_token,
							}
						}).then(function (resp) {
							if (typeof(resp.data.error_message) !== 'undefined') {
								$rootScope.isError = resp.data.error_message;
								delete $rootScope.progress[file.name];
							} else {
								// success
								$rootScope.progress[resp.data.data.previous_filename] = 'done';
								callback(resp.data.data);

								$timeout(function() {
									delete $rootScope.progress[resp.data.data.previous_filename];
								}, 1000);
							}
						}, function(resp) {
							// error
							console.log('Error uploading', resp);
						}, function (evt) {
							// progress event
							$rootScope.progress[evt.config.data.file.name] = parseInt(100.0 * evt.loaded / evt.total);
						});
					} else {
						console.log('Error on file: '+file.$error);
					}
				};
			}
		}
	}

	return {
		call: call,
		upload: upload,
	}
})

editor.factory('preventTemplateCache', function($injector) {
	return {
		'request': function(config) {
			if (config.url.indexOf('partials') !== -1) {
				config.url = config.url + '?t='+Math.random();
			}

			return config;
		}
	}
})

editor.config(function($httpProvider) {
	$httpProvider.interceptors.push('preventTemplateCache');
})

editor.controller('EditorController', function EditorController($scope, $rootScope, $timeout, api) {
	// Initialization /////////////////////////////////////////////////////////////////////////

	$rootScope.progress = {};
	$rootScope.isError = null;

	$scope.property         = app.property;
	$scope.agents           = app.agents;
	$scope.states           = app.states;
	$scope.themes           = app.themes;
	$scope.months           = app.months;
	$scope.fee_setup        = app.fee_setup;
	$scope.fee_ongoing      = app.fee_ongoing;
	$scope.has_subscription = app.has_subscription;

	$scope.starter_details = [
		'Square Footage',
		'Bedrooms',
		'Bathrooms',
		'Floors',
		'Fireplaces',
		'Year Built',
		'Lot Size',
		'School District',
	];

	$rootScope.current_tab = 'basics';
	$rootScope.panel = (app.show_onboarding) ? 'welcome' : 'editor';
	$scope.featured_photos = [];
	$scope.agreements = {};
	$scope.show_page_editor = false;
	$scope.pristine = true;
	$scope.initializing = true;
	$scope.subpages = {
		'photos': 'list',
	}

	// General Utility /////////////////////////////////////////////////////////////////////////

	// handle switching tabs
	$scope.switch_tab = function(to) {
		$rootScope.current_tab = to;
		$scope.show_page_editor = false;
		$scope.show_featured_photos = false;
		$scope.preview_url = null;
		$rootScope.panel = 'editor';
		$scope.subpages = {
			'photos': 'list',
		}
	}

	// determine when to turn on/off the pristine setting
	$scope.$watch('property', function() {
		if (!$scope.initializing) {
			$scope.pristine = false;
		}

		$scope.initializing = false;
	}, true);

	// hide success message automatically
	$rootScope.$watch('isSuccess', function(new_success_message) {
		if (new_success_message) {
			$timeout(function() {
				$rootScope.isSuccess = '';
			}, 2000);
		}
	})

	// save
	$scope.save = function(full_validation, silent, callback) {
		$scope.preview_url = false;

		// set feature_sort_order for each photo as needed
		for(var feature_index in $scope.featured_photos) {
			var feature_guid = $scope.featured_photos[feature_index].guid;

			for(var photo_index in $scope.property.photos) {
				if ($scope.property.photos[photo_index].guid == feature_guid) {
					if ($scope.property.photos[photo_index].feature_sort_order != null) {
						$scope.property.photos[photo_index].feature_sort_order = feature_index;
					}

					continue;
				}
			}
		}

		api.call('post', '/save', {property: $scope.property, do_full_validation: full_validation}, silent).then(function(resp) {
			build_feature_set();

			$scope.property = resp.property;

			setTimeout(function(){$scope.pristine = true;}, 0);

			if (typeof(callback) !== 'undefined') {
				callback();
			}
		}, function() {
			$scope.preview_in_progress = false;
			$scope.publish_in_progress = false;
		});
	}

	// preview
	$scope.preview = function() {
		$scope.save(true, true, function() {
			$scope.preview_in_progress = true;
			
			api.call('post', '/preview', {guid: $scope.property.guid}).then(function(resp) {
				$scope.preview_url = resp.url;
				$scope.preview_in_progress = false;

				if(!window.open(resp.url, '_blank')) {
					$scope.popups_blocked = true;
				};
			}, function() {
				$scope.preview_in_progress = false;
			});
		});
	}

	// initial publish button click
	$scope.publish = function() {
		// save the property
		$scope.save(true, true, function() {

			// check if property is already live
			if ($scope.property.status == 'active') {
				$scope.do_publish();
			} else if($scope.property.status == 'pending') {
				// inform that the property is still in initial set up by the team
				$rootScope.panel = 'pending_status';
			} else {
				// property is still a draft

				// run the preflight
				$rootScope.publish_in_progress = true;

				api.call('post', '/publish_preflight', {guid: $scope.property.guid}).then(function(resp) {
					$rootScope.publish_in_progress = false;

					// check if there's a card on file
					api.call('get', '/card_status').then(function(resp) {
						if (!resp.card) {
							// if no card, sent to card panel
							$rootScope.panel = 'card';
						} else {
							$rootScope.panel = 'checkout';

							// if card exists, save it
							$scope.card = resp.card;

							// retrieve suggested domain name
							api.call('get', '/suggested_domain', {guid: $scope.property.guid}).then(function(resp) {
								$scope.property.domain = resp.domain;
								$scope.suggested_domain = resp.domain;
							})
						}
					});
				}, function() {
					$rootScope.publish_in_progress = false;
				})
			}
		});
	}

	// on blur: check domain
	$scope.check_domain = function() {
		if (!$scope.property.domain) {
			$rootScope.isError = "Please enter a domain name for this property.";

			return;
		}

		$rootScope.isError = null;
		$scope.domain_available = 'checking';

		api.call('post', '/check_domain', {guid: $scope.property.guid, domain: $scope.property.domain}).then(function(resp) {
			$scope.domain_available = resp.available;
		})
	}

	$scope.do_publish = function() {
		// if draft, check for domain, fee agreements
		if ($scope.property.status == 'draft') {
			if (!$scope.property.domain) {
				$rootScope.isError = "Please enter a domain for this property's site.";
				return;
			}

			if(!$scope.domain_available && $scope.property.domain != $scope.suggested_domain) {
				$rootScope.isError = "The domain you entered is not available. Please try another domain.";
				return;
			}

			if(!$scope.agreements.agree_setup) {
				$rootScope.isError = "You must agree to the setup fee in order to publish.";
				return;
			}

			if(!$scope.agreements.agree_ongoing) {
				$rootScope.isError = "You must agree to the ongoing fee in order to publish.";
				return;
			}
		}

		$rootScope.publish_in_progress = true;

		api.call('post', '/publish', {guid: $scope.property.guid, domain: $scope.property.domain}).then(function(resp) {
			$rootScope.publish_in_progress = false;

			$scope.property = resp.property;

			if ($scope.property.status != 'active') {
				$rootScope.panel = 'checkout_success';
			}
		}, function() {
			$rootScope.publish_in_progress = false;
		})
	}

	$scope.save_card = function() {
		$scope.saving_card_in_progress = true;

		api.call('post', '/save_card', {card: $scope.card}).then(function(resp) {
			$scope.saving_card_in_progress = false;
			$scope.publish();
		}, function() {
			$scope.saving_card_in_progress = false;
		})
	}

	$scope.dirty = function() {
		$scope.pristine = false;
	}

	// The Basics /////////////////////////////////////////////////////////////////////////

	// listen for theme switch; delete options if switching themes
	$scope.$watch('property.theme', function(new_theme, old_theme) {
		if (new_theme == null) {
			$scope.property.theme_options = '';
		}
	}, true);

	// click on Manage Agents button
	$scope.manage_agents = function() {
		// save first
		$scope.save(false, false, function() {
			// redirect page
			window.location="/account/agents";
		})
	}

	// Property Details /////////////////////////////////////////////////////////////////////////

	$scope.new_detail = function(name, index) {
		$scope.property.details.push({name: name, value: ''});

		// remove suggestion
		if (typeof(index) !== 'undefined') {
			$scope.starter_details.splice(index, 1);
		}
	}

	// Photos /////////////////////////////////////////////////////////////////////////

	var build_feature_set = function() {
		$scope.featured_photos = [];

		// create second set of photos for the featured sorting
		for(var photo_index in $scope.property.photos) {
			$scope.featured_photos.push($scope.property.photos[photo_index]);
		}

		// sort this featured_photos by the feature_sort_order value
		$scope.featured_photos = $scope.featured_photos.sort(function(a, b) {
			return a.feature_sort_order - b.feature_sort_order;
		})
	}

	build_feature_set();

	$scope.toggle_featured_photo = function(guid, new_value) {
		// find photo in original set, update sort order value
		for(var index in $scope.property.photos) {
			if ($scope.property.photos[index].guid == guid) {
				$scope.property.photos[index].feature_sort_order = new_value;
			}
		}

		// find photo in featured set, update there too
		for(var feature_index in $scope.featured_photos) {
			if ($scope.featured_photos[feature_index].guid == guid) {
				$scope.featured_photos[feature_index].feature_sort_order = new_value;
			}
		}
	}

	$scope.upload_photos = function(files) {
		api.upload(files, function(data) {
			// if this is meant to be the primary photo
			if (data.make_primary) {
				$scope.property.primary_photo_guid = data.photo.guid;
			}

			$scope.property.photos.push(data.photo);
			$scope.featured_photos.push(data.photo);
		})
	}

	// Pages /////////////////////////////////////////////////////////////////////////

	$scope.add_page = function() {
		$scope.property.pages.push({title: 'Untitled Page', content: ''});
		$scope.edit_page($scope.property.pages.length-1);
	}

	$scope.edit_page = function(index) {
		$scope.editing_page_index = index;
		$scope.show_page_editor = true;
	}

	$scope.list_pages = function() {
		$scope.show_page_editor = false;
	}

	// Onboarding /////////////////////////////////////////////////////////////////////////

	$scope.switch_tab_and_goto_step = function(tour, tab, stepId) {
		$scope.switch_tab(tab);

		return setTimeout(function() {
			if(stepId == 'photos_featured') {
				$("html, body").animate({scrollTop: 0}, 'slow');
			}

			return tour.goTo(stepId)
		}, 100 );
	}
});