from flask import Flask, jsonify, request, send_from_directory
from datetime import datetime, timedelta

app = Flask(__name__, static_folder='static', static_url_path='')

# Define a constant for report expiry
REPORT_EXPIRY_HOURS = 3 # Can be set to a smaller value like 0.01 (36 seconds) for testing

# Global list to store incident reports
reports = []

@app.route('/')
def index():
    return send_from_directory(app.static_folder, 'index.html')

@app.route('/api/reports', methods=['POST'])
def submit_report():
    data = request.get_json()
    if not data:
        return jsonify({'error': 'No data provided'}), 400

    # Basic validation (can be expanded)
    required_fields = ['latitude', 'longitude', 'incidentType', 'description']
    if not all(field in data for field in required_fields):
        return jsonify({'error': 'Missing required fields'}), 400

    report = {
        'latitude': data['latitude'],
        'longitude': data['longitude'],
        'incidentType': data['incidentType'],
        'description': data['description'],
        'timestamp': datetime.utcnow().isoformat()
    }
    reports.append(report)
    return jsonify({'message': 'Incidente informado correctamente'}), 201

@app.route('/api/reports', methods=['GET'])
def get_reports():
    now = datetime.utcnow()
    current_reports = []
    for report in reports: # Iterate over the original list
        report_time = datetime.fromisoformat(report['timestamp'])
        if now - report_time < timedelta(hours=REPORT_EXPIRY_HOURS):
            current_reports.append(report)
    return jsonify(current_reports)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
