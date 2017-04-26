<script type="text/javascript">

function paivita_tulossanoitus(eteneminen, sanojenLkm, tulossanoitus)
{
    $prosentti = Math.round((eteneminen * 100) / sanojenLkm);
	tulossanoitus = tulossanoitus.replace(/<rivinvaihto>/g,"\n");
	tulossanoitus = tulossanoitus.replace(/<heittomerkki>/g,"'");
	document.getElementById("tulossanoitus").value = tulossanoitus;
	//document.getElementById("tulossanoitus3").value = tulossanoitus;

	if (eteneminen > 0) {
		document.getElementById("prosentti").innerHTML = $prosentti  + '%';
	}

    var valmiusBar = document.getElementById("valmius_bar");   
    valmiusBar.style.width = $prosentti + '%';

	if (eteneminen < sanojenLkm) {
		valmiusBar.setAttribute('class','w3-progressbar w3-red');
		document.getElementById("valmius_teksti").innerHTML = "LUODAAN TULOSSANOITUSTA...";
	}
	else {
		valmiusBar.setAttribute('class','w3-progressbar w3-theme');
		document.getElementById("valmius_teksti").innerHTML = "";
	}    
}
</script>

<?php

define(EI_RIIMIA, 0);
define(IDENTTINEN_RIIMI, 1);
define(PUHDAS_RIIMI, 2);
define(RAP_VOKAALIRIIMI, 3);
define(KONSONANTTIRIIMI, 4);
define(ALKURIIMI, 5);
define(SAMA_SANA, 6);
define(MUUTTUMATON_SANA, 7);
define(EI_SANA, 8);

define(LAAJIN_SANASTO, 1);
define(YLEISET_SANASTO, 2);
define(K18_SANASTO, 3);
define(BIISEISTA_SANASTO, 4);

//require "../../../system/db_yhteys.php";  // TÄMÄ ON NYKYÄÄN lr_malli.php:ssa

//$yhteys=AvaaTietokanta("sanaris");   // TÄMÄ ON NYKYÄÄN lr_malli.php:ssa

$par_biiseista = $_POST['biiseista'];
$par_yleiset = $_POST['yleiset'];
$par_k18 = $_POST['k18'];

$biisi = array();

$kielletytSanat = array();
$kiellettyRiimi = "";

$tulossanoitus = $_POST['tulossanoitus'];

$dbg = "";

if (isset($_POST['tee_lyriikka'])) {
	print "<script>paivita_tulossanoitus(1, 100, '');</script>";
 	muodostaTulossanoitus($mallisanoitus);
}
else if (isset($_POST['keskeyta'])) {
	print "<script>paivita_tulossanoitus(50, 100, '$tulossanoitus');</script>";
 }

function muodostaTulossanoitus($mallisanoitus)
{
	global $tulossanoitus;
    global $biisi;
	global $kielletytSanat;

    class SanaStruct {
        public $mallisana;  // textAreasta
        public $mallisanaIsoilla;  // isot kirjaimet, skandit kuten kannassa
        public $tavujenLkm;   // tavujen lkm
        public $edeltavatMerkit;  // merkit ennen sanaa
        public $luokka;   // kannasta haettu luokka, jos ei löytynyt, tyhjä stringi
        public $tavunpituudet;  // tavujenpituuskoodi
        public $riimityyppi; // EI_RIIMIA, IDENTTINEN_RIIMI, PUHDAS_RIIMI jne.
        public $riimitavut;  // jos riimi, sen pituus tavuina, muuten 0
        public $riimisarake;  // jos riimi, monesko rivin sana se on (0..), muuten -1
        public $riimirivi;  // jos riimi, monesko rivin sana se on (0..), muuten -1
        public $samaaAlkua; // alkuriimeillä, montako samaa alkukirjainta
        public $rapVokaalit;  // mallisanaIsoilla -sanan vokaalit, tuplat poistettuna
        public $konsonantit;  // mallisanaIsoilla -sanan konsonantit
        public $tulossanaIsoilla;  // kannasta haettu tulossana isoilla
        public $tulossana;  // kannasta haettu tulossana lopullisella casella
    }

	$tavutettu = skanditTextAreasta($mallisanoitus);

	$rivit = explode("\n",$tavutettu);
	$rivienLkm = count($rivit);
	$vanhojenLkm = 0;

    $sanojenLkm = 0;

	// oma säkeist�
	for ($r=0; $r<$rivienLkm; ++$r) {  // kaikki rivit
		$rivi = $rivit[$r];
		$rivinpituus = strlen($rivi);
		if ($rivinpituus > 0) {
			$s = 0;  // sanalaskuri
			$n = 0;  // merkkilaskuri
			$sanassa = FALSE;   // ollaanko sanassa: alussa ei
			$sana = "";
			$edeltavatMerkit = "";
			while ($n < $rivinpituus) {  // rivin merkit
				$m = $rivi[$n];
				if (onkoKirjainTaiTavuviiva($m)) {
					if ($sanassa == FALSE) {
						$sanassa = TRUE;
						$sana = "";
					}
					$sana .= $m;
				}
				else {
					if ($sanassa == TRUE) {
						$sanassa = FALSE;
						analysoiSana($sana, $edeltavatMerkit, $r, $s);
                        ++$sanojenLkm;
						$sana = "";
						$edeltavatMerkit = "";
						$s++;
					}
					$edeltavatMerkit .= $m;
				}
				++$n;
			}

			if (($sana != "") || ($edeltavatMerkit != "")) {
				analysoiSana($sana, $edeltavatMerkit, $r, $s);
                ++$sanojenLkm;
				$sana = "";
				$edeltavatMerkit = "";
			}
		}
	}

	if (($sana != "") || ($edeltavatMerkit != "")) {
		analysoiSana($sana, $edeltavatMerkit, $r, $s);
        ++$sanojenLkm;
		$sana = "";
		$edeltavatMerkit = "";
	}

	analysoiRiimitys();

	$sakeittenLkm = 1;  // tämä on toistaiseksi kiinteästi 1, myöhemmin voidaan laittaa säädettäväksi
    $eteneminen = 0;
	for ($sae=0; $sae<$sakeittenLkm; ++$sae) {
		for ($r=0; $r<$rivienLkm; ++$r) {  // kaikki rivit
			for ($s=0; $s<count($biisi[$r]); $s++) {   // rivin sanat
                ++$eteneminen; 
				$p = &$biisi[$r][$s];
				if (strlen($p->mallisana) > 0) {
					debug("******** rivi: $r, sarake $s, mallisana: $p->mallisana");
					$kielletytSanat = array();
					if ($p->riimityyppi == MUUTTUMATON_SANA) {
                        $p->tulossanaIsoilla = $p->mallisanaIsoilla;
					}
					else if (($p->riimityyppi == PUHDAS_RIIMI) || ($p->riimityyppi == IDENTTINEN_RIIMI)) {
                        $p->tulossanaIsoilla = haePuhdasTaiIdenttinenRiimi($r, $s);
					}
					else if ($p->riimityyppi == SAMA_SANA) {
                        $p->tulossanaIsoilla = $biisi[$p->riimirivi][$p->riimisarake]->tulossanaIsoilla;
                    }
					else {  // RAP_VOKAALIRIIMI, KONSONANTTIRIIMI, ALKURIIMI, EI_RIIMIA
						$p->tulossanaIsoilla = haeSanastoista($r, $s, "");
					}
					$kielletytSanat = array();
					debug("******** rivi: $r, sarake $s, tulossana: $p->tulossanaIsoilla");
				}
				else {
					$p->tulossanaIsoilla = "";
				}

				$tulossanoitus = tahanastinenTulos($r, $s);
       			print "<script>paivita_tulossanoitus($eteneminen, $sanojenLkm, '$tulossanoitus');</script>";
			}
			$tulossanoitus = tahanastinenTulos($r, $s) . "<rivinvaihto>";
        	print "<script>paivita_tulossanoitus($eteneminen, $sanojenLkm, '$tulossanoitus');</script>";
		}

        // tulosta biisi-taulu
        for ($r=0; $r<$rivienLkm; ++$r) {  // kaikki rivit
            for ($s=0; $s<count($biisi[$r]); $s++) {   // rivin sanat
                $p = &$biisi[$r][$s];
                debug("rivi: $r, sarake: $s");
                debug("mallisana: $p->mallisana");  // textAreasta
                debug("mallisanaIsoilla: $p->mallisanaIsoilla");  // isot kirjaimet, skandit kuten kannassa
                debug("tavujenLkm: $p->tavujenLkm");   // tavujen lkm
                debug("edeltavatMerkit: $p->edeltavatMerkit");  // merkit ennen sanaa
                debug("luokka: $p->luokka");   // kannasta haettu luokka, jos ei löytynyt, tyhjä stringi
                debug("tavunpituudet: $p->tavunpituudet");  // tavujenpituuskoodi

				switch ($p->riimityyppi) {
					case EI_RIIMIA:
						debug("riimityyppi: EI_RIIMIA");
						break;
					case IDENTTINEN_RIIMI:
						debug("riimityyppi: IDENTTINEN_RIIMI");
						break;
					case PUHDAS_RIIMI:
						debug("riimityyppi: PUHDAS_RIIMI");
						break;
					case RAP_VOKAALIRIIMI:
						debug("riimityyppi: RAP_VOKAALIRIIMI");
						break;
					case KONSONANTTIRIIMI:
						debug("riimityyppi: KONSONANTTIRIIMI");
						break;
					case ALKURIIMI:
						debug("riimityyppi: ALKURIIMI");
						break;
					case SAMA_SANA:
						debug("riimityyppi: SAMA_SANA");
						break;
					case MUUTTUMATON_SANA:
						debug("riimityyppi: MUUTTUMATON_SANA");
						break;
				}

                debug("riimitavut: $p->riimitavut");  // jos riimi, sen pituus tavuina, muuten 0
                debug("riimisarake: $p->riimisarake");  // jos riimi, monesko rivin sana se on (0..), muuten -1
                debug("riimirivi: $p->riimirivi");  // jos riimi, monesko rivin sana se on (0..), muuten -1
                debug("samaaAlkua: $p->samaaAlkua"); // alkuriimeillä, montako samaa alkukirjainta
                debug("rapVokaalit: $p->rapVokaalit");  // mallisanaIsoilla -sanan vokaalit, tuplat poistettuna
                debug("konsonantit: $p->konsonantit");  // mallisanaIsoilla -sanan konsonantit
                debug("tulossanaIsoilla: $p->tulossanaIsoilla");  // kannasta haettu tulossana isoilla
                debug("tulossana: $p->tulossana\n");  // kannasta haettu tulossana lopullisella casella
            }
        }
	}
}

function tahanastinenTulos($rr, $ss)
{
	global $biisi;

	$tulossanoitus = "";
	for ($r=0; $r<=$rr; ++$r) {
		for ($s=0; $s<count($biisi[$r]); $s++) {
			if (($r<$rr) || ($s<=$ss)) {
				$p = &$biisi[$r][$s];

				if (onkoIsoKirjain($p->mallisana[0])) {  // eka kirjain iso
					if (onkoIsoKirjain($p->mallisana[1])) {  // myös toka kirjain iso: anna olla isona
						$p->tulossana = $p->tulossanaIsoilla;
					}
					else {
						$p->tulossana = pieniksiSkandeineen($p->tulossanaIsoilla);  // laita pieneksi
						$p->tulossana[0] = isoiksiSkandeineen($p->tulossanaIsoilla[0]);  // laita eka silti isoksi
					}
				}
				else {
					$p->tulossana = pieniksiSkandeineen($p->tulossanaIsoilla);  // laita pieneksi
				}


				$tulossana = skanditTextAreaan($p->tulossana);
				$tulossana = str_replace("-","",$tulossana);
				$tulossana = str_replace("_","",$tulossana);

				$tulossanoitus .= $p->edeltavatMerkit;
				$tulossanoitus .= $tulossana;
			}
		}
		$tulossanoitus .= "<rivinvaihto>";
	}

	$tulossanoitus = str_replace("\r", "", $tulossanoitus);
	$tulossanoitus = str_replace("\n", "<rivinvaihto>", $tulossanoitus);
	$tulossanoitus = str_replace("'", "<heittomerkki>", $tulossanoitus);
	return $tulossanoitus;
}

function analysoiSana($sana, $edeltavatMerkit, $r, $s)
{
    global $biisi;

	$biisi[$r][$s] = new SanaStruct();
	$p = &$biisi[$r][$s];
	$p->mallisana = $sana;
	$p->mallisanaIsoilla = isoiksiSkandeineen($sana);
	$p->edeltavatMerkit = $edeltavatMerkit;
	$p->luokka = "";
	if ($sana == "") {
		$p->riimityyppi = EI_SANA;
	}
	else {
		$p->riimityyppi = EI_RIIMIA;
	}
	$p->riimitavut = 0;
	$p->riimirivi = -1;
	$p->riimisarake = -1;
	$p->samaaAlkua = 0;
	$p->rapVokaalit = "";
	$p->konsonantit = "";

	$mallisanaIsoilla = str_replace("_", "-", $p->mallisanaIsoilla);
	$p->tavunpituudet = laskeTavunpituudet($mallisanaIsoilla);

	$tavujenLkm = count(explode("-", $mallisanaIsoilla));
	$p->tavujenLkm = $tavujenLkm;

	if (strlen($sana) > 0) {
		$tavutonsana = str_replace("-", "", $mallisanaIsoilla);

		// tutki sanan luokka
		if ($tavujenLkm <= 6) {
			$sql = "select luokka from lr_sanasto$tavujenLkm where tavutonsana='$tavutonsana'";
		}
		else {
			$sql = "select luokka from lr_sanastoX where tavuja=$tavujenLkm and tavutonsana='$tavutonsana'";
		}
		if ($kysely = mysqlQuery($sql))
		{
			$luettu = mysql_fetch_row($kysely);
			if ($luettu) {
				$p->luokka = $luettu[0];
			}
			else {  // ehkä kannan sanastossa seonkin useampitavuinen
				$tavujenLkm++;
				if ($tavujenLkm <= 6) {
					$sql = "select luokka from lr_sanasto$tavujenLkm where tavutonsana='$tavutonsana'";
				}
				else {
					$sql = "select luokka from lr_sanastoX where tavuja=$tavujenLkm and tavutonsana='$tavutonsana'";
				}
				if ($kysely = mysqlQuery($sql))
				{
					$luettu = mysql_fetch_row($kysely);
					if ($luettu) {
						$p->luokka = $luettu[0];
					}
				}
			}
		}
	}
}

function analysoiRiimitys()
{
    global $biisi;

	// jos näitä sanoja löytyy, ne jätetään koskemattomiksi
	$alkup = array("MUN","SUN","ME","TE","MÄ","SÄ","EI","EN","ET","JA","JO","JOS","KAI","KUN","MYÖS","TAI","ON","SE","SEN","NYT");

	// jos näin alkavia sanoja löytyy, ne jätetään koskemattomiksi
	$alkupAlut = array("MI-NÄ","SI-NÄ","MI-NU","SI-NU");

	// merkkaa jos pidetään alkuperöiset sanat. Laske valmiiksi sanojen rapVokaalit ja konsonantit
	for ($r=0; $r<count($biisi); ++$r) {  // kaikki rivit
		for ($s=0; $s<count($biisi[$r]); ++$s) {  // kaikki rivin sanat
			$p = &$biisi[$r][$s];
			if ($p->riimityyppi == EI_RIIMIA) {
				for ($r2=0; $r2<=$r; ++$r2) {  // kaikki rivit tähän asti
					for ($s2=0; $s2<count($biisi[$r2]); ++$s2) {  // kaikki rivin sanat
						if (($r2<$r) || ($s2<$s)) {  // tällä rivillä vain tätä sanaa aiemmat sanat
							if ($p->mallisanaIsoilla == $biisi[$r2][$s2]->mallisanaIsoilla) {
								$p->riimityyppi = SAMA_SANA;
								$p->riimirivi = $r2;
								$p->riimisarake = $s2;
								break;
							}
						}
					}
					if ($p->riimityyppi == SAMA_SANA) {
						break;
					}
				}

				$p->rapVokaalit = rapVokaalit($p->mallisanaIsoilla);
				$p->konsonantit = konsonantit($p->mallisanaIsoilla);
			}
		}
	}

	for ($r=0; $r<count($biisi); ++$r) {  // kaikki rivit
		$rivi = $biisi[$r];

		// tutki loppuriimit
		$s1 = count($rivi)-1;  // viimeinen sana rivillä
		if ($rivi[$s1]->mallisanaIsoilla == "") {  // viimeinen sana onkin erikoismerkkejä
			--$s1;
		}
		$sinfo1 = $rivi[$s1];

		// tutki rivien loppujen puhtaat loppuriimit
		if (($sinfo1->riimityyppi == EI_RIIMIÄ) && ($r > 0)) {  // toisesta rivistä lähtien
			$maxRiimitavut = 0;
			for ($v=max(0, $r-1); $v>=max(0, $r-4); --$v) {  // tutki neljä edellistä riviä
				$rivi2 = $biisi[$v];
				$s2 = count($rivi2)-1;  // viimeinen sana rivillä
				if ($rivi2[$s2]->mallisanaIsoilla == "") {  // viimeinen sana onkin erikoismerkkejä
					--$s2;
				}
				$sinfo2 = $rivi2[$s2];

				$riimitavut = laskePuhtaatLoppuriimitavut($sinfo1->mallisanaIsoilla, $sinfo2->mallisanaIsoilla);
				if ($riimitavut > $maxRiimitavut) {
					$maxRiimitavut = $riimitavut;
					$biisi[$r][$s1]->riimityyppi = PUHDAS_RIIMI;
					$biisi[$r][$s1]->riimirivi = $v;
					$biisi[$r][$s1]->riimisarake = $s2;
					$biisi[$r][$s1]->riimitavut = $maxRiimitavut;
				}
			}
		}

		// tutki rivien loppujen identtiset loppuriimit
		if (($sinfo1->riimityyppi == EI_RIIMIÄ) && ($r > 0)) {  // toisesta rivistä lähtien
			$maxRiimitavut = 0;
			for ($v=max(0, $r-1); $v>=max(0, $r-4); --$v) {  // tutki neljä edellistä riviä
				$rivi2 = $biisi[$v];
				$s2 = count($rivi2)-1;  // viimeinen sana rivillä
				if ($rivi2[$s2]->mallisanaIsoilla == "") {  // viimeinen sana onkin erikoismerkkejä
					--$s2;
				}
				$sinfo2 = $rivi2[$s2];

				$riimitavut = laskeIdenttisetLoppuriimitavut($sinfo1->mallisanaIsoilla, $sinfo2->mallisanaIsoilla);
				if ($riimitavut > $maxRiimitavut) {
					$maxRiimitavut = $riimitavut;
					$biisi[$r][$s1]->riimityyppi = IDENTTINEN_RIIMI;
					$biisi[$r][$s1]->riimirivi = $v;
					$biisi[$r][$s1]->riimisarake = $s2;
					$biisi[$r][$s1]->riimitavut = $maxRiimitavut;
				}
			}
		}

		// tutki samalla rivillä olevat puhtaat loppuriimit
		if ($sinfo1->riimityyppi == EI_RIIMIÄ) {  // toisesta rivistä lähtien
			$maxRiimitavut = 0;
			for ($s=$s1-1; $s>=0; --$s) {  // kaikki rivin edelliset sanat
				$riimitavut = laskePuhtaatLoppuriimitavut($sinfo1->mallisanaIsoilla, $rivi[$s]->mallisanaIsoilla);
				if ($riimitavut > $maxRiimitavut) {
					$maxRiimitavut = $riimitavut;
					$biisi[$r][$s1]->riimityyppi = PUHDAS_RIIMI;
					$biisi[$r][$s1]->riimirivi = $r;
					$biisi[$r][$s1]->riimisarake = $s;
					$biisi[$r][$s1]->riimitavut = $maxRiimitavut;
				}
			}
		}

		// tutki samalla rivillä olevat identtiset loppuriimit
		if ($sinfo1->riimityyppi == EI_RIIMIÄ) {  // toisesta rivistä lähtien
			$maxRiimitavut = 0;
			for ($s=$s1-1; $s>=0; --$s) {  // kaikki rivin edelliset sanat
				$riimitavut = laskeIdenttisetLoppuriimitavut($sinfo1->mallisanaIsoilla, $rivi[$s]->mallisanaIsoilla);
				if ($riimitavut > $maxRiimitavut) {
					$maxRiimitavut = $riimitavut;
					$biisi[$r][$s1]->riimityyppi = IDENTTINEN_RIIMI;
					$biisi[$r][$s1]->riimirivi = $r;
					$biisi[$r][$s1]->riimisarake = $s;
					$biisi[$r][$s1]->riimitavut = $maxRiimitavut;
				}
			}
		}

	}


	for ($r=0; $r<count($biisi); ++$r) {  // kaikki rivit
		$rivi = $biisi[$r];
	    for ($s=1; $s<count($rivi); ++$s) {  // kaikki rivin sanat, paitsi eka
			$p = &$biisi[$r][$s];
			if ($p->riimityyppi == EI_RIIMIA) {
				// tutki vokaaliriimit
				for ($v=max(0, $s-1); $v>=max(0, $s-4); --$v) {  // tutki kolme edellistä sanaa
					if ($p->rapVokaalit == $rivi[$v]->rapVokaalit) {
						$p->riimityyppi = RAP_VOKAALIRIIMI;
						$p->riimirivi = $r;
						$p->riimisarake = $v;
						break;
					}
				}
			}

			if ($p->riimityyppi == EI_RIIMIA) {
				// tutki konsonanttiriimit
				for ($v=max(0, $s-1); $v>=max(0, $s-4); --$v) {  // tutki kolme edellistä sanaa
					if ($p->konsonantit == $rivi[$v]->konsonantit) {
						$p->riimityyppi = KONSONANTTIRIIMI;
						$p->riimirivi = $r;
						$p->riimisarake = $v;
						break;
					}
				}
			}

			if ($p->riimityyppi == EI_RIIMIA) {
				// tutki onko samaa alkua
				$pisinSamaaAlkua = 0;
				$pisinSamanAlunSarake = -1;
				for ($v=max(0, $s-1); $v>=max(0, $s-4); --$v) {  // tutki kolme edellistä sanaa
					$samaaAlkua = 0;
					for ($n=0; ($n<strlen($p->mallisanaIsoilla)) && ($n<strlen($rivi[$v]->mallisanaIsoilla)); ++$n) {
						if ($p->mallisanaIsoilla[$n] == $rivi[$v]->mallisanaIsoilla[$n]) {
							if (($p->mallisanaIsoilla[$n] != "-") && ($p->mallisanaIsoilla[$n] != "_")) {
								// koska alkuriimiä tutkitaan kentästä "tavutonsana", tavuviivoja ja sanaerottimia ei huomioida
								++$samaaAlkua;
							}
						}
						else {
							break;  // erilainen kirjain löytyi
						}
					}

					if ($samaaAlkua > $pisinSamaaAlkua) {
						$pisinSamaaAlkua = $samaaAlkua;
						$pisinSamanAlunSarake = $v;;
					}
				}
				if ($pisinSamaaAlkua > 0) {
					$p->riimityyppi = ALKURIIMI;
					$p->riimirivi = $r;
					$p->riimisarake = $pisinSamanAlunSarake;
					$p->samaaAlkua = $pisinSamaaAlkua;
				}
            }
        }
    }

	for ($r=0; $r<count($biisi); ++$r) {  // kaikki rivit
		for ($s=0; $s<count($biisi[$r]); ++$s) {  // kaikki rivin sanat
			$p = &$biisi[$r][$s];
			if ($p->riimityyppi == EI_RIIMIÄ) {
				foreach ($alkup as $sana) {
					if ($p->mallisanaIsoilla == skanditTextAreasta($sana)) {
						$p->riimityyppi = MUUTTUMATON_SANA;
						break;
					}
				}
			}
			if ($p->riimityyppi == EI_RIIMIÄ) {
				foreach ($alkupAlut as $alku) {
					if (strpos($p->mallisanaIsoilla, skanditTextAreasta($alku)) === 0) {
						$p->riimityyppi = MUUTTUMATON_SANA;
						break;
					}
				}
			}
		}
	}
}

function rapVokaalit($sana)
{
    $rapVokaalit = "";
    $edellinenVokaali = "";
    for ($n=0; $n<strlen($sana); ++$n) {
        $m = $sana[$n];
        if (onkoVokaali($m)) {
            if ($m != $edellinenVokaali) {
                $rapVokaalit .= $m;
                $edellinenVokaali = $m;
            } 
        }
    }

    return $rapVokaalit;
}

function konsonantit($sana)
{
    $konsonantit = "";
    for ($n=0; $n<strlen($sana); ++$n) {
        if (!onkoVokaali($sana[$n]) && ($sana[$n] != "-") && ($sana[$n] != "_")) {
            $konsonantit .= $sana[$n];
        }
    }

    return $konsonantit;
}

function laskePuhtaatLoppuriimitavut($sana1, $sana2)
{
	$sana1 = str_replace("_", "-", $sana1);
	$tavut1 = explode("-", $sana1);

	$sana2 = str_replace("_", "-", $sana2);
	$tavut2 = explode("-", $sana2);

	$n1 = count($tavut1)-1;
	$n2 = count($tavut2)-1;

	if (endsWith($tavut1[$n1],"N")) {  // poistetaan loppu-N, jotta lavennetutkin riimit luokiteltaisiin puhtaiksi
		$tavut1[$n1] = substr($tavut1[$n1],0,strlen($tavut1[$n1])-1);
	}

	if (endsWith($tavut2[$n2],"N")) {  // poistetaan loppu-N, jotta lavennetutkin riimit luokiteltaisiin puhtaiksi
		$tavut2[$n2] = substr($tavut2[$n2],0,strlen($tavut2[$n2])-1);
	}

	$riimitavut = 0;
	$rimmaava = FALSE;
	while (TRUE) {
		if ($tavut1[$n1] == $tavut2[$n2]) {  // identtiset tavut
			++$riimitavut;
			if (--$n1 < 0) {
				break; // sana loppui
			}
			if (--$n2 < 0) {
				break; // sana loppui
			}
		}
		else if (rimmaakoTavut($tavut1[$n1], $tavut2[$n2])) {
			$rimmaava = TRUE;
			++$riimitavut;
			break;
		}
		else {
			break; // riimi loppui
		}
	}

	if ($rimmaava == FALSE) {
		$riimitavut = 0;  // vaikka olisi identtisiä tavuja, ei kelpaa
	}

	return $riimitavut;
}

function laskeIdenttisetLoppuriimitavut($sana1, $sana2)
{
	$sana1 = str_replace("_", "-", $sana1);
	$tavut1 = explode("-", $sana1);

	$sana2 = str_replace("_", "-", $sana2);
	$tavut2 = explode("-", $sana2);

	$n1 = count($tavut1)-1;
	$n2 = count($tavut2)-1;

	$riimitavut = 0;
	while (TRUE) {
		if ($tavut1[$n1] == $tavut2[$n2]) {
			++$riimitavut;
			if (--$n1 < 0) {
				break; // sana loppui
			}
			if (--$n2 < 0) {
				break; // sana loppui
			}
		}
		else {
			break; // riimi loppui
		}
	}

	return $riimitavut;
}

function rimmaakoTavut($tavu1, $tavu2)
{
	debugR("rimmaakoTavut1: $tavu1, $tavu2: ");

	$n1 = strlen($tavu1)-1;
	$n2 = strlen($tavu2)-1;

	$vokaaliEsiintynyt = FALSE;

	while (TRUE) {

		if ($tavu1[$n1] != $tavu2[$n2]) {
			if (onkoVokaali($tavu1[$n1]) || onkoVokaali($tavu2[$n2])) {  // eri kirjaimet, ainakin toinen vokaali
				debug("EI");
				return FALSE;  // ei riimitavu
			}
			else {  // kumpikin konsonantti
				if ($vokaaliEsiintynyt) {
					debug("KYLLÄ");
					return TRUE; // puhdas riimi
				}
				else {
					debug("EI");
					return FALSE;  // sanan lopun konsonantit erilaisia: ei riimi
				}
			}
		}
		else {  // samat kirjaimet: jatka taaksepäin
			if (onkoVokaali($tavu1[$n1])) {
				$vokaaliEsiintynyt = TRUE;
			}
			--$n1;
			--$n2;
			if ($n1 < 0) {  // eka tavu käyty läpi
				if ($n2 < 0) {
					debug("EI");
					return FALSE;  // identinen tavu: ei rimmaa
				}
				else {
					if (onkoVokaali($tavu2[$n2])) {
						debug("EI");
						return FALSE;
					}
					else {
						debug("KYLLÄ");
						return TRUE;
					}
				}
			}
			else if ($n2 < 0) {  // toka tavu käyty läpi
				if (onkoVokaali($tavu1[$n1])) {
					debug("EI");
					return FALSE;
				}
				else {
					return TRUE;
					debug("KYLLÄ");
				}
			}
		}
	}

	return FALSE;  // virhe jos tultiin tänne
}

function haePuhdasTaiIdenttinenRiimi($r, $s)
{
    global $biisi;
    
    $p = &$biisi[$r][$s];

	$toinenPaa = &$biisi[$p->riimirivi][$p->riimisarake];
	if ($toinenPaa->riimityyppi == $p->riimityyppi) {  // siis PUHDAS_RIIMI tai IDENTTINEN_RIIMI
		// kolminkertainen riimi, merkitse eka riimisana kielletyksi
		$k = skanditTextAreasta($biisi[$toinenPaa->riimirivi][$toinenPaa->riimisarake]->tulossanaIsoilla);
 		array_push($kielletytSanat, $k);					
	}

    $sana = "";
    $tulossanaIsoilla = $toinenPaa->tulossanaIsoilla;
    $riimi = skanditTextAreasta($tulossanaIsoilla);
    $sana = haeSanastoista($r, $s, $riimi);
    if ((strlen($sana) == 0) &&  ($toinenPaa->riimityyppi != SAMA_SANA)) {
        // ei löytynyt, kokeillaan vaihtaa toisen pään sanaa monta kertaa
        $ok = FALSE;
 		array_push($kielletytSanat, $tulossanaIsoilla);					
        for ($t=0; $t<10; $t++) {
			debug("haePuhdasTaiIdenttinenRiimi, vaihda toisen pään sana, $t+1. kerta");
            $toisenPaanSana = haeSanastoista($p->riimirivi, $p->riimisarake, "");
            if (strlen($toisenPaanSana) > 0) {  // toinen pää vaihdettu
                $toinenPaa->tulossanaIsoilla = $toisenPaanSana;
                $riimi = skanditTextAreasta($toisenPaanSana);
                $sana = haeSanastoista($r, $s, $riimi);
                if (strlen($sana) > 0) {  // onnistui !
                    $ok = TRUE;
                    break;
                }
				else {
					array_push($kielletytSanat, skanditTextAreasta($toisenPaanSana));					
				}
            }
        }
        if (($ok == FALSE) && ($p->riimitavut >= 3)) {
            $p->riimitavut = $p->riimitavut - 2;  // vähennetään riimin pituutta kahdella tavulla
            // kokeillaan vielä vaihtaa toisen pään sanaa monta kertaa
            $ok = FALSE;
            for ($t=0; $t<10; $t++) {
				debug("haePuhdasTaiIdenttinenRiimi, riimiä lyhennetty, vaihda toisen pään sana, $t+1. kerta");
                $toisenPaanSana = haeSanastoista($p->riimirivi, $p->riimisarake, "");
                if (strlen($toisenPaanSana) > 0) {  // toinen pää vaihdettu
                    $toinenPaa->tulossanaIsoilla = $toisenPaanSana;
                    $riimi = skanditTextAreasta($toisenPaanSana);
                    $sana = haeSanastoista($r, $s, $riimi);
                    if (strlen($sana) > 0) {  // onnistui !
                        $ok = TRUE;
                        break;
                    }
					else {
						array_push($kielletytSanat, skanditTextAreasta($toisenPaanSana));					
					}
                }
            }
        }
        if ($ok == FALSE) {  // ei onnistu: luovutaan riimistä
            $p->riimitavut = 0;
            $sana = haeSanastoista($r, $s, "");
        }
    }

    return $sana;
}

function haeSanastoista($r, $s, $riimi)
{
    global $par_k18;
    global $par_biiseista;
	global $par_yleiset;
    global $biisi;

	debug("-> hae_sanastoista, riimi: $riimi");

    $sana = "";
    $tavujenLkm = $biisi[$r][$s]->tavujenLkm;
    if ($par_k18 && ($tavujenLkm >= 2) && ($tavujenLkm <= 4)) {
		debug("-> hae_sanastoista, K18");
        $sana = haeYhdestaSanastosta($r, $s, $riimi, K18_SANASTO);
    }
    if (($sana == "") && $par_biiseista && ($tavujenLkm >= 2) && ($tavujenLkm <= 5)) {
		debug("-> hae_sanastoista, BIISEISTA");
        $sana = haeYhdestaSanastosta($r, $s, $riimi, BIISEISTA_SANASTO);
    }
    if (($sana == "") && $par_yleiset && ($tavujenLkm <= 3)) {
		debug("-> hae_sanastoista, YLEISET");
		$sana = haeYhdestaSanastosta($r, $s, $riimi, YLEISET_SANASTO);
	}
	if ($sana == "") {
		debug("-> hae_sanastoista, LAAJIN");
		$sana = haeYhdestaSanastosta($r, $s, $riimi, LAAJIN_SANASTO);
	}

    return $sana;
}

function haeYhdestaSanastosta($r, $s, $riimi, $sanasto)
{
    global $biisi;
	global $kielletytSanat;
	global $kiellettyRiimi;

	$p = &$biisi[$r][$s];
	$tavujenLkm = $p->tavujenLkm;
	$luokka = $p->luokka;
	$riimityyppi = $p->riimityyppi;
    $riiminToinenPaa = skanditTextAreasta($biisi[$p->riimirivi][$p->riimisarake]->tulossanaIsoilla);
	$hakusana = "";

    $selectAlku = "";

	$taulu = "";
	$tavumaaraehto = "";

    if ($sanasto == LAAJIN_SANASTO) {
        if ($tavujenLkm <= 6) {
            $taulu = "lr_sanasto$tavujenLkm";
        }
        else {
            $taulu = "lr_sanastoX";
			$tavumaaraehto = " and tavuja=$tavujenLkm";
        }
    }
    else if ($sanasto == K18_SANASTO) {
        $taulu = "lr_sanasto_k18";
		$tavumaaraehto = " and tavuja=$tavujenLkm";
    }
    else if ($sanasto == BIISEISTA_SANASTO) {
        $taulu = "lr_sanasto_biiseista";
		$tavumaaraehto = " and tavuja=$tavujenLkm";
    }
    else {
        $taulu = "lr_sanasto_yleiset";
 		$tavumaaraehto = " and tavuja=$tavujenLkm";
   }

	$selectAlku = "select sana from $taulu where";

	$tavunpituusehto = " and tavunpituudet=$p->tavunpituudet";

    $crcehto = "";
    if (strlen($luokka) > 0) {
		$str = $tavujenLkm . " " . $p->tavunpituudet . " " . $luokka;
		$crc = sprintf("%u", crc32($str));
		$crcehto = " and crc=$crc";
	}

    $riimiehto = "";
	$kiellettyRiimi = "";
    if ($riimityyppi == PUHDAS_RIIMI) {
        if (strlen($riimi) > 0) {
			$hakusana = strrev(teePuhtaanRiiminHakusana($riimi, $p->riimitavut));  // AS-SI%
			$riimiehto = " and takaperin like '$hakusana'";
			$len = strlen($riimi)-strlen($hakusana);
			if ($len >= 0) {
				$kiellettyRiimi = substr($riimi, strlen($riimi)-strlen($hakusana));  // KIS-SA
			}
		}
    }
    else if ($riimityyppi == IDENTTINEN_RIIMI) {
        if (strlen($riimi) > 0) {
            $hakusana = strrev(teeIdenttisenRiiminHakusana($riimi, $p->riimitavut));  // AS-SIK%
            $riimiehto = " and takaperin like '$hakusana'";
        }
    }
    else if ($riimityyppi == RAP_VOKAALIRIIMI) {
        $riimiehto = " and tavutonsana like '%";
        $rapVokaalit = rapVokaalit($riiminToinenPaa);
        for ($n=0; $n<strlen($rapVokaalit); $n++) {
            $riimiehto .= "$rapVokaalit[$n]%";
        }
        $riimiehto .= "'";
    }
    else if ($riimityyppi == KONSONANTTIRIIMI) {
        $riimiehto = " and tavutonsana like '%";
        $konsonantit = konsonantit($riiminToinenPaa);
        for ($n=0; $n<strlen($konsonantit); $n++) {
            $riimiehto .= "$konsonantit[$n]%";
        }
        $riimiehto .= "'";
    }    
    else if ($riimityyppi == ALKURIIMI) {
        $samaaAlkua = substr($riiminToinenPaa, 0, $p->samaaAlkua);
		$samaaAlkua = str_replace("-","",$samaaAlkua);
		$samaaAlkua = str_replace("_","",$samaaAlkua);
        $riimiehto = " and tavutonsana like '$samaaAlkua%'";
    }

    $limit = " limit 500";

	if (($riimityyppi != SAMA_SANA) && ($riimityyppi != MUUTTUMATON_SANA)) {
		for ($r2=0; $r2<=$r; ++$r2) {  // kaikki rivit tähän asti
			for ($s2=0; $s2<count($biisi[$r2]); ++$s2) {  // kaikki rivin sanat
				if (($r2<$r) || ($s2<$s)) {  // tällä rivillä vain tätä sanaa aiemmat sanat
					array_push($kielletytSanat, skanditTextAreasta($biisi[$r2][$s2]->tulossanaIsoilla));
				}
			}
		}
	}
	else {
		array_push($kielletytSanat, skanditTextAreasta($riiminToinenPaa));
	}

	array_push($kielletytSanat, skanditTextAreasta($p->mallisanaIsoilla));	

	$kielletytSanat = array_unique($kielletytSanat);				
    
	$sana = "";
	
    if ($crcehto != "") {
    	$sql1 = $selectAlku . $crcehto . $riimiehto . $limit;
		$sana = arvoSana($sql1, $sanasto, $p, $riiminToinenPaa, FALSE);
	}

    if ($sana == "") {  // ehkä tuota luokkaa ei löytynyt, etsitään ilman sitä
		if (($riimiehto == "") || ($riimityyppi == PUHDAS_RIIMI)) {
			$sql2 = $selectAlku . $tavumaaraehto . $tavunpituusehto . $riimiehto . $limit;
			$sana = arvoSana($sql2, $sanasto, $p, $riiminToinenPaa, FALSE);
		}
		else if ($sanasto == LAAJIN_SANASTO) {
    		$sql2 = $selectAlku . $crcehto . $limit;
			$sana = arvoSana($sql2, $sanasto, $p, $riiminToinenPaa, FALSE);
		}
	}

	if (($sana == "") && ($sanasto == LAAJIN_SANASTO)) {
		if ($sana == "") {
			$sql3 = $selectAlku . $tavumaaraehto . $riimiehto . $limit;
			$sana = arvoSana($sql3, $sanasto, $p, $riiminToinenPaa, FALSE);  // etsitään ilman tavunpituusehtoa
		}

		if ($sana == "") {
			$sql4 = $selectAlku . $crcehto . $limit;
			$sana = arvoSana($sql4, $sanasto, $p, $riiminToinenPaa, FALSE);  // jos ei riimiä löydy, pelkkä luokkaehto
		}

		if ($sana == "") {
			$sql5 = $selectAlku . $tavumaaraehto . $limit;
			$sana = arvoSana($sql5, $sanasto, $p, $riiminToinenPaa, FALSE);  // jos ei riimiä löydy, pelkkä luokkaehto
		}
	}

	return $sana;
}

function teePuhtaanRiiminHakusana($riimi, $riimitavut)
{
	$riimi = str_replace("_", "-", $riimi);
	$tavut = explode("-", $riimi);
	$eka = count($tavut)-$riimitavut;
	$vika = count($tavut)-1;
	$hakusana = "%";

	// etsi ensimmäisen riimitavun loppuosa, ekasta vokaalista eteenpäin
	for ($n=0; $n<strlen($tavut[$eka]); $n++) {
		if (onkoVokaali($tavut[$eka][$n])) {
			$hakusana .= substr($tavut[$eka], $n);
			break;
		}
		else {
		}
	}
	for ($t=$eka+1; $t<=$vika; $t++) {
		$hakusana .= "-" . $tavut[$t];
	}

	return $hakusana;
}

function teeIdenttisenRiiminHakusana($riimi, $riimitavut)
{
	$riimi = str_replace("_", "-", $riimi);
	$tavut = explode("-", $riimi);
	$eka = count($tavut)-$riimitavut;
	$vika = count($tavut)-1;
	$hakusana = "%";

	if ($eka == $vika) {
		$hakusana .= $tavut[$eka];
	}
	else {
		for ($t=$eka; $t<=$vika; $t++) {
			$hakusana .= "-" . $tavut[$t];
		}
	}

	return $hakusana;
}

function arvoSana($sql, $sanasto, $p, $riiminToinenPaa, $palautaJokuSana)
{
	global $kielletytSanat;

	$sql = str_replace("where and", "where", $sql);
	$sql = str_replace("where limit", "limit", $sql);

	$sana = "";
	$ekaSanaJemma = "";
	if ($kysely = mysqlQuery($sql))
	{
		$osumat = mysql_num_rows($kysely);
		debug("osumia: $osumat");
		if ($osumat > 0)  {
			$kokeillut = array();
			// osumia pitää olla > 3, paitsi jos laajin sanasto ja ei ole luokkahakua
            if (($osumat > 3) || ($sanasto == LAAJIN_SANASTO)) {
				debug("arvotaan!");
                $ids = array();
                for ($n=0; $n<$osumat; $n++) {
                    $luettu = mysql_fetch_row($kysely);
                    if ($luettu) {
                        array_push($ids, utf8_encode($luettu[0]));
                    }
                    else {
                        break;  // oltava virhetilanne
                    }
                }
                while (TRUE) {
                    $valittu = rand(0, $osumat-1);
					if (!in_array($valittu, $kokeillut)) {
						$sana = $ids[$valittu];
						if (strlen($sana) > 0) {
							if (($palautaJokuSana == TRUE) && ($ekaSanaJemma == "")) {
								$ekaSanaJemma = $sana;
							}
							$sana2 = skanditTextAreasta($sana);

							if (($kiellettyRiimi != "") && endsWith($sana2, $kiellettyRiimi)) {
								debug("arvoSana: ******** KIELLETTY RIIMI, sana: $sana, riimi: $kiellettyRiimi");
								$sana = "";
							}
							else if (in_array($sana2, $kielletytSanat)) {
								debug("arvoSana: ******** KIELLETTY SANA, sana: $sana");
								$sana = "";
							}
							else if ($p->riimityyppi == PUHDAS_RIIMI) {
								$riimitavut = laskePuhtaatLoppuriimitavut($sana2, $riiminToinenPaa);
								if ($riimitavut != $p->riimitavut) {
									debug("arvoSana: ******** EI PUHDAS_RIIMI, sana: $sana");
									$sana = "";
								}
								else {
									break;
								}
							}
							else if ($p->riimityyppi == IDENTTINEN_RIIMI) {
								$riimitavut = laskeIdenttisetLoppuriimitavut($sana2, $riiminToinenPaa);
								if ($riimitavut != $p->riimitavut) {
									debug("arvoSana: ******** EI IDENTTINEN RIIMI, sana: $sana");
									$sana = "";
								}
								else {
									break;
								}
							}
							else {
								break;
							}
						}
						array_push($kokeillut, $valittu);
						if (count($kokeillut) >= $osumat) {
							$sana = "";
							break;   // kaikki osumat on kokeiltu, ei löytynyt!
						}
					}
                }
            }
		}
	}

	if ($sana != "") {
 		debug("arvoSana: löytyi: $sana");
	}

	return $sana;
}

function haeSanaKannasta($tavujenLkm, $id, $sanasto)
{
    if ($sanasto == LAAJIN_SANASTO) {
        if ($tavujenLkm <= 6) {
            $sql = "select sana from lr_sanasto$tavujenLkm where id=$id";
        }
        else {
            $sql = "select sana from lr_sanastoX where id=$id";
        }
    }
    else if ($sanasto == K18_SANASTO) {
        $sql = "select sana from lr_sanasto_k18 where id=$id";
    }
    else if ($sanasto == BIISEISTA_SANASTO) {
        $sql = "select sana from lr_sanasto_biiseista where id=$id";
    }
    else {
        $sql = "select sana from lr_sanasto_yleiset where id=$id";
    }

	if ($kysely = mysqlQuery($sql))
	{
		$luettu = mysql_fetch_row($kysely);
		$sana = utf8_encode($luettu[0]);

        // if (sisaltaakoNaitaMerkkeja($sana, "BCFQWXZ")) {
        //     return "";  // ei hyväksytä sanoja joissa on vierasperäisiä kirjaimia
        // }
        // else {
        //     return $sana;
        // }
 		return $sana;
	}
	else {
		return "";
	}
}

function mysqlQuery($sql)
{
    global $yhteys;

	$alkuaika = time();
	if ($kysely = mysql_query($sql,$yhteys)) {
	}
	// else {
	// 	debug("mysql_query failed");
	// }
	$kesto = time() - $alkuaika;
	debug("mysql_query: $sql ($kesto sekuntia)");

	return $kysely;
}
?>
