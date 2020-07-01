<?php
//echo get_remote_data('http://example.com');                                // GET request 
//echo get_remote_data('http://example.com', "var2=something&var3=blabla" ); // POST request


//See Updates and explanation at: https://github.com/tazotodua/useful-php-scripts/
function get_remote_data($url, $user_and_pwd=false,  $header_paramtrs=false, $post_paramtrs=false)    
{   
	$c = curl_init();
	curl_setopt($c, CURLOPT_URL, urldecode($url));
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($c, CURLOPT_HTTP_VERSION,  CURL_HTTP_VERSION_2TLS);
	if ($user_and_pwd) {
	    curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	    curl_setopt($c, CURLOPT_USERPWD, $user_and_pwd);
	}
	if($header_paramtrs) {
	    curl_setopt($c, CURLOPT_HTTPHEADER, $header_paramtrs);
	}
	if($post_paramtrs)	{
		curl_setopt($c, CURLOPT_POST,TRUE);  
		curl_setopt($c, CURLOPT_POSTFIELDS, "var1=bla&".$post_paramtrs );
	}
	curl_setopt($c, CURLOPT_SSL_VERIFYHOST,false);
	curl_setopt($c, CURLOPT_SSL_VERIFYPEER,false);
	curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:33.0) Gecko/20100101 Firefox/33.0"); 
	curl_setopt($c, CURLOPT_COOKIE, 'CookieName1=Value;'); 
	curl_setopt($c, CURLOPT_MAXREDIRS, 10);  
	$follow_allowed= ( ini_get('open_basedir') || ini_get('safe_mode')) ? false:true;  
	if ($follow_allowed){
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
	}
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 9);
	curl_setopt($c, CURLOPT_REFERER, $url);
	curl_setopt($c, CURLOPT_TIMEOUT, 60);
	curl_setopt($c, CURLOPT_AUTOREFERER, true);         
	curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');
	//curl_setopt($c, CURLINFO_HEADER_OUT );
	//curl_setopt($ch, CURLOPT_VERBOSE, true);


	$data=curl_exec($c);
	$status=curl_getinfo($c);
	curl_close($c);
/*	
echo "\n";	
echo "After curl\n";
echo "Status=";
print_r($status);
echo "\n";
echo "data=";
echo "\n";
print_r($data);
echo "\n";
*/
	preg_match('/(http(|s)):\/\/(.*?)\/(.*\/|)/si',  $status['url'],$link);
	$data=preg_replace('/(src|href|action)=(\'|\")((?!(http|https|javascript:|\/\/|\/)).*?)(\'|\")/si','$1=$2'.$link[0].'$3$4$5', $data);
	$data=preg_replace('/(src|href|action)=(\'|\")((?!(http|https|javascript:|\/\/)).*?)(\'|\")/si','$1=$2'.$link[1].'://'.$link[3].'$3$4$5', $data);
	if($status['http_code']==200) {
		return $data;
	} elseif($status['http_code']==301 || $status['http_code']==302) { 
		if (!$follow_allowed){
			if(empty($redirURL)){
				if(!empty($status['redirect_url'])){
					$redirURL=$status['redirect_url'];
				}
			}   
			if(empty($redirURL)){
				preg_match('/(Location:|URI:)(.*?)(\r|\n)/si', $data, $m);
				if (!empty($m[2])){ 
					$redirURL=$m[2]; 
				} 
			} 
			if(empty($redirURL)){
				preg_match('/href\=\"(.*?)\"(.*?)here\<\/a\>/si',$data,$m); 
				if (!empty($m[1])){
					$redirURL=$m[1]; 
				} 
			}   
			if(!empty($redirURL)){
				$t=debug_backtrace(); 
				return call_user_func( $t[0]["function"], trim($redirURL), $post_paramtrs);
			}
		}
	} 
	return false;
//	return "ERRORCODE22 with $url!!<br/>Last status codes<b/>:".json_encode($status)."<br/><br/>Last data got<br/>:$data";
}
?>