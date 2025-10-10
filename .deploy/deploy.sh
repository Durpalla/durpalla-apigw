#!/bin/bash
# restart-octane.sh
# Restart Laravel Octane (RoadRunner) service for apigw.durpalla.com

SERVICE="rr-apigw"
LOG_FILE="/var/log/nginx/error.log"
APP_DIR="/var/www/html/apigw.durpalla.com"

echo "-------------------------------------------"
echo " Restarting Laravel Octane (RoadRunner)"
echo " Service: $SERVICE"
echo "-------------------------------------------"

# Make sure systemd knows about any recent changes
sudo systemctl daemon-reload

# Stop service safely
echo "Stopping $SERVICE..."
sudo systemctl stop $SERVICE

# Wait a moment
sleep 2

# Start service
echo "Starting $SERVICE..."
sudo systemctl start $SERVICE

# Show status
sleep 1
sudo systemctl --no-pager status $SERVICE

# Optional: tail recent error logs if requested
if [[ "$1" == "--logs" || "$1" == "-l" ]]; then
  echo
  echo "-------------------------------------------"
  echo " Showing last 20 lines of Nginx error log:"
  echo "-------------------------------------------"
  sudo tail -n 20 "$LOG_FILE"
fi

# Optional: check if RR port (8000) is open
if command -v ss &>/dev/null; then
  echo
  echo "-------------------------------------------"
  echo " Checking RoadRunner listener on port 8000"
  echo "-------------------------------------------"
  ss -ltnp | grep 8000 || echo "⚠️  RR not listening on port 8000!"
fi

echo "-------------------------------------------"
echo " Done."
echo "-------------------------------------------"
