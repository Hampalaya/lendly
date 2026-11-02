import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { bindListingPin } from './listing-map-pin';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const DEFAULT_CENTER = [14.5995, 120.9842]; // Metro Manila, used when no location is known yet
const hasCoordinates = (lat, lng) => lat != null && lng != null && lat !== '' && lng !== '';

// A street-filled tile around Carmona, shared by every style for easy comparison.
const PREVIEW_TILE = '14/13701/7533.png';

const MAP_LAYERS = [
    {
        id: 'standard',
        name: 'Standard',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors',
        preview: `https://a.tile.openstreetmap.org/${PREVIEW_TILE}`,
    },
    {
        id: 'cyclosm',
        name: 'CyclOSM',
        url: 'https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors, CyclOSM',
        preview: `https://a.tile-cyclosm.openstreetmap.fr/cyclosm/${PREVIEW_TILE}`,
    },
    {
        id: 'transport',
        name: 'Transport Map',
        url: 'https://tile.memomaps.de/tilegen/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors, MemoMaps',
        preview: `https://tile.memomaps.de/tilegen/${PREVIEW_TILE}`,
    },
    {
        id: 'humanitarian',
        name: 'Humanitarian',
        url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors, Humanitarian OpenStreetMap Team',
        preview: `https://a.tile.openstreetmap.fr/hot/${PREVIEW_TILE}`,
    },
    {
        id: 'topo',
        name: 'Topo',
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors, SRTM, OpenTopoMap',
        preview: `https://a.tile.opentopomap.org/${PREVIEW_TILE}`,
    },
];

document.addEventListener('alpine:init', () => {
    Alpine.data('locationPicker', (initialLat, initialLng) => ({
        map: null,
        marker: null,
        locating: false,
        addressLoading: false,
        locationError: '',
        locationStatus: '',
        pinVersion: 0,

        init() {
            const hasLocation = hasCoordinates(initialLat, initialLng);
            const center = hasLocation ? [initialLat, initialLng] : DEFAULT_CENTER;

            this.map = L.map(this.$refs.map).setView(center, hasLocation ? 14 : 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(this.map);

            if (hasLocation) {
                this.marker = L.marker(center, { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => this.reportPosition(this.marker.getLatLng()));
            }

            this.map.on('click', (event) => {
                if (this.marker) {
                    this.marker.setLatLng(event.latlng);
                } else {
                    this.marker = L.marker(event.latlng, { draggable: true }).addTo(this.map);
                    this.marker.on('dragend', () => this.reportPosition(this.marker.getLatLng()));
                }
                this.reportPosition(event.latlng);
            });

            setTimeout(() => this.map.invalidateSize(), 200);
        },

        async reportPosition(latlng) {
            const latitude = Number(latlng.lat.toFixed(7));
            const longitude = Number(latlng.lng.toFixed(7));
            const version = ++this.pinVersion;
            this.locationError = '';
            this.locationStatus = 'Pin updated.';
            this.$wire.set('latitude', latitude, false);
            this.$wire.set('longitude', longitude, false);
            if (typeof this.$wire.resolvePinnedAddress !== 'function') return;
            const previousAddress = this.$wire.get('location');
            this.addressLoading = true;
            try {
                let result;
                for (let attempt = 0; attempt < 3; attempt++) {
                    if (version !== this.pinVersion) return;
                    result = await this.$wire.resolvePinnedAddress(latitude, longitude);
                    if (version !== this.pinVersion) return;
                    if (result?.address || !result?.retry_after || attempt === 2) break;
                    this.locationStatus = 'Finding the address for your latest pin…';
                    await new Promise(resolve => setTimeout(resolve, Math.min(result.retry_after, 2) * 1000));
                }
                if (version !== this.pinVersion) return;
                if (this.$wire.get('location') !== previousAddress) {
                    this.locationStatus = 'Pin updated. Your edited address was kept.';
                } else if (result?.address) {
                    this.$wire.set('location', result.address);
                    this.locationStatus = 'Pin and address updated. You can refine the Location field.';
                } else {
                    this.locationStatus = 'Pin saved. Address lookup is unavailable; enter the address manually or retry.';
                }
            } catch {
                if (version === this.pinVersion) this.locationStatus = 'Pin saved. Enter the address manually or retry.';
            } finally {
                if (version === this.pinVersion) this.addressLoading = false;
            }
        },

        retryAddress() {
            if (this.marker && !this.addressLoading) this.reportPosition(this.marker.getLatLng());
        },

        useMyLocation() {
            if (this.locating) return;
            this.locationError = '';
            this.locationStatus = '';
            if (!navigator.geolocation) {
                this.locationError = 'This browser cannot share your location. Place a pin on the map instead.';
                return;
            }
            this.locating = true;
            const version = this.pinVersion;

            navigator.geolocation.getCurrentPosition((position) => {
                if (version !== this.pinVersion) {
                    this.locating = false;
                    return;
                }
                const latlng = { lat: position.coords.latitude, lng: position.coords.longitude };
                // The form can scroll/resize while the location permission is
                // pending. Refresh Leaflet's size and cancel any previous pan
                // so the first result centers immediately, even off screen.
                this.map.stop();
                this.map.invalidateSize({ pan: false });
                this.map.setView([latlng.lat, latlng.lng], 14, { animate: false });

                if (this.marker) {
                    this.marker.setLatLng(latlng);
                } else {
                    this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
                    this.marker.on('dragend', () => this.reportPosition(this.marker.getLatLng()));
                }

                this.$refs.map.scrollIntoView({ block: 'center', behavior: 'smooth' });
                this.reportPosition(latlng);
                this.locating = false;
            }, (error) => {
                this.locating = false;
                if (version !== this.pinVersion) return;
                this.locationError = error.code === 1
                    ? 'Location access was denied. You can still place the pin on the map.'
                    : 'Your location could not be found. Place a pin on the map or try again.';
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        },
    }));

    Alpine.data('listingsMap', (markers, centerLat, centerLng) => ({
        map: null,
        baseLayer: null,
        markerLayer: null,
        locationMarker: null,
        locating: false,
        locationError: '',
        layers: MAP_LAYERS,
        activeLayer: 'transport',
        layersOpen: false,
        showListingPins: true,
        showMyLocation: true,
        latestMarkers: markers,
        latestLocation: hasCoordinates(centerLat, centerLng) ? [centerLat, centerLng] : null,
        locationNotice: '',

        init() {
            const hasLocation = hasCoordinates(centerLat, centerLng);
            const center = hasLocation ? [centerLat, centerLng] : DEFAULT_CENTER;

            this.map = L.map(this.$refs.map, {
                zoomControl: false,
            }).setView(center, hasLocation ? 14 : 11);

            this.setBaseLayer(this.activeLayer);

            this.markerLayer = L.layerGroup().addTo(this.map);
            this.renderMarkers(markers);

            if (hasLocation) {
                this.setLocationMarker(centerLat, centerLng);
            }

            setTimeout(() => this.map.invalidateSize(), 200);

            this.$watch('$wire.markers', (value) => this.renderMarkers(value));
            this.$watch('$wire.centerLat', () => this.syncLocation());
            this.$watch('$wire.centerLng', () => this.syncLocation());
        },

        syncLocation() {
            if (hasCoordinates(this.$wire.centerLat, this.$wire.centerLng)) {
                this.setLocationMarker(this.$wire.centerLat, this.$wire.centerLng);
                return;
            }

            this.latestLocation = null;
            this.locationNotice = '';
            if (this.locationMarker) {
                this.map.removeLayer(this.locationMarker);
                this.locationMarker = null;
            }
        },

        setBaseLayer(layerId) {
            const layer = this.layers.find((item) => item.id === layerId) ?? this.layers[0];

            if (!this.map || !layer) return;

            if (this.baseLayer) {
                this.map.removeLayer(this.baseLayer);
            }

            this.baseLayer = L.tileLayer(layer.url, {
                attribution: layer.attribution,
                maxZoom: 19,
            }).addTo(this.map);

            this.activeLayer = layer.id;
        },

        renderMarkers(items) {
            this.latestMarkers = items;
            this.markerLayer.clearLayers();

            if (!this.showListingPins) return;

            items.forEach((item) => {
                const marker = L.marker([item.lat, item.lng], {
                    title: `${item.name} · ₱${item.price} per ${item.priceUnit === 'hour' ? 'hour' : 'day'}`,
                    alt: item.name,
                    keyboard: true,
                }).addTo(this.markerLayer);
                bindListingPin(marker, item);
            });
        },

        setLocationMarker(lat, lng) {
            this.latestLocation = [lat, lng];

            if (!this.showMyLocation) return;

            if (this.locationMarker) {
                this.locationMarker.setLatLng([lat, lng]);
            } else {
                this.locationMarker = L.circleMarker([lat, lng], { radius: 6, color: '#2563eb', fillOpacity: 1 })
                    .addTo(this.map)
                    .bindTooltip('Your selected location');
            }
        },

        toggleListingPins() {
            this.renderMarkers(this.latestMarkers);
        },

        toggleMyLocation() {
            if (this.showMyLocation && this.latestLocation) {
                this.setLocationMarker(this.latestLocation[0], this.latestLocation[1]);
                return;
            }

            if (this.locationMarker) {
                this.map.removeLayer(this.locationMarker);
                this.locationMarker = null;
            }
        },

        useMyLocation() {
            if (this.locating) return;

            this.locationError = '';
            this.locationNotice = '';
            if (!navigator.geolocation) {
                this.locationError = 'This browser cannot share your location. You can still search by keyword and category, or browse by city or area in the list view.';
                return;
            }

            this.locating = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
                        this.locating = false;
                        this.locationError = 'Your location could not be found. Try again, or keep searching by keyword and category.';
                        return;
                    }
                    this.locationError = '';

                    this.map.setView([latitude, longitude], 14);
                    this.setLocationMarker(latitude, longitude);

                    // Send both coordinates together to avoid briefly filtering
                    // against a new latitude and an old longitude.
                    this.$wire.set('centerLat', latitude, false);
                    this.$wire.set('centerLng', longitude);
                    this.locating = false;
                    this.locationNotice = 'Location updated. Nearby listings are sorted by distance.';
                    window.dispatchEvent(new CustomEvent('location-found'));
                },
                (error) => {
                    this.locating = false;
                    this.locationError = error.code === 1
                        ? 'Location access was denied. Allow location access in your browser and try again, or keep searching by keyword and category.'
                        : 'Your location could not be found. Try again, or keep searching by keyword and category.';
                },
                { timeout: 10000, maximumAge: 60000 }
            );
        },
    }));

    // Plain geolocation capture with no map — used by pages (e.g. Best
    // Match) that only need lat/lng, not a visual map.
    Alpine.data('geolocationCapture', (latProp = 'lat', lngProp = 'lng') => ({
        capturing: false,

        useMyLocation() {
            if (!navigator.geolocation) return;

            this.capturing = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.$wire.set(latProp, position.coords.latitude);
                    this.$wire.set(lngProp, position.coords.longitude);
                    this.capturing = false;
                },
                () => {
                    this.capturing = false;
                }
            );
        },
    }));
});
