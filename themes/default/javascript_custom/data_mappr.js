(function ($cms) {
    'use strict';

    $cms.templates.formScreenInputMapPosition = function formScreenInputMapPosition(params, container) {
        var name = strVal(params.name),
            googleMapKey = strVal(params.googleMapKey),
            latitude = strVal(params.latitude),
            latitudeInput = container.querySelector('#' + name + '_latitude'),
            longitude = strVal(params.longitude),
            longitudeInput = container.querySelector('#' + name + '_longitude');

        $cms.requireJavascript('https://www.google.com/jsapi').then(function () {
            window.setTimeout(function () {
                window.google.load('maps', '3', { callback: googleMapUsersInitialize, other_params: ((googleMapKey !== '') ? 'key=' + googleMapKey : '') });
            },0);
        });

        $cms.dom.on(container, 'change', '.js-change-set-place-marker', function () {
            placeMarker(latitudeInput.value, longitudeInput.value);
        });

        $cms.dom.on(container, 'click', '.js-click-geolocate-user-for-map-field', function () {
            geolocateUserForMapField();
        });

        var marker, map, lastPoint;

        function googleMapUsersInitialize() {
            marker = new google.maps.Marker();

            var bounds = new google.maps.LatLngBounds(),
                center = new google.maps.LatLng(((latitude !== '') ? latitude : 0), ((longitude !== '') ? longitude : 0));

            map = new google.maps.Map(document.getElementById('map_position_' + name), {
                zoom: (latitude !== '') ? 12 : 1,
                center: center,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                overviewMapControl: true,
                overviewMapControlOptions: { opened: true },
                styles: [{
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }]
            });

            var infoWindow = new google.maps.InfoWindow();

            // Close InfoWindow when clicking anywhere on the map
            google.maps.event.addListener(map, 'click', function () {
                infoWindow.close();
            });

            // Show marker for current position
            if (latitude !== '') {
                placeMarker(((latitude !== '') ? latitude : 0), ((longitude !== '') ? longitude : 0));
            }
            marker.setMap(map);

            // Save into hidden fields
            google.maps.event.addListener(map, 'mousemove', function (point) {
                lastPoint = point.latLng;
            });
            google.maps.event.addListener(map, 'click', _placeMarker);
            google.maps.event.addListener(marker, 'click', _placeMarker);
        }

        function _placeMarker() {
            document.getElementById(name + '_latitude').value = lastPoint.lat();
            document.getElementById(name + '_longitude').value = lastPoint.lng();
            placeMarker(lastPoint.lat(), lastPoint.lng());
            marker.setMap(map);
        }

        function placeMarker(latitude, longitude) {
            var latLng = new google.maps.LatLng(latitude, longitude);
            marker.setPosition(latLng);
            map.setCenter(latLng);
            map.setZoom(12);
        }

        function geolocateUserForMapField() {
            if (navigator.geolocation != null) {
                try {
                    navigator.geolocation.getCurrentPosition(function (position) {
                        placeMarker(position.coords.latitude, position.coords.longitude);

                        document.getElementById(name + '_latitude').value = position.coords.latitude;
                        document.getElementById(name + '_longitude').value = position.coords.longitude;
                    });
                } catch (ignore) {}
            }
        }
    };

    $cms.templates.blockMainGoogleMap = function blockMainGoogleMap(params) {
        var cluster = !!params.cluster,
            googleMapKey = strVal(params.googleMapKey),
            latitude = strVal(params.latitude),
            longitude = strVal(params.longitude),
            divId = strVal(params.divId),
            zoom = strVal(params.zoom),
            center = !!params.center,
            rawData = params.data,
            minLatitude = strVal(params.minLatitude),
            maxLatitude = strVal(params.maxLatitude),
            minLongitude = strVal(params.minLongitude),
            maxLongitude = strVal(params.maxLongitude),
            region = strVal(params.region);

        var scripts = ['https://www.google.com/jsapi'];

        if (cluster) {
            scripts.push('https://raw.githubusercontent.com/printercu/google-maps-utility-library-v3-read-only/master/markerclustererplus/src/markerclusterer_packed.js');
        }

        var options = {
            callback: googleMapInitialize,
            other_params: (googleMapKey !== '') ? 'key=' + $cms.$CONFIG_OPTION('googleMapKey') : ''
        };

        if (region !== '') {
            options.region = region;
        }

        if (window.data_map === undefined) {
            window.data_map = null;
        }

        $cms.requireJavascript(scripts).then(function () {
            google.load('maps', '3', options);
        });

        function googleMapInitialize() {
            var bounds = new google.maps.LatLngBounds();
            var specifiedCenter = new google.maps.LatLng((latitude !== '' ? latitude : 0.0), (longitude !== '' ? longitude : 0.0));
            var gOptions = {
                zoom: zoom,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                overviewMapControl: true,
                overviewMapControlOptions: {opened: true}
            };

            if (!center) { // NB: the block center parameter means to autofit the contents cleanly; if the parameter is not set it will center about the given latitude/longitude
                gOptions.center = specifiedCenter;
            }

            window.data_map = new google.maps.Map(document.getElementById(divId), gOptions);


            var infoWindow = new google.maps.InfoWindow();

            // Close InfoWindow when clicking anywhere on the map
            google.maps.event.addListener(data_map, 'click', function () {
                infoWindow.close();
            });

            var data = [];
            if (Array.isArray(rawData) && rawData.length) {
                rawData.forEach(function (obj) {
                    data.push([
                        obj.entryTitle,
                        obj.latitude,
                        obj.longitude,
                        obj.ccId,
                        obj.entryContent,
                        obj.star
                    ]);
                });
            }

            // Show markers
            var latLng, markerOptions, marker;
            var boundLength = 0;
            if (cluster) {
                var markers = [];
            }

            if ((minLatitude !== maxLatitude) && (minLongitude !== maxLongitude)) {
                if ((minLatitude + minLongitude) !== '') {
                    latLng = new google.maps.LatLng(minLatitude, minLongitude);
                    bounds.extend(latLng);
                    boundLength++;
                }

                if ((maxLatitude + maxLongitude) !== '') {
                    latLng = new google.maps.LatLng(maxLatitude, maxLongitude);
                    bounds.extend(latLng);
                    boundLength++;
                }
            }

            var boundByContents = (boundLength == 0);
            for (var i = 0; i < data.length; i++) {
                latLng = new google.maps.LatLng(data[i][1], data[i][2]);
                if (boundByContents) {
                    bounds.extend(latLng);
                    boundLength++;
                }

                markerOptions = {
                    position: latLng,
                    title: data[i][0]
                };

                /*{$,Reenable if you have put appropriate images in place
                 var categoryIcon='{$BASE_URL;/}/themes/default/images_custom/map_icons/catalogue_category_'+data[i][3]+'.png';
                 marker_options.icon=categoryIcon;}*/
                if (data[i][6] == 1) {
                    var starIcon = $cms.$BASE_URL() + '/themes/default/images_custom/star_highlight.png';
                    markerOptions.icon = starIcon;
                }

                marker = new google.maps.Marker(markerOptions);

                if (cluster) {
                    markers.push(marker);
                } else {
                    marker.setMap(data_map);
                }

                google.maps.event.addListener(marker, 'click', (function (argMarker, entryTitle, entryId, entryContent) {
                    return function () {
                        // Dynamically load entry details only when their marker is clicked
                        var content = entryContent.replace(/<colgroup>(.|\n)*<\/colgroup>/, '').replace(/&nbsp;/g, ' ');
                        if (content != '') {
                            infoWindow.setContent('<div class="global_middle_faux float_surrounder">' + content + '<\/div>');
                            infoWindow.open(data_map, argMarker);
                        }
                    };
                })(marker, data[i][0], data[i][4], data[i][5])); // These are the args passed to the dynamic function above
            }

            if (cluster) {
                var markerCluster = new MarkerClusterer(data_map, markers);
            }

            // Autofit the map around the markers
            if (center) {
                if (boundLength == 0) { // We may have to center at given lat/long after all if there are no pins
                    data_map.setCenter(specifiedCenter);
                } else if (boundLength == 1) { // Center around the only pin
                    data_map.setCenter(new google.maps.LatLng(data[0][1], data[0][2]));
                } else { // Good - autofit lots of pins
                    data_map.fitBounds(bounds);
                }
            }
            // Sample code to grab clicked positions
            var lastPoint;
            google.maps.event.addListener(data_map, 'mousemove', function (point) {
                lastPoint = point.latLng;
            });
            google.maps.event.addListener(data_map, 'click', function () {
                $cms.log(lastPoint.lat() + ', ' + lastPoint.lng());
            });
        }
    }
}(window.$cms));