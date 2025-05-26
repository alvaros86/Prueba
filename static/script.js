// Initialize the map
var map = L.map('map').setView([0, 0], 2);

// Add a tile layer (OpenStreetMap)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

// Get references to the form and its input fields
var incidentForm = document.getElementById('incidentForm');
var incidentTypeInput = document.getElementById('incidentType');
var descriptionInput = document.getElementById('description');
var latitudeInput = document.getElementById('latitude');
var longitudeInput = document.getElementById('longitude');

// Array to store report markers
let reportMarkers = [];

// Function to fetch and display reports
function fetchAndDisplayReports() {
    fetch('/api/reports')
        .then(response => response.json())
        .then(reports => {
            // Clear existing markers
            reportMarkers.forEach(marker => map.removeLayer(marker));
            reportMarkers = [];

            // Iterate over fetched reports and add markers
            reports.forEach(report => {
                var marker = L.marker([report.latitude, report.longitude]);
                marker.bindPopup(`<b>${report.incidentType}</b><br>${report.description}`);
                marker.addTo(map);
                reportMarkers.push(marker);
            });
        })
        .catch(error => console.error('Error fetching reports:', error));
}

// Add a map click event listener
map.on('click', function(e) {
    // Get the latitude and longitude of the click event
    var lat = e.latlng.lat;
    var lng = e.latlng.lng;

    // Set the value of the hidden latitude and longitude input fields
    latitudeInput.value = lat;
    longitudeInput.value = lng;

    // Make the form visible
    incidentForm.style.display = 'block';

    // Log these coordinates to the browser's console
    console.log('Map clicked at: Lat ' + lat + ', Lng ' + lng);
});

// Add an event listener to the form for the submit event
incidentForm.addEventListener('submit', function(event) {
    // Prevent the default form submission
    event.preventDefault();

    // Get the values from the incident type, description, latitude, and longitude fields
    var incidentType = incidentTypeInput.value;
    var description = descriptionInput.value;
    var latitude = parseFloat(latitudeInput.value); // Parse as float
    var longitude = parseFloat(longitudeInput.value); // Parse as float

    // Create an object containing this data
    var incidentData = {
        incidentType: incidentType, // Ensure key matches backend expectation
        description: description,
        latitude: latitude,
        longitude: longitude
    };

    // Send a POST request to /api/reports
    fetch('/api/reports', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(incidentData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Report submitted successfully:', data);
        fetchAndDisplayReports(); // Refresh map with new report
        incidentForm.style.display = 'none'; // Hide the form
        incidentForm.reset(); // Clear the form fields
    })
    .catch(error => {
        console.error('Error submitting report:', error);
    });
});

// Fetch and display existing reports when the script loads
fetchAndDisplayReports();
