#!/bin/bash

set -e

echo "Deploying..."

git pull --rebase

php artisan down

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache

php artisan route:cache

php artisan view:cache

php artisan up

echo "Deploying completed"