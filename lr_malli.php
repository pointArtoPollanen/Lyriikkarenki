<script type="text/javascript">

function paivita_mallisanoitus(mallisanoitus)
{
	//tulossanoitus = tulossanoitus.replace(/<rivinvaihto>/g,"\n");
	//tulossanoitus = tulossanoitus.replace(/<heittomerkki>/g,"'");
	document.getElementById("mallisanoitus").value = mallisanoitus;
}
</script>

<?php

require "../../../system/db_yhteys.php";

$yhteys=AvaaTietokanta("sanaris");

$dbg = "";

$mallisanoitus = $_POST["mallisanoitus"];
$tulossanoitus = $_POST['tulossanoitus'];

if (isset($_POST['tavuta']) || (strpos($mallisanoitus, "-") === false)) {
	$mallisanoitus = tavutaMallisanoitus($mallisanoitus);
}

function tulostaMallisanoituslista()
{
	global $yhteys;

	$sql = "select esittaja,biisinnimi,sanoitus from lr_mallisanoitukset";
	if ($kysely = mysql_query($sql,$yhteys)) {
		while (TRUE) {
			$luettu = mysql_fetch_row($kysely);
			if ($luettu) {
				$esittaja = skanditTextAreaan($luettu[0]);
				$biisinnimi = skanditTextAreaan($luettu[1]);
				$sanoitus = skanditTextAreaan($luettu[2]);
				print "<option class='w3-dropdown-content w3-light-grey w3-grey w3-left-align' value='$sanoitus' >$esittaja: $biisinnimi</option>";
			}
			else {
				break;
			}
		}
	}
	else {
		print "VIRHE: (" . mysql_error() . ")<br>";
	}
}

function tavutaMallisanoitus($mallisanoitus)
{
	$tmp = str_replace("-", "", $mallisanoitus);  // poistetaan mahdolliset vanhat tavuviivat
	$tmp = skanditTextAreasta($tmp);

	// nyt tulos on valmis tavutettavaksi
	$rv = explode("\n",$tmp);
	$tavutettu = "";

	foreach ($rv as $rivi) {
		$rivi = trim($rivi);
		debug("rivi: $rivi");
		$sanassa = FALSE;
		$sana = "";
		for ($n=0; $n<strlen($rivi); $n++) {
			$m = $rivi[$n];
			if (onkoKirjain($m)) {
				$sanassa = TRUE;
				$sana .= $m;
			}
			else {
				if ($sanassa) {
					$sanassa = FALSE;
					if (strlen($sana) > 0) {
						$tavutettuSana = "";
						tavutaSana($sana, $tavutettuSana);
						debug("sana: $sana, tavutettuSana: $tavutettuSana");
						$tavutettu .= $tavutettuSana;
						$sana = "";
					}
				}
				$tavutettu .= $m;
			}
		}

		if (strlen($sana) > 0) {
			$tavutettuSana = "";
			tavutaSana($sana, $tavutettuSana);
			debug("sana: $sana, tavutettuSana: $tavutettuSana");
			$tavutettu .= $tavutettuSana;
			$sana = "";
		}

		$tavutettu .= "\n";
	}

    $tavutettu = substr($tavutettu, 0, strlen($tavutettu)-1);   // viimeinen rivinvaihto pois

	debug("tavutettu: $tavutettu");

	// mutta $tavutettu ei ole vielä textarea-muodossa
	$mallisanoitus = skanditTextAreaan($tavutettu);

    return $mallisanoitus;
}


function tavutaSana($sana, &$tavutettu)
{
	// Tavutussäännöt:

	// 1. Konsonanttisääntö: Jos tavuun kuuluvaa vokaalia seuraa yksi tai useampia konsonantteja, joita vielä seuraa vokaali,
	// tavuraja sijoittuu välittömästi ennen viimeistä konsonanttia.

	// 2. Vokaalisääntö: Jos tavun ensimmäistä vokaalia seuraa toinen vokaali, niiden väliin tulee tavuraja, ellei
	// a) edellinen vokaali ole sama kuin jälkimmäinen (pitkä vokaali).
	// b) jälkimmäinen vokaali ole i (i:hin loppuva diftongi).
	// c) kysymyksessä ole jokin vokaalipareista au, eu, ie, iu, ou, uo, yö, äy, öy, ey tai iy (muu diftongi).

	// 3. Diftongisääntö: Jos tavun kuuluvaa diftongia tai pitkää vokaalia seuraa vokaali, tähän väliin tulee aina tavuraja.

	// 4. Poikkeussääntö: Yhdyssanat jaetaan tavuihin sanojen välistä, myös siinä tapauksessa
	// että sana on yhdyssana vain alkuperäiskielessä.

    $i = 0;
    $tapaus = 2;
  
    $len = strlen($sana);
    while ($i<$len) {
        switch($tapaus) {
        case 2:            
            if (onkoVokaali($sana[$i])) {  // jos käsiteltävä vokaali, kohtaan 4
                $tapaus = 4;
                break;
            }
            else {  // muussa tapauksessa kohtaan 3
                $tapaus = 3;
                break;
            }
        case 3:
            if ($i >= $len-1) {  // jos merkki sanan viimeinen, lopetetaan
                $tavutettu .= $sana[$i];
                $i++;
                $sanassa = 0;
                $tapaus = 2;
                break;
            }
            else {  // muussa tapauksessa seuraava merkki ja kohtaan 2
                $tavutettu .= $sana[$i];
                $i++;
                $tapaus = 2;
                break;
            }
        case 4:
            if ($i >= $len-1) {  // jos merkki sanan viimeinen, lopetetaan
                $tavutettu .= $sana[$i];
                $i++;
                $sanassa = 0;
                $tapaus = 2;
                break;
            }
            else {  // muussa tapauksessa seuraava merkki ja kohtaan 5
                $tavutettu .= $sana[$i];
                $i++;
                $tapaus = 5;
                break;
            }
        case 5:
           if (!onkoVokaali($sana[$i])) {  // jos merkki konsonantti, kohtaan 10
                $tapaus = 10;
                break;
            }
            else {  // muuten seuraavaan kohtaan
                $tapaus = 6;
                break;
            }
        case 6:
            if (onkoVokaalipari($sana[$i-1],$sana[$i])) {  // jos jokin määritellyistä pareista, kohtaan 8
                $tapaus = 8;
                break;
            }
            else {  // muuten seuraava kohta
                $tapaus = 7;
                break;
            }
	    // poikkeaa alkuperäisestä algoritmista
        case 7:           
            $tavutettu .= "-".$sana[$i];  // laitetaan tavuviiva ennen käsiteltävää
	        $i++;            
            if ($i >= $len-1) {  // jos viimeinen kirjain, lopetetaan
                $tapaus = 2;
	            $sanassa = 0;
                break;
            }
	        else {  // muuten kohtaan 5
	            $tapaus = 5;
	            break;
	        } 
        case 8:
            if ($i >= $len-1) { // jos viimeinen kirjain, lopetetaanm
	            $tavutettu .= $sana[$i];
	            $i++;
	            $sanassa = 0;
	            $tapaus = 2;
	            break;
            }            
	        else {  // uuten seuraava kirjain ja kohtaan 9
                $tavutettu .= $sana[$i];
                $i++;
	            $tapaus = 9;
	            break;
	        }
	    case 9:
            if (onkoVokaali($sana[$i])) {  // jos vokaali, kohtaan 7, muuten kohtaan 10
                $tapaus = 7;
                break;
            }
            else {
                $tapaus = 10;
                break;
            }
	    case 10:
            if ($i >= $len-1) {  // jos viimeinen kirjain, lopetetaan, muuten kohtaan 11
                $tavutettu .= $sana[$i];
                $i++;
                $sanassa = 0;
                $tapaus = 2;
                break;
            }
            else {
                $tapaus = 11;
                break;
            }
	    case 11:
            if (onkoVokaali($sana[$i+1])) {  // jos seuraava vokaali, tavuviiva ennen $i:tä, seuraava kirjain ja kohtaan 4
                $tavutettu .= "-".$sana[$i];
                $i++;
                $tapaus = 4;
                break;
            }
            else {  // muuten seuraava kirjain ja kohtaan 10
                $tavutettu .= $sana[$i];
                $i++;
                $tapaus = 10;
                break;
            }
        }
    }
}


// *********** Yleiskäyttöiset funktiot ******************************

function onkoKirjainTaiTavuviiva($m)
{
	if ($m == "-") {
		return TRUE;
	}
	else if (onkoKirjain($m)) {
		return TRUE;
	}
	else {
		return FALSE;
	}
}

function onkoKirjain($m) {
	$asc = ord($m);
	if ((($asc >= 65) && ($asc <= 90)) || (($asc >= 97) && ($asc <= 122)) ||
		($asc == "197") || ($asc == "196") || ($asc == "214") ||
		($asc == "229") || ($asc == "228") || ($asc == "246")) {
		return true;
	}
	else {
		return false;
	}
}

function onkoIsoKirjain($m) {
	$asc = ord($m);
	if ((($asc >= 65) && ($asc <= 90)) ||
		($asc == "197") || ($asc == "196") || ($asc == "214")) {
		return true;
	}
	else {
		return false;
	}
}

function isoiksiSkandeineen($input) {
	$output = "";
	for ($n=0; $n<strlen($input); $n++) {
		$asc = ord($input[$n]);

		if ($asc == 165)   		// å
			$asc = 133;			// Å
		else if ($asc == 164)	// ä
			$asc = 132;			// Ä
		else if ($asc == 182)	// ö
			$asc = 150;			// Ö

		// toinen ääkköskoodaus
		else if ($asc == 229)   // å
			$asc = 197;			// Å
		else if ($asc == 228)	// ä
			$asc = 196;			// Ä
		else if ($asc == 246)	// ö
			$asc = 214;			// Ö

		$output .= chr($asc);

	}
	$output = strtoupper($output);
	return $output;
}

function pieniksiSkandeineen($input) {
	$output = "";
	for ($n=0; $n<strlen($input); $n++) {
		$asc = ord($input[$n]);

		if ($asc == 133)   		// Å
			$asc = 165;			// å
		else if ($asc == 132)	// Ä
			$asc = 164;			// ä
		else if ($asc == 150)	// Ö
			$asc = 182;			// ö

		// toinen ääkköskoodaus
		else if ($asc == 197)   // Å
			$asc = 229;			// å
		else if ($asc == 196)	// Ä
			$asc = 228;			// ä
		else if ($asc == 214)	// Ö
			$asc = 246;			// ö

		$output .= chr($asc);

	}
	$output = strtolower($output);
	return $output;
}

function sisaltaakoNaitaMerkkeja($str, $merkit)
{
     for ($n=0; $n<strlen($merkit); $n++) {
        $m = $merkit[$n];
        if (strpos($str, $m) !== false) {
            return TRUE;
        }
    }

    return FALSE;
}

function laskeTavunpituudet($sana){

	$tavut = explode("-", $sana);

	$tavunpituudet = 0;
	$koodi = 1;
	foreach ($tavut as $tavu) {
		if (onkoPitkaTavu($tavu)) {
			$tavunpituudet += $koodi; 
		}
		else {
		}
		$koodi *= 2;
	}

	return $tavunpituudet;
}

function skanditTextAreasta($text)
{
	$text = str_replace(chr(195).chr(133), chr(197), $text);  // Å
	$text = str_replace(chr(195).chr(132), chr(196), $text);  // Ä
	$text = str_replace(chr(195).chr(150), chr(214), $text);  // Ö

	$text = str_replace(chr(195).chr(165), chr(229), $text);  // å
	$text = str_replace(chr(195).chr(164), chr(228), $text);  // ä
	$text = str_replace(chr(195).chr(182), chr(246), $text);  // ö

	return $text;
}

function skanditTextAreaan($text)
{
	$text = str_replace(chr(197), chr(195).chr(133), $text);  // Å
	$text = str_replace(chr(196), chr(195).chr(132), $text);  // Ä
	$text = str_replace(chr(214), chr(195).chr(150), $text);  // Ö

	$text = str_replace(chr(229), chr(195).chr(165), $text);  // å
	$text = str_replace(chr(228), chr(195).chr(164), $text);  // ä
	$text = str_replace(chr(246), chr(195).chr(182), $text);  // ö

	return $text;
}

function onkoVokaali($k)
{
	if (strpos("AEIOUYaeiouy",$k) !== false) {
		return 1;
	}
	else {
		$asc = ord($k);
		if (($asc == 196) || ($asc == 214) || ($asc == 197) || ($asc == 228) || ($asc == 246) || ($asc == 229)) {
			return 1;   // Ä, Ö, Å, ä, ö, å
		}
		else {
			return 0;
		}
	}
}

function onkoPitkaTavu($tavu)
{
	$len = strlen($tavu);
	if (strpos("LMNRSlmnrs",$tavu[$len-1]) !== false) {
		return 1;
    }

	$vok = 0;
	for ($n=0; $n<$len; $n++) {
		if (onkoVokaali($tavu[$n])) {
			if (++$vok >= 2) {
				return 1;
            }
		}
	}

	return 0;
}

function onkoVokaalipari($k1, $k2) {
  $k2 = strtoupper($k2);
  $k1 = strtoupper($k1);

  if (($k1 == $k2) || ($k2 == 'I')) {  // pitkä vokaali, tai jälkimmäinen on 'I''
      return 1;  // 
  }
  else if ($k1 == 'A') {
	if ($k2 == 'U')
      	return 1;
  }
  else if ($k1 == 'E') {
    if (($k2 == 'U') || ($k2 == 'Y'))
      return 1;
  }
  else if ($k1 == 'I') {
    if (($k2 == 'E') || ($k2 == 'U'))
      return 1;
  }
  else if ($k1 == 'O') {
    if ($k2 == 'U')
      return 1;
  }
  else if ($k1 == 'U') {
    if ($k2 == 'O')
      return 1;
  }
  
  $ascA = ord($k2);
  $ascB = ord($k1);
  
  if ($k1 == 'Y') {
    if (($ascA == 214) || ($ascA == 246))  // Ö tai ö
      return 1;
  }
  else if (($ascB == 196) || ($ascB == 228)) { // Ä tai ä
    if (($k2 == 'Y') || ($ascA == 196) || ($ascA == 228))  // Y, ä tai Ä
      return 1;
  }
  else if (($ascB == 214) || ($ascB == 246)) {  // Ö tai ö
    if (($k2 == 'Y') || ($ascA == 214) || ($ascA == 246))  // Y, ö tai Ö
      return 1;
  }  

  return 0;  // muussa tapauksessa
}

function startsWith($haystack,$needle) {
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($string,$test) {
	$strlen = strlen($string);
	$testlen = strlen($test);
	if ($testlen > $strlen) return false;
	return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

function debug($msg) {
	global $dbg;

	$dbg .= $msg . "\n";
}

function debugR($msg) {
	global $dbg;

	$dbg .= $msg;
}
?>
