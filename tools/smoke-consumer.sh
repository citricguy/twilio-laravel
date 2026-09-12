#!/usr/bin/env bash
set -euo pipefail

project_dir=$(cd "$(dirname "$0")/.." && pwd)
consumer_dir=$(mktemp -d "${TMPDIR:-/tmp}/twilio-consumer.XXXXXX")
trap 'rm -rf "$consumer_dir"' EXIT

composer create-project laravel/laravel "$consumer_dir" '^13.0' --no-install --no-scripts --no-interaction
php -r '
$target = $argv[1];
$root = $argv[2];
$manifest = json_decode(file_get_contents($target."/composer.json"), true, 512, JSON_THROW_ON_ERROR);
$manifest["require"] = ["php" => "^8.5", "laravel/framework" => "^13.23", "citricguy/twilio-laravel" => "^3.0", "symfony/http-client" => "^8.1", "symfony/postmark-mailer" => "^8.1"];
unset($manifest["require-dev"]);
$manifest["repositories"] = [["type" => "path", "url" => $root, "options" => ["versions" => ["citricguy/twilio-laravel" => "3.0.0"]]]];
file_put_contents($target."/composer.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
file_put_contents($target."/.env", "APP_ENV=testing\nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nAPP_DEBUG=false\nCACHE_STORE=array\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=array\n");
' "$consumer_dir" "$project_dir"
composer update --working-dir="$consumer_dir" --no-scripts --no-plugins --no-interaction --no-progress
composer audit --working-dir="$consumer_dir"
php "$consumer_dir/artisan" package:discover
php "$consumer_dir/artisan" config:cache
php "$consumer_dir/artisan" route:cache
php "$project_dir/tools/smoke-consumer.php" "$consumer_dir"
