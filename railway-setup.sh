#!/bin/bash

# Create necessary directories for Railway deployment
mkdir -p public/uploads/profiles
mkdir -p uploads/profiles
mkdir -p logs

# Set permissions
chmod 755 public/uploads/profiles
chmod 755 uploads/profiles  
chmod 755 logs

echo "Directories created successfully for Railway deployment"
echo "- public/uploads/profiles"
echo "- uploads/profiles"
echo "- logs"