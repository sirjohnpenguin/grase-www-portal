<?php
/* DEFINE QRCode images folder (read and write access for apache user) */
define('QRCODE_TMP_SERVERPATH', '/usr/share/grase/qrimages/');

//URL Grase Hotspot
define('URL_GRASE_QRCODE','http://10.1.0.1/grase/uam/?qrc=');

//URL user (where user is redirected after login, can be our homepage)
define('URL_USER_QRCODE','http://google.com/');

/* DEFINE cipher (only aes-128,aes-256 tested)*/
define('SELECTED_CIPHER', 'aes-256-cbc');

/* DEFINE encryption key (aka password) */
define('ENCRYPTION_KEY', 'D%m[~OFT;sVS.f-G');

/* DEFINE fixed iv 
 * (not recommended....but, maybe you want shortest url's)
 * again...not recommended....but, mayyybe...mayybe, etc 
*/
define('FIXED_IV', false); //set true to use fixed iv
define('FIXED_IV_DATA', '%m[~4jRgxt2TaB8P'); //change to something with 16chars value



/*
 *  FUNCTIONS 
*/

function encrypt_qrcode($qrcode_data,$fixed_iv=FIXED_IV){
	if ($fixed_iv){
		//ciframos $qrcode_data con el $iv estático y el cifrado seleccionado
		$encrypted = openssl_encrypt($qrcode_data, SELECTED_CIPHER, ENCRYPTION_KEY, 0, FIXED_IV_DATA);
		//rawurlencode, para usar el valor como $_POST, $_GET, etc.
		$encrypted=rawurlencode($encrypted);
		return $encrypted;
		
	}else{
			
		//generamos el iv aleatorio
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(SELECTED_CIPHER));
		//ciframos $qrcode_data con el $iv generado y el cifrado seleccionado
		$encrypted = openssl_encrypt($qrcode_data, SELECTED_CIPHER, ENCRYPTION_KEY, 0, $iv);
		
		//convertimos el $iv a hex, para poder pasarlo por $_POST o $_GET
		$bin2hex_iv=bin2hex($iv);

		//concatenamos
		$encrypted=rawurlencode($encrypted);
		$concatenated=$encrypted.''.$bin2hex_iv;
		//devolvemos el valor concatenado listo para usar
		return $concatenated;
	}
}

function decrypt_qrcode($qrcode_data, $fixed_iv=FIXED_IV){
	
	if ($fixed_iv){
		//desrawurldecodeamos (?) $qrcode_data
		$encrypted=rawurldecode($qrcode_data);
		//desciframos $qrcode_data con el $iv estático y el cifrado seleccionado
		$decrypted = openssl_decrypt($encrypted, SELECTED_CIPHER, ENCRYPTION_KEY, 0, FIXED_IV_DATA );
		//y devolvemos la cadena descifrada
		return $decrypted;
		
	}else{
		
		//largo del tipo de $iv, lo multiplicamos x 2, y lo restamos
		//por lo menos con aes128/256 funciona el hack
		$substr_value = openssl_cipher_iv_length(SELECTED_CIPHER) * 2;
		$substr_value= -$substr_value;

		//extraemos $iv y convertimos el $iv al valor original
		$hex2bin_iv=hex2bin(substr($qrcode_data, $substr_value));

		//extraemos $encrypted de la cadena cifrada
		//tambien lo desrawurldecodeamos (?)
		$encrypted=substr($qrcode_data,0, $substr_value);
		$encrypted=rawurldecode($encrypted);

		//desciframos y devolvemos la cadena descifrada
		$decrypted = openssl_decrypt($encrypted, SELECTED_CIPHER, ENCRYPTION_KEY, 0, $hex2bin_iv);
		return $decrypted;
	}
}

?>
