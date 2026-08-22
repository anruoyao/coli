<?php
// ‼️‼️‼️
// This file will never be overwritten by the update.
// You can add your disks here. No .env variables needed.

return [
	// Default “public” disk.
	// ▸ Files live in storage/app/public on *your* server (they are not uploaded to the cloud).
	// ▸ Want a different disk?  Add it in the list below and, if you like, delete this entry.
	// ▸ If you do delete it, make sure at least one other disk is configured.
	// ▸ Make sure that disk name is unique. E.g 's3_one', 's3_two', etc. 
	// ▸ Add name and description to make it more readable on the admin panel.

	'public' => [
		'name' => 'Public disk',
		'description' => 'Public disk is the default disk for the application.',
		'driver' => 'local',
		'root' => storage_path('app/public'),
		'url' => env('APP_URL').'/storage',
		'visibility' => 'public',
		'throw' => false,
	],
	
	// You can add here file system disks as much as you want.
	// But make sure that disk name is unique. E.g 's3_one', 's3_two', etc. 
    // 's3' => [
	// 	'name' => 'Disk name',
	// 	'driver' => 's3',
	// 	'key' => '',
	// 	'secret' => '',
	// 	'region' => '',
	// 	'bucket' => '',
	// 	'url' => '',
	// 	'endpoint' => '',
	// 	'use_path_style_endpoint' => false,
	// 	'throw' => false,
	// ],
];