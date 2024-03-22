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

        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
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
        // if(!empty($_SERVER['HTTP_USER_AGENT'])){
        //     $user_ag = $_SERVER['HTTP_USER_AGENT'];
        //     if(preg_match('/(Mobile|Android|Tablet|GoBrowser|[0-9]x[0-9]*|uZardWeb\/|Mini|Doris\/|Skyfire\/|iPhone|Fennec\/|Maemo|Iris\/|CLDC\-|Mobi\/)/uis',$user_ag)){
        //         // dd('Mobile|Android|Tablet|');
        //     };
        // };
        // //  dd('Laptop| Monitor');
    
        $isMob = is_numeric(strpos(strtolower($_SERVER["HTTP_USER_AGENT"]), "mobile")); 
        $device_name = '';
        if($isMob){ 
            $device_name ='Mobile Device'; 
        }else{ 
            $device_name ='Desktop'; 
        }
        
        $erTittle = explode(":",$e->__toString());
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) $browser = 'Opera';
        elseif (strpos($user_agent, 'Edg') || strpos($user_agent, 'Edge')) $browser = 'Edge';
        elseif (strpos($user_agent, 'Chrome')) $browser = 'Chrome';
        elseif (strpos($user_agent, 'Safari')) $browser = 'Safari';
        elseif (strpos($user_agent, 'Firefox')) $browser = 'Firefox';
        elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) $browser = 'Internet Explorer';
        $os_array =   array(
            '/windows nt 10/i'      =>  'Windows 10',
            '/windows nt 6.3/i'     =>  'Windows 8.1',
            '/windows nt 6.2/i'     =>  'Windows 8',
            '/windows nt 6.1/i'     =>  'Windows 7',
            '/windows nt 6.0/i'     =>  'Windows Vista',
            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     =>  'Windows XP',
            '/windows xp/i'         =>  'Windows XP',
            '/windows nt 5.0/i'     =>  'Windows 2000',
            '/windows me/i'         =>  'Windows ME',
            '/win98/i'              =>  'Windows 98',
            '/win95/i'              =>  'Windows 95',
            '/win16/i'              =>  'Windows 3.11',
            '/macintosh|mac os x/i' =>  'Mac OS X',
            '/mac_powerpc/i'        =>  'Mac OS 9',
            '/linux/i'              =>  'Linux',
            '/ubuntu/i'             =>  'Ubuntu',
            '/iphone/i'             =>  'iPhone',
            '/ipod/i'               =>  'iPod',
            '/ipad/i'               =>  'iPad',
            '/android/i'            =>  'Android',
            '/blackberry/i'         =>  'BlackBerry',
            '/webos/i'              =>  'Mobile'
        );
        foreach ( $os_array as $regex => $value ) { 
            if ( preg_match($regex, $user_agent ) ) {
                $os_platform = $value;
            }
        } 
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
            'error_file_details' =>$data1,
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
