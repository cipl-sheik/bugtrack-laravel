<?php

Route::get("/test",function(){
	echo "Ajith test";
});
Route::get('/debug-sentry', function () {
    throw new Exception('My first Sentry error!');
});