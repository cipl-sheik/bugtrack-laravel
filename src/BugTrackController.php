<?php

namespace Ciplnew\BugTracking;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BugTrackController extends Controller
{
    public static function bugTrack($e, $linesBefore = 5, $linesAfter = 5){

        $trace = $e->getTrace();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $errorContext = [];
    
        // Read the file where the error occurred
        $fileLines = file($errorFile);
    
        // Determine start and end lines for context
        $startLine = max(1, $errorLine - $linesBefore);
        $endLine = min(count($fileLines), $errorLine + $linesAfter);

            // Extract context lines
    for ($i = $startLine - 1; $i < $endLine; $i++) {
        $errorContext[] = [
            'line_number' => $i + 1,
            'line_content' => $fileLines[$i],
            'is_error_line' => ($i + 1 === $errorLine)
        ];
    }
    
        $data1 = [
            'error_file' => $errorFile,
            'error_line' => $errorLine,
            'context' => $errorContext
        ];
        $browser = '';
        $ua = strtolower(isset($_SERVER['HTTP_USER_AGENT']));
        // you can add different browsers with the same way ..
        if(preg_match('/(chromium)[ \/]([\w.]+)/', $ua))
                $browser = 'chromium';
        elseif(preg_match('/(chrome)[ \/]([\w.]+)/', $ua))
                $browser = 'chrome';
        elseif(preg_match('/(safari)[ \/]([\w.]+)/', $ua))
                $browser = 'safari';
        elseif(preg_match('/(opera)[ \/]([\w.]+)/', $ua))
                $browser = 'opera';
        elseif(preg_match('/(msie)[ \/]([\w.]+)/', $ua))
                $browser = 'msie';
        elseif(preg_match('/(mozilla)[ \/]([\w.]+)/', $ua))
                $browser = 'mozilla';
    
        preg_match('/('.$browser.')[ \/]([\w]+)/', $ua, $version);
    
        $browser_details = array($browser,$version[2], 'name'=>$browser,'version'=>$version[2]);
        $erTittle = explode(":",$e->__toString());
 
        $data = [
            'title' => ($erTittle[0])?$erTittle[0]:'',
            'description' => ($e->getMessage())?$e->getMessage():'',
            'file_name' => ($e->getFile())?$e->getFile():'',
            'Server_name' => ($_SERVER['SERVER_NAME'])? $_SERVER['SERVER_NAME']:'',
            'environment' => (env('APP_ENV'))?env('APP_ENV'):'',
            'url' => (url()->current())?url()->current():'',
            'php_version' => (phpversion())?phpversion():'',
            'os_name' => (php_uname('s'))?php_uname('s'):'',
            'osversion' => (php_uname('r'))?php_uname('r'):'',
            'browser_name' => ($browser)?$browser:'',
            'browser_version'=>($version[2])?$version[2]:'',
            'language' =>'PHP',
            'error_file_details' =>json_encode($data1),
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://bugtracking.colanapps.in/api/bugtrack/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                // Set here requred headers
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
                "Authorization:".env('BUG_TRCAK_KEY'),
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }
}
