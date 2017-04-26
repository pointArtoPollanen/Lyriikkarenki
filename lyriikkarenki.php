<!DOCTYPE html>
<html>
<title>LYRIIKKARENKI</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="http://www.w3schools.com/lib/w3.css">
<link rel="stylesheet" href="http://www.w3schools.com/lib/w3-theme-blue-grey.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.min.css">

<body>

<!-- Header -->
<header class="w3-container w3-theme w3-padding" id="myHeader">
  <div class="w3-center">
    <h4>Sanoittajan fuusiogeneraattori</h4>
    <h1 class="w3-animate-bottom">LYRIIKKARENKI</h1>
  </div>
</header>

<button onclick="naytaOhje()" class="w3-button w3-block w3-left-align w3-theme">
Käyttöohje</button>

<div id="ohje" class="w3-hide w3-container w3-left-align">
  <p>Sovellus muodostaa sanoituksen, joka rytmisesti, äänneasultaan, riimitykseltään ja monesti myös sanaluokiltaan ja taivutusmuodoiltaan muistuttaa annettua mallisanoitusta.<br><br>
  1. Valitse mallisanoitus, tai kirjoita ja halutessasi tavuta mallisanoitus.<br>
  2. Valitse käytettävät sanastot.<br>
  3. Muodosta tulossanoitus. (Jos tavutusta ei ole tehty, sovellus tavuttaa ensin mallisanoituksen.)
  </p>
</div>

<div class="w3-row-padding w3-center w3-margin-top">

<div class="w3-row w3-margin">

<form action="lyriikkarenki.php" method="post">

<div class="w3-col">
  <div class="w3-progress-container" style="height:25px">
    <div id="valmius_bar" class="w3-progressbar w3-theme" style="width:1%;">
      <div class="w3-center w3-text-white" id="prosentti">0%</div>
    </div>   
    <div id="valmius_teksti" class="w3-center w3-text-red"></div>
  </div>
</div>

</div>

<!-- Vasen puoli -->

<div class="w3-half">
  <div class="w3-card-2 w3-padding-large">
    <h4>Mallisanoitus</h4><br>
    <?php include "./lr_malli.php"; ?> 
    <div style="float:left;width:100%;">
      <textarea name="mallisanoitus" id="mallisanoitus" style="width:100%" rows=16 ><?php print $mallisanoitus; ?></textarea>
    </div>
    <br><br>
    <input type="submit" class="w3-btn w3-theme" name="tavuta" value="Tavuta mallisanoitus">
  </div>
  <p>
    <div class="w3-dropdown-hover">
      <select class="w3-btn w3-theme" name="valittu_mallisanoitus" id="valittu_mallisanoitus" onchange="mallisanoitusValittu()">
        <option value="" disabled selected>Valitse mallisanoitus &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>
        <?php tulostaMallisanoituslista(); ?>
      </select>
    </div>
  </p>
</div>

 <!-- Oikea puoli -->

<div class="w3-half">
  <div class="w3-card-2 w3-padding-large">
    <h4>Tulossanoitus</h4><br> 
    <div style="float:left;width:100%;">
      <textarea name="tulossanoitus" id="tulossanoitus" style="width:100%" rows=16><?php print $tulossanoitus; ?></textarea>
    </div>
    <br><br>
    <input type="submit"  class="w3-btn w3-theme" name="tee_lyriikka" value="Muodosta tulossanoitus">
    <!--<input type="hidden" name="tulossanoitus3" id="tulossanoitus3" value="">-->
    <input type="submit"  class="w3-btn w3-theme" name="keskeyta" value="Keskeytä">
  </div>
  <div class="w3-container w3-left-align">
    <p>
      <input name="k18" type="checkbox" value="1"<?php if ($_POST['k18']) print " checked"; ?>>
      <label class="w3-validate">Käytä K18-sanastoa</label>
      <br>
      <input name="biiseista" type="checkbox" value="1"<?php if ($_POST['biiseista']) print " checked"; ?>>
      <label class="w3-validate">Käytä biiseistä luettua sanastoa</label>
      <br>
      <input name="yleiset" type="checkbox" value="1"<?php if ($_POST['yleiset']) print " checked"; ?>>
      <label class="w3-validate">Käytä yleisimpien sanojen sanastoa</label>
      <br>
      <input name="yleiset" type="checkbox" disabled="disabled" value="1" checked>
      <label class="w3-validate">Käytä laajaa sanastoa</label>
    </p>
  </div>
</div>

</div>

<br>

<?php include "./lr_tulos.php"; ?>

<!-- Debug-ikkuna -->

<!--<div class="w3-card-2 w3-padding-large">
  <h4>Debug-ikkuna</h4><br>
  <div style="float:left;width:100%;">
    <textarea name="dbg" style="width:100%" rows=32><?php print $dbg;?></textarea>
  </div>
</div>

<br>-->

<!-- Footer -->
<footer class="w3-container w3-theme-dark w3-padding-16">
  <p>Powered by <a href="http://www.w3schools.com/w3css/default.asp" target="_blank">w3.css</a></p>
  <div style="position:relative;bottom" class="w3-tooltip w3-right">
    <span class="w3-text w3-theme-light w3-padding">Mene alkuun</span>    
    <a class="w3-text-white" href="#myHeader">
      <span class="w3-xlarge">
        <i class="fa fa-chevron-circle-up"></i>
      </span>
    </a>
  </div>
</footer>


</form>

<!-- Scriptit -->

<script>

function naytaOhje() {
    var x = document.getElementById('ohje');
    if (x.className.indexOf("w3-show") == -1) {
        x.className += " w3-show";
    } else { 
        x.className = x.className.replace(" w3-show", "");
    }
}

function mallisanoitusValittu() {
    var x = document.getElementById("valittu_mallisanoitus").value;
    document.getElementById("mallisanoitus").innerHTML = x;
}

</script>

</body>
</html>
