// Mapbox Configuration
// Using Mapbox instead of Google Maps for better performance and customization

const MAPBOX_CONFIG = {
    accessToken: 'pk.eyJ1Ijoic3RhdHoxMjMiLCJhIjoiY21mdnc1M2gzMGJmZzJwb2VmdTh3bXRzMiJ9.jj7vGCmg3WwQC_wHnbMxPw',
    defaultCenter: {
        lat: 14.5995, // Manila, Philippines
        lng: 120.9842
    },
    defaultZoom: 11,
    style: 'mapbox://styles/mapbox/streets-v12' // Mapbox streets style
};

// Function to get the access token
function getMapboxAccessToken() {
    return MAPBOX_CONFIG.accessToken;
}

// Function to check if access token is set
function isMapboxTokenSet() {
    return MAPBOX_CONFIG.accessToken && MAPBOX_CONFIG.accessToken.length > 0;
}

// Backward compatibility functions (keeping the old names for existing code)
function getGoogleMapsApiKey() {
    return MAPBOX_CONFIG.accessToken;
}

function isApiKeySet() {
    return isMapboxTokenSet();
}


